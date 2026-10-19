<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Translations;

use GoSuccess\WordpressOrg\Directory\Entry;
use GoSuccess\WordpressOrg\Internal\Connection;
use GoSuccess\WordpressOrg\Internal\Json;
use GoSuccess\WordpressOrg\Internal\Query;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * Translation sets of core, plugins and themes (`translations/…/1.0`).
 * wordpress.org falls back to an older set by itself when the exact version has none.
 */
final readonly class Translations
{
    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        private string $apiBaseUri,
    ) {}

    /**
     * @return list<Translation>
     */
    public function core(string $version): array
    {
        return $this->fetch('translations/core/1.0', ['version' => $version]);
    }

    /**
     * Unknown slugs answer with an empty list (measured), as does a slug that cannot be sent.
     *
     * @return list<Translation>
     */
    public function plugin(string $slug, string $version): array
    {
        return Entry::isSendable($slug)
            ? $this->fetch('translations/plugins/1.0', ['slug' => strtolower($slug), 'version' => $version])
            : [];
    }

    /**
     * @return list<Translation>
     */
    public function theme(string $slug, string $version): array
    {
        return Entry::isSendable($slug)
            ? $this->fetch('translations/themes/1.0', ['slug' => strtolower($slug), 'version' => $version])
            : [];
    }

    /**
     * @param array<string, string> $params
     *
     * @return list<Translation>
     */
    private function fetch(string $endpoint, array $params): array
    {
        $uri = rtrim($this->apiBaseUri, '/') . '/' . $endpoint . '/?' . Query::encode($params);
        $data = Json::object($this->connection->get($uri), $endpoint);
        $translations = [];

        foreach (Read::array($data['translations'] ?? null) as $item) {
            if (\is_array($item)) {
                $translations[] = Translation::fromArray($item);
            }
        }

        return $translations;
    }
}
