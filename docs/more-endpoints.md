# More endpoints

## Translations

`$wordpress->translations->core($version)`, `->plugin($slug, $version)` and
`->theme($slug, $version)` return `list<Translation>`: `language`, `version`,
`updated`, `englishName`, `nativeName`, `package`, `iso` (level to code; PHP makes
the keys ints) and `strings` (core only). wordpress.org falls back to an older
set by itself. An unknown slug gives an empty list.

## Stats

```php
$wordpress->stats->wordpress();   // array<string, float>: share in percent
$wordpress->stats->php();
$wordpress->stats->mysql();
$wordpress->stats->locales();
$wordpress->stats->pluginVersions('akismet'); // includes `other`
$wordpress->stats->pluginDownloads('akismet', days: 30); // date => int
```

Keys are as sent (`MariaDB 10.11`, `German (Formal)`); all-digit keys become ints.
Unknown slugs give an empty list for `pluginVersions()`, but
`pluginDownloads()` returns a plausible series of small counts even for a slug
that does not exist (measured 2026-10-19), so it cannot detect unknown slugs.

## Patterns

```php
$page = $wordpress->patterns->search(new PatternQuery(search: 'button', perPage: 2, page: 2));
$page->patterns; $page->total; $page->totalPages; // totals from the x-wp-* headers
```

`PatternQuery`: `search`, `categories` and `keywords` (ids, comma-joined),
`wpVersion`, `locale`, `page`, `perPage`. `Pattern` has `title`, `content`,
`patternContent`, category and keyword slugs, `meta` and `raw`.

## Events

```php
$list = $wordpress->events->upcoming(new EventQuery(location: 'Dresden', number: 3));
$list->error;    // e.g. "no_location_available" when the location is unknown
$list->location; // EventLocation
foreach ($list->events as $event) {
    $event->date;  // local time of the event, as a string
    $event->start; // UTC DateTimeImmutable from the unix timestamp
}
```

Set latitude and longitude together or neither. The `ip` argument sends that IP
address to wordpress.org; it is only sent when you set it.

## Secret keys

```php
$salts = $wordpress->secretKeys->generate();
$salts->get('AUTH_KEY');
```

Returns the eight constants of `wp-config.php`, parsed with a strict pattern and
never evaluated; an unexpected line or a missing constant throws
`SerializationException`. The values are masked only in `var_dump()` and
`print_r()`.
