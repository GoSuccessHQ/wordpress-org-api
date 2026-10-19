<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Plugins;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Directory\DirectoryClient;
use GoSuccess\WordpressOrg\Directory\LookupStatus;
use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Exception\ServerException;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Plugins\PluginLookup;
use GoSuccess\WordpressOrg\Plugins\Plugins;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Plugins::class)]
#[CoversClass(DirectoryClient::class)]
final class PluginsLookupTest extends TestCase
{
    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Fixtures/plugins/' . $name . '.json');
    }

    private static function plugins(MockHttpClient $http, int $batchSize = 50): Plugins
    {
        return new WordpressOrg(new ClientOptions(batchSize: $batchSize), $http)->plugins;
    }

    /**
     * @return array<array-key, mixed> decoded query of the request at $index
     */
    private static function query(MockHttpClient $http, int $index = 0): array
    {
        parse_str((string) parse_url($http->requests[$index]->uri, \PHP_URL_QUERY), $query);

        return $query;
    }

    private static function slugsOf(MockHttpClient $http, int $index): mixed
    {
        $request = self::query($http, $index)['request'] ?? [];

        return \is_array($request) ? ($request['slugs'] ?? null) : null;
    }

    public function testOneRequestPerChunk(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'), new Response(200, '{}'), new Response(200, '{}'));

        self::plugins($http, 2)->lookup(['a', 'b', 'c', 'd', 'e']);

        self::assertSame(3, $http->callCount());
        self::assertSame('a,b', self::slugsOf($http, 0));
        self::assertSame('c,d', self::slugsOf($http, 1));
        self::assertSame('e', self::slugsOf($http, 2));
        self::assertStringStartsWith('https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&', $http->requests[0]->uri);
    }

    public function testDuplicatesAreSentOnce(): void
    {
        $http = new MockHttpClient(new Response(200, '{"akismet":{"version":"5.7.2"}}'));

        $result = self::plugins($http)->lookup(['Akismet', 'akismet', 'Akismet']);

        self::assertSame('akismet', self::slugsOf($http, 0));
        self::assertSame(['Akismet', 'akismet'], array_keys($result));
        self::assertSame(LookupStatus::Found, $result['Akismet']->status);
        self::assertSame('5.7.2', $result['Akismet']->version());
        self::assertSame('Akismet', $result['Akismet']->slug);
        self::assertSame('5.7.2', $result['akismet']->version());
    }

    public function testSlugIsSentLowerCased(): void
    {
        $http = new MockHttpClient(new Response(200, '{"akismet":{"version":"1"}}'), new Response(200, '{"version":"1","slug":"akismet"}'));
        $plugins = self::plugins($http);

        self::assertSame(LookupStatus::Found, $plugins->lookup(['Akismet'])['Akismet']->status);
        self::assertSame('akismet', self::slugsOf($http, 0));

        $info = $plugins->information('Akismet');
        $request = self::query($http, 1)['request'];
        self::assertIsArray($request);
        self::assertSame('akismet', $request['slug']);
        self::assertSame('Akismet', $info->slug);
    }

    public function testExactKeyBeatsKeyDifferingInCase(): void
    {
        $http = new MockHttpClient(new Response(200, '{"foo":{"error":"Plugin not found."},"Foo":{"version":"1"}}'));

        self::assertSame(LookupStatus::NotFound, self::plugins($http)->lookup(['foo'])['foo']->status);
    }

    public function testResultKeepsCallerOrderAndKeys(): void
    {
        $http = new MockHttpClient(new Response(200, '{"b":{"version":"2"},"a":{"version":"1"}}'), new Response(200, '{"c":{"version":"3"}}'));

        $result = self::plugins($http, 2)->lookup(['b', '!bad', 'a', 'c']);

        self::assertSame(['b', '!bad', 'a', 'c'], array_keys($result));
        self::assertSame('2', $result['b']->version());
        self::assertSame(LookupStatus::Unknown, $result['!bad']->status);
        self::assertSame('3', $result['c']->version());
    }

    public function testBatchFixtureGivesFoundClosedNotFound(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('batch')));

        $result = self::plugins($http)->lookup(['akismet', 'display-widgets', 'no-such-plugin-zzzq']);

        self::assertSame(LookupStatus::Found, $result['akismet']->status);
        self::assertSame('5.7.2', $result['akismet']->version());
        self::assertSame(LookupStatus::Closed, $result['display-widgets']->status);
        self::assertSame('security-issue', $result['display-widgets']->closure?->reason);
        self::assertSame(LookupStatus::NotFound, $result['no-such-plugin-zzzq']->status);
        self::assertSame('Plugin not found.', $result['no-such-plugin-zzzq']->error);
    }

    public function testMalformedEntryDoesNotFailTheBatch(): void
    {
        $http = new MockHttpClient(new Response(200, '{"a":{"version":"1.0"},"b":"oops","c":{"version":["x"]}}'));

        $result = self::plugins($http)->lookup(['a', 'b', 'c']);

        self::assertSame(LookupStatus::Found, $result['a']->status);
        self::assertSame('1.0', $result['a']->version());
        self::assertSame(LookupStatus::Unknown, $result['b']->status);
        self::assertSame(LookupStatus::Found, $result['c']->status);
        self::assertNull($result['c']->version());
    }

    public function testMissingAndDifferentlyCasedKeys(): void
    {
        $http = new MockHttpClient(new Response(200, '{"akismet":{"version":"1"},"extra":{"version":"2"}}'));

        $result = self::plugins($http)->lookup(['Akismet', 'gone']);

        self::assertSame('akismet,gone', self::slugsOf($http, 0));
        self::assertSame(['Akismet', 'gone'], array_keys($result));
        self::assertSame(LookupStatus::Found, $result['Akismet']->status);
        self::assertSame('Akismet', $result['Akismet']->slug);
        self::assertSame(LookupStatus::Unknown, $result['gone']->status);
    }

    public function testUnsendableSlugIsUnknownAndNotSent(): void
    {
        $http = new MockHttpClient(new Response(200, '{"a":{"version":"1"}}'));

        $result = self::plugins($http)->lookup(['a', 'b,c', '..']);

        self::assertSame('a', self::slugsOf($http, 0));
        self::assertSame(LookupStatus::Unknown, $result['b,c']->status);
        self::assertSame(LookupStatus::Unknown, $result['..']->status);
    }

    public function testNoRequestForEmptyOrUnsendableList(): void
    {
        $http = new MockHttpClient();
        $plugins = self::plugins($http);

        self::assertSame([], $plugins->lookup([]));
        self::assertSame([], iterator_to_array($plugins->lookupChunks([])));

        $result = $plugins->lookup(['..', 'a b']);

        self::assertSame(['..', 'a b'], array_keys($result));
        self::assertSame(LookupStatus::Unknown, $result['a b']->status);
        self::assertSame(0, $http->callCount());
    }

    public function testEmptyArrayBodyIsEmptyResult(): void
    {
        $http = new MockHttpClient(new Response(200, '[]'));

        $result = self::plugins($http)->lookup(['a', 'b']);

        self::assertSame(LookupStatus::Unknown, $result['a']->status);
        self::assertSame(LookupStatus::Unknown, $result['b']->status);
    }

    public function testFailingSecondChunkThrowsFromLookup(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'), new Response(503));

        $this->expectException(ServerException::class);

        self::plugins($http, 1)->lookup(['a', 'b']);
    }

    public function testLookupChunksYieldsFirstChunkThenThrows(): void
    {
        $http = new MockHttpClient(new Response(200, '{"a":{"version":"1"}}'), new Response(503));
        $chunks = self::plugins($http, 1)->lookupChunks(['a', 'b']);

        self::assertSame(0, $http->callCount());
        $chunks->rewind();
        self::assertSame(0, $chunks->key());
        self::assertSame(['a'], array_keys($chunks->current()));
        self::assertSame(1, $http->callCount());

        $this->expectException(ServerException::class);

        try {
            $chunks->next();
        } finally {
            self::assertSame(2, $http->callCount());
        }
    }

    public function testHtmlWith200Throws(): void
    {
        $http = new MockHttpClient(new Response(200, '<html>maintenance</html>'));

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('plugins/info/1.2');

        self::plugins($http)->lookup(['a']);
    }

    public function testInformationParses404(): void
    {
        $http = new MockHttpClient(new Response(404, self::fixture('information-closed')), new Response(404, '{"error":"Plugin not found."}'));
        $plugins = self::plugins($http);

        $closed = $plugins->information('display-widgets');
        $missing = $plugins->information('nope');

        self::assertInstanceOf(PluginLookup::class, $closed);
        self::assertSame(LookupStatus::Closed, $closed->status);
        self::assertSame(LookupStatus::NotFound, $missing->status);
        $request = self::query($http, 1)['request'];
        self::assertIsArray($request);
        self::assertSame('nope', $request['slug']);
        self::assertArrayNotHasKey('fields', $request);
    }

    public function testInformationOtherStatusThrows(): void
    {
        $http = new MockHttpClient(new Response(500));

        $this->expectException(ServerException::class);

        self::plugins($http)->information('akismet');
    }

    public function testInformationOfUnsendableSlugSendsNothing(): void
    {
        $http = new MockHttpClient();

        self::assertSame(LookupStatus::Unknown, self::plugins($http)->information('..')->status);
        self::assertSame(0, $http->callCount());
    }

    public function testMinimalFieldsAreSent(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));

        self::plugins($http)->lookup(['a']);

        self::assertStringContainsString('request[fields][sections]=0', rawurldecode($http->requests[0]->uri));
    }
}
