# Plugins and themes

`$wordpress->plugins` (`plugins/info/1.2`) and `$wordpress->themes`
(`themes/info/1.2`) work the same way. Examples use plugins.

## Status of many slugs

```php
$results = $wordpress->plugins->lookup(['akismet', 'display-widgets', 'no-such-plugin-zzzq']);

$results['akismet']->status;            // LookupStatus::Found
$results['akismet']->version();          // latest version, or null
$results['display-widgets']->status;     // LookupStatus::Closed
$results['display-widgets']->closure;    // ClosureDetails: date, reason, reasonText, description
$results['no-such-plugin-zzzq']->status; // LookupStatus::NotFound
```

- One request per `batchSize` slugs (default 50). The result has one entry per
  distinct slug, in your order and spelling.
- `Found`: an entry without `error`. `Closed`: `closed: true` or `error: "closed"`.
  `NotFound`: any other `error` (for example "Plugin not found."). `Unknown`:
  missing or malformed entry, or a slug that cannot be sent (empty, or characters
  outside `[A-Za-z0-9._-]`). Such slugs cause no request; a list made only of them
  sends none at all.
- Slugs are sent lower-cased (an upper-case slug is answered "Invalid plugin
  slug."); response keys are matched case-insensitively, extra keys are ignored.
- All-digit slugs become int keys in PHP arrays.
- The default `FieldSelection::minimalPlugin()` (`minimalTheme()`) turns off the
  large fields. Use `new FieldSelection()` for wordpress.org's defaults, or
  `->with('sections', true)` to add one.
- Measured on 2026-10-19: 50 slugs with all fields worked (about 200 KB). A larger
  maximum is unmeasured.

## Storing chunk by chunk

```php
foreach ($wordpress->plugins->lookupChunks($slugs) as $index => $results) {
    store($results);
}
```

If a chunk fails, `lookup()` throws and returns nothing; `lookupChunks()` has
yielded the earlier chunks and throws at the failing one. The slugs of a failed
chunk are never reported as `Unknown`.

## One slug

`information($slug)` asks for one entry with wordpress.org's default fields. The
`404` answers for unknown and closed slugs are parsed, not thrown: you get a
`PluginLookup` with status `NotFound` or `Closed`.

## Search

```php
$page = $wordpress->plugins->search(new PluginQuery(search: 'backup', tags: ['seo'], perPage: 30));
$page->page; $page->pages; $page->results; $page->plugins;

foreach ($wordpress->plugins->searchAll(new PluginQuery(browse: 'popular')) as $plugin) {
    if ($done) { break; }
}
```

`PluginQuery`: `search`, `tags`, `author`, `browse`, `user`, `page`, `perPage`,
`fields`. `ThemeQuery`: `search`, `tags`, `theme`, `author`, `browse` (`popular`,
`featured`, `updated`, `new`), `page`, `perPage`, `fields`. Several tags are sent
comma-joined and match any of them. `searchAll()` stops after the last page, when
`pages` is missing or 0, and on an empty page, and never asks for a page beyond.

Themes also offer `hotTags(?int $number)` (list of `ThemeTag`) and `featureList()`
(group to tags).

## Models

`Plugin` and `Theme` have typed, nullable properties named like the response
(`requires_php` is `requiresPhp`) and `raw`, the decoded entry. A theme's `author`
is a `ThemeAuthor`, also when wordpress.org sends a plain string. HTML entities
and markup (`author` as an `<a>` link) are passed through unchanged.

Closed themes are unverified: none was looked for. `ThemeLookup` follows the
plugin rule.

## Plugin checksums

```php
$checksums = $wordpress->plugins->checksums('akismet', '5.7.2'); // ?PluginChecksums
$checksums?->files['akismet.php']->sha256; // list<string>
```

From `downloads.wordpress.org`. `null` for an unknown version (`404`) and for a
slug or version that cannot be put in the path. Each file has `md5` and `sha256`
lists (wordpress.org sends plain strings).
