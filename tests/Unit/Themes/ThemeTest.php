<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Themes;

use GoSuccess\WordpressOrg\Directory\LookupStatus;
use GoSuccess\WordpressOrg\Themes\Theme;
use GoSuccess\WordpressOrg\Themes\ThemeAuthor;
use GoSuccess\WordpressOrg\Themes\ThemeLookup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Theme::class)]
#[CoversClass(ThemeAuthor::class)]
#[CoversClass(ThemeLookup::class)]
final class ThemeTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function fixture(string $name): array
    {
        $data = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/themes/' . $name . '.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function testReadsTheFullFixture(): void
    {
        $data = self::fixture('information');
        $theme = Theme::fromArray($data);

        self::assertSame('Twenty Twenty-Five', $theme->name);
        self::assertSame('twentytwentyfive', $theme->slug);
        self::assertSame('1.5', $theme->version);
        self::assertSame('https://wp-themes.com/twentytwentyfive/', $theme->previewUrl);
        self::assertSame('//ts.w.org/wp-content/themes/twentytwentyfive/screenshot.png?ver=1.5', $theme->screenshotUrl);
        self::assertSame('https://wordpress.org/support/theme/twentytwentyfive/reviews/', $theme->reviewsUrl);
        self::assertSame('https://wordpress.org/themes/twentytwentyfive/', $theme->themeUrl);
        self::assertSame('https://wordpress.org/themes/twentytwentyfive/', $theme->homepage);
        self::assertNull($theme->description);
        self::assertSame('https://downloads.wordpress.org/theme/twentytwentyfive.1.5.zip', $theme->downloadLink);
        self::assertSame('6.7', $theme->requires);
        self::assertSame('7.2', $theme->requiresPhp);
        self::assertNull($theme->externalSupportUrl);
        self::assertSame('', $theme->externalRepositoryUrl);
        self::assertNull($theme->template);
        self::assertNull($theme->parent);

        self::assertNotNull($theme->author);
        self::assertSame('wordpressdotorg', $theme->author->userNicename);
        self::assertSame('https://profiles.wordpress.org/wordpressdotorg/', $theme->author->profile);
        self::assertStringStartsWith('https://secure.gravatar.com/avatar/', (string) $theme->author->avatar);
        self::assertSame('WordPress.org', $theme->author->displayName);
        self::assertSame('https://wordpress.org', $theme->author->authorUrl);

        self::assertSame(80, $theme->rating);
        self::assertSame(14, $theme->numRatings);
        self::assertSame(10937559, $theme->downloaded);
        self::assertSame(1000000, $theme->activeInstalls);
        self::assertSame(1, $theme->screenshotCount);
        self::assertSame(['https://wp-themes.com/wp-content/themes/twentytwentyfive/screenshot.png'], $theme->screenshots);
        self::assertSame([1 => 1, 2 => 2, 3 => 2, 4 => 0, 5 => 9], $theme->ratings);
        self::assertSame('2026-05-20', $theme->lastUpdated?->format('Y-m-d'));
        self::assertSame('2026-05-20 21:54:25', $theme->lastUpdatedTime?->format('Y-m-d H:i:s'));
        self::assertSame('2024-11-13 00:56:57', $theme->creationTime?->format('Y-m-d H:i:s'));
        self::assertFalse($theme->isCommercial);
        self::assertTrue($theme->isCommunity);
        self::assertSame(['description'], \array_slice(array_keys($theme->sections), 0, 1));
        self::assertSame('Block editor patterns', $theme->tags['block-patterns']);
        self::assertSame('https://downloads.wordpress.org/theme/twentytwentyfive.1.0.zip', $theme->versions['1.0']);
        self::assertSame($data, $theme->raw);
    }

    public function testAuthorAsString(): void
    {
        $theme = Theme::fromArray(['slug' => 'x', 'author' => 'Jane Doe']);

        self::assertNotNull($theme->author);
        self::assertSame('Jane Doe', $theme->author->displayName);
        self::assertNull($theme->author->userNicename);
        self::assertNull($theme->author->profile);
        self::assertNull($theme->author->avatar);
        self::assertNull($theme->author->authorUrl);
    }

    public function testAuthorFalseOrMissingIsNull(): void
    {
        self::assertNull(Theme::fromArray(['author' => false])->author);
        self::assertNull(Theme::fromArray([])->author);
    }

    public function testQueryEntryWithParentObject(): void
    {
        $themes = self::fixture('query')['themes'];
        self::assertIsArray($themes);
        self::assertIsArray($themes[0]);

        /** @var array<string, mixed> $entry */
        $entry = $themes[0];
        $theme = Theme::fromArray($entry);

        self::assertSame('chromenews', $theme->template);
        self::assertSame('chromenews', $theme->parent);
        self::assertSame('BlogChrome', $theme->name);
        self::assertSame('AF themes', $theme->author?->displayName);
    }

    public function testToleratesWrongTypes(): void
    {
        $theme = Theme::fromArray(['name' => ['x'], 'rating' => 'high', 'ratings' => 'none', 'is_community' => 'maybe', 'last_updated' => '2026-02-30', 'tags' => [1, 2]]);

        self::assertNull($theme->name);
        self::assertNull($theme->rating);
        self::assertSame([], $theme->ratings);
        self::assertNull($theme->isCommunity);
        self::assertNull($theme->lastUpdated);
        self::assertSame([], $theme->tags);
    }

    public function testLookupFromFixtureEntries(): void
    {
        $batch = self::fixture('batch');

        $found = ThemeLookup::fromEntry('twentytwentyfive', $batch['twentytwentyfive']);
        self::assertSame(LookupStatus::Found, $found->status);
        self::assertSame('1.5', $found->version());

        $missing = ThemeLookup::fromEntry('no-such-theme-zzzq', $batch['no-such-theme-zzzq']);
        self::assertSame(LookupStatus::NotFound, $missing->status);
        self::assertSame('Theme not found', $missing->error);
        self::assertNull($missing->theme);

        self::assertSame(LookupStatus::Unknown, ThemeLookup::fromEntry('x', null)->status);
    }
}
