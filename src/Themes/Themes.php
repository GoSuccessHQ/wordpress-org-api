<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Themes;

use Generator;
use GoSuccess\WordpressOrg\Directory\DirectoryClient;
use GoSuccess\WordpressOrg\Directory\FieldSelection;
use GoSuccess\WordpressOrg\Internal\Connection;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * The theme directory (`themes/info/1.2`).
 */
final readonly class Themes
{
    /** @var DirectoryClient<ThemeLookup> */
    private DirectoryClient $directory;

    /**
     * @internal
     */
    public function __construct(
        Connection $connection,
        string $apiBaseUri,
        int $batchSize,
    ) {
        $this->directory = new DirectoryClient(
            $connection,
            $apiBaseUri,
            'themes',
            'theme_information',
            ThemeLookup::fromEntry(...),
            $batchSize,
        );
    }

    /**
     * Status of many themes in few requests (chunks of `batchSize`). The result has one
     * entry per distinct slug, in the caller's order and spelling; slugs the directory did
     * not answer, and slugs that cannot be sent, are {@see \GoSuccess\WordpressOrg\Directory\LookupStatus::Unknown}.
     * Throws if any chunk fails, never returning partial results. Slugs are sent lower-cased.
     * Keys of all-digit slugs are ints, because PHP converts such array keys.
     *
     * @param list<string>        $slugs
     * @param FieldSelection|null $fields defaults to {@see FieldSelection::minimalTheme()}
     *
     * @return array<string, ThemeLookup>
     */
    public function lookup(array $slugs, ?FieldSelection $fields = null): array
    {
        return $this->directory->lookup($slugs, $fields ?? FieldSelection::minimalTheme());
    }

    /**
     * Like {@see lookup()}, but yields the results chunk by chunk (chunk index => results),
     * so callers can store them as they arrive. Unsendable slugs are reported in the chunk
     * where they first appear. A failing chunk throws after the earlier ones were yielded.
     * Keys of all-digit slugs are ints, because PHP converts such array keys.
     *
     * @param list<string>        $slugs
     * @param FieldSelection|null $fields defaults to {@see FieldSelection::minimalTheme()}
     *
     * @return Generator<int, array<string, ThemeLookup>>
     */
    public function lookupChunks(array $slugs, ?FieldSelection $fields = null): Generator
    {
        return $this->directory->lookupChunks($slugs, $fields ?? FieldSelection::minimalTheme());
    }

    /**
     * One theme with wordpress.org's default fields when `$fields` is null.
     */
    public function information(string $slug, ?FieldSelection $fields = null): ThemeLookup
    {
        return $this->directory->information($slug, $fields ?? new FieldSelection());
    }

    /**
     * One page of a directory search, starting at `$query->page`. Entries that are not
     * objects are skipped; `pages` and `results` are 0 when the answer lacks them.
     */
    public function search(ThemeQuery $query): ThemePage
    {
        $result = $this->directory->query('query_themes', $query->toRequest($query->page));

        return new ThemePage(
            $result['page'] === 0 ? $query->page : $result['page'],
            $result['pages'],
            $result['results'],
            array_map(Theme::fromArray(...), $result['items']),
        );
    }

    /**
     * Every match from `$query->page` on, requesting the next page only when the iterator
     * asks for it. Stops after the last page, when `pages` is 0 or missing, and on an empty page.
     *
     * @return Generator<int, Theme>
     */
    public function searchAll(ThemeQuery $query): Generator
    {
        foreach ($this->directory->queryAll('query_themes', $query->toRequest($query->page), $query->page) as $item) {
            yield Theme::fromArray($item);
        }
    }

    /**
     * The most used tags, as many as the directory returns by default when `$number` is null.
     *
     * @return list<ThemeTag>
     */
    public function hotTags(?int $number = null): array
    {
        $data = $this->directory->call('hot_tags', ['number' => $number]);
        $tags = [];

        foreach ($data as $key => $tag) {
            if (\is_array($tag) && !array_is_list($tag)) {
                /** @var array<string, mixed> $tag */
                // The answer is keyed by slug; use the key when an entry lacks its own.
                $tags[] = ThemeTag::fromArray($tag + ['slug' => (string) $key]);
            }
        }

        return $tags;
    }

    /**
     * The filterable features by group, e.g. `Layout` => `grid-layout`, `one-column`, ...
     *
     * @return array<string, list<string>>
     */
    public function featureList(): array
    {
        $groups = [];

        foreach ($this->directory->call('feature_list', []) as $group => $features) {
            $groups[(string) $group] = Read::stringList($features);
        }

        return $groups;
    }
}
