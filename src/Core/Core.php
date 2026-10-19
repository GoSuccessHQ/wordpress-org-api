<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

use GoSuccess\WordpressOrg\Internal\Connection;
use GoSuccess\WordpressOrg\Internal\Json;
use GoSuccess\WordpressOrg\Internal\Query;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * WordPress core (`core/…`): releases, their stability and file hashes, credits,
 * importers, handbook links and the Browse Happy and Serve Happy checks.
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
     * The credits of a release (`core/credits/1.1`), optionally in a locale.
     */
    public function credits(string $version, ?string $locale = null): Credits
    {
        $endpoint = 'core/credits/1.1';
        $response = $this->connection->get($this->uri($endpoint, ['version' => $version, 'locale' => $locale]));

        return Credits::fromArray(Json::object($response, $endpoint));
    }

    /**
     * The importers WordPress offers (`core/importers/1.1`). The endpoint ignores a
     * `locale` parameter (measured), so there is none.
     *
     * @return list<Importer>
     */
    public function importers(): array
    {
        $endpoint = 'core/importers/1.1';
        $data = Json::object($this->connection->get($this->uri($endpoint, [])), $endpoint);
        $importers = [];

        foreach (Read::array($data['importers'] ?? null) as $key => $importer) {
            if (\is_array($importer)) {
                $importers[] = Importer::fromArray($importer, (string) $key);
            }
        }

        return $importers;
    }

    /**
     * The handbook page of a function (`core/handbook/1.0`), or `null` if the answer is
     * not an `https://` URL.
     */
    public function handbookUrl(string $function, string $version): ?string
    {
        $response = $this->connection->get($this->uri('core/handbook/1.0', [
            'function' => $function,
            'version' => $version,
            'redirect' => 'false',
        ]));
        $url = trim($response->body);

        if (!str_starts_with($url, 'https://') || preg_match('/[\s"<>]/', $url) === 1) {
            return null;
        }

        // ext-filter is not a required extension, so `filter_var()` is not used.
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        return ($parts['scheme'] ?? '') === 'https' && preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/', $host) === 1 ? $url : null;
    }

    /**
     * The browser wordpress.org recognizes in a User-Agent (`core/browse-happy/1.1`).
     */
    public function browseHappy(string $userAgent): Browser
    {
        $endpoint = 'core/browse-happy/1.1';
        $response = $this->connection->postForm($this->uri($endpoint, []), ['useragent' => $userAgent]);

        return Browser::fromArray(Json::object($response, $endpoint));
    }

    /**
     * How WordPress rates a PHP version (`core/serve-happy/1.0`).
     *
     * @throws \GoSuccess\WordpressOrg\Exception\BadRequestException if the version is missing
     */
    public function serveHappy(string $phpVersion): PhpSupport
    {
        $endpoint = 'core/serve-happy/1.0';
        $response = $this->connection->get($this->uri($endpoint, ['php_version' => $phpVersion]));

        return PhpSupport::fromArray(Json::object($response, $endpoint));
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
