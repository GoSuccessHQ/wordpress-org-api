<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Themes;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Directory\DirectoryClient;
use GoSuccess\WordpressOrg\Directory\FieldSelection;
use GoSuccess\WordpressOrg\Directory\LookupStatus;
use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\Themes\ThemePage;
use GoSuccess\WordpressOrg\Themes\ThemeQuery;
use GoSuccess\WordpressOrg\Themes\Themes;
use GoSuccess\WordpressOrg\Themes\ThemeTag;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Themes::class)]
#[CoversClass(ThemeQuery::class)]
#[CoversClass(ThemePage::class)]
#[CoversClass(ThemeTag::class)]
#[CoversClass(DirectoryClient::class)]
final class ThemesTest extends TestCase
{
    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Fixtures/themes/' . $name . '.json');
    }

    private static function themes(MockHttpClient $http): Themes
    {
        return new WordpressOrg(new ClientOptions(), $http)->themes;
    }

    private static function page(int $page, int $pages, string ...$slugs): Response
    {
        $themes = array_map(static fn(string $slug): array => ['slug' => $slug, 'name' => $slug], $slugs);

        return new Response(200, json_encode(['info' => ['page' => $page, 'pages' => $pages, 'results' => $pages * 2], 'themes' => $themes], \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function request(MockHttpClient $http, int $index = 0): array
    {
        parse_str((string) parse_url($http->requests[$index]->uri, \PHP_URL_QUERY), $query);

        $request = $query['request'] ?? [];

        return \is_array($request) ? $request : [];
    }

    public function testBatchFoundAndNotFound(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('batch')));

        $result = self::themes($http)->lookup(['TwentyTwentyFive', 'no-such-theme-zzzq']);

        self::assertStringStartsWith('https://api.wordpress.org/themes/info/1.2/?action=theme_information&', $http->requests[0]->uri);
        self::assertSame('twentytwentyfive,no-such-theme-zzzq', self::request($http)['slugs']);
        self::assertSame(LookupStatus::Found, $result['TwentyTwentyFive']->status);
        self::assertSame('1.5', $result['TwentyTwentyFive']->version());
        self::assertSame(LookupStatus::NotFound, $result['no-such-theme-zzzq']->status);
        self::assertSame('Theme not found', $result['no-such-theme-zzzq']->error);
    }

    public function testLookupSendsMinimalFieldsAndNothingForUnsendableSlugs(): void
    {
        $http = new MockHttpClient(new Response(200, '{"a":{"version":"1"}}'));

        self::assertSame(LookupStatus::Unknown, self::themes($http)->lookup(['..'])['..']->status);
        self::assertSame(0, $http->callCount());

        self::themes($http)->lookup(['a']);

        self::assertSame(array_map(static fn(bool $on): string => $on ? '1' : '0', FieldSelection::minimalTheme()->toRequest()), self::request($http)['fields']);
    }

    public function testLookupAnswerWithoutRequestedSlugThrows(): void
    {
        $http = new MockHttpClient(new Response(200, '{"error":"Invalid Input"}'));

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('themes/info/1.2: answer contains none of the requested slugs (HTTP 200)');

        self::themes($http)->lookup(['a']);
    }

    public function testInformationReadsTheTheme(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('information')));

        $lookup = self::themes($http)->information('TwentyTwentyFive');

        self::assertSame('twentytwentyfive', self::request($http)['slug']);
        self::assertSame(LookupStatus::Found, $lookup->status);
        self::assertSame('Twenty Twenty-Five', $lookup->theme?->name);
    }

    public function testInformation404IsNotFound(): void
    {
        $http = new MockHttpClient(new Response(404, '{"error":"Theme not found"}'));

        $lookup = self::themes($http)->information('no-such-theme-zzzq');

        self::assertSame(LookupStatus::NotFound, $lookup->status);
        self::assertSame('Theme not found', $lookup->error);
        self::assertNull($lookup->theme);
    }

    public function testSearchSendsQuery(): void
    {
        $http = new MockHttpClient(self::page(3, 9, 'a'));

        self::themes($http)->search(new ThemeQuery(
            search: 'blog',
            tags: ['one-column', 'portfolio'],
            theme: 'twentytwentyfive',
            author: 'someone',
            browse: 'popular',
            page: 3,
            perPage: 2,
            fields: new FieldSelection(['sections' => false]),
        ));

        self::assertStringStartsWith('https://api.wordpress.org/themes/info/1.2/?action=query_themes&', $http->requests[0]->uri);
        self::assertSame([
            'search' => 'blog',
            'tag' => 'one-column,portfolio',
            'theme' => 'twentytwentyfive',
            'author' => 'someone',
            'browse' => 'popular',
            'page' => '3',
            'per_page' => '2',
            'fields' => ['sections' => '0'],
        ], self::request($http));
    }

    public function testSearchReadsInfoAndThemes(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('query')));

        $page = self::themes($http)->search(new ThemeQuery(perPage: 2));

        self::assertSame(['page' => '1', 'per_page' => '2'], self::request($http));
        self::assertSame(1, $page->page);
        self::assertSame(4389, $page->pages);
        self::assertSame(8777, $page->results);
        self::assertCount(1, $page->themes);
        self::assertSame('blogchrome', $page->themes[0]->slug);
    }

    public function testSearchRejectsNonObjectBody(): void
    {
        $http = new MockHttpClient(new Response(200, '"nope"'));

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('themes/info/1.2');

        self::themes($http)->search(new ThemeQuery());
    }

    public function testSearchAllStopsAtLastPage(): void
    {
        $http = new MockHttpClient(self::page(1, 2, 'a', 'b'), self::page(2, 2, 'c'));

        $slugs = array_map(
            static fn($theme) => $theme->slug,
            iterator_to_array(self::themes($http)->searchAll(new ThemeQuery(search: 'x')), false),
        );

        self::assertSame(['a', 'b', 'c'], $slugs);
        self::assertSame(2, $http->callCount());
        self::assertSame('2', self::request($http, 1)['page']);
        self::assertSame('x', self::request($http, 1)['search']);
    }

    public function testHotTags(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('hot-tags')));

        $tags = self::themes($http)->hotTags(3);

        self::assertStringStartsWith('https://api.wordpress.org/themes/info/1.2/?action=hot_tags&', $http->requests[0]->uri);
        self::assertSame(['number' => '3'], self::request($http));
        self::assertCount(3, $tags);
        self::assertSame('custom-menu', $tags[0]->slug);
        self::assertSame('Custom menu', $tags[0]->name);
        self::assertSame(13317, $tags[0]->count);
        self::assertSame('threaded-comments', $tags[2]->slug);
    }

    public function testHotTagsEmptyArray(): void
    {
        $http = new MockHttpClient(new Response(200, '[]'));

        self::assertSame([], self::themes($http)->hotTags());
        self::assertSame([], self::request($http));
    }

    public function testHotTagFallsBackToObjectKey(): void
    {
        $http = new MockHttpClient(new Response(200, '{"blog":{"name":"Blog","count":5},"x":"junk"}'));

        $tags = self::themes($http)->hotTags();

        self::assertCount(1, $tags);
        self::assertSame('blog', $tags[0]->slug);
        self::assertSame(5, $tags[0]->count);
    }

    public function testFeatureList(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('feature-list')));

        $groups = self::themes($http)->featureList();

        self::assertStringStartsWith('https://api.wordpress.org/themes/info/1.2/?action=feature_list', $http->requests[0]->uri);
        self::assertSame(['Layout', 'Features', 'Subject'], array_keys($groups));
        self::assertContains('grid-layout', $groups['Layout']);
    }
}
