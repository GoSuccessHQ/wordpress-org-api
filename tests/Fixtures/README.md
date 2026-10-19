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
