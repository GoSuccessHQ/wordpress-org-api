<?php

declare(strict_types=1);

/**
 * Prints status, latest version and closure of plugins, chunk by chunk.
 *
 * Usage: php examples/plugin-status.php [slug ...]
 */

use GoSuccess\WordpressOrg\Directory\LookupStatus;
use GoSuccess\WordpressOrg\Exception\RateLimitException;
use GoSuccess\WordpressOrg\Exception\WordpressOrgException;
use GoSuccess\WordpressOrg\WordpressOrg;

$client = require __DIR__ . '/bootstrap.php';
assert($client instanceof WordpressOrg);

$slugs = array_slice($argv ?? [], 1);

if ($slugs === []) {
    $slugs = ['akismet', 'display-widgets', 'no-such-plugin-zzzq'];
}

try {
    // Each chunk (up to batchSize slugs) is one request; handle it before the next starts.
    foreach ($client->plugins->lookupChunks(array_values($slugs)) as $results) {
        foreach ($results as $slug => $lookup) {
            $detail = match ($lookup->status) {
                LookupStatus::Found => 'version ' . ($lookup->version() ?? '?'),
                LookupStatus::Closed => sprintf(
                    'closed %s (%s)',
                    $lookup->closure?->date?->format('Y-m-d') ?? '?',
                    $lookup->closure->reason ?? '?',
                ),
                LookupStatus::NotFound => $lookup->error ?? 'not found',
                LookupStatus::Unknown => 'no usable answer',
            };

            printf("%-24s %-9s %s\n", $slug, $lookup->status->name, $detail);
        }
    }
} catch (RateLimitException $e) {
    fwrite(STDERR, sprintf("Rate limited; retry after %s seconds.\n", $e->retryAfter ?? 'an unknown number of'));

    exit(1);
} catch (WordpressOrgException $e) {
    // A failing chunk throws; the slugs of that chunk are not reported as unknown.
    fwrite(STDERR, 'Lookup failed: ' . $e->getMessage() . "\n");

    exit(1);
}
