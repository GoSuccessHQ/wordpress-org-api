<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

/**
 * The MD5 hashes of the files of one WordPress release. MD5 detects accidental
 * changes only, not malicious ones.
 */
final readonly class CoreChecksums
{
    /**
     * @param array<string, string> $files path => MD5
     */
    public function __construct(
        public string $version,
        public string $locale,
        public array $files,
    ) {}
}
