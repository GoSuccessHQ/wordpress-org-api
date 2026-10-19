<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Core;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Core\Browser;
use GoSuccess\WordpressOrg\Core\Core;
use GoSuccess\WordpressOrg\Core\Importer;
use GoSuccess\WordpressOrg\Core\PhpSupport;
use GoSuccess\WordpressOrg\Exception\BadRequestException;
use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Http\Method;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Core::class)]
#[CoversClass(Importer::class)]
#[CoversClass(Browser::class)]
#[CoversClass(PhpSupport::class)]
final class CoreServicesTest extends TestCase
{
    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Fixtures/core/' . $name . '.json');
    }

    private static function core(MockHttpClient $http): Core
    {
        return new WordpressOrg(new ClientOptions(), $http)->core;
    }

    public function testImporters(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('importers')));
        $importers = self::core($http)->importers();

        self::assertSame('https://api.wordpress.org/core/importers/1.1/', $http->requests[0]->uri);
        self::assertNotEmpty($importers);
        self::assertEquals(
            new Importer(
                'blogger',
                'Blogger',
                'Install the Blogger importer to import posts, comments, and users from a Blogger blog.',
                'blogger-importer',
            ),
            $importers[0],
        );
        // The key is `movabletype`, the entry says `mt`.
        $ids = array_map(static fn(Importer $importer): ?string => $importer->id, $importers);
        self::assertContains('mt', $ids);
        self::assertNotContains('movabletype', $ids);
    }

    public function testImporterIdFallsBackToKey(): void
    {
        $http = new MockHttpClient(new Response(200, '{"importers":{"rss":{"name":"RSS"},"bad":"x"}}'));

        self::assertEquals([new Importer('rss', 'RSS', null, null)], self::core($http)->importers());
    }

    public function testHandbookUrl(): void
    {
        $url = 'https://developer.wordpress.org/reference/functions/get_post/';
        $http = new MockHttpClient(new Response(200, $url . "\n"), new Response(200, '<html>'), new Response(200, 'https://a b'));
        $core = self::core($http);

        self::assertSame($url, $core->handbookUrl('get_post', '7.1'));
        self::assertNull($core->handbookUrl('get_post', '7.1'));
        self::assertNull($core->handbookUrl('get_post', '7.1'));
        self::assertSame(
            'https://api.wordpress.org/core/handbook/1.0/?function=get_post&version=7.1&redirect=false',
            $http->requests[0]->uri,
        );
    }

    public function testBrowseHappyPostsUserAgent(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('browse-happy')));
        $browser = self::core($http)->browseHappy('Mozilla/5.0 Chrome/120.0.0.0');

        self::assertSame(Method::Post, $http->requests[0]->method);
        self::assertSame('https://api.wordpress.org/core/browse-happy/1.1/', $http->requests[0]->uri);
        self::assertSame('useragent=Mozilla%2F5.0%20Chrome%2F120.0.0.0', $http->requests[0]->body);
        self::assertSame('Chrome', $browser->name);
        self::assertSame('120.0.0.0', $browser->version);
        self::assertSame('Windows', $browser->platform);
        self::assertSame('https://www.google.com/chrome', $browser->updateUrl);
        self::assertSame('http://s.w.org/images/browsers/chrome.png?1', $browser->imgSrc);
        self::assertSame('https://s.w.org/images/browsers/chrome.png?1', $browser->imgSrcSsl);
        self::assertSame('18', $browser->currentVersion);
        self::assertFalse($browser->upgrade);
        self::assertFalse($browser->insecure);
        self::assertFalse($browser->mobile);
    }

    public function testBrowseHappyHtmlThrows(): void
    {
        $this->expectException(SerializationException::class);

        self::core(new MockHttpClient(new Response(200, '<html>')))->browseHappy('x');
    }

    public function testServeHappy(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('serve-happy')));
        $support = self::core($http)->serveHappy('7.4.0');

        self::assertSame('https://api.wordpress.org/core/serve-happy/1.0/?php_version=7.4.0', $http->requests[0]->uri);
        self::assertSame('8.3', $support->recommendedVersion);
        self::assertSame('7.4', $support->minimumVersion);
        self::assertFalse($support->isSupported);
        self::assertFalse($support->isSecure);
        self::assertFalse($support->isAcceptable);
    }

    public function testServeHappyBadRequest(): void
    {
        $body = '{"code":"missing_param","message":"Missing parameter(s): php_version","data":{"status":400}}';

        try {
            self::core(new MockHttpClient(new Response(400, $body)))->serveHappy('');
            self::fail('Expected a BadRequestException.');
        } catch (BadRequestException $exception) {
            self::assertSame('missing_param', $exception->errorCode);
        }
    }
}
