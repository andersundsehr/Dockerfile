<?php

declare(strict_types=1);

namespace Webdevops\Build\ImageTests\Checks;

use Webdevops\Build\ImageTests\ImageTestCase;

/**
 * The cli command in the images without APPLICATION_USER: it runs CLI_SCRIPT as root, with the further arguments, see
 * docs/customization.md#commands.
 *
 * @phpstan-require-extends ImageTestCase
 */
trait CliWithoutApplicationUserChecks
{
    public function testCliRunsTheCliScriptAsRootWithoutApplicationUser(): void
    {
        $result = $this->runContainer(['cli', '-un'], ['CLI_SCRIPT' => 'id']);
        self::assertSame([0, "root\n"], [$result->exitCode, $result->stdout], $result->describe());
    }
}
