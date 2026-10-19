<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Internal;

/**
 * Builds the query strings of the wordpress.org API.
 *
 * @internal
 */
final class Query
{
    private function __construct() {}

    /**
     * RFC 3986 encoding; `null` entries are dropped, booleans become `1`/`0`,
     * and nested arrays produce keys such as `request[slug]`.
     *
     * @param array<string, mixed> $params
     */
    public static function encode(array $params): string
    {
        return http_build_query(self::normalize($params), '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * @param array<array-key, mixed> $params
     *
     * @return array<array-key, mixed>
     */
    private static function normalize(array $params): array
    {
        $result = [];

        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }

            $result[$key] = match (true) {
                \is_bool($value) => $value ? 1 : 0,
                \is_array($value) => self::normalize($value),
                default => $value,
            };
        }

        return $result;
    }
}
