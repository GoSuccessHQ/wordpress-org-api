<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Integration;

use GoSuccess\WordpressOrg\Directory\LookupStatus;
use GoSuccess\WordpressOrg\Tests\Support\LiveTestCase;
use GoSuccess\WordpressOrg\Themes\ThemeQuery;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ThemesLiveTest extends LiveTestCase
{
    public function testLookupClassifiesFoundAndUnknownSlugs(): void
    {
        $results = $this->client->themes->lookup(['twentytwentyfive', 'no-such-theme-zzzq']);

        self::assertSame(LookupStatus::Found, $results['twentytwentyfive']->status);
        self::assertNotNull($results['twentytwentyfive']->version());
        self::assertSame(LookupStatus::NotFound, $results['no-such-theme-zzzq']->status);
    }

    public function testSearchReturnsAPageOfThemes(): void
    {
        $page = $this->client->themes->search(new ThemeQuery(browse: 'popular', perPage: 3));

        self::assertGreaterThan(0, $page->results);
        self::assertNotSame([], $page->themes);
    }

    public function testFeatureListHasGroups(): void
    {
        self::assertNotSame([], $this->client->themes->featureList());
    }
}
