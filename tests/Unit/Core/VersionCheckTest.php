<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Core;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Core\Core;
use GoSuccess\WordpressOrg\Core\Offer;
use GoSuccess\WordpressOrg\Core\OfferPackages;
use GoSuccess\WordpressOrg\Core\VersionCheck;
use GoSuccess\WordpressOrg\Core\VersionCheckQuery;
use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Core::class)]
#[CoversClass(VersionCheck::class)]
#[CoversClass(VersionCheckQuery::class)]
#[CoversClass(Offer::class)]
#[CoversClass(OfferPackages::class)]
final class VersionCheckTest extends TestCase
{
    private static function fixture(): VersionCheck
    {
        $body = (string) file_get_contents(__DIR__ . '/../../Fixtures/core/version-check.json');

        return new WordpressOrg(new ClientOptions(), new MockHttpClient(new Response(200, $body)))->core->versionCheck();
    }

    private static function check(string $body): VersionCheck
    {
        return new WordpressOrg(new ClientOptions(), new MockHttpClient(new Response(200, $body)))->core->versionCheck();
    }

    public function testLatestIsFirstOffersCurrent(): void
    {
        self::assertSame('7.1.3', self::fixture()->latest());
    }

    public function testReadsAllOffers(): void
    {
        $check = self::fixture();

        self::assertCount(26, $check->offers);
        self::assertSame([], $check->translations);

        $first = $check->offers[0];
        self::assertSame('upgrade', $first->response);
        self::assertSame('https://downloads.wordpress.org/release/wordpress-7.1.3.zip', $first->download);
        self::assertSame('en_US', $first->locale);
        self::assertSame('7.1.3', $first->current);
        self::assertSame('7.1.3', $first->version);
        self::assertSame('7.4', $first->phpVersion);
        self::assertSame('5.5.5', $first->mysqlVersion);
        self::assertSame('6.7', $first->newBundled);
        self::assertNull($first->partialVersion);
        self::assertSame('https://downloads.wordpress.org/release/wordpress-7.1.3-no-content.zip', $first->packages->noContent);
        self::assertSame('https://downloads.wordpress.org/release/wordpress-7.1.3-new-bundled.zip', $first->packages->newBundled);
        self::assertSame($first->download, $first->packages->full);
        self::assertSame('autoupdate', $check->offers[1]->response);
        self::assertTrue($check->offers[1]->raw['new_files']);
    }

    public function testPartialFalseIsNull(): void
    {
        $packages = self::fixture()->offers[0]->packages;

        self::assertNull($packages->partial);
        self::assertNull($packages->rollback);
    }

    public function testLatestIsNullWithoutOffers(): void
    {
        self::assertNull(self::check('{"offers":[]}')->latest());
        self::assertNull(self::check('{}')->latest());
        self::assertSame([], self::check('[]')->offers);
    }

    public function testOffersNotAListThrows(): void
    {
        $this->expectException(SerializationException::class);

        self::check('{"offers":"x"}');
    }

    public function testNonObjectBodyThrows(): void
    {
        $this->expectException(SerializationException::class);

        self::check('"x"');
    }

    public function testOffersThatAreNotObjectsAreSkipped(): void
    {
        $check = self::check('{"offers":["x",7,{"current":"6.0","packages":false},[]]}');

        self::assertCount(2, $check->offers);
        self::assertSame('6.0', $check->latest());
        self::assertNull($check->offers[0]->packages->full);
    }

    public function testQuerySendsOnlyTheFourParameters(): void
    {
        $http = new MockHttpClient(new Response(200, '{"offers":[]}'), new Response(200, '{"offers":[]}'));
        $core = new WordpressOrg(new ClientOptions(), $http)->core;

        $core->versionCheck();
        $core->versionCheck(new VersionCheckQuery(version: '6.8', php: '8.3.1', mysql: '8.0', locale: 'de_DE'));

        self::assertSame('https://api.wordpress.org/core/version-check/1.7/', $http->requests[0]->uri);
        self::assertSame(
            'https://api.wordpress.org/core/version-check/1.7/?version=6.8&php=8.3.1&mysql=8.0&locale=de_DE',
            $http->requests[1]->uri,
        );
    }
}
