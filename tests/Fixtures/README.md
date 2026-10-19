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

Measured once on 2026-10-19 with `curl -g` (package User-Agent): `https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slugs]=Akismet,hello-dolly` plus large fields switched off. The upper-case slug is not normalized: the response key is `Akismet` exactly as sent, and its entry is `{"error":"Invalid plugin slug."}` (so it reads as `NotFound`), while `hello-dolly` was found. The client therefore sends slugs in the caller's spelling and matches response keys case-insensitively, which also covers a server that keys by the lower-case slug.

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
- Closed themes: no closed theme found, classification unverified. `ThemeLookup` classifies entries like plugin entries (`closed: true` or `error: "closed"` is `Closed`, any other `error` is `NotFound`).
