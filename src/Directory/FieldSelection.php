<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Directory;

/**
 * Which fields the directory should include in a response
 * (`request[fields][name]=1|0`).
 */
final readonly class FieldSelection
{
    /** Every large or rarely needed field; only name, slug, version and closure data remain. */
    private const array MINIMAL = [
        'sections', 'description', 'short_description', 'versions', 'screenshots',
        'reviews', 'banners', 'icons', 'contributors', 'ratings', 'tags',
        'donate_link', 'compatibility', 'downloaded', 'active_installs', 'homepage',
        'added', 'last_updated', 'requires', 'requires_php', 'tested', 'author',
        'author_profile', 'download_link', 'upgrade_notice', 'preview_url',
        'screenshot_url', 'theme_url', 'reviews_url',
    ];

    /**
     * @param array<string, bool> $fields
     */
    public function __construct(private array $fields = []) {}

    public static function minimalPlugin(): self
    {
        return new self(array_fill_keys(self::MINIMAL, false));
    }

    public static function minimalTheme(): self
    {
        return new self(array_fill_keys(self::MINIMAL, false));
    }

    public function with(string $field, bool $include): self
    {
        return new self([...$this->fields, $field => $include]);
    }

    /**
     * @return array<string, bool>
     */
    public function toRequest(): array
    {
        return $this->fields;
    }
}
