<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Events;

/**
 * The answer of the events endpoint. When no location can be found, `error`
 * is set (measured: `no_location_available`) and `events` is empty.
 */
final readonly class EventList
{
    /**
     * @param list<Event> $events
     */
    public function __construct(
        public ?bool $sandboxed,
        public ?string $error,
        public ?EventLocation $location,
        public array $events,
    ) {}
}
