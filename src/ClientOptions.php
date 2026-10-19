<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg;

use InvalidArgumentException;

/**
 * Immutable transport and retry settings.
 *
 * Applies to every request of the client, whichever endpoint it reaches.
 */
final readonly class ClientOptions
{
    public const string DEFAULT_USER_AGENT = 'gosuccess/wordpress-org-api (+https://github.com/GoSuccessHQ/wordpress-org-api)';

    /**
     * The two timeouts travel with every request (see {@see Http\Request}), so they
     * also apply through a custom transport and take precedence over the defaults
     * the transport was created with.
     *
     * @param float  $timeout        Maximum duration of a request in seconds; `0.0` means
     *                               no limit.
     * @param float  $connectTimeout Maximum duration of the connection phase in seconds.
     * @param int    $maxRetries     How often an idempotent request is retried after a
     *                               network failure or a server error (5xx); `0` disables
     *                               retries. A `429` is never retried: the caller decides
     *                               when to try again (see {@see Exception\RateLimitException::$retryAfter}).
     * @param float  $retryBaseDelay Base delay in seconds of the exponential backoff.
     * @param float  $maxRetryDelay  Upper bound in seconds for a single wait.
     * @param string $userAgent      Value of the `User-Agent` header.
     * @param int    $batchSize      Slugs per request where an endpoint takes a list.
     *                               wordpress.org refuses more than 100 plugin slugs
     *                               per request with `422` (measured 2026-10-19).
     */
    public function __construct(
        public float $timeout = 30.0,
        public float $connectTimeout = 10.0,
        public int $maxRetries = 0,
        public float $retryBaseDelay = 1.0,
        public float $maxRetryDelay = 30.0,
        public string $userAgent = self::DEFAULT_USER_AGENT,
        public int $batchSize = 50,
    ) {
        foreach (['timeout' => $timeout, 'connectTimeout' => $connectTimeout, 'retryBaseDelay' => $retryBaseDelay, 'maxRetryDelay' => $maxRetryDelay] as $name => $value) {
            // NAN and INF would turn into warnings or endless waits once converted for cURL or sleep().
            if (!is_finite($value) || $value < 0.0) {
                throw new InvalidArgumentException("{$name} must be a finite number that is not negative.");
            }
        }

        if ($maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must not be negative.');
        }

        if ($batchSize < 1) {
            throw new InvalidArgumentException('batchSize must be at least 1.');
        }

        if (trim($userAgent) === '') {
            throw new InvalidArgumentException('userAgent must not be empty.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $userAgent) === 1) {
            throw new InvalidArgumentException('userAgent must not contain control characters.');
        }
    }
}
