<?php

declare(strict_types=1);

namespace Webdevops\Build\Dockerfile;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

use function array_keys;
use function count;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function ltrim;
use function preg_match_all;
use function sort;
use function sprintf;
use function strlen;
use function substr;

/**
 * Renders docker/<image>/<tag>/Dockerfile from the Dockerfile.jinja2 next to it and the templates in template/.
 *
 * Each Dockerfile.jinja2 is the content block of template/Dockerfile/layout.jinja2, which adds the header and
 * imports the macros of template/Dockerfile/. Directories that are symbolic links, such as docker/php/8.5, are left out.
 */
final readonly class DockerfileGenerator
{
    public const string TEMPLATE = 'Dockerfile.jinja2';

    private const string LAYOUT = 'Dockerfile/layout.jinja2';

    /**
     * @param string $rootPath the repository root
     */
    public function __construct(private string $rootPath = __DIR__ . '/../../..')
    {
    }

    /**
     * Renders every Dockerfile.
     *
     * @param ?callable(string): void $log receives the image and tag of each Dockerfile
     */
    public function generate(?callable $log = null): void
    {
        $templates = new FilesystemLoader($this->rootPath . '/template');
        $imports = $this->layoutImports($templates);

        // Each Dockerfile.jinja2 becomes the content block of the layout. Twig makes the macros of the layout
        // available in its own blocks only, so the content block imports them too.
        $dockerfiles = [];
        foreach ($this->templates() as $template) {
            $code = file_get_contents($this->rootPath . '/' . $template);
            if ($code === false) {
                throw new RuntimeException(sprintf('Cannot read %s.', $template));
            }

            $dockerfiles[$template] = JinjaCompatibleLoader::convert(
                '{% extends "' . self::LAYOUT . '" %}' . $imports . '{% block content %}' . $code . '{% endblock %}',
                false,
            );
        }

        $twig = new Environment(
            new ChainLoader([new ArrayLoader($dockerfiles), new JinjaCompatibleLoader($templates)]),
            ['autoescape' => false, 'strict_variables' => false],
        );
        foreach (array_keys($dockerfiles) as $template) {
            [, $image, $tag] = explode('/', $template);
            $dockerfile = $twig->render($template, ['Dockerfile' => ['image' => $image, 'tag' => $tag]]);

            $target = dirname($template) . '/Dockerfile';
            if (file_put_contents($this->rootPath . '/' . $target, ltrim($dockerfile)) === false) {
                throw new RuntimeException(sprintf('Cannot write %s.', $target));
            }

            if ($log !== null) {
                $log(sprintf('webdevops/%s:%s', $image, $tag));
            }
        }
    }

    /**
     * Every docker/<image>/<tag>/Dockerfile.jinja2, relative to the repository root and sorted.
     *
     * @return list<string>
     */
    private function templates(): array
    {
        $templates = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->rootPath . '/docker'));
        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->getFilename() === self::TEMPLATE) {
                $template = substr($file->getPathname(), strlen($this->rootPath) + 1);
                if (count(explode('/', $template)) === 4) {
                    $templates[] = $template;
                }
            }
        }

        sort($templates);
        return $templates;
    }

    /**
     * The import tags of the layout, e.g. {% import 'Dockerfile/docker.jinja2' as docker %}.
     */
    private function layoutImports(FilesystemLoader $templates): string
    {
        preg_match_all('/\{%-?\s*import\s.*?%\}/s', $templates->getSourceContext(self::LAYOUT)->getCode(), $matches);
        return implode('', $matches[0]);
    }
}
