<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Translations;

use DateTimeImmutable;
use GoSuccess\WordpressOrg\Internal\Read;

/**
 * One translation set (`translations/{core|plugins|themes}/1.0`).
 */
final readonly class Translation
{
    /**
     * @param array<array-key, string> $iso     ISO 639 code by level; PHP turns the keys `"1"`, `"2"` into ints
     * @param array<string, string>    $strings Core only: a few translated strings such as `continue`
     */
    public function __construct(
        public ?string $language,
        public ?string $version,
        public ?string $englishName,
        public ?string $nativeName,
        public ?string $package,
        public ?DateTimeImmutable $updated,
        public array $iso,
        public array $strings,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Read::string($data['language'] ?? null),
            Read::string($data['version'] ?? null),
            Read::string($data['english_name'] ?? null),
            Read::string($data['native_name'] ?? null),
            Read::string($data['package'] ?? null),
            Read::date($data['updated'] ?? null, Read::DATETIME),
            Read::stringMap($data['iso'] ?? null),
            Read::stringMap($data['strings'] ?? null),
        );
    }
}
