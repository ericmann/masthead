# P3-01 Spike: Jetpack Stats methods, payloads and probe design

## Question

For each of `views_by_category`, `views_by_weekday`, `top_posts` and `post_views`, which `Automattic\Jetpack\Stats\WPCOM_Stats` method and arguments provide the data, what does it return on success and on failure, and are per-category views available at all?

## Method

Read the Jetpack 16.4 source in wp-env (`npx wp-env run cli`), under `/var/www/html/wp-content/plugins/jetpack/`. Jetpack is not connected in wp-env, so no payload could be fetched. Paths below are relative to `jetpack_vendor/automattic/jetpack-stats/src/` unless stated. Payload shapes were not observed: they are taken from the WordPress.com REST v1.1 stats documentation linked in the docblocks and are marked UNCONFIRMED.

## Facts

1. Class and constructor. `class-wpcom-stats.php:24` `class WPCOM_Stats` in namespace `Automattic\Jetpack\Stats` (line 8). The constructor takes no arguments (line 63) and only records whether the site is WordPress.com Simple. The REST version constant is `STATS_REST_API_VERSION = '1.1'` (line ~40). Jetpack's own package version is `Jetpack 16.4` (`jetpack.php:7`), stats package constant `STATS_VERSION = '9'` (`class-main.php:31`).
2. Visits by day. `get_visits( $args = array() )` (line 377) fetches resource `visits` through `fetch_stats()`. Documented query arguments: `unit` (`day`, `week`, `month`, `year`), `quantity`, `date` (end date), `stat_fields`. UNCONFIRMED shape: `{ date, unit, fields: [ 'period', 'views', 'visitors', ... ], data: [ [ '2026-11-08', 12, 9, ... ], ... ] }`. This backs `views_by_weekday`: Masthead requests `unit=day`, `quantity=<stats.window_days>`, `date=<today>`, and averages the per-day `views` for each weekday.
3. Top posts. `get_top_posts( $args = array(), $override_cache = false )` (line 101) fetches resource `top-posts`. Documented arguments: `period` (`day`, `week`, `month`, `year`), `date`, `num`, `summarize`. UNCONFIRMED shape: `{ date, days: { '<date>': { postviews: [ { id, href, date, title, type, views } ], total_views, other_views } } }`, or with `summarize` a single `summary` block. This backs `top_posts` and the fallback for `views_by_category`.
4. Views of one post. `get_post_views( $post_id, $args = array(), $cache_in_meta = false )` (line 239) fetches resource `post/<id>`. UNCONFIRMED shape: `{ date, views, years, averages, weeks, data: [ [ '2026-11-08', 3 ], ... ] }`. This backs `post_views`.
5. Tags and categories. `get_tags( $args = array() )` (line 172) fetches resource `tags`, whose docblock reads "Get site's views by tags and categories". UNCONFIRMED shape: tag and category entries with `type`, `value` and `views`, grouped by date. It returns views per tag or category name, not per post's primary category, and a post with two categories counts under both. Masthead's swap score uses the primary category (P1-03), so this is not the same quantity.
6. Errors. Every method returns `array|WP_Error` (docblocks). `fetch_stats()` returns the cached `WP_Error` or the decoded array; error codes seen in `fetch_stats()` include `missing_token`, `no_possible_tokens` and `site_not_connected` (line 472). On a site that is not connected the call returns a `WP_Error`; Masthead treats any `WP_Error` as unavailable.
7. Caching. `fetch_stats()` caches every response (and every `WP_Error` other than the connection failures above) in a transient named `jetpack_restapi_stats_cache_<md5>` (`STATS_CACHE_TRANSIENT_PREFIX`, line 30) for `STATS_CACHE_EXPIRATION_IN_MINUTES = 5` minutes (line 37), filterable with `jetpack_fetch_stats_cache_expiration`. A cached success is returned with an added `cached_at` key (line 460). Users who can `view_stats` bypass the cache with `?force_refresh` (line 595-603). Masthead keeps its own cache for `stats.cache_ttl` seconds (six hours by default), so it never relies on this one.
8. Connected and active. `Jetpack::is_connection_ready()` (`class.jetpack.php:1856`) and `Jetpack::is_module_active( 'stats' )` (`class.jetpack.php:2629`); `Jetpack::is_active()` (line 1840) is the older equivalent. Masthead's adapter treats the source as available only when the `WPCOM_Stats` class exists, `is_connection_ready()` is true and the Stats module is active; anything else is "Unavailable".

## Decision

Decision: categories-from-top-posts

Reason: no method returns views per primary category; `get_tags()` counts a post under every category and its payload is UNCONFIRMED. `views_by_category` aggregates `top_posts` by each post's primary category (`Support\Categories`), the fallback SPEC 6.2 names. A post outside the returned top posts contributes nothing, so the category numbers are a lower bound, and the `StatsSource` payload must say so (`fetched_at`, `source`) without estimating.

## stats:probe design

`wp masthead stats:probe [--days=30]` calls, in this order, and prints one JSON object keyed by method name:

- `get_visits( { unit: 'day', quantity: <days>, date: <today> } )` -> key `get_visits`
- `get_top_posts( { period: 'day', num: <candidate limit>, date: <today>, summarize: 1 } )` -> key `get_top_posts`
- `get_post_views( <id of the most recent published post>, { fields: 'views' } )` -> key `get_post_views`
- `get_tags( { } )` -> key `get_tags`

Scrubbing rule, applied recursively to each payload and to the nested keys of `data` arrays: every number becomes `0`, every string value becomes `""`, every boolean and null is kept, arrays keep their length, object keys are kept (date keys such as `"2026-11-08"` are kept because they are keys). A `WP_Error` is printed as `{ "error": "<code>" }`. The output therefore holds shapes only, never traffic numbers or titles, and can be pasted into a spike fixture.

## Open risk

- All payload shapes (facts 2 to 5) are UNCONFIRMED until `stats:probe` runs on a connected site. `JetpackStats` (P3-02) must validate every field it reads and return unavailable on any mismatch.
- `get_tags()` might return per-category views directly; if the probe shows that, a later change could switch to `categories-native`.
- WordPress.com Simple sites take a different code path in `get_total_post_views()` (line ~350); this plugin targets self-hosted Jetpack only.
