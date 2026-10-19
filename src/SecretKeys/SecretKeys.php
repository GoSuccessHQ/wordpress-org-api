<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\SecretKeys;

use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Internal\Connection;

/**
 * Generator for `wp-config.php` keys and salts (`secret-key/1.1/salt/`).
 */
final readonly class SecretKeys
{
    private const string ENDPOINT = 'secret-key/1.1/salt';

    private const array NAMES = [
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
    ];

    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        private string $apiBaseUri,
    ) {}

    /**
     * Fetches freshly generated values; the answer is PHP source that is parsed
     * line by line with a strict pattern and never evaluated.
     *
     * @throws SerializationException when a line does not match or one of the eight constants is missing
     */
    public function generate(): Salts
    {
        $response = $this->connection->get(rtrim($this->apiBaseUri, '/') . '/' . self::ENDPOINT . '/');
        $values = [];

        foreach (preg_split('/\r?\n/', $response->body) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^define\(\s*\'([A-Z_]+)\',\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*\);$/', $line, $match) !== 1) {
                throw new SerializationException(\sprintf('%s: unexpected line (HTTP %d)', self::ENDPOINT, $response->statusCode));
            }

            $values[$match[1]] = preg_replace('/\\\\([\'\\\\])/', '$1', $match[2]) ?? $match[2];
        }

        foreach (self::NAMES as $name) {
            if (!isset($values[$name])) {
                throw new SerializationException(\sprintf('%s: constant %s is missing (HTTP %d)', self::ENDPOINT, $name, $response->statusCode));
            }
        }

        return new Salts($values);
    }
}
