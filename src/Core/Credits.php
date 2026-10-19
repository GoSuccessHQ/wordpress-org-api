<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * The credits of a WordPress release (`core/credits`).
 */
final readonly class Credits
{
    /**
     * @param array<string, CreditGroup> $groups by group id, in the order served
     * @param array<array-key, mixed>    $data   the top-level `data` (`profiles` URL template, `version`)
     */
    public function __construct(
        public array $groups,
        public array $data,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $groups = [];

        foreach (Read::array($data['groups'] ?? null) as $id => $group) {
            if (\is_array($group)) {
                $groups[(string) $id] = CreditGroup::fromArray($group);
            }
        }

        return new self($groups, Read::array($data['data'] ?? null));
    }
}
