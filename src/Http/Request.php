<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Http;

/**
 * Immutable HTTP request passed to an {@see HttpClient}.
 */
final readonly class Request
{
    /**
     * @param non-empty-string      $uri     Absolute request URI.
     * @param array<string, string> $headers Map of header name => value.
     * @param string|null           $body    Request body, if any.
     * @param float|null            $timeout        Maximum duration of the whole request in
     *                                              seconds; `0.0` disables the limit and
     *                                              `null` uses the transport's default.
     * @param float|null            $connectTimeout Maximum duration of the connection phase
     *                                              in seconds; `null` uses the transport's
     *                                              default.
     */
    public function __construct(
        public Method $method,
        public string $uri,
        public array $headers = [],
        public ?string $body = null,
        public ?float $timeout = null,
        public ?float $connectTimeout = null,
    ) {}
}
