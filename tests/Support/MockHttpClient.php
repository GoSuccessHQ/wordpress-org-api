<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Support;

use Closure;
use GoSuccess\WordpressOrg\Exception\TransportException;
use GoSuccess\WordpressOrg\Http\HttpClient;
use GoSuccess\WordpressOrg\Http\Request;
use GoSuccess\WordpressOrg\Http\Response;
use RuntimeException;

/**
 * Test double that returns a queue of predefined responses (or throws predefined
 * exceptions) and records every request it received.
 */
final class MockHttpClient implements HttpClient
{
    /** @var list<Response|TransportException> */
    private array $queue;

    /** @var list<Request> */
    public array $requests = [];

    /**
     * Called for every request before the queued outcome is returned, e.g. to
     * observe the time at which it was sent.
     *
     * @var (Closure(Request): void)|null
     */
    public ?Closure $onSend = null;

    public function __construct(Response|TransportException ...$queue)
    {
        $this->queue = array_values($queue);
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;

        if ($this->onSend !== null) {
            ($this->onSend)($request);
        }

        $next = array_shift($this->queue);

        if ($next === null) {
            throw new RuntimeException('MockHttpClient ran out of queued responses.');
        }

        if ($next instanceof TransportException) {
            throw $next;
        }

        return $next;
    }

    public function callCount(): int
    {
        return \count($this->requests);
    }

    /**
     * Decode the JSON body of the request at the given index.
     *
     * @return array<array-key, mixed>
     */
    public function jsonBody(int $index = 0): array
    {
        $body = ($this->requests[$index] ?? null)?->body;
        $decoded = \is_string($body) ? json_decode($body, true) : null;

        if (!\is_array($decoded)) {
            throw new RuntimeException("Request {$index} has no JSON object body.");
        }

        return $decoded;
    }
}
