<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg;

use GoSuccess\WordpressOrg\Core\Core;
use GoSuccess\WordpressOrg\Events\Events;
use GoSuccess\WordpressOrg\Http\CurlHttpClient;
use GoSuccess\WordpressOrg\Http\HttpClient;
use GoSuccess\WordpressOrg\Internal\Connection;
use GoSuccess\WordpressOrg\Patterns\Patterns;
use GoSuccess\WordpressOrg\Plugins\Plugins;
use GoSuccess\WordpressOrg\SecretKeys\SecretKeys;
use GoSuccess\WordpressOrg\Stats\Stats;
use GoSuccess\WordpressOrg\Themes\Themes;
use GoSuccess\WordpressOrg\Translations\Translations;

/**
 * Client for the public wordpress.org API; entry point of the package.
 *
 * Each endpoint family is a `public readonly` property that shares one
 * connection (options, transport, retries).
 */
final class WordpressOrg
{
    public const string DEFAULT_API_BASE_URI = 'https://api.wordpress.org';

    public const string DEFAULT_DOWNLOADS_BASE_URI = 'https://downloads.wordpress.org';

    public readonly Core $core;

    public readonly Plugins $plugins;

    public readonly Themes $themes;

    public readonly Translations $translations;

    public readonly Stats $stats;

    public readonly Patterns $patterns;

    public readonly Events $events;

    public readonly SecretKeys $secretKeys;

    /**
     * @param HttpClient|null $httpClient       Defaults to {@see CurlHttpClient}.
     * @param string          $apiBaseUri       Host of the JSON endpoints.
     * @param string          $downloadsBaseUri Host of the download and screenshot links.
     */
    public function __construct(
        ClientOptions $options = new ClientOptions(),
        ?HttpClient $httpClient = null,
        string $apiBaseUri = self::DEFAULT_API_BASE_URI,
        string $downloadsBaseUri = self::DEFAULT_DOWNLOADS_BASE_URI,
    ) {
        $connection = new Connection($options, $httpClient ?? new CurlHttpClient($options->timeout, $options->connectTimeout));

        $this->core = new Core($connection, $apiBaseUri);
        $this->plugins = new Plugins($connection, $apiBaseUri, $downloadsBaseUri, $options->batchSize);
        $this->themes = new Themes($connection, $apiBaseUri, $options->batchSize);
        $this->translations = new Translations($connection, $apiBaseUri);
        $this->stats = new Stats($connection, $apiBaseUri);
        $this->patterns = new Patterns($connection, $apiBaseUri);
        $this->events = new Events($connection, $apiBaseUri);
        $this->secretKeys = new SecretKeys($connection, $apiBaseUri);
    }
}
