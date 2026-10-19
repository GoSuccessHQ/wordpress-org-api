<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Stats;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Stats\Stats;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stats::class)]
final class StatsTest extends TestCase
{
    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Fixtures/stats/' . $name . '.json');
    }

    private static function stats(MockHttpClient $http): Stats
    {
        return new WordpressOrg(new ClientOptions(), $http)->stats;
    }

    public function testShares(): void
    {
        $http = new MockHttpClient(
            new Response(200, self::fixture('php')),
            new Response(200, self::fixture('wordpress')),
            new Response(200, self::fixture('plugin')),
        );
        $stats = self::stats($http);
        $php = $stats->php();

        self::assertSame('https://api.wordpress.org/stats/php/1.0/', $http->requests[0]->uri);
        self::assertSame(16.19, $php['7.4']);
        // "8.3" must stay a string key.
        self::assertContains('8.3', array_map(strval(...), array_keys($php)));
        self::assertSame(26.485, $php['8.3']);
        self::assertSame(0.05, $stats->wordpress()['3.0']);
        self::assertSame('https://api.wordpress.org/stats/wordpress/1.0/', $http->requests[1]->uri);

        $versions = $stats->pluginVersions('Akismet');

        self::assertSame('https://api.wordpress.org/stats/plugin/1.0/akismet', $http->requests[2]->uri);
        self::assertSame(60.55, $versions['5.7']);
        self::assertSame(16.23, $versions['other']);
    }

    public function testKeysAreKeptAsSent(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('mysql')), new Response(200, self::fixture('locale')));
        $stats = self::stats($http);

        self::assertSame(19.483, $stats->mysql()['MariaDB 10.11']);
        self::assertSame('https://api.wordpress.org/stats/mysql/1.0/', $http->requests[0]->uri);
        self::assertSame(0.235, $stats->locales()['German (Formal)']);
        self::assertSame('https://api.wordpress.org/stats/locale/1.0/', $http->requests[1]->uri);
    }

    public function testSharesSkipNonNumbers(): void
    {
        $http = new MockHttpClient(new Response(200, '{"a":1.5,"b":"x","c":2,"d":null}'));

        self::assertSame(['a' => 1.5, 'c' => 2.0], self::stats($http)->php());
    }

    public function testDownloadsAreInts(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('downloads')));
        $downloads = self::stats($http)->pluginDownloads('akismet');

        self::assertSame(25688, $downloads['2026-10-17']);
        self::assertSame(25618, $downloads['2026-10-18']);
    }

    public function testDownloadsAcceptMixedValues(): void
    {
        // The endpoint serves `0` as a number and everything else as a string.
        $http = new MockHttpClient(new Response(200, '{"2026-04-22":"1","2026-04-23":0,"2026-04-24":"x"}'));

        self::assertSame(['2026-04-22' => 1, '2026-04-23' => 0], self::stats($http)->pluginDownloads('akismet'));
    }

    public function testDownloadsSendLimitWithoutCallback(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'), new Response(200, '{}'));
        $stats = self::stats($http);
        $stats->pluginDownloads('Akismet', 30);
        $stats->pluginDownloads('akismet');

        self::assertSame(
            'https://api.wordpress.org/stats/plugin/1.0/downloads.php?slug=akismet&limit=30',
            $http->requests[0]->uri,
        );
        self::assertSame(
            'https://api.wordpress.org/stats/plugin/1.0/downloads.php?slug=akismet',
            $http->requests[1]->uri,
        );
        self::assertStringNotContainsString('callback', $http->requests[0]->uri);
    }

    public function testUnknownSlugIsEmpty(): void
    {
        // Measured on 2026-10-19: `stats/plugin/1.0/{unknown}` answers HTTP 200 with `[]`.
        $http = new MockHttpClient(new Response(200, '[]'));

        self::assertSame([], self::stats($http)->pluginVersions('no-such-plugin-zzzq'));
    }

    public function testUnsendableSlugSendsNothing(): void
    {
        $http = new MockHttpClient();
        $stats = self::stats($http);

        self::assertSame([], $stats->pluginVersions('../x'));
        self::assertSame([], $stats->pluginVersions('..'));
        self::assertSame([], $stats->pluginDownloads('a b'));
        self::assertSame([], $http->requests);
    }
}
