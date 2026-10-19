<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Themes;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * The author of a theme. The directory serves an object; a plain string
 * only fills {@see $displayName}.
 */
final readonly class ThemeAuthor
{
    public function __construct(
        public ?string $userNicename,
        public ?string $profile,
        public ?string $avatar,
        public ?string $displayName,
        public ?string $authorUrl,
    ) {}

    /**
     * @return self|null `null` for anything that is neither an object nor a string (e.g. `false`)
     */
    public static function fromValue(mixed $value): ?self
    {
        if (\is_string($value)) {
            return new self(null, null, null, $value, null);
        }

        if (!\is_array($value) || array_is_list($value)) {
            return null;
        }

        return new self(
            Read::string($value['user_nicename'] ?? null),
            Read::string($value['profile'] ?? null),
            Read::string($value['avatar'] ?? null),
            Read::string($value['display_name'] ?? null),
            Read::string($value['author_url'] ?? null),
        );
    }
}
