<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Core;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Core\Contributor;
use GoSuccess\WordpressOrg\Core\Core;
use GoSuccess\WordpressOrg\Core\CreditGroup;
use GoSuccess\WordpressOrg\Core\Credits;
use GoSuccess\WordpressOrg\Core\Library;
use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Core::class)]
#[CoversClass(Credits::class)]
#[CoversClass(CreditGroup::class)]
#[CoversClass(Contributor::class)]
#[CoversClass(Library::class)]
final class CreditsTest extends TestCase
{
    private static function credits(string $body): Credits
    {
        return new WordpressOrg(new ClientOptions(), new MockHttpClient(new Response(200, $body)))->core->credits('7.1');
    }

    private static function fixture(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Fixtures/core/credits.json');
    }

    public function testCreditGroupsByType(): void
    {
        $credits = self::credits(self::fixture());

        self::assertSame(['core-developers', 'contributing-developers', 'props', 'libraries'], array_keys($credits->groups));
        self::assertSame('7.1', $credits->data['version']);

        $titles = $credits->groups['core-developers'];
        self::assertSame('Noteworthy Contributors', $titles->name);
        self::assertSame('titles', $titles->type);
        self::assertFalse($titles->shuffle);
        self::assertCount(2, $titles->contributors);
        self::assertEquals(
            new Contributor(
                'Anne McCarthy',
                '4f50dc56c80eceb636a7ef7d036a019a7d4189e8b3f87d9c899e848248ef36d8',
                'annezazu',
                'Release Lead',
            ),
            $titles->contributors[0],
        );

        // `name` is `false` for a group without a heading.
        self::assertNull($credits->groups['contributing-developers']->name);
        self::assertTrue($credits->groups['contributing-developers']->shuffle);

        $list = $credits->groups['props'];
        self::assertSame('list', $list->type);
        self::assertNull($list->shuffle);
        self::assertEquals(new Contributor('1shiharaT', null, 'ishihara-takashi', null), $list->contributors[0]);

        $libraries = $credits->groups['libraries'];
        self::assertSame('libraries', $libraries->type);
        self::assertSame([], $libraries->contributors);
        self::assertEquals(new Library('Babel Polyfill', 'https://babeljs.io/docs/babel-polyfill'), $libraries->libraries[0]);
    }

    public function testRequestUri(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));

        new WordpressOrg(new ClientOptions(), $http)->core->credits('7.1');

        self::assertSame('https://api.wordpress.org/core/credits/1.1/?version=7.1', $http->requests[0]->uri);
    }

    public function testCompactGroup(): void
    {
        $credits = self::credits(
            '{"groups":{"x":{"name":"Compact","type":"compact","data":{"u":["Name","abc","u"]}}},"data":{}}',
        );

        self::assertEquals(new Contributor('Name', 'abc', 'u', null), $credits->groups['x']->contributors[0]);
    }

    public function testUnknownGroupTypeKeepsRaw(): void
    {
        $credits = self::credits('{"groups":{"x":{"name":"X","type":"mystery","data":{"a":[1,2]}}}}');

        $group = $credits->groups['x'];
        self::assertSame('mystery', $group->type);
        self::assertSame(['a' => [1, 2]], $group->raw);
        self::assertSame([], $group->contributors);
        self::assertSame([], $group->libraries);
    }

    public function testMalformedEntriesAreSkipped(): void
    {
        $credits = self::credits('{"groups":{"a":"text","x":{"type":"libraries","data":[["Ok","u"],"bad",[null]]}}}');

        self::assertSame(['x'], array_keys($credits->groups));
        self::assertEquals([new Library('Ok', 'u'), new Library(null, null)], $credits->groups['x']->libraries);
    }

    public function testLocaleIsSent(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));

        new WordpressOrg(new ClientOptions(), $http)->core->credits('7.1', 'de_DE');

        self::assertSame('https://api.wordpress.org/core/credits/1.1/?version=7.1&locale=de_DE', $http->requests[0]->uri);
    }

    public function testHtmlThrows(): void
    {
        $this->expectException(SerializationException::class);

        self::credits('<html>');
    }
}
