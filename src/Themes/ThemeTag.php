<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Themes;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * A popular theme tag (`action=hot_tags`).
 */
final readonly class ThemeTag
{
    public function __construct(
        public ?string $slug,
        public ?string $name,
        public ?int $count,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Read::string($data['slug'] ?? null),
            Read::string($data['name'] ?? null),
            Read::int($data['count'] ?? null),
        );
    }
}
