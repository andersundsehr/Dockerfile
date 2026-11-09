<?php

declare(strict_types=1);

namespace Webdevops\Build\Provision;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

use function array_any;
use function array_map;
use function array_values;
use function chmod;
use function copy;
use function explode;
use function fileperms;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function is_link;
use function is_string;
use function mkdir;
use function preg_match;
use function preg_quote;
use function rmdir;
use function scandir;
use function sort;
use function sprintf;
use function strval;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function unlink;

/**
 * Copies the files of provisioning/ into the conf/ directory next to each Dockerfile, as conf/provision.yml says.
 *
 * For each image in conf/provision.yml, the conf/ directories of all its tags are emptied first. Then each source
 * directory is copied into the conf/ directory of the tags it names, in the order of the file, so a later source
 * replaces the files of an earlier one. Images that conf/provision.yml does not name are left alone.
 */
final readonly class Provisioner
{
    /**
     * @param string $rootPath the repository root
     */
    public function __construct(private string $rootPath = __DIR__ . '/../../..')
    {
    }

    /**
     * Provisions every image of conf/provision.yml.
     *
     * @param ?callable(string): void $log receives a line for each source that is copied
     */
    public function provision(?callable $log = null): void
    {
        foreach ($this->configuration() as $image => $sources) {
            foreach ($this->dockerfileDirectories($image, '*') as $directory) {
                $this->remove($this->rootPath . '/' . $directory . '/conf');
            }

            foreach ($sources as $source => $tags) {
                $sourcePath = $this->rootPath . '/provisioning/' . $source;
                if (!is_dir($sourcePath)) {
                    throw new RuntimeException(sprintf('conf/provision.yml copies provisioning/%s into webdevops/%s, but it does not exist.', $source, $image));
                }

                foreach ($tags as $tag) {
                    foreach ($this->dockerfileDirectories($image, $tag) as $directory) {
                        $this->copyTree($sourcePath, $this->rootPath . '/' . $directory . '/conf');
                    }
                }

                if ($log !== null) {
                    $log(sprintf('%s => webdevops/%s:%s', $source, $image, implode(', ', $tags)));
                }
            }
        }
    }

    /**
     * The source directories of each image with the tag patterns they are copied to, in the order of conf/provision.yml.
     *
     * @return array<string, array<string, list<string>>>
     */
    private function configuration(): array
    {
        $images = Yaml::parseFile($this->rootPath . '/conf/provision.yml')['provision'] ?? null;
        if (!is_array($images)) {
            throw new RuntimeException('conf/provision.yml has no "provision" section.');
        }

        $configuration = [];
        foreach ($images as $image => $settings) {
            $configuration[(string)$image] = [];
            foreach (is_array($settings) ? $settings['configuration'] ?? [] : [] as $source => $tags) {
                $tags = is_array($tags) ? $tags : [$tags];
                foreach ($tags as $tag) {
                    if (!is_string($tag) || $tag === '') {
                        throw new RuntimeException(sprintf('conf/provision.yml has an invalid tag for provisioning/%s in webdevops/%s.', $source, $image));
                    }
                }

                $configuration[(string)$image][(string)$source] = array_values(array_map(strval(...), $tags));
            }
        }

        return $configuration;
    }

    /**
     * The directories under docker/<image>/ whose tag matches $tag and that hold a Dockerfile,
     * relative to the repository root and sorted. * in $tag stands for one or more characters.
     * Directories that are symbolic links, such as docker/php/8.5, are left out.
     *
     * @return list<string>
     */
    private function dockerfileDirectories(string $image, string $tag): array
    {
        $imagePath = $this->rootPath . '/docker/' . $image;
        if (!is_dir($imagePath) || is_link($imagePath)) {
            return [];
        }

        $tagPattern = $tag === '*' ? '[^/]+' : implode('[^/]+', array_map(fn (string $part): string => preg_quote($part, '#'), explode('*', $tag)));
        $directories = [];
        foreach ($this->entries($imagePath) as $entry) {
            $path = $imagePath . '/' . $entry;
            if (is_dir($path) && !is_link($path) && preg_match('#^' . $tagPattern . '$#i', $entry) && $this->hasDockerfile($path)) {
                $directories[] = 'docker/' . $image . '/' . $entry;
            }
        }

        return $directories;
    }

    private function hasDockerfile(string $directory): bool
    {
        return array_any($this->entries($directory), fn (string $entry): bool => str_ends_with($entry, 'Dockerfile') && is_file($directory . '/' . $entry));
    }

    /**
     * Copies the content of $source into $target, following symbolic links and keeping the permissions of the files.
     */
    private function copyTree(string $source, string $target): void
    {
        if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
            throw new RuntimeException(sprintf('Cannot create %s.', $this->relative($target)));
        }

        foreach ($this->entries($source) as $entry) {
            $from = $source . '/' . $entry;
            $to = $target . '/' . $entry;
            if (is_dir($from)) {
                $this->copyTree($from, $to);
                continue;
            }

            if (!copy($from, $to) || !chmod($to, fileperms($from) & 0777)) {
                throw new RuntimeException(sprintf('Cannot copy %s to %s.', $this->relative($from), $this->relative($to)));
            }
        }
    }

    private function remove(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            if ($file instanceof SplFileInfo) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
        }

        rmdir($path);
    }

    /**
     * The names in a directory without . and .., sorted.
     *
     * @return list<string>
     */
    private function entries(string $directory): array
    {
        $entries = [];
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $entries[] = $entry;
            }
        }

        sort($entries);
        return $entries;
    }

    /**
     * $path relative to the repository root, for messages.
     */
    private function relative(string $path): string
    {
        $root = $this->rootPath . '/';
        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
