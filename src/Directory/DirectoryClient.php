<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Directory;

use Closure;
use Generator;
use GoSuccess\WordpressOrg\Internal\Connection;
use GoSuccess\WordpressOrg\Internal\Json;
use GoSuccess\WordpressOrg\Internal\Query;

/**
 * Shared requests of the plugin and theme directory (`{kind}/info/1.2`).
 *
 * @template T of object
 *
 * @internal
 */
final class DirectoryClient
{
    private readonly string $endpoint;

    /**
     * @param 'plugins'|'themes'      $kind          first path segment of the endpoint
     * @param string                  $action        e.g. `plugin_information`
     * @param Closure(string, mixed): T $entryFactory builds the result for a caller slug from
     *                                               the raw entry, or `null` if there was none
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $apiBaseUri,
        string $kind,
        private readonly string $action,
        private readonly Closure $entryFactory,
        private readonly int $batchSize,
    ) {
        $this->endpoint = $kind . '/info/1.2';
    }

    /**
     * Yields one array per chunk of at most `batchSize` distinct slugs (compared
     * case-insensitively). Slugs that cannot be sent are answered without a request,
     * in the chunk where they first appear in the caller's order; a chunk without any
     * sendable slug causes no request. A failing chunk throws after the earlier ones
     * were yielded.
     *
     * @param list<string> $slugs
     *
     * @return Generator<int, array<string, T>>
     */
    public function lookupChunks(array $slugs, FieldSelection $fields): Generator
    {
        foreach ($this->plan($slugs) as $index => $chunk) {
            $entries = $chunk['send'] === [] ? [] : $this->fetch($chunk['send'], $fields);

            $results = [];

            foreach ($chunk['callers'] as $caller) {
                $entry = Entry::isSendable($caller) ? ($entries[strtolower($caller)] ?? null) : null;
                $results[$caller] = ($this->entryFactory)($caller, $entry);
            }

            yield $index => $results;
        }
    }

    /**
     * @param list<string> $slugs
     *
     * @return array<string, T> keyed and ordered like the caller's list
     */
    public function lookup(array $slugs, FieldSelection $fields): array
    {
        $all = [];

        foreach ($this->lookupChunks($slugs, $fields) as $results) {
            foreach ($results as $slug => $result) {
                $all[$slug] = $result;
            }
        }

        $ordered = [];

        foreach ($slugs as $slug) {
            $ordered[$slug] = $all[$slug];
        }

        return $ordered;
    }

    /**
     * @return T
     */
    public function information(string $slug, FieldSelection $fields): object
    {
        if (!Entry::isSendable($slug)) {
            return ($this->entryFactory)($slug, null);
        }

        $response = $this->connection->get($this->uri(['slug' => $slug, 'fields' => $fields->toRequest()]), [404]);

        return ($this->entryFactory)($slug, Json::object($response, $this->endpoint));
    }

    /**
     * Distributes the distinct caller slugs over chunks.
     *
     * @param list<string> $slugs
     *
     * @return list<array{callers: list<string>, send: list<string>}>
     */
    private function plan(array $slugs): array
    {
        /** @var list<list<string>> $callers */
        $callers = [[]];
        /** @var list<list<string>> $send */
        $send = [[]];
        $chunkOfLower = [];
        $seen = [];
        $current = 0;

        foreach ($slugs as $slug) {
            if (isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;
            $lower = strtolower($slug);

            if (!Entry::isSendable($slug)) {
                $callers[$current][] = $slug;

                continue;
            }

            if (isset($chunkOfLower[$lower])) {
                $callers[$chunkOfLower[$lower]][] = $slug;

                continue;
            }

            if (\count($send[$current]) >= $this->batchSize) {
                $current++;
                $callers[$current] = [];
                $send[$current] = [];
            }

            $send[$current][] = $slug;
            $callers[$current][] = $slug;
            $chunkOfLower[$lower] = $current;
        }

        $chunks = [];

        foreach ($callers as $index => $inChunk) {
            if ($inChunk !== []) {
                $chunks[] = ['callers' => $inChunk, 'send' => $send[$index]];
            }
        }

        return $chunks;
    }

    /**
     * @param list<string> $send
     *
     * @return array<string, mixed> raw entries keyed by lower-cased slug
     */
    private function fetch(array $send, FieldSelection $fields): array
    {
        $response = $this->connection->get($this->uri(['slugs' => implode(',', $send), 'fields' => $fields->toRequest()]));

        $entries = [];

        foreach (Json::object($response, $this->endpoint) as $key => $entry) {
            $entries[strtolower((string) $key)] ??= $entry;
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return non-empty-string
     */
    private function uri(array $request): string
    {
        return \sprintf(
            '%s/%s/?%s',
            rtrim($this->apiBaseUri, '/'),
            $this->endpoint,
            Query::encode(['action' => $this->action, 'request' => $request]),
        );
    }
}
