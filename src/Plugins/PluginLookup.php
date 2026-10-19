<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Plugins;

use GoSuccess\WordpressOrg\Directory\ClosureDetails;
use GoSuccess\WordpressOrg\Directory\Entry;
use GoSuccess\WordpressOrg\Directory\LookupStatus;

/**
 * The answer for one requested plugin slug.
 */
final readonly class PluginLookup
{
    public function __construct(
        public string $slug,
        public LookupStatus $status,
        /** Only for {@see LookupStatus::Found}. */
        public ?Plugin $plugin,
        /** Only for {@see LookupStatus::Closed}. */
        public ?ClosureDetails $closure,
        /** Raw error text of the entry, if it had one. */
        public ?string $error,
    ) {}

    public function version(): ?string
    {
        return $this->plugin?->version;
    }

    public static function fromEntry(string $slug, mixed $entry): self
    {
        [$status, $closure, $error] = Entry::classify($entry);

        $plugin = null;

        if ($status === LookupStatus::Found && \is_array($entry)) {
            /** @var array<string, mixed> $entry */
            $plugin = Plugin::fromArray($entry);
        }

        return new self($slug, $status, $plugin, $closure, $error);
    }
}
