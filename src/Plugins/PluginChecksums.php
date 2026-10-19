<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Plugins;

use GoSuccess\WordpressOrg\Internal\Read;

/**
 * File hashes of one released plugin version (`plugin-checksums`).
 */
final readonly class PluginChecksums
{
    /**
     * @param array<string, FileChecksum> $files by path inside the plugin
     */
    public function __construct(
        public ?string $plugin,
        public ?string $version,
        public ?string $source,
        public ?string $zip,
        public array $files,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $files = [];

        foreach (Read::array($data['files'] ?? null) as $path => $entry) {
            if (\is_array($entry) && ($entry === [] || !array_is_list($entry))) {
                $files[(string) $path] = FileChecksum::fromArray($entry);
            }
        }

        return new self(
            Read::string($data['plugin'] ?? null),
            Read::string($data['version'] ?? null),
            Read::string($data['source'] ?? null),
            Read::string($data['zip'] ?? null),
            $files,
        );
    }
}
