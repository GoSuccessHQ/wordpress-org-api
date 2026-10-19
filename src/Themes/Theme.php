<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Themes;

use DateTimeImmutable;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * A theme as served by `themes/info/1.2`. Values are passed through as served:
 * names keep their HTML entities and `screenshotUrl` stays protocol-relative.
 * A missing field or one of the wrong type is `null` (collections: empty).
 *
 * @see https://codex.wordpress.org/WordPress.org_API
 */
final readonly class Theme
{
    /**
     * @param array<int, int>         $ratings    star count => number of ratings
     * @param list<string>            $screenshots
     * @param array<string, string>   $sections
     * @param array<string, string>   $tags
     * @param array<string, string>   $versions
     * @param array<array-key, mixed> $raw        the decoded entry, including fields not modelled here
     */
    public function __construct(
        public ?string $name,
        public ?string $slug,
        public ?string $version,
        public ?string $previewUrl,
        public ?string $screenshotUrl,
        public ?string $reviewsUrl,
        public ?string $themeUrl,
        public ?string $homepage,
        public ?string $description,
        public ?string $downloadLink,
        public ?string $requires,
        public ?string $requiresPhp,
        public ?string $externalSupportUrl,
        public ?string $externalRepositoryUrl,
        public ?string $template,
        public ?string $parent,
        public ?ThemeAuthor $author,
        public ?int $rating,
        public ?int $numRatings,
        public ?int $downloaded,
        public ?int $activeInstalls,
        public ?int $screenshotCount,
        public array $ratings,
        public ?DateTimeImmutable $lastUpdated,
        public ?DateTimeImmutable $lastUpdatedTime,
        public ?DateTimeImmutable $creationTime,
        public ?bool $isCommercial,
        public ?bool $isCommunity,
        public array $screenshots,
        public array $sections,
        public array $tags,
        public array $versions,
        public array $raw,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $parent = $data['parent'] ?? null;

        return new self(
            Read::string($data['name'] ?? null),
            Read::string($data['slug'] ?? null),
            Read::string($data['version'] ?? null),
            Read::string($data['preview_url'] ?? null),
            Read::string($data['screenshot_url'] ?? null),
            Read::string($data['reviews_url'] ?? null),
            Read::string($data['theme_url'] ?? null),
            Read::string($data['homepage'] ?? null),
            Read::string($data['description'] ?? null),
            Read::string($data['download_link'] ?? null),
            Read::string($data['requires'] ?? null),
            Read::string($data['requires_php'] ?? null),
            // `false` when the theme names no support URL.
            Read::string($data['external_support_url'] ?? null),
            Read::string($data['external_repository_url'] ?? null),
            Read::string($data['template'] ?? null),
            // A child theme names its parent as a string or as an object with a `slug`.
            Read::string(\is_array($parent) ? ($parent['slug'] ?? null) : $parent),
            ThemeAuthor::fromValue($data['author'] ?? null),
            Read::int($data['rating'] ?? null),
            Read::int($data['num_ratings'] ?? null),
            Read::int($data['downloaded'] ?? null),
            Read::int($data['active_installs'] ?? null),
            Read::int($data['screenshot_count'] ?? null),
            self::ratings($data['ratings'] ?? null),
            Read::date($data['last_updated'] ?? null, Read::DATE),
            Read::date($data['last_updated_time'] ?? null, Read::DATETIME),
            Read::date($data['creation_time'] ?? null, Read::DATETIME),
            Read::bool($data['is_commercial'] ?? null),
            Read::bool($data['is_community'] ?? null),
            Read::stringList($data['screenshots'] ?? null),
            Read::stringMap($data['sections'] ?? null),
            Read::stringMap($data['tags'] ?? null),
            Read::stringMap($data['versions'] ?? null),
            $data,
        );
    }

    /**
     * @return array<int, int>
     */
    private static function ratings(mixed $value): array
    {
        $ratings = [];

        foreach (Read::array($value) as $stars => $count) {
            // PHP turns the keys "1".."5" into integers; anything else is skipped.
            if (\is_int($stars) && \is_int($count)) {
                $ratings[$stars] = $count;
            }
        }

        return $ratings;
    }
}
