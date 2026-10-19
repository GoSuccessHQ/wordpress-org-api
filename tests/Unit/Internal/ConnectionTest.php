<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Internal;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Exception\NotFoundException;
use GoSuccess\WordpressOrg\Exception\RateLimitException;
use GoSuccess\WordpressOrg\Exception\ServerException;
use GoSuccess\WordpressOrg\Exception\TransportException;
use GoSuccess\WordpressOrg\Http\Method;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Internal\Connection;
use GoSuccess\WordpressOrg\Internal\Query;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Connection::class)]
#[CoversClass(Query::class)]
final class ConnectionTest extends TestCase
{
    private const string URI = 'https://api.wordpress.org/plugins/info/1.2/';

    public function testEveryRequestCarriesTimeoutsAndUserAgent(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));
        $connection = new Connection(new ClientOptions(timeout: 5.0, connectTimeout: 2.0), $http);

        $connection->get(self::URI);

        $request = $http->requests[0];
        self::assertSame(Method::Get, $request->method);
        self::assertSame(self::URI, $request->uri);
        self::assertSame(5.0, $request->timeout);
        self::assertSame(2.0, $request->connectTimeout);
        self::assertSame(ClientOptions::DEFAULT_USER_AGENT, $request->headers['User-Agent']);
        self::assertSame('application/json', $request->headers['Accept']);
    }

    public function testParsedStatusIsReturned(): void
    {
        $http = new MockHttpClient(new Response(404, '{"error":"Plugin not found."}'));

        $response = new Connection(new ClientOptions(), $http)->get(self::URI, [404]);

        self::assertSame(404, $response->statusCode);
    }

    public function testOtherErrorStatusThrows(): void
    {
        $connection = new Connection(new ClientOptions(), new MockHttpClient(new Response(404)));

        $this->expectException(NotFoundException::class);

        $connection->get(self::URI);
    }

    public function testRateLimitIsNeverRetried(): void
    {
        $http = new MockHttpClient(new Response(429, '', ['retry-after' => '120']));
        $connection = new Connection(new ClientOptions(maxRetries: 3), $http);

        try {
            $connection->get(self::URI);
            self::fail('Expected a RateLimitException.');
        } catch (RateLimitException $exception) {
            self::assertSame(120, $exception->retryAfter);
        }

        self::assertSame(1, $http->callCount());
    }

    public function testServerErrorIsRetriedExactlyMaxRetriesTimes(): void
    {
        $http = new MockHttpClient(new Response(503), new Response(503), new Response(503));
        $connection = new Connection(new ClientOptions(maxRetries: 2, retryBaseDelay: 0.0), $http);

        try {
            $connection->get(self::URI);
            self::fail('Expected a ServerException.');
        } catch (ServerException) {
            self::assertSame(3, $http->callCount());
        }
    }

    public function testTransportErrorIsRetried(): void
    {
        $http = new MockHttpClient(new TransportException('connection reset'), new Response(200, '{}'));
        $connection = new Connection(new ClientOptions(maxRetries: 1, retryBaseDelay: 0.0), $http);

        self::assertSame(200, $connection->get(self::URI)->statusCode);
        self::assertSame(2, $http->callCount());
    }

    public function testNothingIsRetriedByDefault(): void
    {
        $http = new MockHttpClient(new Response(503));

        try {
            new Connection(new ClientOptions(), $http)->get(self::URI);
            self::fail('Expected a ServerException.');
        } catch (ServerException) {
            self::assertSame(1, $http->callCount());
        }
    }

    public function testPostIsNotRetried(): void
    {
        $http = new MockHttpClient(new Response(503));
        $connection = new Connection(new ClientOptions(maxRetries: 2, retryBaseDelay: 0.0), $http);

        try {
            $connection->postForm(self::URI, ['useragent' => 'Mozilla/5.0']);
            self::fail('Expected a ServerException.');
        } catch (ServerException) {
            self::assertSame(1, $http->callCount());
        }
    }

    public function testPostFormSendsUrlEncodedBody(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));

        new Connection(new ClientOptions(), $http)->postForm(self::URI, ['useragent' => 'Mozilla/5.0']);

        $request = $http->requests[0];
        self::assertSame(Method::Post, $request->method);
        self::assertSame('application/x-www-form-urlencoded', $request->headers['Content-Type']);
        self::assertSame('useragent=Mozilla%2F5.0', $request->body);
    }

    public function testQueryEncodesNestedParamsAndDropsNull(): void
    {
        $query = Query::encode(['request' => ['slug' => 'akismet', 'fields' => ['icons' => true, 'sections' => false], 'skip' => null], 'a b' => 'c d', 'none' => null]);

        self::assertSame('request%5Bslug%5D=akismet&request%5Bfields%5D%5Bicons%5D=1&request%5Bfields%5D%5Bsections%5D=0&a%20b=c%20d', $query);
    }
}
