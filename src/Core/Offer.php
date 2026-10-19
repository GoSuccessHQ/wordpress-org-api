<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * One entry of the version check: a WordPress release offered for the request.
 */
final readonly class Offer
{
    /**
     * @param array<array-key, mixed> $raw the entry as served
     */
    public function __construct(
        public ?string $response,
        public ?string $download,
        public ?string $locale,
        public OfferPackages $packages,
        public ?string $current,
        public ?string $version,
        public ?string $phpVersion,
        public ?string $mysqlVersion,
        public ?string $newBundled,
        public ?string $partialVersion,
        public array $raw,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Read::string($data['response'] ?? null),
            Read::string($data['download'] ?? null),
            Read::string($data['locale'] ?? null),
            OfferPackages::fromArray(Read::array($data['packages'] ?? null)),
            Read::string($data['current'] ?? null),
            Read::string($data['version'] ?? null),
            Read::string($data['php_version'] ?? null),
            Read::string($data['mysql_version'] ?? null),
            Read::string($data['new_bundled'] ?? null),
            Read::string($data['partial_version'] ?? null),
            $data,
        );
    }
}
