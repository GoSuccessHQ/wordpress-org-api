<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Internal;

use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Http\Response;
use JsonException;
use Throwable;

/**
 * Decides whether a whole response body is usable JSON of the expected shape.
 *
 * Messages name the endpoint and the HTTP status, never the body.
 *
 * @internal
 */
final class Json
{
    private function __construct() {}

    /**
     * Decode a JSON object. The body `[]` is accepted as an empty object, because
     * PHP's `json_encode` renders an empty array that way.
     *
     * @return array<string, mixed>
     *
     * @throws SerializationException
     */
    public static function object(Response $response, string $endpoint): array
    {
        $body = trim($response->body);

        if ($body === '[]') {
            return [];
        }

        if (!str_starts_with($body, '{')) {
            throw self::error($endpoint, 'expected a JSON object', $response);
        }

        /** @var array<string, mixed> */
        return self::decode($body, $response, $endpoint);
    }

    /**
     * Decode a JSON list.
     *
     * @return list<mixed>
     *
     * @throws SerializationException
     */
    public static function list(Response $response, string $endpoint): array
    {
        $body = trim($response->body);

        if (!str_starts_with($body, '[')) {
            throw self::error($endpoint, 'expected a JSON list', $response);
        }

        /** @var list<mixed> */
        return self::decode($body, $response, $endpoint);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decode(string $body, Response $response, string $endpoint): array
    {
        try {
            $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw self::error($endpoint, 'invalid JSON (' . $exception->getMessage() . ')', $response, $exception);
        }

        if (!\is_array($data)) {
            throw self::error($endpoint, 'unexpected JSON value', $response);
        }

        return $data;
    }

    private static function error(
        string $endpoint,
        string $problem,
        Response $response,
        ?Throwable $previous = null,
    ): SerializationException {
        return new SerializationException(
            \sprintf('%s: %s (HTTP %d)', $endpoint, $problem, $response->statusCode),
            0,
            $previous,
        );
    }
}
