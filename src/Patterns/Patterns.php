<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Patterns;

use GoSuccess\WordpressOrg\Internal\Connection;
use GoSuccess\WordpressOrg\Internal\Json;
use GoSuccess\WordpressOrg\Internal\Query;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * The block pattern directory (`patterns/1.0`), a WordPress REST list.
 */
final readonly class Patterns
{
    private const string ENDPOINT = 'patterns/1.0';

    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        private string $apiBaseUri,
    ) {}

    /**
     * `page` and `per_page` select the page (measured: page 2 differs from page 1 of the same search).
     */
    public function search(PatternQuery $query = new PatternQuery()): PatternPage
    {
        $uri = rtrim($this->apiBaseUri, '/') . '/' . self::ENDPOINT . '/?' . Query::encode($query->toParams());
        $response = $this->connection->get($uri);
        $patterns = [];

        foreach (Json::list($response, self::ENDPOINT) as $item) {
            if (\is_array($item)) {
                $patterns[] = Pattern::fromArray($item);
            }
        }

        return new PatternPage(
            $patterns,
            Read::int($response->header('x-wp-total')),
            Read::int($response->header('x-wp-totalpages')),
        );
    }
}
