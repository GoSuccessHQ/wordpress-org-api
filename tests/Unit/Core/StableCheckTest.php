<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Core;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Core\Core;
use GoSuccess\WordpressOrg\Core\Stability;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Core::class)]
#[CoversClass(Stability::class)]
final class StableCheckTest extends TestCase
{
    private static function core(string $body): Core
    {
        return new WordpressOrg(new ClientOptions(), new MockHttpClient(new Response(200, $body)))->core;
    }

    public function testStability(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../../Fixtures/core/stable-check.json');

        self::assertSame(Stability::Latest, self::core($body)->stability('7.1.3'));
        self::assertSame(Stability::Insecure, self::core($body)->stability('1.0.2'));
        self::assertSame(Stability::Outdated, self::core($body)->stability('7.0.7'));
        self::assertNull(self::core($body)->stability('9.9'));
        self::assertNull(self::core('{"1.0":"weird"}')->stability('1.0'));
    }

    public function testRequestUri(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));

        new WordpressOrg(new ClientOptions(), $http)->core->stableCheck();

        self::assertSame('https://api.wordpress.org/core/stable-check/1.0/', $http->requests[0]->uri);
    }

    public function testStableCheckKeepsEveryString(): void
    {
        $core = self::core('{"1.0":"weird","2.0":"latest","3.0":1,"4.0":null}');

        self::assertSame(['1.0' => 'weird', '2.0' => 'latest'], $core->stableCheck());
    }
}
