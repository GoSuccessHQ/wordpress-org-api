<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Plugins;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Plugins\FileChecksum;
use GoSuccess\WordpressOrg\Plugins\PluginChecksums;
use GoSuccess\WordpressOrg\Plugins\Plugins;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Plugins::class)]
#[CoversClass(PluginChecksums::class)]
#[CoversClass(FileChecksum::class)]
final class PluginChecksumsTest extends TestCase
{
    private static function plugins(MockHttpClient $http): Plugins
    {
        return new WordpressOrg(new ClientOptions(), $http)->plugins;
    }

    public function testChecksumsReadFixture(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../../Fixtures/plugins/checksums-akismet-5.7.2.json');
        $http = new MockHttpClient(new Response(200, $body));

        $checksums = self::plugins($http)->checksums('Akismet', '5.7.2');

        self::assertSame('https://downloads.wordpress.org/plugin-checksums/akismet/5.7.2.json', $http->requests[0]->uri);
        self::assertNotNull($checksums);
        self::assertSame('akismet', $checksums->plugin);
        self::assertSame('5.7.2', $checksums->version);
        self::assertSame('https://plugins.svn.wordpress.org/akismet/tags/5.7.2/', $checksums->source);
        self::assertSame('https://downloads.wordpress.org/plugins/akismet.5.7.2.zip', $checksums->zip);
        self::assertCount(5, $checksums->files);
        self::assertArrayHasKey('_inc/akismet-admin.css', $checksums->files);
        self::assertSame(['36e03d511ba4a6a44cd6009b241e16c0'], $checksums->files['_inc/akismet-admin.css']->md5);
        self::assertCount(1, $checksums->files['.htaccess']->sha256);
    }

    public function testChecksumsNotFoundIsNull(): void
    {
        $http = new MockHttpClient(new Response(404, 'Not found', ['content-type' => 'text/html']));

        self::assertNull(self::plugins($http)->checksums('no-such-plugin', '1.0'));
        self::assertSame(1, $http->callCount());
    }

    public function testChecksumsUnsafeInputSendsNothing(): void
    {
        $http = new MockHttpClient();
        $plugins = self::plugins($http);

        self::assertNull($plugins->checksums('../x', '1.0'));
        self::assertNull($plugins->checksums('..', '1.0'));
        self::assertNull($plugins->checksums('akismet', '../1'));
        self::assertNull($plugins->checksums('akismet', '1.0/x'));
        self::assertNull($plugins->checksums('akismet', ''));
        self::assertNull($plugins->checksums('akismet', '..'));
        self::assertSame(0, $http->callCount());
    }

    public function testVersionKeepsItsCase(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));

        self::plugins($http)->checksums('Akismet', '1.0-RC1');

        self::assertSame('https://downloads.wordpress.org/plugin-checksums/akismet/1.0-RC1.json', $http->requests[0]->uri);
    }

    public function testSingleHashBecomesList(): void
    {
        $body = '{"plugin":"x","version":"1","files":{"a.php":{"md5":"m1","sha256":["s1","s2"]},"b.php":{"md5":["m2",7],"sha256":null},"c.php":"junk","d.php":[]}}';
        $http = new MockHttpClient(new Response(200, $body));

        $checksums = self::plugins($http)->checksums('x', '1');

        self::assertNotNull($checksums);
        self::assertNull($checksums->source);
        self::assertNull($checksums->zip);
        self::assertSame(['a.php', 'b.php', 'd.php'], array_keys($checksums->files));
        self::assertSame(['m1'], $checksums->files['a.php']->md5);
        self::assertSame(['s1', 's2'], $checksums->files['a.php']->sha256);
        self::assertSame(['m2'], $checksums->files['b.php']->md5);
        self::assertSame([], $checksums->files['b.php']->sha256);
    }

    public function testNonObjectBodyThrows(): void
    {
        $http = new MockHttpClient(new Response(200, 'oops'));

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('plugin-checksums');

        self::plugins($http)->checksums('x', '1');
    }
}
