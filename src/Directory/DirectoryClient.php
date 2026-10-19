<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Directory;

use Closure;
use Generator;
use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Internal\Connection;
use GoSuccess\WordpressOrg\Internal\Json;
use GoSuccess\WordpressOrg\Internal\Query;
use GoSuccess\WordpressOrg\Internal\Read;

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

    private readonly string $listKey;

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
        $this->listKey = $kind;
    }

    /**
     * Yields one array per chunk of at most `batchSize` distinct slugs. Slugs are sent
     * lower-cased and compared case-insensitively; results keep the caller's spelling.
     * Slugs that cannot be sent are answered without a request, in the chunk where they
     * first appear in the caller's order; a chunk without any sendable slug causes no
     * request. A failing chunk throws after the earlier ones were yielded, and so does
     * an answer that contains none of the slugs sent for its chunk.
     *
     * @param list<string> $slugs
     *
     * @return Generator<int, array<string, T>>
     */
    public function lookupChunks(array $slugs, FieldSelection $fields): Generator
    {
        foreach ($this->plan($slugs) as $index => $chunk) {
            $entries = $chunk['send'] === [] ? [] : $this->fetch($chunk['send'], $fields);

            $folded = [];

            foreach ($entries as $key => $entry) {
                $folded[strtolower((string) $key)] ??= $entry;
            }

            $results = [];

            foreach ($chunk['callers'] as $caller) {
                $lower = strtolower($caller);
                // The exact key wins; a key that differs only in case is the fallback.
                $entry = Entry::isSendable($caller) ? ($entries[$lower] ?? $folded[$lower] ?? null) : null;
                $results[$caller] = ($this->entryFactory)($caller, $entry);
            }

            yield $index => $results;
        }
    }

    /**
     * One page of a search. `info` problems give 0; entries that are not objects are skipped.
     *
     * @param array<string, mixed> $request `request[...]` arguments, `page` included
     *
     * @return array{page: int, pages: int, results: int, items: list<array<string, mixed>>}
     */
    public function query(string $action, array $request): array
    {
        $response = $this->connection->get(\sprintf(
            '%s/%s/?%s',
            rtrim($this->apiBaseUri, '/'),
            $this->endpoint,
            Query::encode(['action' => $action, 'request' => $request]),
        ));
        $data = Json::object($response, $this->endpoint);

        if (\is_string($data['error'] ?? null) && !isset($data['info']) && !isset($data[$this->listKey])) {
            throw new SerializationException(\sprintf('%s: search answered an error (HTTP %d)', $this->endpoint, $response->statusCode));
        }

        $info = Read::array($data['info'] ?? null);
        $items = [];

        foreach (Read::array($data[$this->listKey] ?? null) as $item) {
            if (\is_array($item) && !array_is_list($item)) {
                /** @var array<string, mixed> $item */
                $items[] = $item;
            }
        }

        return [
            'page' => Read::int($info['page'] ?? null) ?? 0,
            'pages' => Read::int($info['pages'] ?? null) ?? 0,
            'results' => Read::int($info['results'] ?? null) ?? 0,
            'items' => $items,
        ];
    }

    /**
     * Any other action of the endpoint (e.g. `hot_tags`) as a decoded JSON object.
     *
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    public function call(string $action, array $request): array
    {
        $response = $this->connection->get(\sprintf(
            '%s/%s/?%s',
            rtrim($this->apiBaseUri, '/'),
            $this->endpoint,
            Query::encode(['action' => $action, 'request' => $request]),
        ));

        return Json::object($response, $this->endpoint);
    }

    /**
     * Every item from `$startPage` on, fetched page by page as the caller iterates. Stops
     * after the last page, when `pages` is 0 or unreadable, and on an empty page; it never
     * requests a page beyond the last.
     *
     * @param array<string, mixed> $request `request[...]` arguments; `page` is set here
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function queryAll(string $action, array $request, int $startPage): Generator
    {
        for ($page = $startPage; ; $page++) {
            $result = $this->query($action, [...$request, 'page' => $page]);

            yield from $result['items'];

            if ($result['items'] === [] || $page >= $result['pages']) {
                return;
            }
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

        $response = $this->connection->get($this->uri(['slug' => strtolower($slug), 'fields' => $fields->toRequest()]), [404]);

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

            $send[$current][] = $lower;
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
     * @return array<string, mixed> raw entries as keyed by the server
     */
    private function fetch(array $send, FieldSelection $fields): array
    {
        $response = $this->connection->get($this->uri(['slugs' => implode(',', $send), 'fields' => $fields->toRequest()]));

        $data = Json::object($response, $this->endpoint);
        $present = false;

        foreach ($data as $key => $_) {
            if (\in_array(strtolower((string) $key), $send, true)) {
                $present = true;

                break;
            }
        }

        if (!$present || \is_string($data['error'] ?? null)) {
            throw new SerializationException(\sprintf('%s: answer contains none of the requested slugs (HTTP %d)', $this->endpoint, $response->statusCode));
        }

        return $data;
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
