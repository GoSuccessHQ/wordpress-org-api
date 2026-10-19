<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * One group of the credits page. Depending on the type, either `contributors`
 * (`titles`, `compact`, `list`) or `libraries` (`libraries`) is filled; the
 * data of an unknown type stays in `raw`.
 */
final readonly class CreditGroup
{
    /**
     * @param list<Contributor>       $contributors
     * @param list<Library>           $libraries
     * @param array<array-key, mixed> $raw          the `data` of a group of an unknown type
     */
    public function __construct(
        public ?string $name,
        public ?string $type,
        public ?bool $shuffle,
        public array $contributors,
        public array $libraries,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $type = Read::string($data['type'] ?? null);
        $items = Read::array($data['data'] ?? null);
        $contributors = [];
        $libraries = [];
        $raw = [];

        switch ($type) {
            case 'titles':
            case 'compact':
                foreach ($items as $entry) {
                    if (\is_array($entry)) {
                        $contributors[] = Contributor::fromEntry($entry);
                    }
                }

                break;
            case 'list':
                foreach ($items as $user => $name) {
                    $contributors[] = Contributor::fromListItem((string) $user, $name);
                }

                break;
            case 'libraries':
                foreach ($items as $entry) {
                    if (\is_array($entry)) {
                        $libraries[] = Library::fromEntry($entry);
                    }
                }

                break;
            default:
                $raw = $items;
        }

        return new self(
            Read::string($data['name'] ?? null),
            $type,
            Read::bool($data['shuffle'] ?? null),
            $contributors,
            $libraries,
            $raw,
        );
    }
}
