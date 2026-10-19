<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Stats;

use GoSuccess\WordpressOrg\Directory\Entry;
use GoSuccess\WordpressOrg\Internal\Connection;
use GoSuccess\WordpressOrg\Internal\Json;
use GoSuccess\WordpressOrg\Internal\Query;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * Usage statistics (`stats/…/1.0`). Shares are percentages. Keys are kept as sent
 * (`8.3`, `MariaDB 10.11`); PHP turns a key made only of digits, such as `10`, into
 * an int, which is a limitation of PHP arrays.
 */
final readonly class Stats
{
    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        private string $apiBaseUri,
    ) {}

    /**
     * Share of each WordPress version.
     *
     * @return array<string, float>
     */
    public function wordpress(): array
    {
        return $this->shares('stats/wordpress/1.0', '/');
    }

    /**
     * @return array<string, float>
     */
    public function php(): array
    {
        return $this->shares('stats/php/1.0', '/');
    }

    /**
     * @return array<string, float>
     */
    public function mysql(): array
    {
        return $this->shares('stats/mysql/1.0', '/');
    }

    /**
     * @return array<string, float>
     */
    public function locales(): array
    {
        return $this->shares('stats/locale/1.0', '/');
    }

    /**
     * Share of each version of a plugin, including `other`. An unknown slug answers
     * with an empty list (measured), as does a slug that cannot be sent.
     *
     * @return array<string, float>
     */
    public function pluginVersions(string $slug): array
    {
        // The slug is part of the path, so it must be checked.
        return Entry::isSendable($slug) ? $this->shares('stats/plugin/1.0', '/' . strtolower($slug)) : [];
    }

    /**
     * Downloads per day, date => count, in the order served (oldest first on
     * 2026-10-19). `$days` is sent as `limit`. An unknown slug is not detectable: wordpress.org answers a series of its own
     * (measured), so the result for it is not empty.
     *
     * @return array<string, int>
     */
    public function pluginDownloads(string $slug, ?int $days = null): array
    {
        if (!Entry::isSendable($slug)) {
            return [];
        }

        $endpoint = 'stats/plugin/1.0/downloads.php';
        $query = Query::encode(['slug' => strtolower($slug), 'limit' => $days]);
        $data = Json::object($this->connection->get($this->base() . $endpoint . '?' . $query), $endpoint);
        $downloads = [];

        foreach ($data as $date => $count) {
            $count = Read::int($count);

            if ($count !== null) {
                $downloads[(string) $date] = $count;
            }
        }

        return $downloads;
    }

    /**
     * @return array<string, float>
     */
    private function shares(string $endpoint, string $suffix): array
    {
        $data = Json::object($this->connection->get($this->base() . $endpoint . $suffix), $endpoint);
        $shares = [];

        foreach ($data as $key => $share) {
            $share = Read::float($share);

            if ($share !== null) {
                $shares[(string) $key] = $share;
            }
        }

        return $shares;
    }

    /**
     * @return non-empty-string
     */
    private function base(): string
    {
        return rtrim($this->apiBaseUri, '/') . '/';
    }
}
