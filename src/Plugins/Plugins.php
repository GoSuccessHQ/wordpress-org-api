<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Plugins;

use Generator;
use GoSuccess\WordpressOrg\Directory\DirectoryClient;
use GoSuccess\WordpressOrg\Directory\Entry;
use GoSuccess\WordpressOrg\Directory\FieldSelection;
use GoSuccess\WordpressOrg\Internal\Connection;
use GoSuccess\WordpressOrg\Internal\Json;

/**
 * The plugin directory (`plugins/info/1.2`).
 */
final readonly class Plugins
{
    /** @var DirectoryClient<PluginLookup> */
    private DirectoryClient $directory;

    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        string $apiBaseUri,
        private string $downloadsBaseUri,
        int $batchSize,
    ) {
        $this->directory = new DirectoryClient(
            $connection,
            $apiBaseUri,
            'plugins',
            'plugin_information',
            PluginLookup::fromEntry(...),
            $batchSize,
        );
    }

    /**
     * Status of many plugins in few requests (chunks of `batchSize`). The result has one
     * entry per distinct slug, in the caller's order and spelling; slugs the directory did
     * not answer, and slugs that cannot be sent, are {@see \GoSuccess\WordpressOrg\Directory\LookupStatus::Unknown}.
     * Throws if any chunk fails, never returning partial results. Slugs are sent lower-cased.
     * Keys of all-digit slugs are ints, because PHP converts such array keys.
     *
     * @param list<string>        $slugs
     * @param FieldSelection|null $fields defaults to {@see FieldSelection::minimalPlugin()}
     *
     * @return array<string, PluginLookup>
     */
    public function lookup(array $slugs, ?FieldSelection $fields = null): array
    {
        return $this->directory->lookup($slugs, $fields ?? FieldSelection::minimalPlugin());
    }

    /**
     * Like {@see lookup()}, but yields the results chunk by chunk (chunk index => results),
     * so callers can store them as they arrive. Unsendable slugs are reported in the chunk
     * where they first appear. A failing chunk throws after the earlier ones were yielded.
     * Keys of all-digit slugs are ints, because PHP converts such array keys.
     *
     * @param list<string>        $slugs
     * @param FieldSelection|null $fields defaults to {@see FieldSelection::minimalPlugin()}
     *
     * @return Generator<int, array<string, PluginLookup>>
     */
    public function lookupChunks(array $slugs, ?FieldSelection $fields = null): Generator
    {
        return $this->directory->lookupChunks($slugs, $fields ?? FieldSelection::minimalPlugin());
    }

    /**
     * One plugin with wordpress.org's default fields when `$fields` is null.
     */
    public function information(string $slug, ?FieldSelection $fields = null): PluginLookup
    {
        return $this->directory->information($slug, $fields ?? new FieldSelection());
    }

    /**
     * One page of a directory search, starting at `$query->page`. Entries that are not
     * objects are skipped; `pages` and `results` are 0 when the answer lacks them.
     */
    public function search(PluginQuery $query): PluginPage
    {
        $result = $this->directory->query('query_plugins', $query->toRequest($query->page));

        return new PluginPage(
            $result['page'] === 0 ? $query->page : $result['page'],
            $result['pages'],
            $result['results'],
            array_map(Plugin::fromArray(...), $result['items']),
        );
    }

    /**
     * Every match from `$query->page` on, requesting the next page only when the iterator
     * asks for it. Stops after the last page, when `pages` is 0 or missing, and on an empty page.
     *
     * @return Generator<int, Plugin>
     */
    public function searchAll(PluginQuery $query): Generator
    {
        foreach ($this->directory->queryAll('query_plugins', $query->toRequest($query->page), $query->page) as $item) {
            yield Plugin::fromArray($item);
        }
    }

    /**
     * File hashes of a released version, or `null` if there is none. A slug or version that
     * cannot be put in the path safely also gives `null`, without a request. The slug is
     * sent lower-cased, the version as given.
     */
    public function checksums(string $slug, string $version): ?PluginChecksums
    {
        if (!Entry::isSendable($slug) || !Entry::isSendable($version)) {
            return null;
        }

        $response = $this->connection->get(
            \sprintf('%s/plugin-checksums/%s/%s.json', rtrim($this->downloadsBaseUri, '/'), strtolower($slug), $version),
            [404],
        );

        if ($response->statusCode === 404) {
            return null;
        }

        return PluginChecksums::fromArray(Json::object($response, 'plugin-checksums'));
    }
}
