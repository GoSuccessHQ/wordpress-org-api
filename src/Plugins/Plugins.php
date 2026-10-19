<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Plugins;

use Generator;
use GoSuccess\WordpressOrg\Directory\DirectoryClient;
use GoSuccess\WordpressOrg\Directory\FieldSelection;
use GoSuccess\WordpressOrg\Internal\Connection;

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
    public function __construct(Connection $connection, string $apiBaseUri, int $batchSize)
    {
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
     * Throws if any chunk fails, never returning partial results.
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
}
