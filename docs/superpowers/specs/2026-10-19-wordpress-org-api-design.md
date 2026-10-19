# wordpress-org-api – design

Status: draft of 2026-10-19, with Paul's answers of the same day. Measurements
in this document were taken on 2026-10-19 unless noted otherwise.

## Why

The gosuccess API talks to providers through its own typed PHP packages
(`bunny-api`, `uptimerobot-api`, `wordfence-api`, …). For wordpress.org it still
uses a small in-house client (gosuccess-api
`app/Integrations/WordpressOrg/WordpressOrg.php`): status per plugin/theme slug
and the latest core version, for the maintenance of customer websites.

`gosuccess/wordpress-org-api` replaces it and covers the **whole public
wordpress.org API** as listed on
<https://codex.wordpress.org/WordPress.org_API> (Paul, 2026-10-19: "alles, was
hier angeboten wird"). Paul does not know yet what else the package will be
used for, so coverage is broad rather than tailored. The API switches to the
package after Paul has published it; that switch is done by the gosuccess-api
session, not here.

## Goals

- A typed, dependency-free client for every endpoint of the Codex page, each in
  its **newest JSON version** (Paul, 2026-10-19), plus the plugin checksums on
  `downloads.wordpress.org` (proposed by the gosuccess-api session, accepted by
  Paul).
- What the API needs today works first-class and is cheap:
  - status of many plugin/theme slugs in few requests: found, latest version,
    closed with date and reason;
  - the latest core version.
- Built like `wordfence-api` (template, `/home/paul/dev/git/wordfence-api`):
  same layout, tooling, CI, README style, exception hierarchy, `ClientOptions`,
  own `HttpClient` interface with a cURL default transport.

## Non-goals

- `plugins/update-check/1.1` and `themes/update-check/1.1` (Paul, 2026-10-19):
  internal, undocumented POST endpoints WordPress uses for its own update
  notices; they do not report closed plugins, and `info/1.2` already gives the
  status per slug. Listed under "Not covered" in the README with this reason.
- Older endpoint versions, in particular the PHP-serialized ones
  (`core/version-check/1.6`, `core/credits/1.0`, `core/importers/1.0`,
  `themes/info/1.0` and `1.1`, `plugins/info/1.0`, `secret-key/1.0`) (Paul,
  2026-10-19). Their data is the same as in the newest version; decoding
  serialized PHP from a foreign source is a needless risk. Listed under "Not
  covered".
- The JSONP `callback` parameter of the download stats.
- No business logic of the API (which versions count as outdated, how closed
  plugins are handled). The package is a client.

## Anti-goals (would be a failure even if it technically works)

- **One broken entry fails a whole batch.** Text from wordpress.org is foreign
  data. A malformed field or entry must never throw for the whole response; the
  field becomes `null`, the entry gets a status (see "Reading").
- **A broken response passes silently.** A `200` with HTML, a JSON array where
  an object belongs, or truncated JSON must throw `SerializationException`,
  never yield an empty or partial result.
- **The package waits for or retries a `429`**, or retries anything by default.
- **The transport follows a redirect.**
- Fixtures or typed shapes that were guessed instead of measured.

## Constraints

- PHP ≥ 8.4, `declare(strict_types=1)` everywhere, no runtime dependency (only
  `ext-curl`, `ext-json`). Dev tools and versions as in the template
  (php-cs-fixer `@PER-CS2x0`, PHPStan `max`, PHPUnit 13), `composer check`
  = cs, stan, unit tests; `composer.lock` committed; CI matrix PHP 8.4/8.5.
- Package `gosuccess/wordpress-org-api`, namespace `GoSuccess\WordpressOrg\`,
  MIT, author "GoSuccess GmbH", homepage
  `https://github.com/GoSuccessHQ/wordpress-org-api`.
- English in code, comments, README, docs and commits; Conventional Commits.
- Paul's rules: commit after every change, **never push**, **no co-author or
  other trailers**; versions and tags are set by Paul only.
- The public API is used without a key; still query it sparingly (also in
  tests and while building).

## Shared infrastructure (from the template)

- `Http\HttpClient::send(Request): Response`, `Request` (method, uri, headers,
  body, timeout, connectTimeout), `Response`, `Method`, `CurlHttpClient` – copied
  from `wordfence-api` with the namespace changed. **No** `StreamingHttpClient`:
  the largest measured response is the core checksums with 270 KB.
  Contract unchanged: only network failures throw (`TransportException`),
  header names lower-case, never follow redirects. Timeouts travel with every
  request, so the API's Laravel transport keeps working as with the other
  packages.
- Exceptions: marker interface `WordpressOrgException`, `ApiException` with
  subclasses by status (400, 401, 403, 404, 409, 422, 429 with `retryAfter`,
  5xx), `TransportException`, `SerializationException`. Classes the API cannot
  produce (401/409/422) may be dropped if the template's
  `ApiException::fromResponse` still maps them sensibly – builder's call.
- `ClientOptions`: `timeout` (default 30.0), `connectTimeout` (10.0),
  `maxRetries` (**0**), `retryBaseDelay`, `maxRetryDelay`, `userAgent`
  (`gosuccess/wordpress-org-api (+https://github.com/GoSuccessHQ/wordpress-org-api)`),
  **`batchSize`** (default 50, must be ≥ 1). Retries only after a network
  failure or a 5xx and only up to `maxRetries`; a `429` is never waited for or
  retried, it throws `RateLimitException`.
- Entry point `WordpressOrg` with `ClientOptions`, optional `HttpClient`, and two
  overridable base URIs (`https://api.wordpress.org`,
  `https://downloads.wordpress.org`) for tests.

## Reading foreign data

Measured on 2026-10-19, types are unreliable (`requires_php` is a string or
`false`; download counts are strings; screenshot URLs are protocol-relative) and
error forms differ per endpoint (see the table below). Therefore:

- **Whole response:** status not as expected, body not JSON, or the top level
  not of the expected kind (object/list) → exception (`ApiException` by status,
  else `SerializationException`). The message names the endpoint and status,
  never the body.
- **Per field:** a missing field or a value of the wrong type becomes `null`
  (lists/maps: empty, documented per property). Unknown values of an enum-like
  field (stability, closure reason, …) stay available as the raw string; a typed
  enum accessor returns `null` for unknown values.
- **Dates** become `DateTimeImmutable` or `null` when unparsable. Formats seen:
  `YYYY-MM-DD` (`closed_date`, `added`), `YYYY-MM-DD h:mma GMT`
  (`last_updated`, e.g. `2026-08-18 11:42pm GMT`), `YYYY-MM-DD HH:MM:SS` (UTC in
  translations; **local time of the event** in events, which also carry unix
  timestamps). Where a source time zone is unknown, keep the raw string next to
  the parsed value.
- **Raw data:** the main models (plugin, theme, offer, pattern, event) keep the
  decoded entry as `array $raw`, so fields wordpress.org adds later are
  reachable without a package update.
- **Not found is a result, not an exception**, wherever an endpoint has a "not
  found" answer (batch entry with `error`, `404` with JSON `error` on a single
  lookup, `{"checksums":false}`, `404` on plugin checksums). The single lookup
  of a **closed** plugin also answers `404` with the full closure object
  (measured with `display-widgets`), so a 404 on these endpoints is parsed, not
  thrown.

## Endpoints and package surface

Class and method names are proposals; the builder may rename them in the style
of the template. Grouped as properties or accessors on `WordpressOrg`:
`plugins`, `themes`, `core`, `translations`, `stats`, `patterns`, `events`,
`secretKeys`.

### Plugins – `plugins/info/1.2`, downloads `plugin-checksums`

- `lookup(list<string> $slugs, ?FieldSelection $fields = minimal): array<string, PluginLookup>`
  – `action=plugin_information&request[slugs]=a,b,…`. Splits the list into
  chunks of `batchSize` (one request each), de-duplicates, keeps the caller's
  order and keys. The minimal default switches off every large field (the
  current client's `SKIPPED_FIELDS` list is the starting point) so that only
  name, slug, version and the closure fields arrive. Measured: 50 slugs in one
  request were fine with all fields (200 KB); a 3-slug request with large
  fields off was 1.5 KB.
- **A failing chunk** (429, 5xx after `maxRetries`, network failure, unusable
  body) makes `lookup()` throw; it never returns partial results, and it never
  marks the slugs of a failed chunk as `Unknown` – a temporary outage must not
  look like vanished plugins. For callers that store results as they come
  (today's `SyncWordpressOrg` saves each chunk), `lookupChunks(...)` is a
  generator with the same arguments that yields one `array<string, PluginLookup>`
  per chunk; an exception is thrown at the failing chunk after the earlier
  chunks were yielded (like the feed in `wordfence-api`).
- `PluginLookup`: `slug`, `status` (`Found`, `Closed`, `NotFound`, `Unknown`),
  `?Plugin $plugin` (when found), `?Closure $closure` (when closed: `?DateTimeImmutable $date`,
  `?string $reason` raw, e.g. `security-issue`, `?string $reasonText`,
  `?string $description`), `?string $error` (raw error text), and a shortcut
  for the version. Entry forms measured:
  - found: object with `version`, `name`, `slug`, …;
  - closed: `error: "closed"`, `closed: true`, `closed_date`, `reason`,
    `reason_text`, `name`, `slug`, `description`;
  - unknown slug: only `error: "Plugin not found."`.
  Classification: `closed === true` or `error === "closed"` → Closed; an object
  with `error` otherwise → NotFound; an object without `error` → Found; entry
  missing or not an object → Unknown.
- **Slugs that cannot be sent safely** (empty, containing a comma, whitespace or
  characters outside `[A-Za-z0-9._-]`) are not sent; they come back as
  `Unknown` instead of failing the batch. How wordpress.org keys a slug sent in
  upper case is measured while building and documented.
- `information(string $slug, ?FieldSelection $fields = null): PluginLookup` –
  `request[slug]=…`, wordpress.org's default fields unless selected; `404` with
  JSON is parsed as above.
- `search(PluginQuery $query): PluginPage` – `action=query_plugins`; query:
  `search`, `tag` (one or more), `author`, `browse`, `page`, `perPage`,
  `fields`, and the other arguments the plugin directory's API accepts (builder
  reads them from its source on meta.trac and measures). `PluginPage`: `page`,
  `pages`, `results`, `list<Plugin> $plugins`. Plus an iterator over all pages
  that stops at `pages` or an empty page; the caller limits it with `break`.
- `Plugin` model: every field seen in `plugin_information` and `query_plugins`
  with all fields switched on (measure once with all fields and record the list
  in the fixture notes), typed and nullable per "Reading". Field names follow
  the response (`requires_php` → `requiresPhp`).
- `checksums(string $slug, string $version): ?PluginChecksums` –
  `https://downloads.wordpress.org/plugin-checksums/{slug}/{version}.json`;
  `plugin`, `version`, `source`, `zip`, `files` as path → `{md5, sha256}`
  (measured: strings for akismet 5.5; if a file ever carries several hashes,
  keep them as a list – builder checks a few plugins). `404` (body is the text
  `Not found`) → `null`.

### Themes – `themes/info/1.2`

- `lookup`, `lookupChunks`, `information`, `search` (+ iterator) as for plugins, with
  `ThemeLookup`, `Theme`, `ThemePage`, `ThemeQuery` (`search`, `tag` list,
  `theme`, `author`, `browse` `popular|featured|updated|new`, `page`,
  `perPage`, `fields`). Unknown slug: `error: "Theme not found"` (batch, `200`)
  or `404` with the same JSON (single). Whether closed themes exist in the
  plugin's closure form is unmeasured; the builder tries to find one and
  otherwise keeps the same classification, documented as unverified.
  Measured: the theme batch returns `sections` by default; `author` is an
  object (`user_nicename`, `profile`, `avatar`, `display_name`, `author`,
  `author_url`) – accept a plain string too.
- `hotTags(?int $number = null): list<ThemeTag>` (`slug`, `name`, `count`) –
  `action=hot_tags`, answer is an object keyed by slug.
- `featureList(): array<string, list<string>>` – `action=feature_list`, group →
  tags.

### Core

- `versionCheck(?VersionCheckQuery $query = null): VersionCheck` –
  `core/version-check/1.7/`; optional `version`, `php`, `mysql`, `locale` (the
  parameters that change the offers; WordPress's telemetry parameters are not
  sent). `VersionCheck`: `list<Offer> $offers`, `list<…> $translations`, plus
  `latest(): ?string` = `offers[0].current` (what the API uses today). `Offer`:
  `response` (`upgrade`, `autoupdate`, …), `download`, `locale`, `packages`
  (`full`, `noContent`, `newBundled`, `partial`, `rollback`; `false` → `null`),
  `current`, `version`, `phpVersion`, `mysqlVersion`, `newBundled`,
  `partialVersion`. Measured: 26 offers, 7.1.3 down to 4.7.38, 14 KB.
- `stableCheck(): array<string, string>` plus a typed view (`Latest`,
  `Outdated`, `Insecure`) – `core/stable-check/1.0/`, version → state.
- `checksums(string $version, string $locale = 'en_US'): ?CoreChecksums` –
  `core/checksums/1.0/`; path → MD5. `{"checksums":false}` → `null`. The README
  repeats wordpress.org's note that MD5 does not detect malicious changes.
  Measured: 6.8.3/en_US = 3,230 files, 270 KB.
- `credits(string $version, ?string $locale = null): Credits` –
  `core/credits/1.1/`; `groups` keyed by id with `name`, `type`, `shuffle`,
  `data`, and top-level `data` (`profiles`, `version`). Group types measured:
  `titles` and `compact` (user → `[name, gravatar hash, user, title?]`),
  `list` (user → name), `libraries` (list of `[name, url]`). Model each type;
  an unknown type keeps its raw data.
- `importers(): list<Importer>` – `core/importers/1.1/`; `id`, `name`,
  `description`, `pluginSlug`.
- `handbookUrl(string $function, string $version): ?string` –
  `core/handbook/1.0/?…&redirect=false`; the body is the plain URL. Anything
  that is not an `https://` URL → `null`.
- `browseHappy(string $userAgent): Browser` – `core/browse-happy/1.1/`, **POST**
  form field `useragent`; `name`, `version`, `platform`, `updateUrl`, `imgSrc`,
  `imgSrcSsl`, `currentVersion`, `upgrade`, `insecure`, `mobile`.
- `serveHappy(string $phpVersion): PhpSupport` – `core/serve-happy/1.0/`;
  `recommendedVersion`, `minimumVersion`, `isSupported`, `isSecure`,
  `isAcceptable`. A missing parameter answers `400` JSON
  (`code`, `message`, `status`) → `BadRequestException`.

### Translations – `translations/{core|plugins|themes}/1.0/`

- `core(string $version)`, `plugin(string $slug, string $version)`,
  `theme(string $slug, string $version)` → `list<Translation>`: `language`,
  `version`, `updated`, `englishName`, `nativeName`, `package`, `iso` (level →
  code), `strings` (core only). wordpress.org falls back to older translation
  sets by itself.

### Stats – `stats/…/1.0/`

- `wordpress()`, `php()`, `mysql()`, `locales()` → `array<string, float>`
  (share in percent, keys as sent: `MariaDB 10.11`, `German (Formal)`).
- `pluginVersions(string $slug)` → `array<string, float>` (includes `other`).
- `pluginDownloads(string $slug, ?int $days = null)` → `array<string, int>`
  date → downloads (values arrive as strings). No `callback`.
- Unknown plugin slug: measure and map to an empty result or `null`.

### Patterns – `patterns/1.0/`

- `search(PatternQuery $query): PatternPage` – `search`, `pattern-categories`
  (ids), `pattern-keywords` (ids), `wp-version`, `locale`, plus the paging
  arguments the endpoint accepts (measure). The answer is a JSON **list** in the
  WordPress REST format; totals come in the headers `x-wp-total` and
  `x-wp-totalpages` (measured: 387 / 194 for `search=button&per_page=2`).
  `Pattern`: `id`, `title` (rendered), `content` (rendered), `patternContent`,
  `categorySlugs`, `keywordSlugs`, `meta` (`wpop_description`,
  `wpop_viewport_width`, `wpop_block_types`, `wpop_locale`, `wpop_wp_version`,
  …), `raw`.

### Events – `events/1.0/`

- `upcoming(EventQuery $query): Events` – `location`, `latitude`+`longitude`,
  `ip`, `country`, `locale`, `timezone`, `number`. `Events`: `sandboxed`,
  `?string $error`, `location` (`description`, `latitude`, `longitude`,
  `country`), `list<Event>` (`type`, `title`, `url`, `meetup`, `meetupUrl`,
  `date` and `endDate` as **local** time strings, `start`/`end` from the unix
  timestamps, `location`). The Codex states that `date`/`end_date` are in the
  event's local time zone.
- The `ip` parameter sends an IP address to wordpress.org; the README says so.

### Secret keys – `secret-key/1.1/salt/`

- `generate(): array<string, string>` – the eight constants (`AUTH_KEY` …
  `NONCE_SALT`) parsed from the `define('NAME', 'value');` lines (values may
  contain quotes and backslashes; parse with a strict pattern, never `eval`).
  A model holding them masks the values in `__debugInfo`. `secret-key/1.1/`
  (keys only) is a subset and not covered separately.

## Error forms measured on 2026-10-19

| Endpoint | Unknown input | Answer |
|---|---|---|
| plugins info batch | unknown slug | `200`, entry `{"error":"Plugin not found."}` |
| plugins info single | unknown slug | `404`, `{"error":"Plugin not found."}` |
| plugins info single | closed slug | `404`, full closure object |
| themes info batch | unknown slug | `200`, entry `{"error":"Theme not found"}` |
| themes info single | unknown slug | `404`, `{"error":"Theme not found"}` |
| core checksums | unknown version | `200`, `{"checksums":false}` |
| plugin checksums | unknown version | `404`, `text/html`, `Not found` |
| serve-happy | no `php_version` | `400`, `{"code":"missing_param",…}` |
| handbook | `redirect=true` | `302` with `location` (package uses `redirect=false`: `200`, plain URL) |

## Tests and measurement

- Unit tests with `MockHttpClient` against **fixtures taken from real
  responses** (`tests/Fixtures/`, shortened where large, e.g. checksums and
  credits); a `tests/Fixtures/README.md` lists each fixture with its source URL
  and measurement date. Every typed shape is measured, never guessed; the
  scratch measurements of this design were not kept and are redone while
  building.
- Required unit cases include: a batch with found, closed, unknown and a
  malformed entry (one field of the wrong type, one entry that is a string) that
  still returns all others; an unparsable `closed_date`; `requires_php: false`;
  chunking into `batchSize` with order and keys kept; an unsendable slug;
  a failing second chunk (`lookup()` throws, `lookupChunks()` yields the first
  chunk, then throws);
  HTML with `200` → `SerializationException`; `404` with closure JSON on a
  single lookup; `429` never retried with `maxRetries > 0`; 5xx retried exactly
  `maxRetries` times; timeouts present on every request.
- `CurlHttpClient` tests against the template's `LocalServer`.
- **Live tests** in `tests/Integration`, testsuite `integration`, skipped unless
  `WORDPRESS_ORG_LIVE=1`; one request per endpoint, read-only, checking the
  shape. Run only when Paul asks.

## Docs

README in the template's style (features, requirements, install, quick start
with the API's use case, each area briefly, error handling, custom transport,
measured sizes with date, "Not covered" with reasons), `docs/` per area,
`examples/`. Link the Codex page and the meta.trac sources; no copied
wordpress.org documentation text.

## For the API's later switch (information for the gosuccess-api session)

- Today's array `{found, version, closed, closed_on, reason}` maps to
  `PluginLookup`/`ThemeLookup`: `found` = status `Found` or `Closed`;
  `closed_on` = `closure->date` (now `DateTimeImmutable`); `reason` raw string.
  The new status `Unknown` (missing or malformed entry, unsendable slug) is
  `found = false` today.
- To keep storing chunk by chunk as `SyncWordpressOrg` does, use
  `lookupChunks()`; a failing chunk throws instead of returning `Unknown`.
- `latestCore()` = `core->versionCheck()->latest()`.
- `batchSize` and `maxRetries` are set by the API; `retryOnRateLimit` does not
  exist (a 429 is never retried).

## Calls I made (to check)

Approved by Paul with the scope playback on 2026-10-19: tolerant reading,
"not found" as a result, plugin checksums, `batchSize` default 50,
`maxRetries` default 0. Not yet seen by Paul in detail:

- A failing chunk throws (`lookup()`), with `lookupChunks()` for storing chunk
  by chunk; never partial results, never `Unknown` for a failed chunk.
- Plugin checksums from `downloads.wordpress.org` are included although they are
  not on the Codex page.
- Tolerant reading instead of the template's strict reading, with `raw` on the
  main models.
- `Unknown` as a fourth lookup status, and unsendable slugs reported as
  `Unknown` instead of an exception.
- Version check sends only `version`, `php`, `mysql`, `locale`.
- Secret keys only from `1.1/salt/`.
- Spec and plan live in this repository under `docs/superpowers/`
  (export-ignored with `docs/`), not in gosuccess-api.

## Left to the builder

- Class names, grouping and file layout within the template's style.
- The exact field lists per model, from measurement.
- The full argument lists of `query_plugins`/`query_themes` and the patterns
  endpoint, from the sources and measurement.
- Which exception subclasses to keep.
