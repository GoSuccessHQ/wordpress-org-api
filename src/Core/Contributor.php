<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * One person of a credits group.
 */
final readonly class Contributor
{
    public function __construct(
        public ?string $name,
        public ?string $gravatarHash,
        public ?string $user,
        public ?string $title,
    ) {}

    /**
     * From `[name, gravatar hash, user, title?]`.
     *
     * @param array<array-key, mixed> $entry
     */
    public static function fromEntry(array $entry): self
    {
        $entry = array_values($entry);

        return new self(
            Read::string($entry[0] ?? null),
            Read::string($entry[1] ?? null),
            Read::string($entry[2] ?? null),
            Read::string($entry[3] ?? null),
        );
    }

    /**
     * From a `list` group, where the key is the user name and the value the display name.
     */
    public static function fromListItem(string $user, mixed $name): self
    {
        return new self(Read::string($name), null, $user, null);
    }
}
