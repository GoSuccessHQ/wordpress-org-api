<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Events;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * A place: the resolved search location of a list, or the venue of one event.
 */
final readonly class EventLocation
{
    public function __construct(
        public ?string $description,
        public ?string $country,
        public ?float $latitude,
        public ?float $longitude,
    ) {}

    /**
     * The list names the place `description`, an event `location`. Coordinates
     * arrive as strings in the list and as numbers in an event.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Read::string($data['description'] ?? $data['location'] ?? null),
            Read::string($data['country'] ?? null),
            Read::float($data['latitude'] ?? null),
            Read::float($data['longitude'] ?? null),
        );
    }
}
