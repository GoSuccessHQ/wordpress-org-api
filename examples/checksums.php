<?php

declare(strict_types=1);

/**
 * Prints the number of files in the core checksums of a version, and a few of them.
 * The hashes are MD5: they detect accidental changes, not malicious ones.
 *
 * Usage: php examples/checksums.php [version] [locale]
 */

use GoSuccess\WordpressOrg\Exception\WordpressOrgException;
use GoSuccess\WordpressOrg\WordpressOrg;

$client = require __DIR__ . '/bootstrap.php';
assert($client instanceof WordpressOrg);

try {
    $version = $argv[1] ?? $client->core->versionCheck()->latest();

    if ($version === null) {
        fwrite(STDERR, "No version given and none offered.\n");

        exit(1);
    }

    $checksums = $client->core->checksums($version, $argv[2] ?? 'en_US');

    if ($checksums === null) {
        fwrite(STDERR, "wordpress.org has no checksums for {$version}.\n");

        exit(1);
    }

    printf("%d files in %s (%s)\n", count($checksums->files), $checksums->version, $checksums->locale);

    foreach (array_slice($checksums->files, 0, 5, true) as $path => $md5) {
        printf("%s  %s\n", $md5, $path);
    }
} catch (WordpressOrgException $e) {
    fwrite(STDERR, 'Request failed: ' . $e->getMessage() . "\n");

    exit(1);
}
