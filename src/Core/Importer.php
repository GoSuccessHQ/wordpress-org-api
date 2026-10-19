<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * An importer WordPress offers to install (`core/importers`).
 */
final readonly class Importer
{
    public function __construct(
        public ?string $id,
        public ?string $name,
        public ?string $description,
        public ?string $pluginSlug,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     * @param string                  $key  the entry's key, used when `importer-id` is missing
     */
    public static function fromArray(array $data, string $key): self
    {
        return new self(
            Read::string($data['importer-id'] ?? null) ?? $key,
            Read::string($data['name'] ?? null),
            Read::string($data['description'] ?? null),
            Read::string($data['plugin-slug'] ?? null),
        );
    }
}
