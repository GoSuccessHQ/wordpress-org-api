<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Events;

use GoSuccess\WordpressOrg\Internal\Connection;
use GoSuccess\WordpressOrg\Internal\Json;
use GoSuccess\WordpressOrg\Internal\Query;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * Upcoming community events (`events/1.0`). `date` and `endDate` are in the
 * event's local time zone, `start` and `end` are UTC.
 */
final readonly class Events
{
    private const string ENDPOINT = 'events/1.0';

    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        private string $apiBaseUri,
    ) {}

    /**
     * An unresolvable location answers HTTP 200 with an `error` string, not an HTTP error.
     */
    public function upcoming(EventQuery $query = new EventQuery()): EventList
    {
        $uri = rtrim($this->apiBaseUri, '/') . '/' . self::ENDPOINT . '/?' . Query::encode($query->toParams());
        $data = Json::object($this->connection->get($uri), self::ENDPOINT);
        $location = $data['location'] ?? null;
        $events = [];

        foreach (Read::array($data['events'] ?? null) as $item) {
            if (\is_array($item)) {
                $events[] = Event::fromArray($item);
            }
        }

        return new EventList(
            Read::bool($data['sandboxed'] ?? null),
            Read::string($data['error'] ?? null),
            \is_array($location) ? EventLocation::fromArray($location) : null,
            $events,
        );
    }
}
