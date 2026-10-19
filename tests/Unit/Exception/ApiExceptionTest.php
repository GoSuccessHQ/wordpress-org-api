<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Exception;

use GoSuccess\WordpressOrg\Exception\ApiException;
use GoSuccess\WordpressOrg\Exception\ErrorDetails;
use GoSuccess\WordpressOrg\Exception\NotFoundException;
use GoSuccess\WordpressOrg\Exception\RateLimitException;
use GoSuccess\WordpressOrg\Exception\WordpressOrgException;
use GoSuccess\WordpressOrg\Http\Method;
use GoSuccess\WordpressOrg\Http\Request;
use GoSuccess\WordpressOrg\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiException::class)]
#[CoversClass(ErrorDetails::class)]
#[CoversClass(RateLimitException::class)]
final class ApiExceptionTest extends TestCase
{
    #[DataProvider('errorBodies')]
    public function testNormalizesTheErrorBodies(string $body, string $message, ?string $errorCode): void
    {
        $exception = ApiException::fromResponse(new Response(400, $body), $this->request());

        self::assertSame($message, $exception->getMessage());
        self::assertSame($errorCode, $exception->errorCode);
        self::assertSame($body, $exception->responseBody);
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function errorBodies(): iterable
    {
        $prefix = 'GET https://api.wordpress.org/plugins/info/1.2/ failed with HTTP 400';

        yield 'error string' => ['{"error":"Plugin not found."}', "{$prefix}: Plugin not found.", null];
        yield 'code and message' => ['{"code":"missing_param","message":"Missing parameter: php_version","status":400}', "{$prefix}: Missing parameter: php_version (missing_param)", 'missing_param'];
        yield 'message wins over error' => ['{"message":"One","error":"Two"}', "{$prefix}: One", null];
        yield 'blank message' => ['{"message":" ","error":"Two"}', "{$prefix}: Two", null];
        yield 'values without strings' => ['{"error":1,"code":2}', $prefix, null];
        yield 'other json' => ['{"foo":"x"}', $prefix, null];
        yield 'plain text' => ['Bad Gateway', "{$prefix}: Bad Gateway", null];
        yield 'json list' => ['["x"]', $prefix, null];
        yield 'empty' => ['', $prefix, null];
    }

    public function testReadsWordpressOrgErrorBodies(): void
    {
        $error = ApiException::fromResponse(new Response(404, '{"error":"Plugin not found."}'), $this->request());
        $code = ApiException::fromResponse(new Response(400, '{"code":"missing_param","message":"Missing parameter: php_version","status":400}'), $this->request());

        self::assertStringContainsString('Plugin not found.', $error->getMessage());
        self::assertSame('missing_param', $code->errorCode);
    }

    public function testIncludesTheReasonPhraseButNotTheQuery(): void
    {
        $exception = ApiException::fromResponse(
            new Response(404, '{"error":"Not found"}', [], 'Not Found'),
            new Request(Method::Get, 'https://api.wordpress.org/plugins/info/1.2/?request[slug]=secret'),
        );

        self::assertInstanceOf(NotFoundException::class, $exception);
        self::assertInstanceOf(WordpressOrgException::class, $exception);
        self::assertSame('GET https://api.wordpress.org/plugins/info/1.2/ failed with HTTP 404 Not Found: Not found', $exception->getMessage());
    }

    #[DataProvider('plainStatuses')]
    public function testUnmappedStatusesFallBackToTheBaseClass(int $status): void
    {
        $exception = ApiException::fromResponse(new Response($status), $this->request());

        self::assertSame(ApiException::class, $exception::class);
        self::assertSame($status, $exception->statusCode);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function plainStatuses(): iterable
    {
        yield '401' => [401];
        yield '409' => [409];
        yield '422' => [422];
    }

    public function testShortensHugeBodiesWithoutBreakingUtf8(): void
    {
        // One ASCII byte first, so that the cut after 500 bytes splits an "ä".
        $body = 'a' . str_repeat('ä', 400);
        $exception = ApiException::fromResponse(new Response(502, $body), $this->request());

        self::assertTrue(mb_check_encoding($exception->getMessage(), 'UTF-8'));
        self::assertStringEndsWith('…', $exception->getMessage());
        // The prefix (method, URL, status) plus at most 500 bytes of the body and the ellipsis.
        self::assertLessThan(650, \strlen($exception->getMessage()));
        self::assertSame($body, $exception->responseBody);
    }

    public function testDecodesTheBody(): void
    {
        $exception = ApiException::fromResponse(new Response(400, '{"error":"x"}'), $this->request());

        self::assertSame(['error' => 'x'], $exception->decodedBody());
        self::assertNull(ApiException::fromResponse(new Response(502), $this->request())->decodedBody());
    }

    public function testParsesRetryAfter(): void
    {
        $now = 1_800_000_000.0;

        self::assertSame(30, RateLimitException::parseRetryAfter('30', $now));
        self::assertSame(0, RateLimitException::parseRetryAfter('Wed, 21 Oct 2015 07:28:00 GMT', $now));
        self::assertSame(120, RateLimitException::parseRetryAfter(gmdate('D, d M Y H:i:s', 1_800_000_120) . ' GMT', $now));
        // The obsolete RFC 850 and asctime() formats of RFC 9110.
        self::assertSame(120, RateLimitException::parseRetryAfter(gmdate('l, d-M-y H:i:s', 1_800_000_120) . ' GMT', $now));
        self::assertSame(3600, RateLimitException::parseRetryAfter('Fri Jan 15 09:00:00 2027', (float) gmmktime(8, 0, 0, 1, 15, 2027)));
        self::assertSame(3600, RateLimitException::parseRetryAfter('Wed Jan  6 09:00:00 2027', (float) gmmktime(8, 0, 0, 1, 6, 2027)));

        $future = gmdate('D, d M Y H:i:s', time() + 120) . ' GMT';
        self::assertGreaterThanOrEqual(118, RateLimitException::parseRetryAfter($future));
    }

    #[DataProvider('invalidRetryAfterValues')]
    public function testRejectsRetryAfterValuesOfNeitherForm(string $value): void
    {
        self::assertNull(RateLimitException::parseRetryAfter($value, 1_800_000_000.0));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRetryAfterValues(): iterable
    {
        yield 'empty' => [''];
        yield 'word' => ['soon'];
        // strtotime() read these as times of day, relative dates or zone offsets.
        yield 'fraction' => ['1.5'];
        yield 'fraction read as a time of day' => ['10.0'];
        yield 'negative' => ['-1'];
        yield 'signed' => ['+5'];
        yield 'military zone' => ['x'];
        yield 'now' => ['now'];
        yield 'relative' => ['tomorrow'];
        yield 'weekday' => ['Sun'];
        yield 'unit' => ['30s'];
        yield 'weekday that does not match' => ['Mon, 21 Oct 2015 07:28:00 GMT'];
        yield 'day beyond the month' => ['Thu, 32 Oct 2015 07:28:00 GMT'];
        yield 'other zone' => ['Wed, 21 Oct 2015 07:28:00 CET'];
        yield 'lower case' => ['wed, 21 oct 2015 07:28:00 GMT'];
        yield 'ISO 8601' => ['2015-10-21T07:28:00Z'];
    }

    public function testIgnoresAnInvalidRetryAfter(): void
    {
        $exception = ApiException::fromResponse(new Response(429, '', ['retry-after' => '1.5']), $this->request(), 1_800_000_000.0);

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertNull($exception->retryAfter);
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('rateLimitHeaders')]
    public function testRateLimitExceptionCarriesTheAnnouncedWait(array $headers, ?int $retryAfter): void
    {
        $exception = ApiException::fromResponse(new Response(429, '', $headers), $this->request(), 1_800_000_000.0);

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertSame($retryAfter, $exception->retryAfter);
    }

    /**
     * @return iterable<string, array{array<string, string>, int|null}>
     */
    public static function rateLimitHeaders(): iterable
    {
        yield 'seconds' => [['retry-after' => '7'], 7];
        yield 'date' => [['retry-after' => 'Fri, 15 Jan 2027 08:00:12 GMT'], 12];
        yield 'date in the past' => [['retry-after' => 'Wed, 21 Oct 2015 07:28:00 GMT'], 0];
        yield 'nothing' => [[], null];
    }

    private function request(): Request
    {
        return new Request(Method::Get, 'https://api.wordpress.org/plugins/info/1.2/');
    }
}
