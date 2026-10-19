# wordpress.org API for PHP

A modern, strongly-typed, dependency-free PHP client for the public
[wordpress.org API](https://codex.wordpress.org/WordPress.org_API): the plugin
and theme directory, WordPress core, translations, statistics, block patterns,
community events and secret keys.

## Features

- **Status of many plugins or themes in few requests**: found, latest version,
  closed with date and reason. Lists are split into chunks of `batchSize` slugs.
- **Latest WordPress version** and the stability rating of every release.
- **The whole Codex list**, each endpoint in its newest JSON version: plugins,
  themes, core (version check, stability, checksums, credits, importers,
  handbook, Browse Happy, Serve Happy), translations, stats, patterns, events and
  secret keys. Plus the plugin checksums from `downloads.wordpress.org`.
- **Tolerant reading**: wordpress.org's types are unreliable, so a malformed
  field becomes `null` instead of failing the response. The main models keep the
  decoded entry as `raw`.
- **"Not found" is a result**, not an exception, wherever an endpoint has such an
  answer.
- **A `429` is never retried**, and nothing is retried by default.
- **No dependencies**: only `ext-curl` and `ext-json`; bring your own transport
  if you like.

## Requirements

- PHP 8.4 or newer
- `ext-curl`, `ext-json`

## Installation

```bash
composer require gosuccess/wordpress-org-api
```

## Quick start

wordpress.org needs no API key.

```php
use GoSuccess\WordpressOrg\Directory\LookupStatus;
use GoSuccess\WordpressOrg\WordpressOrg;

$wordpress = new WordpressOrg();

foreach ($wordpress->plugins->lookupChunks(['akismet', 'display-widgets', 'no-such-plugin']) as $results) {
    foreach ($results as $slug => $lookup) {
        echo $slug, ' ', $lookup->status->name, ' ', $lookup->version() ?? '-', "\n";

        if ($lookup->status === LookupStatus::Closed) {
            echo '  closed ', $lookup->closure?->date?->format('Y-m-d'), ' ', $lookup->closure?->reason, "\n";
        }
    }
}

echo $wordpress->core->versionCheck()->latest(); // e.g. 7.1.3
```

`lookupChunks()` is a generator that yields one `array<string, PluginLookup>` per
chunk, so you can store each chunk before the next request starts. `lookup()`
returns everything at once (see "Error handling" for how they differ on failure).

`WordpressOrg` exposes one property per area: `plugins`, `themes`, `core`,
`translations`, `stats`, `patterns`, `events`, `secretKeys`.

## Plugins and themes

```php
use GoSuccess\WordpressOrg\Plugins\PluginQuery;

$results = $wordpress->plugins->lookup(['akismet', 'hello-dolly']); // minimal fields
$lookup = $wordpress->plugins->information('akismet');              // default fields
$page = $wordpress->plugins->search(new PluginQuery(search: 'seo', tags: ['seo', 'backup']));
foreach ($wordpress->plugins->searchAll(new PluginQuery(browse: 'popular')) as $plugin) { /* … */ }
$checksums = $wordpress->plugins->checksums('akismet', '5.7.2');    // ?PluginChecksums
```

`themes` has the same `lookup`, `lookupChunks`, `information`, `search` and
`searchAll`, plus `hotTags()` and `featureList()`.

- **Status.** `PluginLookup` and `ThemeLookup` carry `status` (`Found`, `Closed`,
  `NotFound`, `Unknown`), the model when found, the closure when closed
  (`date`, raw `reason`, `reasonText`, `description`) and the raw `error` text.
  `Unknown` means there is no usable answer for the slug: the entry was missing or
  malformed, or the slug cannot be sent (empty, or characters outside
  `[A-Za-z0-9._-]`). Such slugs cause no request. An answer that contains none of
  the slugs sent for a chunk (for example `{"error":"Invalid Input"}`) is a failed
  chunk and throws a `SerializationException`.
- **Slugs** are sent lower-cased; an upper-case slug is answered
  "Invalid plugin slug." by wordpress.org (measured 2026-10-19), so lower-casing
  makes `Akismet` find `akismet`. Results keep your spelling and order, duplicates
  are answered once. PHP turns all-digit array keys into ints, so the key of a
  slug like `2020` is an int.
- **Fields.** The default for `lookup()` switches off every large field, which
  leaves name, slug, version and the closure data (a 3-slug request was 1.5 KB).
  Pass a `FieldSelection` to choose others. 50 slugs per request worked with all
  fields (measured 2026-10-19, about 200 KB); a larger maximum has not been
  measured, which is why `batchSize` defaults to 50.
- **Search** takes `search`, `tags`, `author`, `browse`, `user`, `page`, `perPage` and `fields`
  (themes: `theme` instead of `user`). Several tags are sent comma-joined and
  match any of them (OR); the `tag[]` form is refused by the plugin directory.
  `searchAll()` requests the next page only when you iterate to it and stops after
  the last page, when `pages` is missing or 0, and on an empty page.
- **Closed themes are unverified.** No closed theme was looked for; `ThemeLookup`
  applies the plugin rule (`closed: true` or `error: "closed"` is `Closed`).
- **Checksums** return `null` for an unknown version (`404`). The hashes are
  `md5` and `sha256`; both are exposed as lists.

See [docs/plugins-and-themes.md](docs/plugins-and-themes.md).

## Core

```php
$check = $wordpress->core->versionCheck();            // offers, latest()
$wordpress->core->stability('6.8.3');                  // Stability::Insecure, or null if unknown
$wordpress->core->stableCheck();                       // array<string, string>, version => state
$wordpress->core->checksums('6.8.3');                  // ?CoreChecksums, path => MD5
$wordpress->core->credits('7.1');
$wordpress->core->importers();
$wordpress->core->handbookUrl('get_option', '6.8');   // ?string
$wordpress->core->browseHappy($userAgent);
$wordpress->core->serveHappy('8.3.0');
```

`latest()` is the `current` value of the first offer. The version check sends only
`version`, `php`, `mysql` and `locale` (`VersionCheckQuery`), not WordPress's
telemetry parameters.

`checksums()` returns `null` when wordpress.org answers `{"checksums":false}`.
The hashes are **MD5**: as wordpress.org itself notes, they detect accidental
changes, not malicious ones. A single version's list is large (3,230 files, 270 KB
for 6.8.3 on 2026-10-19).

See [docs/core.md](docs/core.md).

## Translations, stats, patterns, events and secret keys

```php
use GoSuccess\WordpressOrg\Events\EventQuery;
use GoSuccess\WordpressOrg\Patterns\PatternQuery;

$wordpress->translations->core('6.8');                 // list<Translation>
$wordpress->translations->plugin('akismet', '5.3');
$wordpress->stats->wordpress();                        // array<string, float>, share in percent
$wordpress->stats->pluginDownloads('akismet', 30);     // array<string, int>, date => downloads
$wordpress->patterns->search(new PatternQuery(search: 'button', perPage: 2));
$wordpress->events->upcoming(new EventQuery(location: 'Dresden', number: 3));
$wordpress->secretKeys->generate();                    // Salts
```

- **Download stats** return a plausible series even for unknown slugs
  (measured 2026-10-19: a 180-day series of small counts), so an unknown slug
  cannot be told apart there. The other plugin endpoints (version shares,
  translations) answer an unknown slug with an empty result.
- **Stats keys** are kept as sent (`8.3`, `MariaDB 10.11`); a key made only of
  digits becomes an int in a PHP array.
- **Events**: `date` and `endDate` are the event's **local** time as a string;
  `start` and `end` are UTC. A location that cannot be resolved answers `200`
  with `error` set and no events.
  The `ip` argument of `EventQuery` sends that IP address to wordpress.org, which
  uses it to guess a location. It is sent only when you set it.
- **Patterns**: `PatternPage` carries the totals from the `x-wp-total` and
  `x-wp-totalpages` headers (`null` when absent).
- **Secret keys** are generated by wordpress.org and parsed with a strict pattern,
  never evaluated. `Salts` masks the values only in `var_dump()` and `print_r()`;
  reading `values` or `get()` returns them in clear, and an exception, a log line
  or `json_encode` is not covered by the masking. Treat them as secrets.

See [docs/more-endpoints.md](docs/more-endpoints.md).

## Tolerant reading and `raw`

Text from wordpress.org is foreign data; the types are unreliable (`requires_php`
is a string or `false`, download counts are strings, screenshot URLs are
protocol-relative). Therefore:

- A whole-response problem throws (see "Error handling").
- A field problem does not: a missing field or a value of the wrong type is `null`
  (lists and maps: empty). Models never throw.
- Dates become `DateTimeImmutable`, or `null` when unparsable.
- Enum-like values stay available as the raw string; the typed accessor
  (`Stability::tryFrom`) returns `null` for a value the package does not know.
- `Plugin`, `Theme`, `Offer`, `Pattern` and `Event` keep the decoded entry as
  `raw`, so a field wordpress.org adds later is reachable without an update.
- **HTML entities and markup pass through unchanged**: a name such as
  `UpdraftPlus: WP Backup &amp; Migration Plugin` and an `author` such as
  `<a href="…">…</a>` are never decoded or stripped. Escape them for the context
  you print them in.

## Error handling

Every exception implements `GoSuccess\WordpressOrg\Exception\WordpressOrgException`.
Problems with the **whole response** throw; problems with a **field** become `null`.

| Exception | When |
|---|---|
| `BadRequestException`, `ForbiddenException`, `NotFoundException` | `400`, `403`, `404` where `404` is not a result of the endpoint |
| `RateLimitException` | `429`, never retried; `retryAfter` holds the announced seconds |
| `ServerException` | `5xx` after the retries |
| other `ApiException` subclasses | other error statuses |
| `SerializationException` | the body is not JSON (a `200` with HTML), or not the expected object or list; the message names the endpoint and status, never the body |
| `TransportException` | network failure after the retries |

`404` is a result, not an exception, for the single plugin or theme lookup (also a
closed plugin answers `404` with its closure) and for plugin checksums.

`maxRetries` defaults to `0`. With a higher value, only network failures and `5xx`
are retried, with exponential backoff (`retryBaseDelay`, `maxRetryDelay`). A `429`
is never waited for or retried: catch `RateLimitException` and decide yourself.

**A failing chunk.** `lookup()` throws if any chunk fails and never returns partial
results; the slugs of a failed chunk are not marked `Unknown`, because an outage
must not look like vanished plugins. `lookupChunks()` yields the chunks before the
failing one and throws at it.

## Configuration and custom transport

`ClientOptions` sets `timeout` (30 s), `connectTimeout` (10 s), `maxRetries` (0),
`retryBaseDelay`, `maxRetryDelay`, `userAgent` and `batchSize` (50, at least 1).
The timeouts travel with every request, so they also apply through a custom
transport.

A custom transport implements `GoSuccess\WordpressOrg\Http\HttpClient`:

```php
use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Http\HttpClient;
use GoSuccess\WordpressOrg\Http\Request;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\WordpressOrg;

final class MyTransport implements HttpClient
{
    public function send(Request $request): Response
    {
        // Send it with your HTTP stack and return status, body and lower-cased headers.
    }
}

$wordpress = new WordpressOrg(new ClientOptions(maxRetries: 2), new MyTransport());
```

The contract: throw `TransportException` only for network failures (never for HTTP
error statuses, which are returned as a `Response`), return header names in lower
case, and never follow redirects. Honor `$request->timeout` and
`$request->connectTimeout` when they are set.

## Not covered

- `plugins/update-check` and `themes/update-check`: internal, undocumented POST
  endpoints WordPress uses for its own update notices. They carry no information
  about closed plugins, and `info/1.2` already gives the status per slug.
- The older and PHP-serialized endpoint versions (`core/version-check/1.6`,
  `core/credits/1.0`, `core/importers/1.0`, `themes/info/1.0` and `1.1`,
  `plugins/info/1.0`, `secret-key/1.0`): the data is the same as in the newest
  version, and decoding serialized PHP from a foreign source is a needless risk.
- `secret-key/1.1/` (keys only): a subset of `secret-key/1.1/salt/`, which the
  package uses.
- The JSONP `callback` parameter of the download stats.
- Business logic: which versions count as outdated and how closed plugins are
  handled is up to you. This package is a client.

## Documentation and examples

- [docs/plugins-and-themes.md](docs/plugins-and-themes.md),
  [docs/core.md](docs/core.md), [docs/more-endpoints.md](docs/more-endpoints.md)
- [examples/bootstrap.php](examples/bootstrap.php) (shared setup),
  [examples/plugin-status.php](examples/plugin-status.php),
  [examples/core.php](examples/core.php),
  [examples/checksums.php](examples/checksums.php)
- [WordPress.org API on the Codex](https://codex.wordpress.org/WordPress.org_API),
  the source of the endpoint list. The endpoints themselves are implemented in
  [meta.trac](https://meta.trac.wordpress.org/).

The package is not affiliated with WordPress.org.

## Development

```bash
composer check                         # code style, static analysis, unit tests
WORDPRESS_ORG_LIVE=1 composer test:integration  # one request per endpoint
```

The live tests read the real API, read-only, and are skipped unless
`WORDPRESS_ORG_LIVE=1`. Run them sparingly. The unit tests use shortened real
responses from `tests/Fixtures/`, each listed with its source and capture date in
`tests/Fixtures/README.md`.

## License

MIT
