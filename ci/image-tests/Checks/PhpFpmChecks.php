<?php

declare(strict_types=1);

namespace Webdevops\Build\ImageTests\Checks;

use Webdevops\Build\ImageTests\ImageTestCase;

/**
 * Checks of the PHP-FPM configuration in every PHP image.
 *
 * @phpstan-require-extends ImageTestCase
 */
trait PhpFpmChecks
{
    public function testPhpFpmLayout(): void
    {
        foreach (['/opt/docker/etc/php', '/opt/docker/etc/php/fpm', '/opt/docker/etc/php/fpm/pool.d'] as $path) {
            self::assertTrue($this->file($path)->isDirectory, $path . ' should be a directory');
        }

        foreach (['/opt/docker/etc/php/php.ini', '/opt/docker/etc/php/fpm/php-fpm.conf', '/opt/docker/etc/php/fpm/pool.d/application.conf'] as $path) {
            self::assertTrue($this->file($path)->isFile, $path . ' should be a file');
        }
    }

    public function testPoolDirectoryOfTheOfficialImageIsALink(): void
    {
        if (!self::image()->phpOfficial) {
            self::markTestSkipped('Only the images based on the official PHP image have /usr/local/etc/php-fpm.d.');
        }

        self::assertTrue($this->file('/usr/local/etc/php-fpm.d')->isSymlink, '/usr/local/etc/php-fpm.d should be a symbolic link');
    }

    public function testPhpFpmDisablesUploadProgress(): void
    {
        self::assertMatchesRegularExpression('/^session\.upload_progress\.enabled => Off => Off$/m', $this->stdout('php-fpm -i'));
    }

    /**
     * fpm.pool.<key> also sets the directives that docker.conf of the official image sets, such as clear_env: the values
     * from the environment go to zz-environment.conf, which PHP-FPM reads after docker.conf. application.conf, which
     * you may replace, stays as the image has it. In a container of its own.
     */
    public function testFpmPoolSetsTheDirectivesOfDockerConf(): void
    {
        $result = $this->runContainer(
            ['sh', '-c', 'php-fpm -tt 2>&1 | grep -E "NOTICE:[[:space:]]+(clear_env|catch_workers_output) = "; grep -c "container env settings" /opt/docker/etc/php/fpm/pool.d/application.conf'],
            ['fpm.pool.clear_env' => 'yes', 'fpm.pool.catch_workers_output' => 'no'],
        );
        self::assertMatchesRegularExpression('/NOTICE:\s+clear_env = yes$/m', $result->stdout, $result->describe());
        self::assertMatchesRegularExpression('/NOTICE:\s+catch_workers_output = no$/m', $result->stdout, $result->describe());
        self::assertMatchesRegularExpression('/^0$/m', $result->stdout, 'application.conf has no values from the environment');
    }
}
