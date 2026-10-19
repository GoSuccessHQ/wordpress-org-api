<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Core;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Core\Core;
use GoSuccess\WordpressOrg\Core\CoreChecksums;
use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Core::class)]
#[CoversClass(CoreChecksums::class)]
final class CoreChecksumsTest extends TestCase
{
    public function testChecksums(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../../Fixtures/core/checksums.json');
        $http = new MockHttpClient(new Response(200, $body));

        $checksums = new WordpressOrg(new ClientOptions(), $http)->core->checksums('6.8.3');

        self::assertSame('https://api.wordpress.org/core/checksums/1.0/?version=6.8.3&locale=en_US', $http->requests[0]->uri);
        self::assertNotNull($checksums);
        self::assertSame('6.8.3', $checksums->version);
        self::assertSame('en_US', $checksums->locale);
        self::assertCount(5, $checksums->files);
        self::assertSame('fb407463c202f1a8ab8783fa5b24ec13', $checksums->files['xmlrpc.php']);
    }

    public function testLocaleIsSent(): void
    {
        $http = new MockHttpClient(new Response(200, '{"checksums":{"a.php":"m1","b.php":7}}'));

        $checksums = new WordpressOrg(new ClientOptions(), $http)->core->checksums('6.8.3', 'de_DE');

        self::assertSame('https://api.wordpress.org/core/checksums/1.0/?version=6.8.3&locale=de_DE', $http->requests[0]->uri);
        self::assertSame('de_DE', $checksums?->locale);
        self::assertSame(['a.php' => 'm1'], $checksums->files);
    }

    public function testUnknownVersionIsNull(): void
    {
        $http = new MockHttpClient(new Response(200, '{"checksums":false}'));

        self::assertNull(new WordpressOrg(new ClientOptions(), $http)->core->checksums('9.9.9'));
    }

    public function testNonObjectBodyThrows(): void
    {
        $this->expectException(SerializationException::class);

        new WordpressOrg(new ClientOptions(), new MockHttpClient(new Response(200, 'oops')))->core->checksums('6.8.3');
    }
}
