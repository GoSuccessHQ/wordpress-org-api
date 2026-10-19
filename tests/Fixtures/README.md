# Fixtures

Shortened real responses of the wordpress.org API. Long strings are cut, and `sections`, `versions` and `contributors` keep only a few entries. Markup and HTML entities are as served and must pass through the models unchanged (for example `author` is an `<a href=...>` link). Captured with `curl -g` (brackets in the query) and the package User-Agent.

| File | Source | Captured |
| --- | --- | --- |
| `plugins/information-akismet.json` | `https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=akismet` plus `request[fields][<name>]=1` for every field below | 2026-10-19 |
| `plugins/information-closed.json` | same endpoint, `request[slug]=display-widgets` (HTTP 404 with the closure object) | 2026-10-19 |
| `plugins/batch.json` | same endpoint, `request[slugs]=akismet,display-widgets,no-such-plugin-zzzq`, large fields switched off | 2026-10-19 |
| `plugins/query.json` | `https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&request[per_page]=2`, shortened to one plugin and the `info` object | 2026-10-19 |

## Plugin fields

The plugin directory source on meta.trac could not be fetched (bot check). The all-fields capture therefore switched on these fields explicitly: sections, description, short_description, versions, screenshots, reviews, banners, icons, contributors, ratings, tags, donate_link, compatibility, downloaded, active_installs, homepage, added, last_updated, requires, requires_php, requires_plugins, tested, author, author_profile, download_link, upgrade_notice, support_threads, support_threads_resolved, business_model, repository_url, commercial_support_url, support_url, preview_link.

The response contained: name, slug, version, author, author_profile, contributors, requires, tested, requires_php, requires_plugins, compatibility, rating, ratings, num_ratings, support_url, support_threads, support_threads_resolved, active_installs, downloaded, last_updated, added, homepage, sections, short_description, description, download_link, upgrade_notice, screenshots, tags, versions, business_model, repository_url, commercial_support_url, donate_link, banners, icons, preview_link. Every one has a `Plugin` property; no further field appeared. `screenshots` was empty for akismet at capture time.

## Slug casing in a batch

Measured once on 2026-10-19 with `curl -g` (package User-Agent): `https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slugs]=Akismet,hello-dolly` plus large fields switched off. The upper-case slug is not normalized: the response key is `Akismet` exactly as sent, and its entry is `{"error":"Invalid plugin slug."}` (so it reads as `NotFound`), while `hello-dolly` was found. The client therefore sends slugs lower-cased and matches response keys case-insensitively, which also covers a server that keys by a different spelling.

## Search and checksums

| File | Source | Captured |
| --- | --- | --- |
| `plugins/checksums-akismet-5.7.2.json` | `https://downloads.wordpress.org/plugin-checksums/akismet/5.7.2.json`, `files` shortened to five entries | 2026-10-19 |

Further measurements on 2026-10-19 (`curl -g`, package User-Agent), none stored as fixtures:

- Several tags: `request[tag][]=seo&request[tag][]=backup` (also with `per_page` and field switches) answers `{"error":"Invalid Input"}`. `request[tag]=seo,backup` works and matches plugins with any of the tags (3080 results, mixed seo and backup plugins). `PluginQuery` therefore sends the tags comma separated.
- Hash form: the checksums of `akismet/5.7.2`, `elementor/4.3.5`, `woocommerce/11.2.1` and `jetpack/16.3.1` (47 to 5488 files each) carry `md5` and `sha256` as plain strings for every file. No list form was seen; the model accepts both and always exposes lists. The versions of woocommerce, jetpack and contact-form-7 came from one `plugin_information` request with `request[slugs]` and large fields switched off.

## Themes

All captured on 2026-10-19 from `https://api.wordpress.org/themes/info/1.2/` with `curl -g` and the package User-Agent, long strings and lists shortened.

| File | Source | Captured |
| --- | --- | --- |
| `themes/information.json` | `?action=theme_information&request[slug]=twentytwentyfive` plus `request[fields][<name>]=1` for sections, description, rating, ratings, downloaded, download_link, last_updated, last_updated_time, homepage, tags, versions, screenshot_url, preview_url, parent, template, reviews_url, photon_screenshots_url, creation_time, is_commercial, external_support_url, is_community, external_repository_url, requires, requires_php, theme_url, author, active_installs, screenshot_count, screenshots, num_ratings; `sections` and `versions` shortened | 2026-10-19 |
| `themes/batch.json` | same endpoint, `request[slugs]=twentytwentyfive,no-such-theme-zzzq`, `sections` and `description` switched off (the spec notes the theme batch returns `sections` by default; with the switches off none came back) | 2026-10-19 |
| `themes/query.json` | `?action=query_themes&request[per_page]=2`, shortened to one theme and the `info` object | 2026-10-19 |
| `themes/hot-tags.json` | `?action=hot_tags&request[number]=3` (an object keyed by slug) | 2026-10-19 |
| `themes/feature-list.json` | `?action=feature_list` | 2026-10-19 |

The all-fields response contained: name, slug, version, preview_url, author, screenshot_url, screenshot_count, screenshots, theme_url, ratings, rating, num_ratings, reviews_url, downloaded, active_installs, last_updated, last_updated_time, creation_time, homepage, sections, download_link, tags, versions, requires, requires_php, is_commercial, external_support_url, is_community, external_repository_url. `description`, `template`, `parent` and `photon_screenshots_url` were absent for this theme; the query entry carried `description`, `template` and `parent` (an object with `slug`, `name`, `homepage`, which `Theme::$parent` reduces to the slug). `author` is an object with `user_nicename`, `profile`, `avatar`, `display_name`, `author` and `author_url`; `screenshot_url` is protocol-relative; `external_support_url` was `false`.

- Tags: one request, `request[tag]=one-column,portfolio&request[per_page]=3`, answered normally (3564 results, the three returned themes carried both tags). `ThemeQuery` therefore sends the tags comma separated, as `PluginQuery` does; the `request[tag][]` form was not tried for themes.
- Closed themes: none was looked for, so the closed classification for themes is unverified. `ThemeLookup` follows the plugin rule: `closed: true` or `error: "closed"` is `Closed`, any other `error` is `NotFound`.

## Core

All captured on 2026-10-19 from `https://api.wordpress.org/core/` with `curl` and the package User-Agent.

| File | Source | Captured |
| --- | --- | --- |
| `core/version-check.json` | `version-check/1.7/` without parameters, complete (26 offers, 7.1.3 down to 4.7.38; `translations` was an empty list) | 2026-10-19 |
| `core/stable-check.json` | `stable-check/1.0/`, shortened from 959 entries (934 `insecure`, 24 `outdated`, 1 `latest`) to nine | 2026-10-19 |
| `core/checksums.json` | `checksums/1.0/?version=6.8.3&locale=en_US`, shortened from 3,230 files to five | 2026-10-19 |

- Offers carry `packages` with `full`, `no_content`, `new_bundled`, `partial` and `rollback`; `partial` and `rollback` were `false`, as was `partial_version`. Some offers have extra keys such as `new_files`, which `Offer::$raw` keeps.
- Unknown version: `checksums/1.0/?version=9.9.9&locale=en_US` answered HTTP 200 with `{"checksums":false}`. The "keyed by version" form for some inputs was not observed, so it is not handled.
- wordpress.org notes that MD5 hashes detect accidental changes only, not malicious ones.
| `core/credits.json` | `credits/1.1/?version=7.1`, shortened to two entries per group (four groups: two `titles`, one `list`, one `libraries`; `compact` did not occur, it is covered by an inline test) | 2026-10-19 |
| `core/importers.json` | `importers/1.1/`, complete (an object keyed by importer under `importers`) | 2026-10-19 |
| `core/browse-happy.json` | `browse-happy/1.1/`, POST form `useragent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36` | 2026-10-19 |
| `core/serve-happy.json` | `serve-happy/1.0/?php_version=7.4.0` | 2026-10-19 |

- Credits: group `name` can be `false` (no heading); `props` carries `placeholders` and a `%s` in its name, the top-level `data` is `profiles` (URL template) and `version`. The `importer-id` can differ from the key (`movabletype` has `mt`).
- Importers and locale: `importers/1.1/?locale=de_DE` returned a body byte-identical to the request without it (one request, 2026-10-19), so `Core::importers()` takes no locale. The meta.trac source is behind a bot check and was not read.
- Handbook: no fixture; the body is a plain URL and the unit test uses the URL from the spec.
- Serve Happy: the 400 for a missing `php_version` is not stored; the test uses the spec's `missing_param` form.

## Translations and stats

All captured on 2026-10-19 from `https://api.wordpress.org/` with `curl -g` and the package User-Agent.

| File | Source | Captured |
| --- | --- | --- |
| `translations/core.json` | `translations/core/1.0/?version=6.8`, shortened from 132 sets to two (the first carries `strings`) | 2026-10-19 |
| `translations/plugin.json` | `translations/plugins/1.0/?slug=akismet&version=5.3`, shortened to two sets (no `strings`) | 2026-10-19 |
| `stats/wordpress.json`, `stats/php.json`, `stats/mysql.json`, `stats/locale.json` | `stats/{wordpress,php,mysql,locale}/1.0/`, complete | 2026-10-19 |
| `stats/plugin.json` | `stats/plugin/1.0/akismet` (no trailing slash), complete | 2026-10-19 |
| `stats/downloads.json` | `stats/plugin/1.0/downloads.php?slug=akismet&limit=3`, complete (two days came back) | 2026-10-19 |

- Unknown slug, measured once per endpoint: `stats/plugin/1.0/no-such-plugin-zzzq` and `translations/plugins/1.0/?slug=no-such-plugin-zzzq&version=1.0` answered HTTP 200 with `[]` and `{"translations":[]}`, so both map to an empty list. `stats/plugin/1.0/downloads.php?slug=no-such-plugin-zzzq` answered HTTP 200 with a 180-day series of small counts (0 to 10), the same shape as a real plugin, so an unknown slug cannot be told apart there. The client does not guess; the doc comment says so.
- `iso` keys arrive as `"1"`, `"2"`; PHP turns them into int keys, so `Translation::$iso` is `array<array-key, string>`. The same applies to an all-digit share key; the captured keys (`8.3`, `MariaDB 10.11`) are not affected.
- Downloads: zero comes as the number `0`, every other count as a string (`"25688"`); both read as int.
- Version-like keys in the stats (`7.4`, `10.11` inside a longer label) stay strings. The stats paths for WordPress, PHP, MySQL and locale end with `/`; the plugin one does not.

## Patterns, events and secret keys

Captured on 2026-10-19 from `https://api.wordpress.org/` with the package User-Agent.

| File | Source | Captured |
| --- | --- | --- |
| `patterns/search.json` | `patterns/1.0/?search=button&per_page=2`, `content.rendered` and `pattern_content` cut to 200 characters | 2026-10-19 |
| `events/dresden.json` | `events/1.0/?location=Dresden&number=3`, complete | 2026-10-19 |
| `events/error.json` | `events/1.0/?location=zzzqxq`, complete | 2026-10-19 |
| `secret-keys/salt.txt` | written by hand in the real format with made-up values (one with `\'`, one with `\\`); the real endpoint was never requested | n/a |

- Pattern headers of the first page: `x-wp-total: 387`, `x-wp-totalpages: 194` (the same on `page=2`). Paging was measured once: `search=button&per_page=2&page=2` answered other patterns (ids 80683, 57748 against 406479, 87098), so `page` and `per_page` are the paging arguments. The `link` header carries `rel="next"`/`rel="prev"`, which the client does not use.
- Events: a location that cannot be resolved (`location=zzzqxq`) answers HTTP 200 `{"sandboxed":false,"error":"no_location_available","location":false,"events":[]}`. A successful answer has `"error": null`. The list location carries `description`, string coordinates and an upper-case `country`; an event location carries `location` (text), a lower-case `country` and numeric coordinates.
