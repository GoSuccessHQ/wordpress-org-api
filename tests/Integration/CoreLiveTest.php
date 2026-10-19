<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Integration;

use GoSuccess\WordpressOrg\Core\Stability;
use GoSuccess\WordpressOrg\Tests\Support\LiveTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class CoreLiveTest extends LiveTestCase
{
    public function testLatestVersionLooksLikeAVersion(): void
    {
        $latest = $this->client->core->versionCheck()->latest();

        self::assertNotNull($latest);
        self::assertMatchesRegularExpression('/^\d+\.\d+(\.\d+)?$/', $latest);
    }

    public function testStabilityOfTheLatestVersionIsLatest(): void
    {
        $latest = $this->client->core->versionCheck()->latest();

        self::assertNotNull($latest);
        self::assertSame(Stability::Latest, $this->client->core->stability($latest));
    }

    public function testChecksumsOfTheLatestVersionAreNotEmpty(): void
    {
        $latest = $this->client->core->versionCheck()->latest();

        self::assertNotNull($latest);

        $checksums = $this->client->core->checksums($latest);

        self::assertNotNull($checksums);
        self::assertNotSame([], $checksums->files);
    }
}
