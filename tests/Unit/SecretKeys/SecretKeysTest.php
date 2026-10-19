<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\SecretKeys;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\SecretKeys\Salts;
use GoSuccess\WordpressOrg\SecretKeys\SecretKeys;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecretKeys::class)]
#[CoversClass(Salts::class)]
final class SecretKeysTest extends TestCase
{
    private static function fixture(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Fixtures/secret-keys/salt.txt');
    }

    private static function generate(string $body): Salts
    {
        $http = new MockHttpClient(new Response(200, $body));

        return new WordpressOrg(new ClientOptions(), $http)->secretKeys->generate();
    }

    public function testSecretKeysParseEightValues(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture()));
        $salts = new WordpressOrg(new ClientOptions(), $http)->secretKeys->generate();

        self::assertSame('https://api.wordpress.org/secret-key/1.1/salt/', $http->requests[0]->uri);
        self::assertCount(8, $salts->values);
        self::assertSame("made-up value with a quote ' inside", $salts->get('AUTH_KEY'));
        self::assertSame('fixture-logged-in-key-0003 with back\\slash', $salts->get('LOGGED_IN_KEY'));
        self::assertSame('fixture-nonce-salt-0008', $salts->get('NONCE_SALT'));
        self::assertNull($salts->get('OTHER'));
    }

    public function testCrlfAndBlankLinesAreTolerated(): void
    {
        $body = "\r\n  " . str_replace("\n", "\r\n\r\n", self::fixture()) . "  \r\n";

        self::assertCount(8, self::generate($body)->values);
    }

    public function testSecretKeysRejectMissingLine(): void
    {
        $lines = explode("\n", trim(self::fixture()));
        array_pop($lines);

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('NONCE_SALT');
        self::generate(implode("\n", $lines));
    }

    public function testUnexpectedLineIsRejectedWithoutLeakingValues(): void
    {
        try {
            self::generate(self::fixture() . "system('echo secret-value');\n");
            self::fail('Expected an exception.');
        } catch (SerializationException $exception) {
            self::assertStringNotContainsString('secret-value', $exception->getMessage());
            self::assertStringNotContainsString('fixture-', $exception->getMessage());
        }
    }

    public function testHtmlBodyIsRejected(): void
    {
        $this->expectException(SerializationException::class);
        self::generate('<html>Not found</html>');
    }

    public function testSaltsDebugInfoMasksValues(): void
    {
        $salts = self::generate(self::fixture());

        ob_start();
        var_dump($salts);
        $dump = (string) ob_get_clean();

        self::assertStringContainsString('AUTH_KEY', $dump);
        self::assertStringNotContainsString('fixture-', $dump);
        self::assertStringNotContainsString('made-up', $dump);
        self::assertSame(array_keys($salts->values), array_keys($salts->__debugInfo()['values']));
    }
}
