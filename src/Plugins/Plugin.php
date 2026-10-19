<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Plugins;

use DateTimeImmutable;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * A plugin as served by `plugins/info/1.2`. Values are passed through as served:
 * names and authors keep their HTML entities and markup. A missing field or one
 * of the wrong type is `null` (collections: empty).
 *
 * @see https://codex.wordpress.org/WordPress.org_API
 */
final readonly class Plugin
{
    /**
     * @param array<int, int>                $ratings    star count => number of ratings
     * @param list<string>                   $requiresPlugins
     * @param array<string, string>          $sections
     * @param array<string, string>          $tags
     * @param array<string, string>          $versions
     * @param array<string, string>          $banners
     * @param array<string, string>          $icons
     * @param array<string, string>          $upgradeNotice
     * @param array<array-key, mixed>        $screenshots
     * @param array<array-key, mixed>        $contributors
     * @param array<array-key, mixed>        $compatibility
     * @param array<array-key, mixed>        $raw        the decoded entry, including fields not modelled here
     */
    public function __construct(
        public ?string $name,
        public ?string $slug,
        public ?string $version,
        public ?string $author,
        public ?string $authorProfile,
        public ?string $requires,
        public ?string $tested,
        public ?string $requiresPhp,
        public ?string $homepage,
        public ?string $shortDescription,
        public ?string $description,
        public ?string $downloadLink,
        public ?string $donateLink,
        public ?string $businessModel,
        public ?string $repositoryUrl,
        public ?string $commercialSupportUrl,
        public ?string $supportUrl,
        public ?string $previewLink,
        public ?int $rating,
        public ?int $numRatings,
        public ?int $supportThreads,
        public ?int $supportThreadsResolved,
        public ?int $activeInstalls,
        public ?int $downloaded,
        public array $ratings,
        public ?DateTimeImmutable $lastUpdated,
        public ?DateTimeImmutable $added,
        public array $requiresPlugins,
        public array $sections,
        public array $tags,
        public array $versions,
        public array $banners,
        public array $icons,
        public array $upgradeNotice,
        public array $screenshots,
        public array $contributors,
        public array $compatibility,
        public array $raw,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Read::string($data['name'] ?? null),
            Read::string($data['slug'] ?? null),
            Read::string($data['version'] ?? null),
            Read::string($data['author'] ?? null),
            Read::string($data['author_profile'] ?? null),
            Read::string($data['requires'] ?? null),
            Read::string($data['tested'] ?? null),
            // `false` when the plugin states no requirement.
            Read::string($data['requires_php'] ?? null),
            Read::string($data['homepage'] ?? null),
            Read::string($data['short_description'] ?? null),
            Read::string($data['description'] ?? null),
            Read::string($data['download_link'] ?? null),
            Read::string($data['donate_link'] ?? null),
            Read::string($data['business_model'] ?? null),
            Read::string($data['repository_url'] ?? null),
            Read::string($data['commercial_support_url'] ?? null),
            Read::string($data['support_url'] ?? null),
            Read::string($data['preview_link'] ?? null),
            Read::int($data['rating'] ?? null),
            Read::int($data['num_ratings'] ?? null),
            Read::int($data['support_threads'] ?? null),
            Read::int($data['support_threads_resolved'] ?? null),
            Read::int($data['active_installs'] ?? null),
            Read::int($data['downloaded'] ?? null),
            self::ratings($data['ratings'] ?? null),
            Read::date($data['last_updated'] ?? null, Read::UPDATED),
            Read::date($data['added'] ?? null, Read::DATE),
            Read::stringList($data['requires_plugins'] ?? null),
            Read::stringMap($data['sections'] ?? null),
            Read::stringMap($data['tags'] ?? null),
            Read::stringMap($data['versions'] ?? null),
            Read::stringMap($data['banners'] ?? null),
            Read::stringMap($data['icons'] ?? null),
            Read::stringMap($data['upgrade_notice'] ?? null),
            Read::array($data['screenshots'] ?? null),
            Read::array($data['contributors'] ?? null),
            Read::array($data['compatibility'] ?? null),
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
