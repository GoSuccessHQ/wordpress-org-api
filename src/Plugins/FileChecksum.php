<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Plugins;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * The hashes of one file of a plugin release. Each algorithm has a list, because
 * a value may be served as one string or as several.
 */
final readonly class FileChecksum
{
    /**
     * @param list<string> $md5
     * @param list<string> $sha256
     */
    public function __construct(
        public array $md5,
        public array $sha256,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(self::hashes($data['md5'] ?? null), self::hashes($data['sha256'] ?? null));
    }

    /**
     * @return list<string>
     */
    private static function hashes(mixed $value): array
    {
        return \is_string($value) ? [$value] : Read::stringList($value);
    }
}
