<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Themes;

use GoSuccess\WordpressOrg\Directory\ClosureDetails;
use GoSuccess\WordpressOrg\Directory\Entry;
use GoSuccess\WordpressOrg\Directory\LookupStatus;

/**
 * The answer for one requested theme slug.
 */
final readonly class ThemeLookup
{
    public function __construct(
        public string $slug,
        public LookupStatus $status,
        /** Only for {@see LookupStatus::Found}. */
        public ?Theme $theme,
        /** Only for {@see LookupStatus::Closed}. */
        public ?ClosureDetails $closure,
        /** Raw error text of the entry, if it had one. */
        public ?string $error,
    ) {}

    public function version(): ?string
    {
        return $this->theme?->version;
    }

    public static function fromEntry(string $slug, mixed $entry): self
    {
        [$status, $closure, $error] = Entry::classify($entry);

        $theme = null;

        if ($status === LookupStatus::Found && \is_array($entry)) {
            /** @var array<string, mixed> $entry */
            $theme = Theme::fromArray($entry);
        }

        return new self($slug, $status, $theme, $closure, $error);
    }
}
