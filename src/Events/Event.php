<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Events;

use DateTimeImmutable;
use DateTimeZone;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * One upcoming community event.
 */
final readonly class Event
{
    /**
     * @param string|null            $date    start in the event's local time, as sent
     * @param string|null            $endDate end in the event's local time, as sent
     * @param DateTimeImmutable|null $start   from `start_unix_timestamp`, UTC
     * @param DateTimeImmutable|null $end     from `end_unix_timestamp`, UTC
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public ?string $type,
        public ?string $title,
        public ?string $url,
        public ?string $meetup,
        public ?string $meetupUrl,
        public ?string $date,
        public ?string $endDate,
        public ?DateTimeImmutable $start,
        public ?DateTimeImmutable $end,
        public ?EventLocation $location,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $location = $data['location'] ?? null;

        return new self(
            Read::string($data['type'] ?? null),
            Read::string($data['title'] ?? null),
            Read::string($data['url'] ?? null),
            Read::string($data['meetup'] ?? null),
            Read::string($data['meetup_url'] ?? null),
            Read::string($data['date'] ?? null),
            Read::string($data['end_date'] ?? null),
            self::timestamp($data['start_unix_timestamp'] ?? null),
            self::timestamp($data['end_unix_timestamp'] ?? null),
            \is_array($location) ? EventLocation::fromArray($location) : null,
            $data,
        );
    }

    private static function timestamp(mixed $value): ?DateTimeImmutable
    {
        $seconds = Read::int($value);

        return $seconds === null ? null : new DateTimeImmutable('@' . $seconds)->setTimezone(new DateTimeZone('UTC'));
    }
}
