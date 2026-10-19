<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\SecretKeys;

use SensitiveParameter;

/**
 * The eight generated authentication constants. Values are secrets: they are
 * masked in `var_dump()` and similar output.
 */
final readonly class Salts
{
    /**
     * @param array<string, string> $values constant name => value
     */
    public function __construct(
        #[SensitiveParameter]
        public array $values,
    ) {}

    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function __debugInfo(): array
    {
        return ['values' => array_map(static fn(): string => '***', $this->values)];
    }
}
