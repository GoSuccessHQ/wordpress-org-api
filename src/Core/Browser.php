<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * The browser wordpress.org recognized in a User-Agent (`core/browse-happy`).
 */
final readonly class Browser
{
    public function __construct(
        public ?string $name,
        public ?string $version,
        public ?string $platform,
        public ?string $updateUrl,
        public ?string $imgSrc,
        public ?string $imgSrcSsl,
        public ?string $currentVersion,
        public ?bool $upgrade,
        public ?bool $insecure,
        public ?bool $mobile,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Read::string($data['name'] ?? null),
            Read::string($data['version'] ?? null),
            Read::string($data['platform'] ?? null),
            Read::string($data['update_url'] ?? null),
            Read::string($data['img_src'] ?? null),
            Read::string($data['img_src_ssl'] ?? null),
            Read::string($data['current_version'] ?? null),
            Read::bool($data['upgrade'] ?? null),
            Read::bool($data['insecure'] ?? null),
            Read::bool($data['mobile'] ?? null),
        );
    }
}
