<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * The answer of `core/version-check`: the releases on offer, newest first.
 */
final readonly class VersionCheck
{
    /**
     * @param list<Offer>                     $offers
     * @param list<array<array-key, mixed>> $translations
     */
    public function __construct(
        public array $offers,
        public array $translations,
    ) {}

    /**
     * The newest release, which wordpress.org lists first.
     */
    public function latest(): ?string
    {
        return $this->offers[0]->current ?? null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws SerializationException if `offers` is present but not a list
     */
    public static function fromArray(array $data, string $endpoint): self
    {
        $offers = $data['offers'] ?? [];

        if (!\is_array($offers) || !array_is_list($offers)) {
            throw new SerializationException(\sprintf('%s: expected "offers" to be a list', $endpoint));
        }

        $translations = [];

        foreach (Read::array($data['translations'] ?? null) as $translation) {
            if (\is_array($translation)) {
                $translations[] = $translation;
            }
        }

        $list = [];

        foreach ($offers as $offer) {
            if (\is_array($offer) && ($offer === [] || !array_is_list($offer))) {
                $list[] = Offer::fromArray($offer);
            }
        }

        return new self($list, $translations);
    }
}
