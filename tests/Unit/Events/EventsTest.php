<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Events;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Events\Event;
use GoSuccess\WordpressOrg\Events\EventList;
use GoSuccess\WordpressOrg\Events\EventLocation;
use GoSuccess\WordpressOrg\Events\EventQuery;
use GoSuccess\WordpressOrg\Events\Events;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\WordpressOrg;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Events::class)]
#[CoversClass(Event::class)]
#[CoversClass(EventList::class)]
#[CoversClass(EventLocation::class)]
#[CoversClass(EventQuery::class)]
final class EventsTest extends TestCase
{
    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Fixtures/events/' . $name . '.json');
    }

    public function testEventsReadLocalDatesAndTimestamps(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('dresden')));
        $list = new WordpressOrg(new ClientOptions(), $http)->events->upcoming(new EventQuery('Dresden', number: 3));

        self::assertSame('https://api.wordpress.org/events/1.0/?location=Dresden&number=3', $http->requests[0]->uri);
        self::assertFalse($list->sandboxed);
        self::assertNull($list->error);
        self::assertSame('Dresden', $list->location?->description);
        self::assertSame('DE', $list->location->country);
        self::assertSame(51.05089, $list->location->latitude);
        self::assertSame(13.73832, $list->location->longitude);
        self::assertCount(3, $list->events);

        $event = $list->events[0];
        self::assertSame('meetup', $event->type);
        self::assertSame('WordPress Meetup Leipzig - Werkstatt', $event->title);
        self::assertSame('WordPress Meetup Leipzig', $event->meetup);
        self::assertSame('https://www.meetup.com/Leipzig-WordPress-Meetup/', $event->meetupUrl);
        self::assertSame('2026-11-03 19:00:00', $event->date);
        self::assertSame('2026-11-03 21:00:00', $event->endDate);
        self::assertSame('2026-11-03 18:00:00', $event->start?->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $event->start->getTimezone()->getName());
        self::assertSame(1793736000, $event->end?->getTimestamp());
        self::assertSame('Leipzig, Germany', $event->location?->description);
        self::assertSame('de', $event->location->country);
        self::assertSame(51.332104, $event->location->latitude);
        self::assertSame(1793728800, $event->raw['start_unix_timestamp']);
    }

    public function testEventsError(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('error')));
        $list = new WordpressOrg(new ClientOptions(), $http)->events->upcoming(new EventQuery('zzzqxq'));

        self::assertSame('no_location_available', $list->error);
        self::assertNull($list->location);
        self::assertSame([], $list->events);
    }

    public function testQuerySendsAllSetParametersAndNoIpByDefault(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'), new Response(200, '{}'));
        $events = new WordpressOrg(new ClientOptions(), $http)->events;
        $events->upcoming(new EventQuery(latitude: 51.5, longitude: 13.25, ip: '203.0.113.7', country: 'DE', locale: 'de_DE', timezone: 'Europe/Berlin', number: 5));
        $events->upcoming();

        self::assertSame(
            'https://api.wordpress.org/events/1.0/?latitude=51.5&longitude=13.25&ip=203.0.113.7&country=DE&locale=de_DE&timezone=Europe%2FBerlin&number=5',
            $http->requests[0]->uri,
        );
        self::assertStringNotContainsString('ip=', $http->requests[1]->uri);
    }

    public function testLatitudeNeedsLongitude(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EventQuery(latitude: 51.0);
    }

    public function testLongitudeNeedsLatitude(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EventQuery(longitude: 13.0);
    }
}
