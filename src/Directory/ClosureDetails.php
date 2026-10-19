<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Directory;

use DateTimeImmutable;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * Why and when a plugin or theme was closed.
 */
final readonly class ClosureDetails
{
    public function __construct(
        public ?DateTimeImmutable $date,
        /** Raw reason code, e.g. `security-issue`. */
        public ?string $reason,
        public ?string $reasonText,
        public ?string $description,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Read::date($data['closed_date'] ?? null, Read::DATE),
            Read::string($data['reason'] ?? null),
            Read::string($data['reason_text'] ?? null),
            Read::string($data['description'] ?? null),
        );
    }
}
