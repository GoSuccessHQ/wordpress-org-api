<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Integration;

use GoSuccess\WordpressOrg\Events\EventQuery;
use GoSuccess\WordpressOrg\Patterns\PatternQuery;
use GoSuccess\WordpressOrg\Tests\Support\LiveTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class OtherEndpointsLiveTest extends LiveTestCase
{
    public function testTranslationsOfCore(): void
    {
        self::assertNotSame([], $this->client->translations->core('6.8'));
    }

    public function testStatsOfWordpressVersions(): void
    {
        self::assertNotSame([], $this->client->stats->wordpress());
    }

    public function testPatternSearch(): void
    {
        $page = $this->client->patterns->search(new PatternQuery(search: 'button', perPage: 2));

        self::assertNotSame([], $page->patterns);
    }

    public function testEventsForALocation(): void
    {
        $events = $this->client->events->upcoming(new EventQuery(location: 'Dresden', number: 3));

        self::assertNull($events->error);
        self::assertNotNull($events->location);
    }

    public function testSecretKeysHaveAllConstants(): void
    {
        $salts = $this->client->secretKeys->generate();

        self::assertCount(8, $salts->values);
        self::assertNotNull($salts->get('AUTH_KEY'));
    }
}
