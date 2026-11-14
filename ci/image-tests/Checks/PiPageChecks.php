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
        $container = $this->startTestContainer('ecdsa', ['IMAGE_TEST_ECDSA_CERT' => '1']);
        self::assertStringContainsString('3.14159265359', $this->request('https://localhost/pi-number.html', container: $container));
        self::assertMatchesRegularExpression(
            '/^New, TLSv1\.[23], Cipher is [A-Z0-9_-]+$/m',
            $this->stdout('echo | openssl s_client -connect localhost:443 2>/dev/null', $container),
        );
    }

    /**
     * TLS 1.0 and 1.1 are refused. The client offers only that version, with all its ciphers (SECLEVEL=0), so the
     * handshake fails because of the server: the connection is made, the client asks for the version, and no cipher is
     * agreed on.
     */
    #[DataProvider('oldTlsVersions')]
    public function testWebServerRefusesOldTlsVersions(string $option, string $protocol): void
    {
        $this->assertListening(443);
        $output = $this->stdout('echo | openssl s_client -connect localhost:443 -' . $option . ' -cipher DEFAULT@SECLEVEL=0 2>&1; true');
        self::assertStringContainsString('CONNECTED(', $output, $output);
        self::assertMatchesRegularExpression('/^\s*Protocol\s*: ' . $protocol . '$/m', $output, $output);
        self::assertMatchesRegularExpression('/^New, \(NONE\), Cipher is \(NONE\)$/m', $output, $output);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function oldTlsVersions(): iterable
    {
        yield 'TLS 1.0' => ['tls1', 'TLSv1'];
        yield 'TLS 1.1' => ['tls1_1', 'TLSv1\.1'];
    }
}
