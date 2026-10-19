<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Integration;

use GoSuccess\WordpressOrg\Directory\LookupStatus;
use GoSuccess\WordpressOrg\Plugins\PluginQuery;
use GoSuccess\WordpressOrg\Tests\Support\LiveTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PluginsLiveTest extends LiveTestCase
{
    private static ?string $akismetVersion = null;

    public function testLookupClassifiesFoundClosedAndUnknownSlugs(): void
    {
        $results = $this->client->plugins->lookup(['akismet', 'display-widgets', 'no-such-plugin-zzzq']);

        self::assertSame(['akismet', 'display-widgets', 'no-such-plugin-zzzq'], array_keys($results));
        self::assertSame(LookupStatus::Found, $results['akismet']->status);
        self::$akismetVersion ??= $results['akismet']->version();
        self::assertNotNull($results['akismet']->version());
        self::assertSame(LookupStatus::Closed, $results['display-widgets']->status);
        self::assertNotNull($results['display-widgets']->closure?->date);
        self::assertSame(LookupStatus::NotFound, $results['no-such-plugin-zzzq']->status);
    }

    public function testSearchReturnsAPageOfPlugins(): void
    {
        $page = $this->client->plugins->search(new PluginQuery(search: 'akismet', perPage: 3));

        self::assertGreaterThan(0, $page->results);
        self::assertNotSame([], $page->plugins);
    }

    public function testChecksumsOfTheLatestAkismetVersion(): void
    {
        $checksums = $this->client->plugins->checksums('akismet', $this->akismetVersion());

        self::assertNotNull($checksums);
        self::assertNotSame([], $checksums->files);
    }

    /**
     * One plugins/info batch request per run, shared by the tests.
     */
    private function akismetVersion(): string
    {
        self::$akismetVersion ??= $this->client->plugins->lookup(['akismet'])['akismet']->version();

        self::assertNotNull(self::$akismetVersion);

        return self::$akismetVersion;
    }
}
