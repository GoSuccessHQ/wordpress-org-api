<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * The download links of one offer; a link the API reports as `false` is `null`.
 */
final readonly class OfferPackages
{
    public function __construct(
        public ?string $full,
        public ?string $noContent,
        public ?string $newBundled,
        public ?string $partial,
        public ?string $rollback,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Read::string($data['full'] ?? null),
            Read::string($data['no_content'] ?? null),
            Read::string($data['new_bundled'] ?? null),
            Read::string($data['partial'] ?? null),
            Read::string($data['rollback'] ?? null),
        );
    }
}
