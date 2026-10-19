<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

use GoSuccess\WordpressOrg\Internal\Connection;
use GoSuccess\WordpressOrg\Internal\Json;
use GoSuccess\WordpressOrg\Internal\Query;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * WordPress core (`core/…`): releases, their stability and file hashes.
 */
final readonly class Core
{
    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        private string $apiBaseUri,
    ) {}

    /**
     * The releases wordpress.org offers (`core/version-check/1.7`). Without a query it
     * answers for the current WordPress and the default locale.
     */
    public function versionCheck(?VersionCheckQuery $query = null): VersionCheck
    {
        $endpoint = 'core/version-check/1.7';
        $response = $this->connection->get($this->uri($endpoint, ($query ?? new VersionCheckQuery())->toParams()));

        return VersionCheck::fromArray(Json::object($response, $endpoint), $endpoint);
    }

    /**
     * The rating of every known release, version => state (`core/stable-check/1.0`).
     * The states are kept as served; {@see stability()} maps them to {@see Stability}.
     *
     * @return array<string, string>
     */
    public function stableCheck(): array
    {
        $endpoint = 'core/stable-check/1.0';

        return Read::stringMap(Json::object($this->connection->get($this->uri($endpoint, [])), $endpoint));
    }

    /**
     * The rating of one release, or `null` if wordpress.org does not know the version or
     * uses a state this package does not know.
     */
    public function stability(string $version): ?Stability
    {
        $state = $this->stableCheck()[$version] ?? null;

        return $state === null ? null : Stability::tryFrom($state);
    }

    /**
     * The file hashes of a release (`core/checksums/1.0`), or `null` if wordpress.org
     * has none for the version and locale.
     */
    public function checksums(string $version, string $locale = 'en_US'): ?CoreChecksums
    {
        $endpoint = 'core/checksums/1.0';
        $data = Json::object(
            $this->connection->get($this->uri($endpoint, ['version' => $version, 'locale' => $locale])),
            $endpoint,
        );

        if (!\is_array($data['checksums'] ?? null)) {
            return null;
        }

        return new CoreChecksums($version, $locale, Read::stringMap($data['checksums']));
    }

    /**
     * @param array<string, string|null> $params
     *
     * @return non-empty-string
     */
    private function uri(string $endpoint, array $params): string
    {
        $query = Query::encode($params);

        return rtrim($this->apiBaseUri, '/') . '/' . $endpoint . '/' . ($query === '' ? '' : '?' . $query);
    }
}
