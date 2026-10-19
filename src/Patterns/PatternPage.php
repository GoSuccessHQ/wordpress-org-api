<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Patterns;

/**
 * One page of a pattern search.
 */
final readonly class PatternPage
{
    /**
     * @param list<Pattern> $patterns
     * @param int|null      $total      `x-wp-total`, `null` when the header is absent
     * @param int|null      $totalPages `x-wp-totalpages`, `null` when the header is absent
     */
    public function __construct(
        public array $patterns,
        public ?int $total,
        public ?int $totalPages,
    ) {}
}
