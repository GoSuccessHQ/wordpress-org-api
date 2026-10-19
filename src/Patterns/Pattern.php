<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Patterns;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * One block pattern in the WordPress REST format. Markup is passed through unchanged.
 */
final readonly class Pattern
{
    /**
     * @param list<string>        $categorySlugs
     * @param list<string>        $keywordSlugs
     * @param array<string, mixed> $meta         `wpop_description`, `wpop_viewport_width`, `wpop_block_types`, …
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public ?int $id,
        public ?string $title,
        public ?string $content,
        public ?string $patternContent,
        public array $categorySlugs,
        public array $keywordSlugs,
        public array $meta,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $title = Read::array($data['title'] ?? null);
        $content = Read::array($data['content'] ?? null);
        $meta = [];

        foreach (Read::array($data['meta'] ?? null) as $key => $value) {
            $meta[(string) $key] = $value;
        }

        return new self(
            Read::int($data['id'] ?? null),
            Read::string($title['rendered'] ?? null),
            Read::string($content['rendered'] ?? null),
            Read::string($data['pattern_content'] ?? null),
            Read::stringList($data['category_slugs'] ?? null),
            Read::stringList($data['keyword_slugs'] ?? null),
            $meta,
            $data,
        );
    }
}
