<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Patterns;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Patterns\Pattern;
use GoSuccess\WordpressOrg\Patterns\PatternPage;
use GoSuccess\WordpressOrg\Patterns\PatternQuery;
use GoSuccess\WordpressOrg\Patterns\Patterns;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Patterns::class)]
#[CoversClass(Pattern::class)]
#[CoversClass(PatternPage::class)]
#[CoversClass(PatternQuery::class)]
final class PatternsTest extends TestCase
{
    private static function fixture(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Fixtures/patterns/search.json');
    }

    public function testPatternsReadListAndTotals(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture(), ['x-wp-total' => '387', 'x-wp-totalpages' => '194']));
        $page = new WordpressOrg(new ClientOptions(), $http)->patterns->search(new PatternQuery('button', perPage: 2));

        self::assertSame('https://api.wordpress.org/patterns/1.0/?search=button&per_page=2', $http->requests[0]->uri);
        self::assertSame(387, $page->total);
        self::assertSame(194, $page->totalPages);
        self::assertCount(2, $page->patterns);

        $pattern = $page->patterns[0];
        self::assertSame(406479, $pattern->id);
        self::assertSame('Header with Button', $pattern->title);
        self::assertStringStartsWith("\n<div class=\"wp-block-group", (string) $pattern->content);
        self::assertStringStartsWith('<!-- wp:group', (string) $pattern->patternContent);
        self::assertSame(['buttons', 'columns'], $pattern->categorySlugs);
        self::assertSame([], $pattern->keywordSlugs);
        self::assertArrayHasKey('wpop_description', $pattern->meta);
        self::assertSame(406479, $pattern->raw['id']);
    }

    public function testMissingHeadersGiveNullTotals(): void
    {
        $http = new MockHttpClient(new Response(200, '[]'));
        $page = new WordpressOrg(new ClientOptions(), $http)->patterns->search();

        self::assertNull($page->total);
        self::assertNull($page->totalPages);
        self::assertSame([], $page->patterns);
        self::assertSame('https://api.wordpress.org/patterns/1.0/?', $http->requests[0]->uri);
    }

    public function testPatternsSendQuery(): void
    {
        $http = new MockHttpClient(new Response(200, '[]'));
        new WordpressOrg(new ClientOptions(), $http)->patterns->search(new PatternQuery(
            categories: [2],
            keywords: [5, 6],
            wpVersion: '6.8',
            locale: 'de_DE',
            page: 2,
            perPage: 2,
        ));

        self::assertSame(
            'https://api.wordpress.org/patterns/1.0/?pattern-categories=2&pattern-keywords=5%2C6&wp-version=6.8&locale=de_DE&page=2&per_page=2',
            $http->requests[0]->uri,
        );
    }

    public function testObjectBodyIsRejected(): void
    {
        $http = new MockHttpClient(new Response(200, '{"code":"x"}'));

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('patterns/1.0');
        new WordpressOrg(new ClientOptions(), $http)->patterns->search();
    }

    public function testBadEntriesAreTolerated(): void
    {
        $http = new MockHttpClient(new Response(200, '["x",{"id":"7","title":"no","category_slugs":"no"}]'));
        $page = new WordpressOrg(new ClientOptions(), $http)->patterns->search();

        self::assertCount(1, $page->patterns);
        self::assertSame(7, $page->patterns[0]->id);
        self::assertNull($page->patterns[0]->title);
        self::assertSame([], $page->patterns[0]->categorySlugs);
    }
}
