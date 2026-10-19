<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Internal;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Exception\ApiException;
use GoSuccess\WordpressOrg\Exception\TransportException;
use GoSuccess\WordpressOrg\Http\HttpClient;
use GoSuccess\WordpressOrg\Http\Method;
use GoSuccess\WordpressOrg\Http\Request;
use GoSuccess\WordpressOrg\Http\Response;

/**
 * Sends the requests of all endpoint clients: adds the shared headers and
 * timeouts, retries transient failures of idempotent requests and turns error
 * statuses into exceptions.
 *
 * @internal
 */
final class Connection
{
    public function __construct(
        private readonly ClientOptions $options,
        private readonly HttpClient $httpClient,
    ) {}

    /**
     * @param non-empty-string $uri
     * @param list<int> $parsedStatuses Error statuses that are returned instead of thrown,
     *                                  because the caller reads their body
     *
     * @throws ApiException       for any other error status
     * @throws TransportException when the request keeps failing
     */
    public function get(string $uri, array $parsedStatuses = []): Response
    {
        return $this->send(Method::Get, $uri, [], null, $parsedStatuses);
    }

    /**
     * @param non-empty-string      $uri
     * @param array<string, string> $fields         sent as `application/x-www-form-urlencoded`
     * @param list<int>             $parsedStatuses see {@see get()}
     *
     * @throws ApiException
     * @throws TransportException
     */
    public function postForm(string $uri, array $fields, array $parsedStatuses = []): Response
    {
        return $this->send(
            Method::Post,
            $uri,
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query($fields, '', '&', \PHP_QUERY_RFC3986),
            $parsedStatuses,
        );
    }

    /**
     * @param non-empty-string      $uri
     * @param array<string, string> $extraHeaders
     * @param list<int>             $parsedStatuses
     */
    private function send(Method $method, string $uri, array $extraHeaders, ?string $body, array $parsedStatuses): Response
    {
        $request = new Request(
            method: $method,
            uri: $uri,
            headers: [
                'User-Agent' => $this->options->userAgent,
                'Accept' => 'application/json',
                ...$extraHeaders,
            ],
            body: $body,
            timeout: $this->options->timeout,
            connectTimeout: $this->options->connectTimeout,
        );

        for ($attempt = 0; ; $attempt++) {
            $canRetry = $method->isIdempotent() && $attempt < $this->options->maxRetries;

            try {
                $response = $this->httpClient->send($request);
            } catch (TransportException $exception) {
                if (!$canRetry) {
                    throw $exception;
                }

                $this->wait($attempt);

                continue;
            }

            if ($response->isSuccessful || \in_array($response->statusCode, $parsedStatuses, true)) {
                return $response;
            }

            if ($response->statusCode < 500 || !$canRetry) {
                throw ApiException::fromResponse($response, $request);
            }

            $this->wait($attempt);
        }
    }

    private function wait(int $attempt): void
    {
        $delay = min($this->options->maxRetryDelay, $this->options->retryBaseDelay * (2 ** $attempt));

        if ($delay > 0.0) {
            usleep((int) ($delay * 1_000_000));
        }
    }
}
