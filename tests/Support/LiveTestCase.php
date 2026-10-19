<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Support;

use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\TestCase;

/**
 * Base of the live tests: they read the real wordpress.org API (one request per
 * endpoint, read-only) and are skipped unless WORDPRESS_ORG_LIVE=1.
 */
abstract class LiveTestCase extends TestCase
{
    protected WordpressOrg $client;

    protected function setUp(): void
    {
        if (getenv('WORDPRESS_ORG_LIVE') !== '1') {
            self::markTestSkipped('Set WORDPRESS_ORG_LIVE=1 to query the live wordpress.org API.');
        }

        $this->client = new WordpressOrg();
    }
}
