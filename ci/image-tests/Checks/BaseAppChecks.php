<?php

declare(strict_types=1);

namespace Webdevops\Build\ImageTests\Checks;

use Webdevops\Build\ImageTests\ImageTestCase;

use function explode;
use function str_contains;
use function trim;

/**
 * Checks of the images based on webdevops/base-app: the application user, locales and moreutils.
 *
 * @phpstan-require-extends ImageTestCase
 */
trait BaseAppChecks
{
    public function testLocalesAreGenerated(): void
    {
        if (!self::image()->isDebianOrUbuntu()) {
            self::markTestSkipped('The locales are only generated in the Debian and Ubuntu images.');
        }

        $locales = $this->stdout('locale -a');
        foreach (['C', 'POSIX', 'en_US.utf8', 'de_DE.utf8', 'de_LU', 'es_BO', 'st_ZA', 'zu_ZA', 'ca_ES', 'fr_FR'] as $locale) {
            self::assertStringContainsString($locale, $locales);
        }
    }

    /**
     * APPLICATION_PATH: /app exists and belongs to the application user.
     */
    public function testApplicationPathBelongsToTheApplicationUser(): void
    {
        $file = $this->file('/app');
        self::assertTrue($file->isDirectory, '/app should be a directory');
        self::assertSame('application', $file->owner);
        self::assertSame('application', $file->group);
    }

    public function testApplicationUser(): void
    {
        $this->assertCommandSucceeds('id application');
        self::assertContains('application', explode(' ', trim($this->stdout('id -Gn application'))), 'application should belong to the group application');
        self::assertSame('1000', trim($this->stdout('id -u application')));
        // APPLICATION_GID
        self::assertSame('1000', trim($this->stdout('id -g application')));

        $entry = explode(':', trim($this->stdout('getent passwd application')));
        self::assertSame('/home/application', $entry[5] ?? null, 'home directory of application');
        self::assertSame('/bin/bash', $entry[6] ?? null, 'login shell of application');
    }

    /**
     * The cli command runs CLI_SCRIPT with the further arguments as the application user, see docs/customization.md.
     */
    public function testCliRunsTheCliScriptAsTheApplicationUser(): void
    {
        $result = $this->runContainer(['cli', '-un'], ['CLI_SCRIPT' => 'id']);
        self::assertSame([0, "application\n"], [$result->exitCode, $result->stdout], $result->describe());
    }

    /**
     * /docker.stdout points to the output of process 1, which belongs to root, so a process of the application user
     * cannot write to it, see docs/known-issues.md#dockerstdout-and-dockerstderr-for-other-users.
     */
    public function testApplicationUserCannotWriteToDockerStdout(): void
    {
        $result = $this->execute('gosu application sh -c "echo image-test > /docker.stdout"');
        self::assertNotSame(0, $result->exitCode, $result->describe());
        self::assertStringContainsString('Permission denied', $result->stderr);
    }

    /**
     * The way around it, as in docs/known-issues.md: a supervisord program of the application user with
     * stdout_logfile = /dev/stdout writes to the container log, because supervisord itself opens /dev/stdout.
     */
    public function testSupervisordProgramOfTheApplicationUserLogsToTheContainerLog(): void
    {
        $container = $this->startContainer('application-program');
        if (!str_contains($this->stdout('tr "\\000" " " < /proc/1/cmdline', $container), 'supervisord')) {
            self::markTestSkipped('The image does not run supervisord.');
        }

        $this->assertCommandSucceeds(<<<'SH'
            set -e
            mkdir -p /app/bin
            printf '%s\n' '#!/bin/sh' 'echo "worker as $(id -un)"' 'exec sleep 3600' > /app/bin/worker
            chmod +x /app/bin/worker
            printf '%s\n' '[program:worker]' 'command = /app/bin/worker' 'user = application' 'stdout_logfile = /dev/stdout' \
                'stdout_logfile_maxbytes = 0' 'redirect_stderr = true' > /opt/docker/etc/supervisor.d/worker.conf
            supervisorctl update
            SH, $container);
        $this->eventually(static function () use ($container): void {
            self::assertStringContainsString("worker as application\n", $container->logs());
        });
    }

    public function testMoreutils(): void
    {
        if (!self::image()->isDebianOrUbuntu()) {
            self::markTestSkipped('moreutils is only installed in the Debian and Ubuntu images.');
        }

        self::assertSame("/usr/bin/sponge\n", $this->stdout('command -v sponge'));
    }
}
