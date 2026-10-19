# Core

`$wordpress->core` covers `core/…` on api.wordpress.org.

## Latest version and stability

```php
$check = $wordpress->core->versionCheck();
$check->latest();          // current of the first offer, or null
$check->offers;            // list<Offer>: response, download, locale, packages, current, version, ...

$wordpress->core->stability('6.8.3'); // Stability::Latest|Outdated|Insecure, or null
$wordpress->core->stableCheck();      // array<string, string>: version => state as served
```

`versionCheck(new VersionCheckQuery(version: '6.0', php: '8.3', mysql: '8.0', locale: 'de_DE'))`
asks for the offers for that setup. Only these four parameters are sent. In
`OfferPackages`, `false` becomes `null`; keys the package does not model are in
`Offer::$raw`.

`stability()` fetches the whole list (about 960 entries) on every call; use
`stableCheck()` once if you need several versions.

## Checksums

```php
$checksums = $wordpress->core->checksums('6.8.3', 'en_US'); // ?CoreChecksums
$checksums?->files['wp-includes/version.php'];               // MD5
```

`null` when wordpress.org answers `{"checksums":false}` (unknown version). MD5
detects accidental changes only, not malicious ones. 6.8.3 has 3,230 files
(270 KB, measured 2026-10-19).

## Credits and importers

`credits($version, $locale)` returns `Credits` with `groups` by id; each
`CreditGroup` has `name` (can be `null`), `type`, `shuffle`, `contributors`,
`libraries` and `raw`. An unknown group type keeps its raw data. `importers()`
returns `list<Importer>` (`id`, `name`, `description`, `pluginSlug`); the endpoint
ignores a locale.

## Handbook, Browse Happy, Serve Happy

- `handbookUrl($function, $version)`: the handbook page as `https://` URL, else `null`.
- `browseHappy($userAgent)`: `Browser` with `name`, `version`, `platform`,
  `upgrade`, `insecure`, `mobile`, ... (a POST request).
- `serveHappy($phpVersion)`: `PhpSupport` with `recommendedVersion`,
  `minimumVersion`, `isSupported`, `isSecure`, `isAcceptable`. A missing
  parameter would be a `BadRequestException`; the method always sends one.
