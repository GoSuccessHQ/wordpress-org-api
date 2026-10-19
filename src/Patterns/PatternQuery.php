<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Patterns;

/**
 * The parameters of a pattern search. Unset ones are not sent; several category or
 * keyword ids are sent comma separated.
 */
final readonly class PatternQuery
{
    /**
     * @param list<int> $categories ids, sent as `pattern-categories`
     * @param list<int> $keywords   ids, sent as `pattern-keywords`
     */
    public function __construct(
        public ?string $search = null,
        public array $categories = [],
        public array $keywords = [],
        public ?string $wpVersion = null,
        public ?string $locale = null,
        public ?int $page = null,
        public ?int $perPage = null,
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function toParams(): array
    {
        return [
            'search' => $this->search,
            'pattern-categories' => $this->categories === [] ? null : implode(',', $this->categories),
            'pattern-keywords' => $this->keywords === [] ? null : implode(',', $this->keywords),
            'wp-version' => $this->wpVersion,
            'locale' => $this->locale,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }
}
