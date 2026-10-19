<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Themes;

/**
 * One page of a theme directory search.
 */
final readonly class ThemePage
{
    /**
     * @param int          $page    0 when unreadable
     * @param int          $pages   0 when unreadable
     * @param int          $results total number of matches, 0 when unreadable
     * @param list<Theme> $themes
     */
    public function __construct(
        public int $page,
        public int $pages,
        public int $results,
        public array $themes,
    ) {}
}
