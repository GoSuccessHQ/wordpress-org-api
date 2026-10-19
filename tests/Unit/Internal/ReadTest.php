<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Internal;

use GoSuccess\WordpressOrg\Internal\Read;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Read::class)]
final class ReadTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function strings(): iterable
    {
        yield 'string' => ['a', 'a'];
        yield 'int' => [5, '5'];
        yield 'float' => [1.5, '1.5'];
        yield 'false' => [false, null];
        yield 'null' => [null, null];
        yield 'array' => [['a'], null];
    }

    #[DataProvider('strings')]
    public function testString(mixed $input, ?string $expected): void
    {
        self::assertSame($expected, Read::string($input));
    }

    /**
     * @return iterable<string, array{mixed, ?int}>
     */
    public static function ints(): iterable
    {
        yield 'int' => [7, 7];
        yield 'digits' => ['25688', 25688];
        yield 'negative' => ['-3', -3];
        yield 'decimal' => ['1.5', null];
        yield 'float' => [1.5, null];
        yield 'empty' => ['', null];
        yield 'overflow' => ['99999999999999999999', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('ints')]
    public function testInt(mixed $input, ?int $expected): void
    {
        self::assertSame($expected, Read::int($input));
    }

    /**
     * @return iterable<string, array{mixed, ?float}>
     */
    public static function floats(): iterable
    {
        yield 'string' => ['0.05', 0.05];
        yield 'int' => [3, 3.0];
        yield 'float' => [2.5, 2.5];
        yield 'text' => ['abc', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('floats')]
    public function testFloat(mixed $input, ?float $expected): void
    {
        self::assertSame($expected, Read::float($input));
    }

    /**
     * @return iterable<string, array{mixed, ?bool}>
     */
    public static function bools(): iterable
    {
        yield 'true' => [true, true];
        yield 'false' => [false, false];
        yield 'one' => [1, true];
        yield 'zero' => [0, false];
        yield 'string one' => ['1', true];
        yield 'string zero' => ['0', false];
        yield 'yes' => ['yes', null];
        yield 'two' => [2, null];
        yield 'null' => [null, null];
    }

    #[DataProvider('bools')]
    public function testBool(mixed $input, ?bool $expected): void
    {
        self::assertSame($expected, Read::bool($input));
    }

    public function testArray(): void
    {
        self::assertSame(['a' => 1], Read::array(['a' => 1]));
        self::assertSame([], Read::array('x'));
    }

    public function testStringListSkipsNonStrings(): void
    {
        self::assertSame(['a', 'b'], Read::stringList(['a', 1, ['x'], 'b']));
        self::assertSame(['x', 'y'], Read::stringList(['k' => 'x', 'j' => 'y']));
        self::assertSame([], Read::stringList(null));
    }

    public function testStringMapKeepsStringValuesAndCastsKeys(): void
    {
        self::assertSame(['a' => 'x', '1' => 'y'], Read::stringMap(['a' => 'x', 1 => 'y', 'b' => 2]));
        self::assertSame([], Read::stringMap('x'));
    }

    /**
     * @return iterable<string, array{mixed, list<string>, ?string}>
     */
    public static function dates(): iterable
    {
        yield 'updated' => ['2026-08-18 11:42pm GMT', [Read::UPDATED], '2026-08-18T23:42:00+00:00'];
        yield 'date' => ['2021-01-30', [Read::DATE], '2021-01-30T00:00:00+00:00'];
        yield 'invalid day' => ['2026-02-30', [Read::DATE], null];
        yield 'false' => [false, [Read::DATE], null];
        yield 'empty' => ['', [Read::DATE], null];
        yield 'datetime' => ['2026-11-03 19:00:00', [Read::DATETIME], '2026-11-03T19:00:00+00:00'];
        yield 'second format' => ['2026-11-03', [Read::DATETIME, Read::DATE], '2026-11-03T00:00:00+00:00'];
        yield 'no match' => ['nope', [Read::DATE, Read::DATETIME], null];
    }

    /**
     * @param list<string> $formats
     */
    #[DataProvider('dates')]
    public function testDate(mixed $input, array $formats, ?string $expected): void
    {
        $date = Read::date($input, ...$formats);

        self::assertSame($expected, $date?->format(\DATE_ATOM));
    }

    public function testRatingsKeepsIntegerStarsAndCounts(): void
    {
        self::assertSame([1 => 1, 5 => 9], Read::ratings(['1' => 1, '5' => 9, 'x' => 3, '3' => '4', '2' => null]));
        self::assertSame([], Read::ratings('none'));
    }
}
