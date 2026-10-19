# wordpress-org-api Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A typed, dependency-free PHP client for the whole public wordpress.org API (newest JSON version of every endpoint on the Codex page, plus plugin checksums) that reads foreign data tolerantly.

**Architecture:** Infrastructure copied from `wordfence-api` (Http layer, exceptions, options, tooling, CI) without the streaming client. One internal `Connection` sends requests (timeouts on every request, retries only for network failures and 5xx up to `maxRetries`, never for 429) and lets each endpoint declare which non-2xx statuses it parses itself. Small internal helpers decode JSON strictly at the top level and read fields tolerantly. One client class per area hangs off the entry class `WordpressOrg`; models are `final readonly` with a non-throwing `fromArray()`.

**Tech Stack:** PHP ^8.4 (local 8.5.4), ext-curl, ext-json; dev: php-cs-fixer ^3.95, phpstan ^2.2 (level max), phpunit ^13.3.

**Spec:** `docs/superpowers/specs/2026-10-19-wordpress-org-api-design.md` (read it with this plan; it holds the measured shapes and error forms).

Repository: `/home/paul/dev/git/wordpress-org-api` (branch `main`, one commit with the spec). Template: `/home/paul/dev/git/wordfence-api`. Commands run on the host (`php`, `composer`).

## Global Constraints

- Package `gosuccess/wordpress-org-api`, namespace `GoSuccess\WordpressOrg\` → `src/`, tests `GoSuccess\WordpressOrg\Tests\` → `tests/`; MIT; author "GoSuccess GmbH"; homepage `https://github.com/GoSuccessHQ/wordpress-org-api`.
- `php ^8.4`, `ext-curl`, `ext-json`; no runtime package; `config.platform.php` `8.4.1`; `composer.lock` committed; `declare(strict_types=1)` in every PHP file.
- Same tooling and scripts as the template: `cs`, `cs-fix`, `stan`, `test` (unit), `test:integration`, `check` (`@cs`, `@stan`, `@test`); CI matrix PHP 8.4/8.5.
- English everywhere; Conventional Commits; commit after every task; **never push; no co-author or any other trailer**; no version tags.
- Base URIs `https://api.wordpress.org` and `https://downloads.wordpress.org`; User-Agent `gosuccess/wordpress-org-api (+https://github.com/GoSuccessHQ/wordpress-org-api)`.
- `ClientOptions` defaults: `timeout` 30.0, `connectTimeout` 10.0, `maxRetries` 0, `retryBaseDelay` 1.0, `maxRetryDelay` 30.0, `batchSize` 50.
- Query wordpress.org sparingly: capture each fixture once with the given `curl`, never in unit tests. Fixture files are shortened real responses; `tests/Fixtures/README.md` lists file, source URL and date (`2026-10-19` or the actual capture date).
- Tolerant reading (spec "Reading foreign data"): whole-response problems throw, field problems become `null`/empty; models never throw.
- No copied wordpress.org documentation text; link the Codex page and the meta.trac sources.

## Review Focus

1. A batch response keyed differently from the request (slug sent as `Akismet`, extra keys, a key missing) must map back to the caller's slugs: compare case-insensitively, ignore extra keys, missing → `Unknown` — test in Task 5.
2. A JSON body `[]` where an object is expected (PHP's `json_encode` of an empty array: empty batch, no hot tags, no translations) is an empty object, not a `SerializationException` — test in Task 2.
3. An empty slug list, or one made only of unsendable slugs, sends no request — test in Task 5.
4. The page iterator stops when `page >= pages`, when `pages` is 0 or missing, and on an empty page, without an extra request — test in Task 6.
5. HTML entities and markup in names and authors (`UpdraftPlus: WP Backup &amp; Migration Plugin`, `author` as `<a href=…>`) are passed through unchanged, never decoded or stripped; the README says so — test in Task 4.

---

### Task 1: Scaffold, Http layer, exceptions, options, connection

**Files:**
- Create: `composer.json`, `composer.lock`, `.gitignore` (`.env`, `/vendor/`, `.phpunit.cache/`, `.php-cs-fixer.cache`), `.gitattributes`, `.php-cs-fixer.php`, `phpstan.neon`, `phpunit.xml` (integration comment: skipped unless `WORDPRESS_ORG_LIVE=1`), `LICENSE`, `.github/workflows/ci.yml` – adapted from the template.
- Create: `src/Http/{HttpClient,CurlHttpClient,Request,Response,Method}.php` – copied, namespace changed, `StreamingHttpClient`/`sendToFile` removed, doc comments no longer speak of an API key (wordpress.org needs none).
- Create: `src/Exception/{WordpressOrgException,ApiException,BadRequestException,ForbiddenException,NotFoundException,RateLimitException,ServerException,TransportException,SerializationException,ErrorDetails}.php` – copied; drop `Authentication`, `Conflict`, `Validation`, `InvalidSignature` (401/409/422 fall to the plain `ApiException`).
- Create: `src/ClientOptions.php`, `src/Internal/Connection.php`, `src/WordpressOrg.php`
- Test: `tests/Support/{MockHttpClient,LocalServer,server.php,PhpExtensions,ExceptionTrace}.php`, `tests/Unit/{RequirementsTest,ClientOptionsTest}.php`, `tests/Unit/Http/CurlHttpClientTest.php`, `tests/Unit/Exception/ApiExceptionTest.php`, `tests/Unit/Internal/ConnectionTest.php`

**Interfaces:**
- Produces: `HttpClient::send(Request): Response`; `Request(Method, string $uri, array<string,string> $headers = [], ?string $body = null, ?float $timeout = null, ?float $connectTimeout = null)`; `Response(int $statusCode, string $body = '', array<string,string> $headers = [], string $reasonPhrase = '')` with `isSuccessful`, `header()`.
- `ClientOptions(float $timeout = 30.0, float $connectTimeout = 10.0, int $maxRetries = 0, float $retryBaseDelay = 1.0, float $maxRetryDelay = 30.0, string $userAgent = self::DEFAULT_USER_AGENT, int $batchSize = 50)`; `batchSize < 1` → `InvalidArgumentException`; other checks as in the template.
- `Internal\Connection` (`@internal`): `__construct(ClientOptions $options, HttpClient $httpClient)`; `get(string $uri, list<int> $parsedStatuses = []): Response`; `postForm(string $uri, array<string,string> $fields, list<int> $parsedStatuses = []): Response`. Every request carries `User-Agent`, `Accept: application/json`, `timeout`, `connectTimeout`. A 2xx or a status in `$parsedStatuses` is returned; any other status throws `ApiException::fromResponse()`. Transport errors and 5xx are retried up to `maxRetries` times with the template's backoff (`min(maxRetryDelay, retryBaseDelay * 2 ** attempt)`), only for idempotent methods; 429 is never retried.
- `Internal\Query::encode(array<string, mixed> $params): string` – `http_build_query` with `PHP_QUERY_RFC3986`; `null` dropped, `true`/`false` → `1`/`0`, nested arrays for `request[...]`.
- `ErrorDetails::parse()` reads wordpress.org's bodies: `{"error": "…"}` and `{"code": "…", "message": "…", "status": …}` (message, `errorCode` = `code`); text/HTML shortened as in the template.
- `WordpressOrg(ClientOptions $options = new ClientOptions(), ?HttpClient $httpClient = null, string $apiBaseUri = 'https://api.wordpress.org', string $downloadsBaseUri = 'https://downloads.wordpress.org')`; later tasks add one `public readonly` client property each, built from the shared `Connection`.

- [ ] **Step 1: Copy and adapt** the files above; `composer install`.
- [ ] **Step 2: Write the failing tests** in `ConnectionTest`: `testEveryRequestCarriesTimeoutsAndUserAgent` (options `timeout: 5.0, connectTimeout: 2.0` → request has both and the UA), `testParsedStatusIsReturned` (`get($uri, [404])` with a 404 returns it), `testOtherErrorStatusThrows` (404 without list → `NotFoundException`), `testRateLimitIsNeverRetried` (`maxRetries: 3`, one 429 with `Retry-After: 120` → `RateLimitException`, `retryAfter` 120, exactly 1 request), `testServerErrorIsRetriedExactlyMaxRetriesTimes` (`maxRetries: 2`, `retryBaseDelay: 0.0`, three 503 → `ServerException` after 3 requests), `testTransportErrorIsRetried` (`TransportException` then 200 → 200, 2 requests), `testNothingIsRetriedByDefault` (one 503 → 1 request), `testPostIsNotRetried` (`maxRetries: 2`, 503 → 1 request), `testPostFormSendsUrlEncodedBody` (`content-type: application/x-www-form-urlencoded`, body `useragent=Mozilla%2F5.0`); `ApiExceptionTest::testReadsWordpressOrgErrorBodies` (`{"error":"Plugin not found."}` → message contains it; `{"code":"missing_param","message":"Missing parameter: php_version","status":400}` → `errorCode` `missing_param`); `ClientOptionsTest::testBatchSizeMustBePositive`.
- [ ] **Step 3: Run** `vendor/bin/phpunit --testsuite unit` — Expected: FAIL (classes missing).
- [ ] **Step 4: Implement** `Connection`, `Query`, `ClientOptions`, `ErrorDetails`, `WordpressOrg` (no client properties yet).
- [ ] **Step 5: Run** `composer check` — Expected: cs clean, phpstan `[OK] No errors`, unit tests green.
- [ ] **Step 6: Commit** `chore: scaffold package from wordfence-api`.

### Task 2: JSON and tolerant reading helpers

**Files:**
- Create: `src/Internal/Json.php`, `src/Internal/Read.php`
- Test: `tests/Unit/Internal/JsonTest.php`, `tests/Unit/Internal/ReadTest.php`

**Interfaces:**
- `Json::object(Response $response, string $endpoint): array<string, mixed>` – first non-whitespace byte must be `{`, or the body must be exactly `[]` (empty object, see Review Focus 2); `Json::list(Response $response, string $endpoint): list<mixed>` – first byte `[`; both decode with `JSON_THROW_ON_ERROR` and throw `SerializationException` with `"{$endpoint}: …"` and the status, never the body.
- `Read` (all static, never throw): `string(mixed): ?string` (string as is; int/float → string; else null); `int(mixed): ?int` (int; string of digits with optional `-`); `float(mixed): ?float` (int, float, numeric string); `bool(mixed): ?bool` (bool; `0`/`1`, `"0"`/`"1"`); `array(mixed): array<array-key, mixed>` (`[]` if not array); `stringList(mixed): list<string>` (string items of a list or map, others skipped); `stringMap(mixed): array<string, string>` (string values, keys cast to string); `date(mixed, string ...$formats): ?DateTimeImmutable` (UTC, `!`-prefixed `createFromFormat`, accepted only if formatting back with the same format gives the input, so `2026-02-30` is null).
- Format constants on `Read`: `DATE = 'Y-m-d'`, `DATETIME = 'Y-m-d H:i:s'`, `UPDATED = 'Y-m-d g:ia \G\M\T'`.

- [ ] **Step 1: Write the failing tests**: `testObjectAcceptsEmptyArrayAsEmptyObject` (`[]` → `[]`), `testObjectRejectsList` (`[1]`), `testObjectRejectsHtml` (`<!DOCTYPE html>` with 200), `testObjectRejectsTruncatedJson` (`{"a":`), `testMessageNamesEndpointNotBody`; `ReadTest` data providers: `string(false)` null, `string(5)` `'5'`; `int('25688')` 25688, `int('1.5')` null; `float('0.05')` 0.05; `bool(1)` true, `bool('yes')` null; `stringList(['a', 1, ['x'], 'b'])` `['a', 'b']`; `date('2026-08-18 11:42pm GMT', Read::UPDATED)` → `2026-08-18T23:42:00+00:00`; `date('2021-01-30', Read::DATE)` → `2021-01-30T00:00:00+00:00`; `date('2026-02-30', Read::DATE)` null; `date(false, Read::DATE)` null; `date('2026-11-03 19:00:00', Read::DATETIME)` → that time in UTC.
- [ ] **Step 2: Run** `vendor/bin/phpunit tests/Unit/Internal` — Expected: FAIL.
- [ ] **Step 3: Implement** `Json` and `Read`.
- [ ] **Step 4: Run** the tests — Expected: PASS; `composer check` green.
- [ ] **Step 5: Commit** `feat: decode responses and read foreign fields tolerantly`.

### Task 3: Directory lookups – fields, status, closure, classification

Shared by plugins and themes (`info/1.2`).

**Files:**
- Create: `src/Directory/{FieldSelection,LookupStatus,ClosureDetails,Entry}.php` (`Entry` marked `@internal`)
- Test: `tests/Unit/Directory/EntryTest.php`, `tests/Unit/Directory/FieldSelectionTest.php`

**Interfaces:**
- `enum LookupStatus { Found, Closed, NotFound, Unknown }`.
- `ClosureDetails`: `?DateTimeImmutable $date`, `?string $reason` (raw, e.g. `security-issue`), `?string $reasonText`, `?string $description`; `fromArray(array<string,mixed>)`.
- `FieldSelection`: `__construct(array<string, bool> $fields = [])`, `with(string $field, bool $include): self`, `toRequest(): array<string, bool>` (→ `request[fields][x]=1|0`); `FieldSelection::minimalPlugin()` and `minimalTheme()` switch off: `sections`, `description`, `short_description`, `versions`, `screenshots`, `reviews`, `banners`, `icons`, `contributors`, `ratings`, `tags`, `donate_link`, `compatibility`, `downloaded`, `active_installs`, `homepage`, `added`, `last_updated`, `requires`, `requires_php`, `tested`, `author`, `author_profile`, `download_link`, `upgrade_notice`, `preview_url`, `screenshot_url`, `theme_url`, `reviews_url` (the current client's list).
- `Internal` helper `Entry::classify(mixed $entry): array{LookupStatus, ?ClosureDetails, ?string}` – rules of the spec: `closed === true` or `error === "closed"` → Closed with details; array with `error` → NotFound with the error text; array without `error` → Found; anything else → Unknown.
- `Entry::isSendable(string $slug): bool` – non-empty and matches `/^[A-Za-z0-9._-]+$/D`.

- [ ] **Step 1: Write the failing tests**: `testClassifiesFound`, `testClassifiesClosed` (the `display-widgets` entry from the spec: date `2021-01-30`, reason `security-issue`, reasonText `Security Issue`), `testClosedWithUnparsableDateKeepsTheRest` (`closed_date: "2021-13-45"` → Closed, date null, reason kept), `testClassifiesNotFound` (`{"error":"Plugin not found."}` → error text kept), `testStringEntryIsUnknown`, `testNullEntryIsUnknown`, `testSendableSlugs` (`akismet`, `wp-file_manager.2` true; `''`, `a,b`, `a b`, `ä` false), `testMinimalSelectionSwitchesOffLargeFields` (`toRequest()['sections'] === false`).
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** — Expected: PASS; `composer check` green.
- [ ] **Step 5: Commit** `feat(directory): classify lookups of plugins and themes`.

### Task 4: Plugin model

**Files:**
- Create: `src/Plugins/{Plugin,PluginLookup}.php`, fixtures `tests/Fixtures/plugins/{information-akismet,information-closed,batch,query}.json`, `tests/Fixtures/README.md`
- Test: `tests/Unit/Plugins/PluginTest.php`

**Interfaces:**
- Capture (once): `curl -g -sS -A "$UA" 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=akismet&request[fields][<every field off by default>]=1'` (all fields on: read the default field list from the plugin directory API source on meta.trac, `wordpress.org/public_html/wp-content/plugins/plugin-directory/api/`), the closed single lookup of `display-widgets`, a batch `akismet,display-widgets,no-such-plugin-zzzq` with the minimal selection, and `query_plugins` with `per_page=2`. Shorten `sections`/`versions`/`screenshots` to two entries each.
- `Plugin::fromArray(array<string, mixed> $data): self`, readonly properties (all nullable or empty per spec): `?string $name, $slug, $version, $author, $authorProfile, $requires, $tested, $requiresPhp, $homepage, $shortDescription, $description, $downloadLink, $donateLink, $businessModel, $repositoryUrl, $commercialSupportUrl, $supportUrl, $previewLink`; `?int $rating, $numRatings, $supportThreads, $supportThreadsResolved, $activeInstalls, $downloaded`; `array<int,int> $ratings` (star → count); `?DateTimeImmutable $lastUpdated` (`Read::UPDATED`), `$added` (`Read::DATE`); `list<string> $requiresPlugins`; `array<string,string> $sections, $tags, $versions, $banners, $icons, $upgradeNotice`; `array<array-key,mixed> $screenshots, $contributors, $compatibility`; `array<string,mixed> $raw`. Any further field found in the all-fields capture gets a property of the same kind; record the field list in the fixture README.
- `PluginLookup`: `string $slug`, `LookupStatus $status`, `?Plugin $plugin` (Found only), `?ClosureDetails $closure` (Closed only), `?string $error`; `version(): ?string`; `static fromEntry(string $slug, mixed $entry): self` (uses `Entry::classify`).

- [ ] **Step 1: Capture the fixtures** and write `tests/Fixtures/README.md`.
- [ ] **Step 2: Write the failing tests**: `testReadsTheFullFixture` (every property from the fixture's values, `lastUpdated` = `2026-08-18T23:42:00+00:00` if unchanged at capture time, else the captured value), `testRequiresPhpFalseIsNull`, `testWrongTypesBecomeNull` (`version: ["x"]`, `rating: "high"`, `tags: "x"` → null/null/`[]`, other fields intact), `testEntitiesAndMarkupPassThrough` (name `UpdraftPlus: WP Backup &amp; Migration Plugin` and an `<a href=…>` author unchanged), `testRawKeepsUnknownFields` (`{"new_field": 1}` → `raw['new_field'] === 1`), `testLookupFromClosedSingleResponse` (fixture `information-closed`: status Closed, `plugin` null, closure date `2021-01-30`).
- [ ] **Step 3: Run** `vendor/bin/phpunit tests/Unit/Plugins` — Expected: FAIL.
- [ ] **Step 4: Implement.**
- [ ] **Step 5: Run** — Expected: PASS; `composer check` green.
- [ ] **Step 6: Commit** `feat(plugins): plugin model and lookup result`.

### Task 5: Plugins client – lookup, lookupChunks, information

**Files:**
- Create: `src/Directory/DirectoryClient.php` (internal, shared with themes), `src/Plugins/Plugins.php`
- Modify: `src/WordpressOrg.php` (`public readonly Plugins $plugins`)
- Test: `tests/Unit/Plugins/PluginsLookupTest.php`

**Interfaces:**
- Consumes: Task 1 `Connection`, `Query`; Task 2 `Json`; Task 3 `Entry`, `FieldSelection`; Task 4 `PluginLookup`.
- `Plugins::lookup(list<string> $slugs, ?FieldSelection $fields = null): array<string, PluginLookup>` (null → `FieldSelection::minimalPlugin()`); `Plugins::lookupChunks(list<string> $slugs, ?FieldSelection $fields = null): Generator<int, array<string, PluginLookup>>`; `lookup()` = merge of all chunks, throwing on the first failing chunk.
- `Plugins::information(string $slug, ?FieldSelection $fields = null): PluginLookup` – `request[slug]`, parsed statuses `[404]`; an unsendable slug → `Unknown` without a request.
- Request: `GET {api}/plugins/info/1.2/?action=plugin_information&request[slugs]=a,b&request[fields][…]=0`; chunks of `batchSize` after de-duplication; unsendable slugs never sent and returned as `Unknown`; result keyed and ordered as the caller's list; the response is matched case-insensitively (Review Focus 1), and how wordpress.org keys an upper-case slug is measured once and noted in the fixture README.

- [ ] **Step 1: Write the failing tests**: `testOneRequestPerChunk` (`batchSize: 2`, 5 slugs → 3 requests with `request[slugs]` = `a,b` / `c,d` / `e`), `testDuplicatesAreSentOnce`, `testResultKeepsCallerOrderAndKeys`, `testBatchFixtureGivesFoundClosedNotFound`, `testMalformedEntryDoesNotFailTheBatch` (`{"a":{"version":"1.0"},"b":"oops","c":{"version":["x"]}}` → a Found `1.0`, b Unknown, c Found with version null), `testMissingAndDifferentlyCasedKeys` (sent `Akismet,gone`; response `{"akismet":{…},"extra":{…}}` → `Akismet` Found, `gone` Unknown, no `extra`), `testUnsendableSlugIsUnknownAndNotSent`, `testNoRequestForEmptyOrUnsendableList`, `testEmptyArrayBodyIsEmptyResult` (`[]` → all Unknown), `testFailingSecondChunkThrowsFromLookup` (200 then 503 → `ServerException`), `testLookupChunksYieldsFirstChunkThenThrows`, `testHtmlWith200Throws`, `testInformationParses404` (closure fixture → Closed; `{"error":"Plugin not found."}` → NotFound), `testInformationOtherStatusThrows` (500 → `ServerException`), `testMinimalFieldsAreSent` (`request[fields][sections]=0` in the URI).
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement** `DirectoryClient` (chunking, request building, response matching; parameterized by kind `plugins|themes`, action and an entry factory) and `Plugins`.
- [ ] **Step 4: Run** — Expected: PASS; `composer check` green.
- [ ] **Step 5: Commit** `feat(plugins): look up many slugs in batches`.

### Task 6: Plugins client – search, all pages, checksums

**Files:**
- Create: `src/Plugins/{PluginQuery,PluginPage,PluginChecksums,FileChecksum}.php`, fixture `tests/Fixtures/plugins/checksums-akismet-5.5.json`
- Modify: `src/Plugins/Plugins.php`, `src/Directory/DirectoryClient.php`
- Test: `tests/Unit/Plugins/PluginsSearchTest.php`, `tests/Unit/Plugins/PluginChecksumsTest.php`

**Interfaces:**
- `PluginQuery(?string $search = null, list<string> $tags = [], ?string $author = null, ?string $browse = null, ?string $user = null, int $page = 1, ?int $perPage = null, ?FieldSelection $fields = null)` plus every other argument the plugin directory source accepts (read it; add as named nullable parameters). How several tags are encoded (`request[tag][]` or repeated) is measured once with two tags and noted.
- `PluginPage`: `int $page, int $pages, int $results` (0 when unreadable), `list<Plugin> $plugins`; non-object items skipped.
- `Plugins::search(PluginQuery $query): PluginPage`; `Plugins::searchAll(PluginQuery $query): Generator<int, Plugin>` from `$query->page` on.
- `Plugins::checksums(string $slug, string $version): ?PluginChecksums` – `GET {downloads}/plugin-checksums/{slug}/{version}.json`, parsed statuses `[404]` → `null`; unsendable slug or a version outside `/^[A-Za-z0-9._-]+$/D` → `null` without a request. `PluginChecksums`: `?string $plugin, $version, $source, $zip`, `array<string, FileChecksum> $files`; `FileChecksum`: `list<string> $md5`, `list<string> $sha256` (a single string becomes a one-element list; check two or three popular plugins once for the list form and note the result).

- [ ] **Step 1: Capture** the checksums fixture (shorten `files` to five entries) and test the tag encoding once.
- [ ] **Step 2: Write the failing tests**: `testSearchSendsQuery` (`search=backup`, `per_page=2`, `page=3` in `request[...]`), `testSearchReadsInfoAndPlugins` (query fixture), `testSearchAllStopsAtLastPage` (pages 1/2 then 2/2 → 2 requests), `testSearchAllStopsWithoutPagesOrOnEmptyPage` (`pages` 0 → 1 request; empty `plugins` → 1 request), `testChecksumsReadFixture`, `testChecksumsNotFoundIsNull` (404 `text/html` `Not found`), `testChecksumsUnsafeInputSendsNothing` (`../x`), `testSingleHashBecomesList`.
- [ ] **Step 3: Run** — Expected: FAIL.
- [ ] **Step 4: Implement.**
- [ ] **Step 5: Run** — Expected: PASS; `composer check` green.
- [ ] **Step 6: Commit** `feat(plugins): search with paging and plugin checksums`.

### Task 7: Themes

**Files:**
- Create: `src/Themes/{Themes,Theme,ThemeAuthor,ThemeLookup,ThemeQuery,ThemePage,ThemeTag}.php`, fixtures `tests/Fixtures/themes/{information,batch,query,hot-tags,feature-list}.json`
- Modify: `src/WordpressOrg.php` (`public readonly Themes $themes`)
- Test: `tests/Unit/Themes/ThemeTest.php`, `tests/Unit/Themes/ThemesTest.php`

**Interfaces:**
- Consumes: Task 3, Task 5 `DirectoryClient`.
- `Themes::lookup`, `lookupChunks`, `information`, `search`, `searchAll` – same signatures as plugins with `ThemeLookup`, `ThemeQuery(?string $search = null, list<string> $tags = [], ?string $theme = null, ?string $author = null, ?string $browse = null, int $page = 1, ?int $perPage = null, ?FieldSelection $fields = null)`, `ThemePage` (`list<Theme> $themes`).
- `Theme::fromArray()`: `?string $name, $slug, $version, $previewUrl, $screenshotUrl, $reviewsUrl, $homepage, $description, $downloadLink, $requires, $requiresPhp, $externalSupportUrl, $externalRepositoryUrl, $template, $parent`; `?ThemeAuthor $author` (object → fields `userNicename, profile, avatar, displayName, authorUrl`, plain string → `displayName`); `?int $rating, $numRatings, $downloaded, $activeInstalls`; `array<int,int> $ratings`; `?DateTimeImmutable $lastUpdated` (`Read::DATE`), `$lastUpdatedTime`, `$creationTime` (`Read::DATETIME`); `?bool $isCommercial, $isCommunity`; `array<string,string> $sections, $tags, $versions`; `raw`. Any further field of the all-fields capture as in Task 4. `screenshotUrl` stays as sent (protocol-relative).
- `Themes::hotTags(?int $number = null): list<ThemeTag>` (`?string $slug, $name`, `?int $count`); `Themes::featureList(): array<string, list<string>>`.
- Closed themes: search once for a closed theme (e.g. via the theme directory's "closed" notice or a known suspended slug); record the outcome in the fixture README; the classification stays as in Task 3 either way.

- [ ] **Step 1: Capture** the fixtures (`theme_information` for `twentytwentyfive` with all fields, batch `twentytwentyfive,no-such-theme-zzzq`, `query_themes` `per_page=2`, `hot_tags` `number=3`, `feature_list`).
- [ ] **Step 2: Write the failing tests**: `testReadsTheFullFixture`, `testAuthorAsString`, `testBatchFoundAndNotFound` (`Theme not found`), `testInformation404IsNotFound`, `testHotTags` (3 tags, slug/name/count), `testHotTagsEmptyArray` (`[]` → `[]`), `testFeatureList` (group `Layout` contains `grid-layout`), `testSearchAllStopsAtLastPage`.
- [ ] **Step 3: Run** — Expected: FAIL.
- [ ] **Step 4: Implement.**
- [ ] **Step 5: Run** — Expected: PASS; `composer check` green.
- [ ] **Step 6: Commit** `feat(themes): lookups, search, tags and features`.

### Task 8: Core – version check, stable check, checksums

**Files:**
- Create: `src/Core/{Core,VersionCheck,VersionCheckQuery,Offer,OfferPackages,Stability,CoreChecksums}.php`, fixtures `tests/Fixtures/core/{version-check,stable-check,checksums}.json`
- Modify: `src/WordpressOrg.php` (`public readonly Core $core`)
- Test: `tests/Unit/Core/VersionCheckTest.php`, `tests/Unit/Core/StableCheckTest.php`, `tests/Unit/Core/CoreChecksumsTest.php`

**Interfaces:**
- `Core::versionCheck(?VersionCheckQuery $query = null): VersionCheck` – `core/version-check/1.7/`; `VersionCheckQuery(?string $version = null, ?string $php = null, ?string $mysql = null, ?string $locale = null)`, nothing else sent.
- `VersionCheck`: `list<Offer> $offers`, `list<array<string,mixed>> $translations`, `latest(): ?string` (= `offers[0]->current`). `Offer`: `?string $response, $download, $locale, $current, $version, $phpVersion, $mysqlVersion, $newBundled, $partialVersion`; `OfferPackages $packages` (`?string $full, $noContent, $newBundled, $partial, $rollback`; `false` → null); `raw`.
- `Core::stableCheck(): array<string, string>`; `Core::stability(string $version): ?Stability` (`enum Stability: string { Latest = 'latest'; Outdated = 'outdated'; Insecure = 'insecure' }`, `tryFrom`).
- `Core::checksums(string $version, string $locale = 'en_US'): ?CoreChecksums` – `checksums: false` → null; `CoreChecksums`: `string $version`, `string $locale`, `array<string, string> $files` (path → MD5). If the endpoint keys checksums by version for some inputs, measure and handle (note in README).

- [ ] **Step 1: Capture** the three fixtures (checksums of 6.8.3/en_US shortened to five files).
- [ ] **Step 2: Write the failing tests**: `testLatestIsFirstOffersCurrent` (`7.1.3` from the fixture as captured), `testReadsAllOffers` (count as captured, 26 on 2026-10-19), `testPartialFalseIsNull`, `testLatestIsNullWithoutOffers` (`{"offers":[]}`), `testOffersNotAListThrows` (`{"offers":"x"}` → `SerializationException`, the shape API relies on), `testQuerySendsOnlyTheFourParameters`, `testStability` (`7.1.3` Latest, `1.0.2` Insecure, `9.9` null, unknown value `"weird"` → null while `stableCheck()` keeps it), `testChecksums`, `testUnknownVersionIsNull` (`{"checksums":false}`).
- [ ] **Step 3: Run** — Expected: FAIL.
- [ ] **Step 4: Implement.**
- [ ] **Step 5: Run** — Expected: PASS; `composer check` green.
- [ ] **Step 6: Commit** `feat(core): version check, stability and checksums`.

### Task 9: Core – credits, importers, handbook, Browse Happy, Serve Happy

**Files:**
- Create: `src/Core/{Credits,CreditGroup,Contributor,Library,Importer,Browser,PhpSupport}.php`, fixtures `tests/Fixtures/core/{credits,importers,browse-happy,serve-happy}.json`
- Modify: `src/Core/Core.php`
- Test: `tests/Unit/Core/CreditsTest.php`, `tests/Unit/Core/CoreServicesTest.php`

**Interfaces:**
- `Core::credits(string $version, ?string $locale = null): Credits` – `Credits`: `array<string, CreditGroup> $groups`, `array<string,mixed> $data`; `CreditGroup`: `?string $name, $type`, `?bool $shuffle`, `list<Contributor> $contributors` (types `titles`, `compact`: `[name, gravatarHash, user, title?]`; `list`: user → name), `list<Library> $libraries` (`[name, url]`), `array<array-key,mixed> $raw` (unknown types).
- `Core::importers(): list<Importer>` (`?string $id, $name, $description, $pluginSlug`; `id` from the key when `importer-id` is missing). Whether `importers/1.1` takes a locale is checked once against its source; if so, add `?string $locale = null`.
- `Core::handbookUrl(string $function, string $version): ?string` – `redirect=false`, body trimmed; only a string starting with `https://` without whitespace is returned.
- `Core::browseHappy(string $userAgent): Browser` – `postForm(…/core/browse-happy/1.1/, ['useragent' => …])`; `?string $name, $version, $platform, $updateUrl, $imgSrc, $imgSrcSsl, $currentVersion`; `?bool $upgrade, $insecure, $mobile`.
- `Core::serveHappy(string $phpVersion): PhpSupport` – `?string $recommendedVersion, $minimumVersion`; `?bool $isSupported, $isSecure, $isAcceptable`; 400 → `BadRequestException` (from `Connection`).

- [ ] **Step 1: Capture** the four fixtures (credits for 7.1, shortened to two members per group).
- [ ] **Step 2: Write the failing tests**: `testCreditGroupsByType` (one group each of `titles`, `list`, `compact`, `libraries`), `testUnknownGroupTypeKeepsRaw`, `testImporters`, `testHandbookUrl` (body `https://developer.wordpress.org/reference/functions/get_post/` → that URL; `<html>` → null; request has `redirect=false`), `testBrowseHappyPostsUserAgent`, `testServeHappy` (`7.4.0` → recommended `8.3`, isSecure false), `testServeHappyBadRequest` (400 body from the spec → `BadRequestException`, `errorCode` `missing_param`).
- [ ] **Step 3: Run** — Expected: FAIL.
- [ ] **Step 4: Implement.**
- [ ] **Step 5: Run** — Expected: PASS; `composer check` green.
- [ ] **Step 6: Commit** `feat(core): credits, importers, handbook and happy checks`.

### Task 10: Translations and stats

**Files:**
- Create: `src/Translations/{Translations,Translation}.php`, `src/Stats/Stats.php`, fixtures `tests/Fixtures/translations/{core,plugin}.json`, `tests/Fixtures/stats/{wordpress,php,mysql,locale,plugin,downloads}.json`
- Modify: `src/WordpressOrg.php` (`translations`, `stats`)
- Test: `tests/Unit/Translations/TranslationsTest.php`, `tests/Unit/Stats/StatsTest.php`

**Interfaces:**
- `Translations::core(string $version)`, `plugin(string $slug, string $version)`, `theme(string $slug, string $version)` → `list<Translation>`; `Translation`: `?string $language, $version, $englishName, $nativeName, $package`; `?DateTimeImmutable $updated` (`Read::DATETIME`, UTC); `array<string,string> $iso`, `array<string,string> $strings`. Unknown slug: measure once and map to `[]`.
- `Stats::wordpress()`, `php()`, `mysql()`, `locales()`, `pluginVersions(string $slug)` → `array<string, float>`; `Stats::pluginDownloads(string $slug, ?int $days = null): array<string, int>` (`limit`, never `callback`). Unknown plugin slug: measure once; map to `[]`.

- [ ] **Step 1: Capture** the fixtures (translations shortened to two entries) and the two unknown-slug answers.
- [ ] **Step 2: Write the failing tests**: `testCoreTranslations` (`language`, `updated` parsed, `iso[1]`, `strings['continue']`), `testPluginTranslationsHaveNoStrings`, `testShares` (`php()['7.4']` 16.19 as captured), `testKeysAreKeptAsSent` (`MariaDB 10.11`), `testDownloadsAreInts` (`"25688"` → 25688), `testDownloadsSendLimitWithoutCallback`, `testUnknownSlugIsEmpty`.
- [ ] **Step 3: Run** — Expected: FAIL.
- [ ] **Step 4: Implement.**
- [ ] **Step 5: Run** — Expected: PASS; `composer check` green.
- [ ] **Step 6: Commit** `feat: translations and stats`.

### Task 11: Patterns, events, secret keys

**Files:**
- Create: `src/Patterns/{Patterns,Pattern,PatternQuery,PatternPage}.php`, `src/Events/{Events,EventQuery,EventList,Event,EventLocation}.php`, `src/SecretKeys/{SecretKeys,Salts}.php`, fixtures `tests/Fixtures/patterns/search.json` (+ captured headers in the README), `tests/Fixtures/events/dresden.json`, `tests/Fixtures/secret-keys/salt.txt` (**own** made-up values in the real format, never real generated keys)
- Modify: `src/WordpressOrg.php` (`patterns`, `events`, `secretKeys`)
- Test: `tests/Unit/Patterns/PatternsTest.php`, `tests/Unit/Events/EventsTest.php`, `tests/Unit/SecretKeys/SecretKeysTest.php`

**Interfaces:**
- `PatternQuery(?string $search = null, list<int> $categories = [], list<int> $keywords = [], ?string $wpVersion = null, ?string $locale = null, ?int $page = null, ?int $perPage = null)` → `pattern-categories`, `pattern-keywords`, `wp-version`, `locale`, `page`, `per_page` (paging names measured once). `Patterns::search(PatternQuery): PatternPage` (`list<Pattern> $patterns`, `?int $total`, `?int $totalPages` from `x-wp-total`/`x-wp-totalpages`). `Pattern`: `?int $id`, `?string $title` (`title.rendered`), `$content` (`content.rendered`), `$patternContent`; `list<string> $categorySlugs, $keywordSlugs`; `array<string,mixed> $meta`; `raw`.
- `EventQuery(?string $location = null, ?float $latitude = null, ?float $longitude = null, ?string $ip = null, ?string $country = null, ?string $locale = null, ?string $timezone = null, ?int $number = null)`; latitude without longitude → `InvalidArgumentException`. `Events::upcoming(EventQuery): EventList` (`?bool $sandboxed`, `?string $error`, `?EventLocation $location`, `list<Event> $events`). `Event`: `?string $type, $title, $url, $meetup, $meetupUrl, $date, $endDate` (local time strings as sent), `?DateTimeImmutable $start, $end` (from `start_unix_timestamp`/`end_unix_timestamp`, UTC), `?EventLocation $location`, `raw`. `EventLocation`: `?string $description` (or `location`), `$country`, `?float $latitude, $longitude` (strings accepted).
- `SecretKeys::generate(): Salts` – `GET {api}/secret-key/1.1/salt/`, plain text; each line must match `/^define\(\s*'([A-Z_]+)',\s*'((?:[^'\\\\]|\\\\.)*)'\s*\);$/` (unescape `\'` and `\\`); fewer than the eight names `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT` → `SerializationException`. `Salts`: `array<string,string> $values` (`#[SensitiveParameter]` in the constructor), `get(string $name): ?string`, `__debugInfo()` masks every value. Never `eval`.

- [ ] **Step 1: Capture** the pattern (`search=button&per_page=2`, shorten `content`) and event fixtures; write the secret-key fixture by hand (a value containing `\'` and `\\`).
- [ ] **Step 2: Write the failing tests**: `testPatternsReadListAndTotals` (headers 387/194 as captured), `testPatternsSendQuery` (`pattern-categories=2`), `testEventsReadLocalDatesAndTimestamps` (`date` string kept, `start` from the timestamp), `testEventsError` (`{"error":"no_location_available", …}` style body as measured once → `error` set, `events` `[]`), `testLatitudeNeedsLongitude`, `testSecretKeysParseEightValues` (escaped quote and backslash restored), `testSecretKeysRejectMissingLine`, `testSaltsDebugInfoMasksValues`.
- [ ] **Step 3: Run** — Expected: FAIL.
- [ ] **Step 4: Implement.**
- [ ] **Step 5: Run** — Expected: PASS; `composer check` green.
- [ ] **Step 6: Commit** `feat: patterns, events and secret keys`.

### Task 12: Live tests, README, docs, examples

**Files:**
- Create: `tests/Integration/{PluginsLiveTest,ThemesLiveTest,CoreLiveTest,OtherEndpointsLiveTest}.php`, `README.md`, `docs/{README,plugins-and-themes,core,more-endpoints}.md`, `examples/{bootstrap,plugin-status,core,checksums}.php`

**Interfaces:**
- Consumes: everything above.
- Live tests skipped unless `getenv('WORDPRESS_ORG_LIVE') === '1'`; one request per endpoint, read-only; assert shapes (e.g. lookup of `akismet,display-widgets,no-such-plugin-zzzq` gives Found/Closed/NotFound; `latest()` matches `/^\d+\.\d+(\.\d+)?$/`; checksums of the latest version non-empty). Measure the largest batch that still succeeds with the minimal fields (try 50, 100, 200 once) and put the result with date in the README.

- [ ] **Step 1: Write** the live tests.
- [ ] **Step 2: Ask Paul** before the live run (live tests only on request), then run `WORDPRESS_ORG_LIVE=1 composer test:integration` once — Expected: PASS. Without his yes, skip the run and the batch measurement and say so in the report.
- [ ] **Step 3: Write** README in the template's style: features, requirements, install, quick start with the API's use case (`lookupChunks`, `latest()`), each area, tolerant reading and `raw`, entities passed through, error handling (`429` never retried, `maxRetries` 0), custom transport, measured sizes with date, MD5 note for core checksums, the `ip` note for events, "Not covered" (`update-check` and old/serialized versions, with the spec's reasons; `secret-key/1.1/` keys-only form); `docs/*` and examples.
- [ ] **Step 4: Run** `composer check` — Expected: green.
- [ ] **Step 5: Commit** `docs: readme, docs, examples and live tests`.
