<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * An external library bundled with WordPress.
 */
final readonly class Library
{
    public function __construct(
        public ?string $name,
        public ?string $url,
    ) {}

    /**
     * From `[name, url]`.
     *
     * @param array<array-key, mixed> $entry
     */
    public static function fromEntry(array $entry): self
    {
        $entry = array_values($entry);

        return new self(Read::string($entry[0] ?? null), Read::string($entry[1] ?? null));
    }
}
