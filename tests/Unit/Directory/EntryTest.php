<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Directory;

use GoSuccess\WordpressOrg\Directory\ClosureDetails;
use GoSuccess\WordpressOrg\Directory\Entry;
use GoSuccess\WordpressOrg\Directory\LookupStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Entry::class)]
#[CoversClass(ClosureDetails::class)]
final class EntryTest extends TestCase
{
    public function testClassifiesFound(): void
    {
        [$status, $closure, $error] = Entry::classify(['name' => 'Akismet', 'slug' => 'akismet', 'version' => '5.5']);

        self::assertSame(LookupStatus::Found, $status);
        self::assertNull($closure);
        self::assertNull($error);
    }

    public function testClassifiesClosed(): void
    {
        [$status, $closure, $error] = Entry::classify([
            'error' => 'closed',
            'closed' => true,
            'closed_date' => '2021-01-30',
            'reason' => 'security-issue',
            'reason_text' => 'Security Issue',
            'name' => 'Display Widgets',
            'slug' => 'display-widgets',
            'description' => 'This plugin has been closed.',
        ]);

        self::assertSame(LookupStatus::Closed, $status);
        self::assertSame('closed', $error);
        self::assertInstanceOf(ClosureDetails::class, $closure);
        self::assertSame('2021-01-30', $closure->date?->format('Y-m-d'));
        self::assertSame('security-issue', $closure->reason);
        self::assertSame('Security Issue', $closure->reasonText);
        self::assertSame('This plugin has been closed.', $closure->description);
    }

    public function testClosedWithUnparsableDateKeepsTheRest(): void
    {
        [$status, $closure] = Entry::classify([
            'closed' => true,
            'closed_date' => '2021-13-45',
            'reason' => 'security-issue',
        ]);

        self::assertSame(LookupStatus::Closed, $status);
        self::assertNotNull($closure);
        self::assertNull($closure->date);
        self::assertSame('security-issue', $closure->reason);
    }

    public function testClassifiesNotFound(): void
    {
        [$status, $closure, $error] = Entry::classify(['error' => 'Plugin not found.']);

        self::assertSame(LookupStatus::NotFound, $status);
        self::assertNull($closure);
        self::assertSame('Plugin not found.', $error);
    }

    public function testStringEntryIsUnknown(): void
    {
        self::assertSame([LookupStatus::Unknown, null, null], Entry::classify('akismet'));
    }

    public function testNullEntryIsUnknown(): void
    {
        self::assertSame([LookupStatus::Unknown, null, null], Entry::classify(null));
    }

    #[DataProvider('slugs')]
    public function testSendableSlugs(string $slug, bool $expected): void
    {
        self::assertSame($expected, Entry::isSendable($slug));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function slugs(): iterable
    {
        yield 'plain' => ['akismet', true];
        yield 'dots and underscores' => ['wp-file_manager.2', true];
        yield 'empty' => ['', false];
        yield 'comma' => ['a,b', false];
        yield 'space' => ['a b', false];
        yield 'non-ascii' => ['ä', false];
        yield 'trailing newline' => ["akismet\n", false];
    }
}
