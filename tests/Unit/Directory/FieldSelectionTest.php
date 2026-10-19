<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Directory;

use GoSuccess\WordpressOrg\Directory\FieldSelection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldSelection::class)]
final class FieldSelectionTest extends TestCase
{
    public function testMinimalSelectionSwitchesOffLargeFields(): void
    {
        foreach ([FieldSelection::minimalPlugin(), FieldSelection::minimalTheme()] as $selection) {
            self::assertFalse($selection->toRequest()['sections']);
            self::assertFalse($selection->toRequest()['reviews_url']);
        }
    }

    public function testDefaultSelectionIsEmpty(): void
    {
        self::assertSame([], new FieldSelection()->toRequest());
    }

    public function testWithReturnsNewInstance(): void
    {
        $base = FieldSelection::minimalPlugin();
        $changed = $base->with('sections', true)->with('extra', false);

        self::assertFalse($base->toRequest()['sections']);
        self::assertTrue($changed->toRequest()['sections']);
        self::assertFalse($changed->toRequest()['extra']);
        self::assertArrayNotHasKey('extra', $base->toRequest());
    }
}
