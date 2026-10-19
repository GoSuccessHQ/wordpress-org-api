<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

/**
 * The parameters of a version check that change the offers. Unset ones are not sent.
 */
final readonly class VersionCheckQuery
{
    public function __construct(
        public ?string $version = null,
        public ?string $php = null,
        public ?string $mysql = null,
        public ?string $locale = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toParams(): array
    {
        return [
            'version' => $this->version,
            'php' => $this->php,
            'mysql' => $this->mysql,
            'locale' => $this->locale,
        ];
    }
}
