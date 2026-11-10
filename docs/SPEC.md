# Masthead — Specification

Version: 0.1 Status: draft (2026-11-09)

The Kūkulu planner reads this file in full and derives the build plan from it. Headings are the ones the planner looks for by name. Every guessed number carries `⚠️ ASSUMPTION` and a config key.

## 1. Overview

Masthead is a WordPress plugin that adds an editorial calendar to wp-admin (Posts › Calendar): every published, scheduled and planned post on a month/week/list grid, coloured by category, with a weekly **rhythm** that gives each weekday a lane (Business on Monday, Fiction on Saturday, …). A side panel called the **Desk** suggests what to put on empty days (an unscheduled draft, the next part of a series, a neglected category, a post worth re-sharing, or an AI-generated idea with an outline), flags scheduled posts that fit their day badly, and re-shares posts through Jetpack Social. It is built for one writer publishing daily on eric.mann.blog, uses only WordPress core APIs, Jetpack, and the core WordPress AI Client (any provider configured under Settings › Connectors), and is "done" when the owner can plan a month of daily posts from the calendar without leaving wp-admin.

## 2. Goals and non-goals

- Goal: a calendar view of posts in `publish`, `future` and dated `draft` status, coloured by primary category, with drag-to-reschedule for `future` and dated drafts.
- Goal: a configurable weekly rhythm (weekday → lane → categories → default time) that labels every day and drives suggestions.
- Goal: **planned slots are dated drafts.** A plan made in Masthead is a real `post` in `draft` status with a fixed (non-floating) date, so it is visible to every other tool, MCP client and the block editor.
- Goal: Desk suggestions for empty days, ordered deterministically: unscheduled drafts, then series continuations, then re-shares, then category gaps, then AI ideas.
- Goal: a nightly swap check that flags lane mismatches, same-day collisions, series cadence breaks, and better-fitting candidates for scheduled posts, using Jetpack Stats numbers only.
- Goal: Jetpack Social re-sharing from the calendar (share now, or queue a share for a date), with share copy the Desk can draft on request.
- Goal: every capability is a WordPress **Ability** (`masthead/*`), so the admin UI, WP-CLI and MCP clients (Claude Desktop/Code via the MCP Adapter) use one implementation.
- Goal: shippable as a release zip that the site repository pins by tag, and open-sourceable without edits (GPL-2.0-or-later, no owner-specific data in code).
- Non-goal: any front-end output. Masthead adds nothing to public pages, feeds or the REST responses the theme uses (the site is served from cache; see ttm_theme `docs/DEPLOYMENT.md`).
- Non-goal: any analytics source other than Jetpack Stats; no Google Analytics, no Cloudflare analytics, no own tracking.
- Non-goal: storing API keys. AI credentials live in Connectors / provider env vars; Masthead never reads or writes them.
- Non-goal: automatic publishing, rescheduling or sharing. Every write is a user click or an explicit ability call; cron only reads and caches.
- Non-goal: own social-network integrations (Mastodon, Bluesky, LinkedIn APIs). Sharing goes through Jetpack Social or is unavailable.
- Non-goal: multi-author workflow (assignments, approvals, comments, editorial statuses beyond core's).
- Non-goal: post types other than `post`; pages; a block-editor sidebar panel; a mobile app; WordPress.org submission in 0.1.
- Non-goal: replacing ttm-core's series UI. Masthead reads ttm-core's `series` taxonomy when present and only adds its own term meta.
- Non-goal: writing post bodies. The AI writes titles, angles, outlines and share copy, and only when asked.

## 3. Engineering principles

Each item that can be a single-line pattern becomes a `constraints` entry in `docs/kukulu.json`; the rest say how the reviewer checks. All constraints are active `from: "P0-01"`.

### 3.1 Boundaries

- No front-end hooks: no `wp_head`, `wp_footer`, `wp_enqueue_scripts`, `template_redirect`, `the_content`, `the_excerpt`, `render_block`, `pre_get_posts` in `src/` [no-front-end].
- Admin assets load only on Masthead's own screens: `admin_enqueue_scripts` callbacks return early unless the hook suffix is one of the two registered page hooks (reviewer reads `src/Admin/Assets.php`).
- `WPCOM_Stats` and any `Automattic\Jetpack\Stats` symbol appear only in `src/Stats/JetpackStats.php` [stats-confined].
- `publicize` (any case) appears only in `src/Social/JetpackSocial.php` [social-confined].
- `wp_ai_client_prompt` appears only in `src/Ai/WpAiClient.php` [ai-confined].
- No direct HTTP in the plugin: no `wp_remote_`, `wp_safe_remote_`, `curl_`, `fsockopen`, `file_get_contents( 'http`, `fopen( 'http` under `src/` [no-direct-http]. All network traffic goes through Jetpack (Stats, Social) or the WordPress AI Client.
- Client JS talks only to `wp/v2/*` (posts, categories, series taxonomy) and the core Abilities run endpoint; no `wpcom/`, `jetpack/`, `admin-ajax.php` strings under `client/` [client-endpoints].
- `wp_insert_post`, `wp_update_post`, `wp_delete_post` appear only in `src/Plan/Slots.php` and `src/Plan/Reschedule.php` [writes-confined]; nothing under `src/Cron/` calls them or any ability marked non-readonly [cron-read-only].
- No option, meta or transient name containing `key`, `token`, `secret` or `password` [no-secrets-stored]; no reads of `ANTHROPIC_API_KEY`, `OPENROUTER_API_KEY` or `getenv(` [no-key-reads].

### 3.2 Determinism and time

- One clock: no `time()`, `date(`, `gmdate(`, `wp_date(`, `current_time(`, `current_datetime(`, `strtotime(`, `new DateTime` outside `src/Support/Clock.php` [one-clock]. `Clock::now()` is filterable via `masthead_now` for tests and `wp masthead … --now`.
- No randomness in suggestion ordering: no `rand(`, `mt_rand(`, `shuffle(`, `array_rand(`, `random_int(` under `src/` [no-rng]. Ties break by kind order, then score, then post ID ascending, then title (reviewer reads `src/Desk/Ranker.php`; unit tests assert order).
- Every tunable is a `Config` key read as `Config::get( 'key' )`, with defaults only in `Config::defaults()`. No bare numeric literal ≥ 2 outside `src/Config.php` except HTTP status codes, array indices and weekday arithmetic (7) [hard-coded-tunable].
- Bounded queries: no `posts_per_page => -1`, `nopaging => true`, `numberposts => -1`, `number => 0` [bounded-queries]; every `WP_Query` sets `no_found_rows => true` unless it paginates.

### 3.3 Honesty of numbers and suggestions

- Every traffic number shown to a user, returned by an ability or used in a score comes from `StatsSource`. The only production implementation is `JetpackStats`; when Jetpack Stats is unavailable the value is `null`, the UI shows "Unavailable", and scores fall back to the non-stats terms. No fixture or placeholder number is reachable in production: `FixtureStats` is selected only when `WP_ENVIRONMENT_TYPE` is `local` and the `MASTHEAD_STATS_FIXTURE` constant names a readable file (reviewer reads `src/Stats/Factory.php`; unit test covers the production branch).
- Every stat value in the UI renders through `client/components/StatValue.js`, which shows the value, its `source` ("Jetpack Stats") and `fetchedAt`; no other component reads `.views`, `.visitors` or `.visits` [stat-render-confined].
- "Why" lines for draft, series, reshare and gap suggestions, and every swap-check finding, are produced by `src/Desk/Reasons.php` templates filled from WordPress or Jetpack data. The AI never writes a sentence that states a fact about traffic, dates or history; AI output is limited to idea titles, angles, outlines and share copy (reviewer reads `src/Ai/Prompts.php` and `Reasons.php`).
- AI output is validated against a JSON schema before use; an idea whose `category` is not one of the lane's categories (or any category, for a lane with none) is dropped, not coerced. Text from posts sent to the model is wrapped in `<untrusted-post-data>` delimiters with an instruction to treat it as data.

### 3.4 Security

- Every ability declares `permission_callback`; read abilities require `edit_posts`, write abilities `edit_others_posts` or `publish_posts` as listed in §6.1, AI-using abilities additionally `prompt_ai`, settings `manage_options`. Reviewer reads `src/Abilities/`.
- Every non-ability REST route (there should be none) and every settings save checks a capability and a nonce. Settings use the Settings API with `register_setting` + `sanitize_callback`.
- Output escaping: all PHP output uses `esc_html`, `esc_attr`, `esc_url` or `wp_kses_post`; PHPCS (WordPress-Extra + VIP-Go) enforces it.
- No `eval|unserialize|extract|create_function|assert|system|exec|shell_exec|passthru|proc_open` under `src/` [dangerous-php].
- `masthead/share-post` is not exposed to MCP unless `abilities.expose_share` is true (default false).

### 3.5 Code quality and tests

- PHP 8.3 minimum, `declare(strict_types=1);` in every file under `src/` [strict-types]; PSR-4 `Masthead\` → `src/`.
- Unit tests never load WordPress (PHPUnit + Brain Monkey). Integration tests run in wp-env. Every class under `src/Desk/`, `src/Plan/` and `src/Stats/` has unit tests; every ability has an integration test for its permission callback (allowed and denied) and its happy path.
- The swap-check and Desk acceptance scenario is `docs/fixtures/queue-2026-11-09.json`; tests seed it with `wp masthead seed --fixture=…` and pin `--now`.
- Every user-facing string is translatable (text domain `masthead`).
- `package.json` `dependencies` stays `{}`; lockfiles committed; `composer audit` and `npm audit --audit-level=high` pass.
- Commits and tags are GPG-signed (global git config); never pass `--no-gpg-sign`.

## 4. Architecture

### 4.1 Repository layout

```
masthead.php             plugin header + bootstrap only (Requires at least: 7.1, Requires PHP: 8.3)
src/                     PHP, PSR-4 Masthead\
client/                  admin React apps (calendar, settings), built by wp-scripts → build/
tests/unit/              PHPUnit + Brain Monkey (no WordPress)
tests/integration/       PHPUnit in wp-env
tests/e2e/               Playwright + axe, plus tests/e2e/mu/ (local-only mu-plugins: fake AI client, fixture stats)
scripts/                 forbidden-patterns.sh, release/pack.mjs
docs/                    SPEC.md, mockups/, fixtures/, DEPLOYMENT.md (written in Phase 6)
readme.txt, README.md, LICENSE, CHANGELOG.md
```

### 4.2 Module map (`src/`)

The dependency arrow points down this list; a module may import only modules below it. `BoundariesTest` enforces it by scanning `use` statements.

| Module | Responsibility | May import |
|---|---|---|
| `Plugin.php` | Wires hooks for every module | everything |
| `Admin/` | `CalendarPage` (Posts › Calendar), `SettingsPage` (Settings › Calendar), `Assets` (enqueue + `wp_add_inline_script` of boot data), `Notices` | `Config`, `Support`, `Plan` |
| `Cli/` | `wp masthead` commands (§6.4) | `Abilities`, `Desk`, `Plan`, `Stats`, `Social`, `Config`, `Support` |
| `Abilities/` | `Registrar` (category + all abilities), one class per ability (§6.1) | `Desk`, `Plan`, `Stats`, `Social`, `Ai`, `Config`, `Support` |
| `Cron/` | `Nightly`: run swap check for the horizon, warm stats cache, store findings | `Desk`, `Stats`, `Config`, `Support` |
| `Desk/` | `Candidates/{DraftMatch,SeriesNext,Reshare,CategoryGap}`, `Ideas` (AI), `Ranker`, `Reasons`, `SwapCheck`, `Scoring`, `Dismissals`, `Budget` | `Plan`, `Stats` (interface), `Social` (interface), `Ai` (interface), `Config`, `Support` |
| `Ai/` | `AiClient` interface, `WpAiClient` (the only `wp_ai_client_prompt` caller), `Prompts`, `Schemas`, `Factory` (honours `masthead_ai_client` filter only when environment is `local`) | `Config`, `Support` |
| `Social/` | `ShareSource` interface, `JetpackSocial`, `NullShare`, `Factory` | `Config`, `Support` |
| `Stats/` | `StatsSource` interface, `JetpackStats`, `FixtureStats`, `NullStats`, `Factory`, `Cache` (transients) | `Config`, `Support` |
| `Plan/` | `Rhythm` (option), `Lanes`, `Slots` (dated drafts), `Reschedule`, `SeriesSource` (adapter), `Cadence`, `Colors`, `PlanReader` (posts in a date range) | `Config`, `Support` |
| `Support/` | `Clock`, `Dates` (site-timezone helpers), `Json` | `Config` |
| `Config.php` | `defaults()`, `get()`, filter `masthead_config` | nothing |

Shared wiring that must stay serial in the plan: `Plugin.php`, `Abilities/Registrar.php`, `client/index.js` routes, `Config::defaults()`.

### 4.3 Client (`client/`)

- `calendar/` — `App` (route by `#month`, `#week`, `#list` hash), `MonthGrid`, `WeekGrid`, `ListView`, `DayCell`, `PostChip`, `GhostCard`, `OpenSlot`, `DraftTray`, `DeskRail`, `DayPlan`, `SwapPanel`, `SharePanel`, `AskDesk`.
- `settings/` — `App`, `RhythmTable`, `SeriesCadence`, `AiSettings`, `Exposure`.
- `components/` — `StatValue`, `LaneDot`, `CategoryLegend`.
- `data/` — `useAbility( name )` (wraps `apiFetch` to the core Abilities run endpoint), `usePlan( from, to )` (core-data `getEntityRecords` for posts + `masthead/get-plan` for lanes).
- Built with `@wordpress/scripts`; UI from `@wordpress/components`; state from `@wordpress/data` / `@wordpress/core-data`. Matches `docs/mockups/` at 1440 px and stacks to one column at 390 px.

### 4.4 How a suggestion is produced

1. `masthead/suggest-for-date { date }` → `Plan\Lanes::for_date()` gives the lane.
2. Deterministic candidates: `DraftMatch` (unscheduled drafts whose categories intersect the lane's), `SeriesNext` (series whose cadence lands on or near the date and whose next part is not dated), `Reshare` (published posts in lane categories, older than `desk.reshare_min_age_days`, not shared within `desk.reshare_cooldown_days`, ranked by Jetpack views in `stats.window_days`), `CategoryGap` (the lane's categories, or every category when the lane has none, with no publish in `desk.gap_days`; ordered by days since last publish descending, never-published first, then slug). `Reshare` yields nothing when stats are unavailable.
3. `Ranker` orders by kind (draft, series, reshare, gap, idea), then score, then ID; drops dismissed keys; truncates to `desk.max_suggestions`.
4. Only if `include_ideas` is true, the user has `prompt_ai`, a provider supports text generation, and the budget allows: `Ideas` asks the AI for `desk.ideas_per_request` ideas and appends the valid ones.
5. `Reasons` fills each non-AI "why" from data.

## 5. Data and configuration

### 5.1 Options

| Option | Shape | Notes |
|---|---|---|
| `masthead_rhythm` | `{ "0".."6": { lane, label, categories: [slug], time: "HH:MM" } }` | Keys are PHP `w` weekdays (0 = Sunday). Default = `docs/fixtures/rhythm-defaults.json`, applied on activation only for categories that exist. A lane with `categories: []` accepts any category. |
| `masthead_findings` | `{ generated_at, items: [Finding] }` | Written only by `Cron\Nightly`; read by the swap ability. |
| `masthead_ai_usage` | `{ month: "YYYY-MM", requests: int }` | Incremented per AI request; resets when the month changes. |
| `masthead_settings` | `{ ai: {...}, abilities: {...}, desk: {...} }` | User-editable overrides of the `Config` keys marked *settable* below. |

### 5.2 Taxonomy and term meta

- **Series source.** `SeriesSource::taxonomy()` returns `series` when that taxonomy is registered (ttm-core), else registers and returns `masthead_series` (non-hierarchical, `show_in_rest`, not public, no rewrite). Reads of part number use post meta `ttm_series_part` when the taxonomy is `series`, else `masthead_series_part`. Total parts: `ttm_total_parts` or `masthead_total_parts`.
- Term meta added by Masthead on the series taxonomy (registered with `show_in_rest`):
  - `masthead_cadence` JSON `{ every_weeks: int ≥ 1, weekday: 0–6 | null, time: "HH:MM" }`; `weekday: null` means "same weekday as the previous part".
  - `masthead_lane_exception` bool: the swap check does not flag lane mismatches for parts of this series.
- Category term meta `masthead_color` (hex). Defaults by slug: technology `#2f5fd0`, business `#8a5409`, faith `#7a5c2e`, writing `#b8325a`, journal `#1f7a52`, opinion `#6d45c9`, security `#0f6e7a`; any other category gets `Colors::PALETTE[ term_id % count ]`. All colours pass 4.5:1 against white for text and are used with white text on solid chips.

### 5.3 Post meta

| Key | Type | Meaning |
|---|---|---|
| `masthead_planned` | bool | Draft created by Masthead as a planned slot. |
| `masthead_outline` | JSON string[] | Outline the user accepted; also inserted into the draft body as a `core/list` inside a `core/group` with class `masthead-outline`. |
| `masthead_last_shared` | ISO 8601 | Set after a successful Masthead share; read by `Reshare` cooldown. |

**Dated drafts are non-floating.** `Slots::create()` sets `post_date` and `post_date_gmt` explicitly and `edit_date => true`. Integration test: create a dated draft, save it through `wp/v2/posts/<id>` with a title change, assert both dates are unchanged. A draft with `post_date_gmt = '0000-00-00 00:00:00'` is *unscheduled* and appears in the Draft tray, never on the grid.

### 5.4 Config keys (`Config::defaults()`)

| Key | Default | Settable | Notes |
|---|---|---|---|
| `post_types` | `['post']` | no | |
| `calendar.week_starts_on` | `get_option('start_of_week')` | no | Read at runtime. |
| `calendar.drag_published` | `false` | no | Published posts cannot be dragged. |
| `calendar.max_posts_per_range` | `500` ⚠️ ASSUMPTION | no | Cap for one `get-plan` call. |
| `desk.horizon_days` | `21` ⚠️ ASSUMPTION | yes | Days ahead the Desk pre-computes and the nightly run covers. |
| `desk.ghost_days` | `14` ⚠️ ASSUMPTION | yes | Days ahead that show ghost cards; later empty days show "Open · lane". |
| `desk.max_suggestions` | `3` | yes | |
| `desk.ideas_per_request` | `3` | yes | |
| `desk.gap_days` | `45` ⚠️ ASSUMPTION | yes | A category with no publish in this many days is a gap. |
| `desk.reshare_min_age_days` | `90` ⚠️ ASSUMPTION | yes | |
| `desk.reshare_cooldown_days` | `180` ⚠️ ASSUMPTION | yes | |
| `desk.series_window_days` | `3` ⚠️ ASSUMPTION | yes | A series part is "due" within ± this many days of its cadence date. |
| `swap.lookahead_days` | `60` ⚠️ ASSUMPTION | yes | |
| `swap.min_score_delta` | `0.25` ⚠️ ASSUMPTION | yes | A candidate must beat the scheduled post by this much to raise a finding. |
| `swap.candidate_window_days` | `3` ⚠️ ASSUMPTION | yes | Other scheduled posts within ± days are swap candidates. |
| `swap.weights` | `{lane: 0.5, category: 0.3, weekday: 0.2}` ⚠️ ASSUMPTION | yes | §6.3. |
| `stats.window_days` | `30` | yes | |
| `stats.cache_ttl` | `21600` ⚠️ ASSUMPTION | no | Seconds. |
| `ai.monthly_request_cap` | `300` ⚠️ ASSUMPTION | yes | `0` disables AI. |
| `ai.models.fast` | `['claude-haiku-5-5']` ⚠️ ASSUMPTION | yes | Passed to `using_model_preference()`; the provider's default is used if none match (e.g. OpenRouter ids). |
| `ai.models.strong` | `['claude-sonnet-5-5']` ⚠️ ASSUMPTION | yes | Used for Ask the Desk and outlines. |
| `ai.temperature` | `null`: not sent (1.0.1; Claude 5 models reject sampling parameters, and a temperature the model doesn't list removes it from the AI Client's candidates). A number from `masthead_config` is sent | no | |
| `ai.max_tokens` | per kind: `series_proposal` 4000, `default` 1200 (1.0.0; see `docs/tuning/P5-13.md`) | no | |
| `ai.context_days` | `90` | yes | Recent posts sent as context. |
| `ai.context_max_posts` | `60` ⚠️ ASSUMPTION | no | |
| `ai.include_draft_excerpts` | `true` | yes | Titles and excerpts of drafts only; bodies are never sent. |
| `abilities.expose_mcp` | `true` | yes | Sets `meta.public` on all abilities except share. |
| `abilities.expose_share` | `false` | yes | |
| `cron.nightly_hour` | `3` | no | Site-local hour. |

## 6. Interfaces

### 6.1 Abilities (category `masthead`, registered on `wp_abilities_api_init`)

Every ability has `input_schema`, `output_schema`, `permission_callback`, `meta.annotations` (`readonly`, `destructive`, `idempotent`) and `meta.public` per §5.4. Dates are `YYYY-MM-DD` in site time; datetimes are ISO 8601 with offset.

| Ability | Input | Output | Annotations | Capability |
|---|---|---|---|---|
| `masthead/get-plan` | `{ from, to }` (≤ 92 days) | `{ days: [{ date, lane: {slug,label,categories}, posts: [PlanPost], open: bool }] }` | readonly, idempotent | `edit_posts` |
| `masthead/suggest-for-date` | `{ date, include_ideas?: bool = false, kinds?: [kind] }` | `{ date, lane, suggestions: [Suggestion], ai: { used: bool, remaining: int } }` | readonly | `edit_posts` (+ `prompt_ai` when `include_ideas`) |
| `masthead/dismiss-suggestion` | `{ date, key }` | `{ ok }` | idempotent | `edit_posts` |
| `masthead/create-planned-draft` | `{ date, time?, title, category, series?: {term_id, part}, outline?: [string] }` | `{ post_id, edit_url, date }` | — | `edit_posts` |
| `masthead/reschedule` | `{ post_id, date, time? }` | `{ post_id, date, status }` | idempotent | `edit_post` on that post; refuses `publish` status |
| `masthead/swap-check` | `{ from?, to? }` | `{ generated_at, findings: [Finding] }` (from `masthead_findings`, filtered) | readonly | `edit_posts` |
| `masthead/stats-summary` | `{ days?: int }` | `{ available, source: "Jetpack Stats", fetched_at, window_days, by_category: [{slug, views}], by_weekday: [{weekday, avg_views}], top_posts: [{post_id, views}] }` or `{ available: false, reason }` | readonly | `edit_posts` |
| `masthead/share-connections` | `{}` | `{ available, connections: [{id, service, display_name}] }` | readonly | `publish_posts` |
| `masthead/share-post` | `{ post_id, message, connection_ids: [int], at?: datetime }` | `{ status: "shared"\|"queued", results: [...] }` | — | `publish_posts`; refuses non-published posts |
| `masthead/draft-share-copy` | `{ post_id }` | `{ message }` | readonly | `edit_posts` + `prompt_ai` |
| `masthead/propose-series` | `{ prompt, start?: date }` | `{ name, lane, parts: [{date, title, outline:[string]}] }` | readonly | `edit_posts` + `prompt_ai` |
| `masthead/accept-series` | `{ name, parts: [...], cadence? }` | `{ term_id, post_ids: [int] }` | — | `edit_posts` |

Types:

```text
PlanPost   { id, title, status: publish|future|draft, date, categories:[slug], primary_category, color, series?:{term_id,name,part,total}, planned: bool, edit_url }
Suggestion { key, kind: draft|series|reshare|gap|idea, title, why, category?, post_id?, series?:{term_id,part}, outline?:[string], actions:[create_draft|schedule|open|share|suggest_topics|dismiss] }
Finding    { key, post_id, date, type: lane_mismatch|collision|cadence_break|better_candidate, severity: info|review, why, numbers?:{source,fetched_at,values:{...}}, options:[{label, ability, input}] }
```

`Suggestion.key` is `kind:post_id` or `kind:sha1(title)` for ideas, so dismissals are stable.

### 6.2 Stats (`StatsSource`)

```php
interface StatsSource {
    public function available(): bool;
    /** @return array<string,int>|null category slug => views over $days */
    public function views_by_category( int $days ): ?array;
    /** @return array<int,float>|null weekday 0–6 => average daily views over $days */
    public function views_by_weekday( int $days ): ?array;
    /** @return array<int,int>|null post_id => views over $days, at most $limit */
    public function top_posts( int $days, int $limit ): ?array;
    public function post_views( int $post_id, int $days ): ?int;
    public function fetched_at(): ?string;
}
```

`JetpackStats` maps these onto `Automattic\Jetpack\Stats\WPCOM_Stats` (`get_tags` or equivalent for categories, `get_visits` with day units for weekdays, `get_top_posts`, `get_post_views`). The exact methods and payload shapes are confirmed in spike P3-01 and recorded in `docs/spikes/P3-01-jetpack-stats.md` with a sanitised payload fixture; if categories are not available from WPCOM, `views_by_category` aggregates `top_posts` by primary category and the spike records that choice. Responses are cached in transients for `stats.cache_ttl`.

### 6.3 Swap-check scoring

For a post *p* on date *d*:

```text
lane     = 1.0 if primary category ∈ lane(d).categories or lane(d).categories = [] or p's series has masthead_lane_exception
           0.5 if any category of p ∈ lane(d).categories
           0.0 otherwise
category = views_by_category[primary(p)] / max(views_by_category)          (null → term dropped)
weekday  = views_by_weekday[weekday(d)] / max(views_by_weekday)            (null → term dropped)
score    = Σ weight·term / Σ weight of the terms present
```

Findings: `lane_mismatch` when `lane < 1` and no exception; `collision` when two non-Journal posts share a date; `cadence_break` when a series part's date differs from `previous part date + every_weeks` (on `weekday` if set) by more than `desk.series_window_days`; `better_candidate` when an unscheduled draft or another scheduled post within `swap.candidate_window_days` scores ≥ `swap.min_score_delta` higher on *d*. Each finding offers concrete options that call abilities: for `lane_mismatch`, move to the nearest date within ±`desk.series_window_days` whose lane includes the post's primary category (earlier date wins a tie), applied to every remaining part when the post is in a series; mark the series as a lane exception; for `better_candidate`, swap the two dates.

Acceptance (fixture, `--now=2026-11-09 14:00`, stats unavailable): The Mantle parts 2–4 (12156, 12157, 12158) on Mondays each produce `lane_mismatch`, with the series option "Move to Saturdays: 2026-12-12, 2027-01-09, 2027-02-06"; part 2 also produces `cadence_break` (expected 2026-12-07 from part 1 + 4 weeks, actual 2026-12-14); marking the series as a lane exception clears the three `lane_mismatch` findings and leaves the `cadence_break`; 12177 and 10194 produce no findings.

### 6.4 WP-CLI (`wp masthead …`)

- `seed --fixture=<path> [--now=<datetime>] [--reset]` — local environments only; refuses when `WP_ENVIRONMENT_TYPE` is `production`.
- `plan <from> <to> [--format=table|json]` · `suggest <date> [--ideas]` · `swap-check [--write]` (`--write` stores findings like the cron) · `stats:probe [--days=30]` (prints raw WPCOM payload shapes with numbers replaced by `0`, for spike fixtures) · `social:probe` (connection list and endpoint shapes) · `ai:status` (provider supported?, budget).

### 6.5 Jetpack Social (`ShareSource`)

```php
interface ShareSource {
    public function available(): bool;
    /** @return list<array{id:int,service:string,display_name:string}> */
    public function connections(): array;
    /** @return array{status:string,results:array} */
    public function share_now( int $post_id, string $message, array $connection_ids ): array;
    public function share_at( int $post_id, string $message, array $connection_ids, string $iso_datetime ): array;
}
```

`JetpackSocial` dispatches internal `rest_do_request()` calls to Jetpack's own publicize controllers (connections, share-post, scheduled actions). Routes, payloads, plan gating and errors are confirmed in spike P3-03. If resharing or scheduling is unavailable on the site's plan, `available()`/`share_at` report it and the UI hides the action with the reason; nothing is emulated.

### 6.6 AI (`AiClient`)

```php
interface AiClient {
    public function supported(): bool;                       // is_supported_for_text_generation(), no network
    /** @return array|WP_Error decoded JSON matching $schema */
    public function json( string $system, string $user, array $schema, string $tier ): array|\WP_Error;
    public function text( string $system, string $user, string $tier ): string|\WP_Error;
}
```

`WpAiClient` builds `wp_ai_client_prompt( $user )` with the system instruction, `using_model_preference( ...Config ai.models.<tier> )`, `using_temperature` only when `ai.temperature` is a number, `using_max_tokens`, and `as_json_response( Schemas::wire( $schema ) )` for `json()`: the wire schema drops `title`, `minLength`, `minimum`, `maxItems` and `minItems` above 1 (Anthropic's structured outputs return 400 for them) and restates them in `description`, while `Schemas::validate()` checks the reply against the full schema. `supported()` asks whether a model takes a system instruction and a JSON schema, since every request needs both. Before each call `Budget::reserve()` checks `ai.monthly_request_cap`; a refusal returns `WP_Error( 'masthead_ai_budget' )`. Prompts live in `Ai/Prompts.php`; schemas in `Ai/Schemas.php` (`ideas`, `outline`, `share_copy`, `series_proposal`). Idea context: lane label and categories, the date, titles + excerpts of posts from the last `ai.context_days` (max `ai.context_max_posts`), in-progress series names, and current gaps, all inside `<untrusted-post-data>`.

Tests and e2e use `FakeAiClient` (returns fixtures from `tests/fixtures/ai/*.json`), installed via the `masthead_ai_client` filter from `tests/e2e/mu/fake-ai.php`; `Ai\Factory` ignores the filter unless the environment is `local`.

### 6.7 Admin screens (match `docs/mockups/`)

- **Posts › Calendar** (`edit.php?page=masthead`): toolbar (prev/next, Today, Month/Week/List, category legend that toggles visibility, "Fill open days" = run suggestions for every empty day in the visible range without ideas), grid, Draft tray, Desk rail. Published = solid chip in category colour; `future` = outlined with left bar and clock glyph; dated draft = tinted with "Draft" label; ghost suggestion = dashed lane-coloured card with kind label; open day = "Open · lane". Clicking a day selects it and loads suggestions in the rail; dragging a `future` post or dated draft to another day calls `masthead/reschedule` with optimistic update and rollback on error.
- **Day plan** (rail expanded, mock `DayPlan`): chosen suggestion with outline, "why this day", date/time and "Save as" (dated draft | scheduled), "Create draft with outline".
- **Post panel** (mock `PostDetail`): swap-check findings with numbers via `StatValue` and options; Share panel with editable message ("Draft with AI" button), connection checkboxes, Share now / Queue for date.
- **Settings › Calendar** (mock `Settings`): rhythm table, series cadence + lane exception, Desk/AI settings, exposure toggles, and a notice when no AI provider supports text generation linking to Settings › Connectors. The mock's "Draft bodies" toggle is `ai.include_draft_excerpts` and is relabelled "Draft titles and excerpts".
- Accessibility: every control is a real `<button>`/`<input>`/`<a>`; day cells are buttons with `aria-label` "Wednesday, November 18: open Standalone slot"; keyboard drag alternative is a "Move to…" date picker on each chip; axe serious/critical = 0 at 1280 and 390.

### 6.8 Hooks

Filters: `masthead_config`, `masthead_now`, `masthead_lane_for_date( $lane, $date )`, `masthead_suggestions( $list, $date )`, `masthead_ai_client` (local only), `masthead_series_taxonomy`. Actions: `masthead_planned_draft_created( $post_id )`, `masthead_rescheduled( $post_id, $old, $new )`, `masthead_shared( $post_id, $result )`, `masthead_findings_updated( $count )`.

## 7. Commands

- lint (PHP): `composer lint` — `php -l` over `src/ tests/` + PHPCS (WordPress-Extra, WordPress-Docs, WordPressVIPMinimum, PHPCompatibilityWP 8.3+)
- test (PHP unit): `composer test:unit` — PHPUnit + Brain Monkey, `tests/unit`
- targeted: `vendor/bin/phpunit -c phpunit.xml.dist --filter {filter}`
- lint (JS/CSS): `npm run lint` — `wp-scripts lint-js client tests/e2e scripts` + `wp-scripts lint-style 'client/**/*.scss'`
- test (JS unit): `npm run test:unit` — Jest via `wp-scripts test-unit-js`
- build: `npm run build` — `wp-scripts build` → `build/` (gitignored)
- constraints: `bash scripts/forbidden-patterns.sh` — §3 greps not expressible as `constraints` entries
- extra, when `src/` or `tests/integration/` is touched: `npm run test:integration` — wp-env (WordPress 7.1.4, PHP 8.5, Jetpack 16.4 unconnected) + PHPUnit `tests/integration`
- extra, when `client/` or `tests/e2e/` is touched: `npm run test:e2e` — `wp-env start && wp-env run cli wp masthead seed --fixture=wp-content/masthead-fixtures/queue-2026-11-09.json --now="2026-11-09 14:00:00" --reset && wp-scripts test-playwright --config tests/e2e/playwright.config.mjs`
- release: `npm run release:pack` — build + `dist/masthead.zip` (includes `build/`, excludes `tests/`, `docs/`, `client/`, dev config) + `dist/masthead.zip.sha256`

`.wp-env.json`: core `WordPress/WordPress#7.1.4`, `phpVersion` 8.5, plugins `[".", "https://downloads.wordpress.org/plugin/jetpack.16.4.zip"]`, mappings `wp-content/masthead-fixtures → ./docs/fixtures`, `wp-content/mu-plugins/masthead-e2e → ./tests/e2e/mu`, config `WP_ENVIRONMENT_TYPE=local`, `MASTHEAD_STATS_FIXTURE=/var/www/html/wp-content/masthead-fixtures/stats-sample.json` (created in P3-01).

## 8. Phases

### Phase 0 — Toolchain is real

P0-01 scaffolds everything in §4.1 and §7 (composer, package.json, wp-env, PHPCS, PHPUnit unit + integration bootstraps, Jest, Playwright + axe, `forbidden-patterns.sh`, `.github/workflows/ci.yml` running §7 verify + integration + e2e, `release.yml` on `v*` tags that checks the tag matches `package.json` and uploads `dist/masthead.zip` + `.sha256`), `masthead.php`, `Config`, `Clock`, `Plugin`, and an empty Posts › Calendar page that renders the React root. **Manual check:** `npx wp-env start`, open Posts › Calendar, see the empty app shell; push a `v0.0.1` tag on a scratch fork and see the release assets.

### Phase 1 — Plan model and read abilities

Rhythm option + activation defaults; `Lanes`; `SeriesSource` adapter (ttm `series` vs `masthead_series`, test both by registering/unregistering a stub `series` taxonomy); `Cadence`; `Colors`; `Slots` (non-floating dated drafts) and `Reschedule`; `PlanReader`; Abilities `Registrar` + category + `get-plan`, `create-planned-draft`, `reschedule`; `wp masthead seed|plan`. **Manual check:** seed the fixture, run `wp masthead plan 2026-11-01 2026-11-30 --format=table`; in Claude Code with the MCP adapter, discover abilities and see `masthead/*`.

### Phase 2 — Calendar UI and settings

Month/Week/List views, chips, legend toggles, Draft tray, drag-to-reschedule with keyboard alternative, Today, hash routing, Settings page (rhythm table, series cadence + lane exception, exposure toggles; AI section shows provider status only). e2e: fixture renders the November 2026 grid as in `Main.dc.html` (2 published chips on Nov 9, outlined chips on Nov 11 and Nov 12, Draft tray holds 10178), dragging 12177 to Nov 10 calls reschedule and persists, axe clean at 1280/390. **Manual check:** compare Posts › Calendar with `docs/mockups/Main.dc.html` side by side in a browser at 1440 and 390.

### Phase 3 — Jetpack adapters

P3-01 spike: `wp masthead stats:probe` design + `docs/spikes/P3-01-jetpack-stats.md` + `docs/fixtures/stats-sample.json` (shape-true, numbers synthetic and labelled synthetic). P3-02 `JetpackStats`, `NullStats`, `FixtureStats`, `Factory`, `Cache`, `stats-summary` ability. P3-03 spike: Jetpack Social routes and plan gating → `docs/spikes/P3-03-jetpack-social.md`. P3-04 `JetpackSocial`, `NullShare`, `share-connections`, `share-post` (respecting `abilities.expose_share`), `SharePanel` without AI copy, writes `masthead_last_shared`. **Manual check (human, on eric.mann.blog after deploying a pre-release build):** run `wp masthead stats:probe` and `social:probe`, confirm shapes match the fixtures; share one post to one connection from the Share panel.

### Phase 4 — Desk (deterministic) and swap check

Candidates, `Ranker`, `Reasons`, `Dismissals`, `suggest-for-date` (no ideas), `dismiss-suggestion`; ghost cards for `desk.ghost_days`; Desk rail and Day plan; `Scoring`, `SwapCheck`, `Cron\Nightly`, `swap-check` ability and CLI, Post panel findings. e2e acceptance: §6.3 Mantle scenario; Nov 18's first suggestion is draft 10178 with why "Wednesday is your Standalone slot. This draft isn't on the calendar; last edited Oct 29."; Nov 18 also lists a `gap` suggestion and no `reshare` (stats unavailable); dismissing the draft suggestion persists across reloads. **Manual check:** run `wp masthead swap-check --write` against the fixture and read the Post panel; confirm no number appears anywhere without "Jetpack Stats" beside it.

### Phase 5 — AI

`AiClient`, `WpAiClient`, `FakeAiClient` + local filter, `Budget`, `Prompts`, `Schemas`; ideas in `suggest-for-date` when `include_ideas`; outline generation in Day plan; `draft-share-copy`; `propose-series` + `accept-series` behind Ask the Desk (proposal shown as a list of dated parts the user can edit, accept creates the series term with cadence and dated drafts); Settings AI section (model tiers, cap, context toggles). Unit tests: invalid AI JSON → `WP_Error` and no partial writes; out-of-lane category ideas dropped; budget exhaustion. **Manual check:** install AI Provider for Anthropic on a local site with a real key, ask the Desk for "a 4-part security series starting the first Thursday in January", accept it, and see four dated drafts on Thursdays.

### Phase 6 — Release and deployment

`readme.txt` (Requires at least 7.1, Tested up to 7.1, Requires PHP 8.3, GPL-2.0-or-later), `README.md` with screenshots generated by Playwright, `CHANGELOG.md`, `docs/DEPLOYMENT.md` (§Appendix B), version `0.1.0` (first stable release: `1.0.0`), tag. **Manual check:** bump the site repository's pinned version, build the image, roll it, and plan one real week from the calendar.

## 9. Open questions

1. **Repository and slug.** Owner (`ericmann/` like ttmm_theme, or `DisplaceTech/`) and whether `masthead` is free as a WordPress.org slug before public release. Planner: use `masthead` everywhere and keep the slug in one constant (`Masthead\SLUG`) so a rename is one line.
2. **Abilities run endpoint from JS.** Confirm the WP 7.1 route and method for executing an ability over REST (and whether `@wordpress/abilities` exposes a client) in a P1 spike; if unsuitable, add a thin `masthead/v1` controller whose handlers call `WP_Ability::execute()` and nothing else.
3. **Jetpack Stats coverage.** Whether views by category and by weekday are available from `WPCOM_Stats` on this site's plan (P3-01 decides; fallback in §6.2).
4. **Jetpack Social plan gating.** Whether re-sharing and scheduled shares need a paid Social plan on this site (P3-03 decides; unavailable actions are hidden with a reason).
5. **Model ids under OpenRouter.** `ai.models.*` defaults are Anthropic ids; with the OpenRouter provider they will not match and the provider default applies. Settings shows the model actually used (from the AI Client result metadata, if exposed; P5 spike).
6. **Rhythm changes over time.** One rhythm for all dates in 0.1. If a dated rhythm history is needed (e.g. a different 2026 rhythm), add `effective_from` later.
7. **Fixture privacy before going public.** `docs/fixtures/queue-2026-11-09.json` holds real titles, including one unpublished draft. Replace with synthetic titles before the repository is made public (P6 task).

## Appendix A — Files in `docs/`

- `mockups/Main.dc.html` — Posts › Calendar month view with Desk rail (interactive: click a day). Real queue as of 2026-11-09.
- `mockups/DayPlan.dc.html` — week view with the Day plan for Wed Nov 18.
- `mockups/PostDetail.dc.html` — scheduled queue with swap-check verdicts, the Mantle lane-mismatch finding, and the Share panel.
- `mockups/Settings.dc.html` — rhythm, series, Desk/AI settings and exposure.
- `mockups/support.js` — runtime for viewing the `.dc.html` mockups in a browser (copied from ttmm_theme; not shipped).
- `fixtures/queue-2026-11-09.json` — real categories, series and posts (IDs, dates, statuses) captured over MCP; the acceptance scenario.
- `fixtures/rhythm-defaults.json` — default weekly rhythm.

In the mockups, `[Jetpack]` marks a number the plugin must read from Jetpack Stats; it is never filled with an estimate.

## Appendix B — Deployment into the site image

The site repository builds an immutable WordPress image and pins ttm-core/ttm-theme release zips by tag. Masthead follows the same path: no submodule, no subtree.

```dockerfile
ARG MASTHEAD_VERSION=1.0.1
ARG MASTHEAD_SHA256=<from dist/masthead.zip.sha256>
RUN curl -fsSL -o /tmp/masthead.zip \
      "https://github.com/<owner>/masthead/releases/download/v${MASTHEAD_VERSION}/masthead.zip" \
 && echo "${MASTHEAD_SHA256}  /tmp/masthead.zip" | sha256sum -c - \
 && unzip -q /tmp/masthead.zip -d "<plugins dir used for ttm-core>" \
 && rm /tmp/masthead.zip
```

- The AI provider is a wordpress.org plugin: add `ai-provider-for-anthropic` (or `ai-provider-for-openrouter`) to the site's `composer.json` with the other plugins, and supply `ANTHROPIC_API_KEY` (or `OPENROUTER_API_KEY`) to the pod from a Kubernetes Secret. Masthead never sees the key.
- Upgrading Masthead is a two-line change (version + checksum) in the site repository; Keel rolls the new image.
- While the Masthead repository is private, the image build needs a read token for the release asset; making the repository public before the first tag avoids that.
