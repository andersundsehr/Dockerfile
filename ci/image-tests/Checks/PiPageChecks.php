<?php

declare(strict_types=1);

namespace Webdevops\Build\ImageTests\Checks;

use PHPUnit\Framework\Attributes\DataProvider;
use Webdevops\Build\ImageTests\ImageTestCase;

/**
 * The web server answers with the static page Fixtures/app/pi-number.html, over http and https.
 *
 * @phpstan-require-extends ImageTestCase
 */
trait PiPageChecks
{
    #[DataProvider('webSchemes')]
    public function testWebServerAnswers(string $scheme): void
    {
        self::assertStringContainsString('3.14159265359', $this->request($scheme . '://localhost/pi-number.html'));
    }

    /**
     * https works with an ECDSA certificate too, here a self-signed one from Hooks/, over TLS 1.2 or 1.3.
     */
    public function testWebServerAnswersWithAnEcdsaCertificate(): void
    {
        $container = $this->startContainer('ecdsa', ['IMAGE_TEST_ECDSA_CERT' => '1']);
        self::assertStringContainsString('3.14159265359', $this->request('https://localhost/pi-number.html', container: $container));
        self::assertMatchesRegularExpression(
            '/^New, TLSv1\.[23], Cipher is [A-Z0-9_-]+$/m',
            $this->stdout('echo | openssl s_client -connect localhost:443 2>/dev/null', $container),
        );
    }
}
