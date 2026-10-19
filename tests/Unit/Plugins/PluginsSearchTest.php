<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Plugins;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Directory\DirectoryClient;
use GoSuccess\WordpressOrg\Directory\FieldSelection;
use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Plugins\PluginPage;
use GoSuccess\WordpressOrg\Plugins\PluginQuery;
use GoSuccess\WordpressOrg\Plugins\Plugins;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Plugins::class)]
#[CoversClass(PluginQuery::class)]
#[CoversClass(PluginPage::class)]
#[CoversClass(DirectoryClient::class)]
final class PluginsSearchTest extends TestCase
{
    private static function plugins(MockHttpClient $http): Plugins
    {
        return new WordpressOrg(new ClientOptions(), $http)->plugins;
    }

    private static function page(int $page, int $pages, string ...$slugs): Response
    {
        $plugins = array_map(static fn(string $slug): array => ['slug' => $slug, 'name' => $slug], $slugs);

        return new Response(200, json_encode(['info' => ['page' => $page, 'pages' => $pages, 'results' => $pages * 2], 'plugins' => $plugins], \JSON_THROW_ON_ERROR));
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

    public function testSearchSendsQuery(): void
    {
        $http = new MockHttpClient(self::page(3, 9, 'a'));

        self::plugins($http)->search(new PluginQuery(
            search: 'backup',
            tags: ['seo', 'cache'],
            author: 'someone',
            browse: 'popular',
            user: 'me',
            page: 3,
            perPage: 2,
            fields: new FieldSelection(['sections' => false]),
        ));

        self::assertStringStartsWith('https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&', $http->requests[0]->uri);
        self::assertSame([
            'search' => 'backup',
            'tag' => 'seo,cache',
            'author' => 'someone',
            'browse' => 'popular',
            'user' => 'me',
            'page' => '3',
            'per_page' => '2',
            'fields' => ['sections' => '0'],
        ], self::request($http));
    }

    public function testSearchOmitsUnsetArguments(): void
    {
        $http = new MockHttpClient(self::page(1, 1, 'a'));

        self::plugins($http)->search(new PluginQuery());

        self::assertSame(['page' => '1'], self::request($http));
    }

    public function testSearchReadsInfoAndPlugins(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../../Fixtures/plugins/query.json');
        $http = new MockHttpClient(new Response(200, $body));

        $page = self::plugins($http)->search(new PluginQuery());

        self::assertSame(1, $page->page);
        self::assertSame(36047, $page->pages);
        self::assertSame(72094, $page->results);
        self::assertCount(1, $page->plugins);
        self::assertSame('elementor', $page->plugins[0]->slug);
        self::assertSame('Elementor Website Builder &#8211; more than just a page builder', $page->plugins[0]->name);
    }

    public function testSearchToleratesMissingInfoAndSkipsNonObjects(): void
    {
        $http = new MockHttpClient(new Response(200, '{"plugins":[{"slug":"a"},"x",[1],7,{"slug":"b"}]}'));

        $page = self::plugins($http)->search(new PluginQuery(page: 4));

        self::assertSame(4, $page->page);
        self::assertSame(0, $page->pages);
        self::assertSame(0, $page->results);
        self::assertSame(['a', 'b'], array_map(static fn($plugin) => $plugin->slug, $page->plugins));
    }

    public function testSearchRejectsNonObjectBody(): void
    {
        $http = new MockHttpClient(new Response(200, '"nope"'));

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('plugins/info/1.2');

        self::plugins($http)->search(new PluginQuery());
    }

    public function testSearchAllStopsAtLastPage(): void
    {
        $http = new MockHttpClient(self::page(1, 2, 'a', 'b'), self::page(2, 2, 'c'));

        $slugs = [];

        foreach (self::plugins($http)->searchAll(new PluginQuery(search: 'x')) as $plugin) {
            $slugs[] = $plugin->slug;
        }

        self::assertSame(['a', 'b', 'c'], $slugs);
        self::assertSame(2, $http->callCount());
        self::assertSame('1', self::request($http, 0)['page']);
        self::assertSame('2', self::request($http, 1)['page']);
        self::assertSame('x', self::request($http, 1)['search']);
    }

    public function testSearchAllStartsAtQueryPage(): void
    {
        $http = new MockHttpClient(self::page(5, 6, 'a'), self::page(6, 6, 'b'));

        $slugs = array_map(
            static fn($plugin) => $plugin->slug,
            iterator_to_array(self::plugins($http)->searchAll(new PluginQuery(page: 5)), false),
        );

        self::assertSame(['a', 'b'], $slugs);
        self::assertSame('5', self::request($http, 0)['page']);
    }

    public function testSearchAllStopsWithoutPagesOrOnEmptyPage(): void
    {
        $noPages = new MockHttpClient(self::page(1, 0, 'a'));
        self::assertCount(1, iterator_to_array(self::plugins($noPages)->searchAll(new PluginQuery()), false));
        self::assertSame(1, $noPages->callCount());

        $unreadable = new MockHttpClient(new Response(200, '{"info":{"pages":"many"},"plugins":[{"slug":"a"}]}'));
        self::assertCount(1, iterator_to_array(self::plugins($unreadable)->searchAll(new PluginQuery()), false));
        self::assertSame(1, $unreadable->callCount());

        $empty = new MockHttpClient(self::page(1, 5));
        self::assertSame([], iterator_to_array(self::plugins($empty)->searchAll(new PluginQuery()), false));
        self::assertSame(1, $empty->callCount());
    }

    public function testSearchAllIsLazy(): void
    {
        $http = new MockHttpClient(self::page(1, 3, 'a'), self::page(2, 3, 'b'));

        foreach (self::plugins($http)->searchAll(new PluginQuery()) as $plugin) {
            self::assertSame('a', $plugin->slug);

            break;
        }

        self::assertSame(1, $http->callCount());
    }
}
