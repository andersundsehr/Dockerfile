<?php

declare(strict_types=1);

namespace Webdevops\Build\Tests\Dockerfile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Error\RuntimeError;
use Webdevops\Build\Dockerfile\DockerfileGenerator;
use Webdevops\Build\Dockerfile\JinjaCompatibleLoader;
use Webdevops\Build\FileReader;
use Webdevops\Build\Tests\TemporaryRepository;

use function file_get_contents;

#[CoversClass(DockerfileGenerator::class)]
#[CoversClass(JinjaCompatibleLoader::class)]
final class DockerfileGeneratorTest extends TestCase
{
    private TemporaryRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new TemporaryRepository();
    }

    protected function tearDown(): void
    {
        $this->repository->remove();
    }

    /**
     * The Dockerfiles of the test repository were rendered by Jinja from their Dockerfile.jinja2, as bin/console
     * generate:dockerfile does. docker/base/alpine uses comments, if/elif/else and a macro that uses an import of its file.
     */
    public function testRendersTheDockerfilesLikeJinja(): void
    {
        $dockerfiles = new FileReader($this->repository->path)->collectDockerfiles();
        foreach ($dockerfiles as $dockerfile) {
            $this->repository->write($dockerfile, '');
        }

        $this->generate();

        self::assertCount(9, $dockerfiles);
        foreach ($dockerfiles as $dockerfile) {
            self::assertSame(file_get_contents(TemporaryRepository::FIXTURE . '/' . $dockerfile), $this->repository->read($dockerfile), $dockerfile);
        }
    }

    public function testLogsEachDockerfileAndLeavesOutLinkedDirectories(): void
    {
        // docker/php/8.4 links to docker/php-official/8.4
        self::assertSame([
            'webdevops/base-app:ubuntu-22.04',
            'webdevops/base:alpine',
            'webdevops/base:ubuntu-22.04',
            'webdevops/php-nginx-dev:8.5-alpine',
            'webdevops/php-nginx:8.5-alpine',
            'webdevops/php-official:8.4',
            'webdevops/php-official:8.5-alpine',
            'webdevops/php-official:8.5',
            'webdevops/toolbox:latest',
        ], $this->generate());
    }

    public function testTemplatesOutsideAnImageTagDirectoryAreLeftOut(): void
    {
        $this->repository->write('docker/base/Dockerfile.jinja2', '{{ undefined.macro() }}');
        $this->repository->write('docker/base/alpine/conf/Dockerfile.jinja2', '{{ undefined.macro() }}');

        self::assertCount(9, $this->generate());
    }

    public function testUnknownMacroIsAnError(): void
    {
        $this->repository->write('docker/base/alpine/Dockerfile.jinja2', '{{ docker.missing() }}');

        $this->expectException(RuntimeError::class);
        $this->generate();
    }

    /**
     * @return list<string> the log lines
     */
    private function generate(): array
    {
        $lines = [];
        new DockerfileGenerator($this->repository->path)->generate(function (string $line) use (&$lines): void {
            $lines[] = $line;
        });
        return $lines;
    }
}
