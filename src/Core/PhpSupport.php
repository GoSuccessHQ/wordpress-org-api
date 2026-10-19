<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * How WordPress rates a PHP version (`core/serve-happy`).
 */
final readonly class PhpSupport
{
    public function __construct(
        public ?string $recommendedVersion,
        public ?string $minimumVersion,
        public ?bool $isSupported,
        public ?bool $isSecure,
        public ?bool $isAcceptable,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Read::string($data['recommended_version'] ?? null),
            Read::string($data['minimum_version'] ?? null),
            Read::bool($data['is_supported'] ?? null),
            Read::bool($data['is_secure'] ?? null),
            Read::bool($data['is_acceptable'] ?? null),
        );
    }
}
