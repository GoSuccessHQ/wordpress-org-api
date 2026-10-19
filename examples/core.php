<?php

declare(strict_types=1);

/**
 * Prints the latest WordPress version and how wordpress.org rates a given one.
 *
 * Usage: php examples/core.php [version]
 */

use GoSuccess\WordpressOrg\Exception\WordpressOrgException;
use GoSuccess\WordpressOrg\WordpressOrg;

$client = require __DIR__ . '/bootstrap.php';
assert($client instanceof WordpressOrg);

try {
    $latest = $client->core->versionCheck()->latest();
    printf("Latest version: %s\n", $latest ?? 'unknown');

    $version = $argv[1] ?? $latest;

    if ($version !== null) {
        $stability = $client->core->stability($version);
        printf("Stability of %s: %s\n", $version, $stability === null ? 'unknown' : $stability->value);
    }
} catch (WordpressOrgException $e) {
    fwrite(STDERR, 'Request failed: ' . $e->getMessage() . "\n");

    exit(1);
}
