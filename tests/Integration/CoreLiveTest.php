<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Integration;

use GoSuccess\WordpressOrg\Core\Stability;
use GoSuccess\WordpressOrg\Tests\Support\LiveTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class CoreLiveTest extends LiveTestCase
{
    private static ?string $latest = null;

    public function testLatestVersionLooksLikeAVersion(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+(\.\d+)?$/', $this->latest());
    }

    public function testStabilityOfTheLatestVersion(): void
    {
        // The two endpoints can briefly disagree while a release is rolled out.
        self::assertContains($this->client->core->stability($this->latest()), [Stability::Latest, Stability::Outdated]);
    }

    public function testChecksumsOfTheLatestVersionAreNotEmpty(): void
    {
        $checksums = $this->client->core->checksums($this->latest());

        self::assertNotNull($checksums);
        self::assertNotSame([], $checksums->files);
    }

    /**
     * One version-check request per run, shared by the tests.
     */
    private function latest(): string
    {
        self::$latest ??= $this->client->core->versionCheck()->latest();

        self::assertNotNull(self::$latest);

        return self::$latest;
    }
}
