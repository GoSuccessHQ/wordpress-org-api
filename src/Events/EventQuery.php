<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Events;

use InvalidArgumentException;

/**
 * The parameters of an events lookup. Unset ones are not sent.
 *
 * `ip` sends an IP address to wordpress.org, which uses it to guess a location;
 * it is only sent when set here.
 */
final readonly class EventQuery
{
    /**
     * @throws InvalidArgumentException when only one of latitude and longitude is set
     */
    public function __construct(
        public ?string $location = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $ip = null,
        public ?string $country = null,
        public ?string $locale = null,
        public ?string $timezone = null,
        public ?int $number = null,
    ) {
        if (($latitude === null) !== ($longitude === null)) {
            throw new InvalidArgumentException('Latitude and longitude must be set together.');
        }
    }

    /**
     * @return array<string, float|int|string|null>
     */
    public function toParams(): array
    {
        return [
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'ip' => $this->ip,
            'country' => $this->country,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'number' => $this->number,
        ];
    }
}
