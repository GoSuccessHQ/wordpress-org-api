<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Plugins;

use GoSuccess\WordpressOrg\Directory\LookupStatus;
use GoSuccess\WordpressOrg\Plugins\Plugin;
use GoSuccess\WordpressOrg\Plugins\PluginLookup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Plugin::class)]
#[CoversClass(PluginLookup::class)]
final class PluginTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function fixture(string $name): array
    {
        $data = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plugins/' . $name . '.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function testReadsTheFullFixture(): void
    {
        $data = self::fixture('information-akismet');
        $plugin = Plugin::fromArray($data);

        self::assertSame('Akismet Anti-spam: Spam Protection', $plugin->name);
        self::assertSame('akismet', $plugin->slug);
        self::assertSame('5.7.2', $plugin->version);
        self::assertSame('<a href="https://profiles.wordpress.org/automattic/">Automattic</a>', $plugin->author);
        self::assertSame('https://profiles.wordpress.org/automattic/', $plugin->authorProfile);
        self::assertSame('5.8', $plugin->requires);
        self::assertSame('7.1.3', $plugin->tested);
        self::assertSame('7.2', $plugin->requiresPhp);
        self::assertSame('https://akismet.com/', $plugin->homepage);
        self::assertSame($data['short_description'], $plugin->shortDescription);
        self::assertSame($data['description'], $plugin->description);
        self::assertSame('https://downloads.wordpress.org/plugin/akismet.5.7.2.zip', $plugin->downloadLink);
        self::assertSame('', $plugin->donateLink);
        self::assertSame('commercial', $plugin->businessModel);
        self::assertSame('', $plugin->repositoryUrl);
        self::assertSame('https://akismet.com/getting-started/free-or-paid', $plugin->commercialSupportUrl);
        self::assertSame('https://wordpress.org/support/plugin/akismet/', $plugin->supportUrl);
        self::assertSame('https://wordpress.org/plugins/akismet/?preview=1', $plugin->previewLink);
        self::assertSame(94, $plugin->rating);
        self::assertSame(1187, $plugin->numRatings);
        self::assertSame(1, $plugin->supportThreads);
        self::assertSame(0, $plugin->supportThreadsResolved);
        self::assertSame(5000000, $plugin->activeInstalls);
        self::assertSame(407684612, $plugin->downloaded);
        self::assertSame([5 => 1034, 4 => 67, 3 => 16, 2 => 13, 1 => 57], $plugin->ratings);
        self::assertSame('2026-08-18T23:42:00+00:00', $plugin->lastUpdated?->format('c'));
        self::assertSame('2005-10-20', $plugin->added?->format('Y-m-d'));
        self::assertSame([], $plugin->requiresPlugins);
        self::assertSame(['description', 'changelog'], array_keys($plugin->sections));
        self::assertSame('anti-spam', $plugin->tags['anti-spam']);
        self::assertSame(['5.7.2', 'trunk'], array_keys($plugin->versions));
        self::assertArrayHasKey('high', $plugin->banners);
        self::assertArrayHasKey('2x', $plugin->icons);
        self::assertSame([], $plugin->upgradeNotice);
        self::assertSame([], $plugin->screenshots);
        self::assertSame(['matt', 'ryan'], array_keys($plugin->contributors));
        self::assertSame([], $plugin->compatibility);
        self::assertSame($data, $plugin->raw);
    }

    public function testRequiresPhpFalseIsNull(): void
    {
        self::assertNull(Plugin::fromArray(['requires_php' => false])->requiresPhp);
    }

    public function testWrongTypesBecomeNull(): void
    {
        $plugin = Plugin::fromArray([
            'name' => 'Akismet',
            'version' => ['x'],
            'rating' => 'high',
            'tags' => 'x',
            'ratings' => ['5' => 3, 'x' => 2, '4' => 'many'],
            'requires_plugins' => 'x',
        ]);

        self::assertNull($plugin->version);
        self::assertNull($plugin->rating);
        self::assertSame([], $plugin->tags);
        self::assertSame([5 => 3], $plugin->ratings);
        self::assertSame([], $plugin->requiresPlugins);
        self::assertSame('Akismet', $plugin->name);
    }

    public function testEntitiesAndMarkupPassThrough(): void
    {
        $plugin = Plugin::fromArray([
            'name' => 'UpdraftPlus: WP Backup &amp; Migration Plugin',
            'author' => '<a href="https://updraftplus.com">UpdraftPlus.Com</a>',
        ]);

        self::assertSame('UpdraftPlus: WP Backup &amp; Migration Plugin', $plugin->name);
        self::assertSame('<a href="https://updraftplus.com">UpdraftPlus.Com</a>', $plugin->author);
    }

    public function testRawKeepsUnknownFields(): void
    {
        self::assertSame(1, Plugin::fromArray(['new_field' => 1])->raw['new_field']);
    }

    public function testLookupFromClosedSingleResponse(): void
    {
        $lookup = PluginLookup::fromEntry('display-widgets', self::fixture('information-closed'));

        self::assertSame('display-widgets', $lookup->slug);
        self::assertSame(LookupStatus::Closed, $lookup->status);
        self::assertNull($lookup->plugin);
        self::assertSame('2021-01-30', $lookup->closure?->date?->format('Y-m-d'));
        self::assertSame('closed', $lookup->error);
        self::assertNull($lookup->version());
    }

    public function testLookupFromFoundAndMissingEntries(): void
    {
        $batch = self::fixture('batch');

        $found = PluginLookup::fromEntry('akismet', $batch['akismet']);
        self::assertSame(LookupStatus::Found, $found->status);
        self::assertSame('5.7.2', $found->version());
        self::assertNull($found->closure);
        self::assertNull($found->error);

        $missing = PluginLookup::fromEntry('no-such-plugin-zzzq', $batch['no-such-plugin-zzzq']);
        self::assertSame(LookupStatus::NotFound, $missing->status);
        self::assertNull($missing->plugin);
        self::assertSame('Plugin not found.', $missing->error);

        $unknown = PluginLookup::fromEntry('gone', null);
        self::assertSame(LookupStatus::Unknown, $unknown->status);
        self::assertNull($unknown->version());
    }
}
