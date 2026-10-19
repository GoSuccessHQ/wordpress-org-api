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

    public function testCreditsHaveGroups(): void
    {
        self::assertNotSame([], $this->client->core->credits($this->latest())->groups);
    }

    public function testImportersAreListed(): void
    {
        self::assertNotSame([], $this->client->core->importers());
    }

    public function testHandbookUrlIsAnHttpsUrlOrNull(): void
    {
        $url = $this->client->core->handbookUrl('get_post', $this->latest());

        self::assertTrue($url === null || str_starts_with($url, 'https://'));
    }

    public function testBrowseHappyRecognizesABrowser(): void
    {
        $browser = $this->client->core->browseHappy('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        self::assertNotNull($browser->name);
    }

    public function testServeHappyRatesAPhpVersion(): void
    {
        $support = $this->client->core->serveHappy('8.3');

        self::assertNotNull($support->recommendedVersion);
        self::assertNotNull($support->minimumVersion);
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
