# Summary: build/2026-11-10 (Masthead 0.1.0)

**Merge line:** `build/2026-11-10`, base `main` `b579920` → head `b50b53f`, 194 commits (before this summary commit), 2 reviews, final verdict **APPROVED** (review 2).

Tasks: 91 of 91 done (85 planned and 6 review fixes). None blocked and none skipped. Signing was required, and review 2 found every commit signed.

**CI warning:** when this summary was written, the PR's checks were **failing**: `verify`, `integration` and `e2e`, across two workflow runs. Review 2 saw them as `pending`, and locally lint, unit, integration (127), e2e (106 passed, 4 skipped) and `npm audit --audit-level=high` all passed. Read the failing jobs before you merge. These workflows had never run on GitHub before this branch.

## What was built

- **Phase 0, toolchain (P0-01..P0-09).** Set up the PHP and JS toolchains, the plugin bootstrap with `Config`, `Clock` and `Plugin`, the structural-checks script and module-boundaries test, and the Posts › Calendar admin page. Added wp-env integration and Playwright/axe smoke harnesses, the release packer with LICENSE, and the CI and release workflows.
- **Phase 1, plan model and read abilities (P1-01..P1-17).** Added date and JSON helpers and the weekly rhythm (lanes) with activation defaults. Built primary category and colours, series cadence, and series term meta. Added non-floating dated drafts (`Slots`), `Reschedule`, and `PlanReader`. Spiked the Abilities REST transport. Registered `masthead/get-plan`, `create-planned-draft` and `reschedule`. Added `wp masthead seed` and `wp masthead plan`, and tuned `calendar.max_posts_per_range`.
- **Phase 2, calendar UI and settings (P2-01..P2-12).** Added boot data and the client data layer, and shared components (`StatValue`, `LaneDot`, `CategoryLegend`, chips and day cells). Built month, week and list views with hash routing, legend toggles, the Draft tray, and drag rescheduling with a keyboard "Move to…" alternative. The layout works at 390 px and passes axe. The Settings › Calendar page has a rhythm table, series cadence, exposure toggles and AI provider status.
- **Phase 3, Jetpack adapters (P3-01..P3-10).** Spiked Jetpack Stats and Jetpack Social. Built the Stats core (null, fixture, cache and factory), the `JetpackStats` adapter with `stats:probe`, and `masthead/stats-summary` with an e2e stats switch. Tuned `stats.cache_ttl`. Added the Social adapters with `social:probe`, the `share-connections` and `share-post` abilities, and the Share panel (without AI copy).
- **Phase 4, deterministic Desk and swap check (P4-01..P4-18).** Built the suggestion candidates (matching drafts, next series part, category gaps, reshares), the ranker, dismissals and reason templates, and the Suggester. Added `suggest-for-date`, `dismiss-suggestion` and `wp masthead suggest`, ghost cards with "Fill open days", the Desk rail, and the Day plan. Built scoring and the swap check (lane mismatch, cadence break, collision, better candidate) with a nightly read-only cron, the `swap-check` ability and CLI, and the swap panel with Review/Fits verdicts. Tuned the Desk and swap defaults.
- **Phase 5, AI (P5-01..P5-14).** Spiked the WordPress AI Client. Built the `AiClient` interface with a strict schema validator, prompts that wrap all post data in `<untrusted-post-data>`, `WpAiClient` with a fake client and fixtures, and the monthly budget as the `BudgetedAi` decorator. Added ideas in `suggest-for-date`, the `draft-outline` and `draft-share-copy` abilities, and `SeriesPlanner` with `propose-series` and `accept-series`, where Masthead computes all dates. Added Ask the Desk, the AI fields in Settings with `ai:status`, and AI tuning.
- **Phase 6, release (P6-01..P6-05).** Replaced the fixture titles with synthetic ones. Added `readme.txt`, `CHANGELOG.md` and version 0.1.0, a README with Playwright-generated screenshots, and `docs/DEPLOYMENT.md`.
- **Review fixes (R1-01..R1-06).** Added npm overrides so `npm audit --audit-level=high` passes. The swap check now flags a collision between two non-Journal posts even when a Journal post shares the date. The ideas prompt lists every category on an any-category lane. Ask the Desk has an optional "Start on" date. `suggest-for-date` is no longer annotated idempotent. Added boundary tests for reshare min-age and cooldown.

## Decisions that shaped it

From PLAN.md Decisions:

- (P0-01) The slug is `masthead` everywhere, held in `Masthead\SLUG`, and the Composer name is `ericmann/masthead`.
- (P1-09, P1-13) Decision `core-endpoint`: the UI runs abilities over core's `/wp-abilities/v1/abilities/<name>/run` (GET for read-only, POST otherwise, DELETE only for destructive and idempotent), with no `masthead/v1` bridge. `show_in_rest` is always true. `public` follows `abilities.expose_mcp`, or `expose_share` for `share-post`. A read-only ability whose input is all optional needs `default => array()`. The MCP Adapter's own exposure key is still `UNCONFIRMED` (fallback `meta.public`).
- (P3-01) Decision `categories-from-top-posts`: views by category come from the top-posts payload plus each post's primary category. There is no native per-category call.
- (P3-03) Decision `share-and-schedule`, over Jetpack Social's internal REST routes. Connection IDs that were not chosen are passed as `skipped_connections`. Unavailable actions are hidden with a reason.
- (P5-01) Decision `model-id-exposed`: the model id is read from `generate_text_result()` (`getModelMetadata()->getId()`).
- (plan) One rhythm covers all dates in 0.1, with no `effective_from`.
- (P6-01) Fixture privacy: the six non-series titles became synthetic and "The Mantle" was kept. The mockups and git history still hold real titles.
- (plan) Phase 0 was split into P0-01..P0-08, with every constraint active from P0-01. The spike ids P3-01 and P3-03 were kept. There are no parallel streams.
- (P1-03) The primary category is the category with the lowest `term_id`.
- (plan) `masthead.php` registers its own PSR-4 autoloader. The zip has no `vendor/` and no runtime Composer packages.
- (P1-06, P1-07) `Slots` and `Reschedule` pin `post_status` with a temporary `wp_insert_post_data` filter, because core flips `future` to `publish` by the wall clock.
- (P1-14) The e2e clock pin lives in option `masthead_seed_now`, read only by `tests/e2e/mu/pin-now.php`.
- (P3-05) `MASTHEAD_STATS_FIXTURE` is defined by an mu-plugin only when option `masthead_e2e_stats` is `1`, so the default e2e state is "stats unavailable".
- (P5-05) The budget reservation lives in the `Desk\BudgetedAi` decorator, because `Ai/` may not import `Desk/`. A cap of 0 disables AI and writes nothing.
- (P5-07) Added a thirteenth ability, `masthead/draft-outline`. It is annotated read-only (it saves nothing) with `idempotent=false` (it spends budget).
- (P4-13) A lane exception from a finding is written through `wp/v2/<series>` term meta, with no ability.
- (plan) Added Config keys: `calendar.max_range_days` 92, `desk.candidate_pool` 50, `stats.top_posts_limit` 10, `ai.series_max_parts` 12, `swap.collision_exempt_categories` `['journal']`. None is settable.
- (plan) JS unit tests live in `tests/js/`. `test:e2e` and `test:integration` build first.
- (plan) `policies.signing` is `required`. The signed `v0.1.0` tag is a human step after merge.

From HANDOFF.md Interpretation choices:

- (P0-01) `autoload-dev` maps the Unit, Integration and Support test namespaces. Planner keys such as `calendar.max_range_days` are not settable.
- (P1-06) An invalid explicit time is `masthead_invalid_date`. An empty time means the lane's time.
- (P1-08) `PlanReader::unscheduled()` bounds undated drafts with `desk.candidate_pool` through a temporary `posts_where` filter. `range()` always includes them.
- (P1-11) An empty title is `masthead_missing_title` (400).
- (P1-14) The seeded post author is the first administrator.
- (P2-03) The day cell's accessible post count counts visible posts only.
- (P2-04) Notices use plain `notice notice-*` markup instead of `@wordpress/components` `Notice`, which is ESM-only under jest.
- (P2-09) Core's `wp/v2/settings` rejects unknown keys with a 400. The sanitiser still drops them on other write paths.
- (P2-10, P2-11) Settings read categories and exposure from boot data, because the settings entity holds only overrides.
- (P3-02) `by_weekday` omits weekdays that have no rows.
- (P3-07) A Jetpack error code that matches a fixed pattern (not connected, plan, quota, no route) becomes "unavailable".
- (P4-01, P4-06) `final` was removed from many Desk and Plan classes so unit tests can subclass them.
- (P4-03) A category is a gap when the days since it last published are strictly greater than `desk.gap_days`. A category that never published scores 1.0.
- (P4-14, P4-15) The swap check and the nightly run cover `swap.lookahead_days`. A series with one part in the window is examined through its last scheduled part. `desk.horizon_days` bounds only "Fill open days".
- (P5-02) The schema validator is strict: no integer coercion, strict enum comparison, and an empty array is a valid empty object.
- (P5-03) `Prompts::untrusted()` strips tags repeatedly so nested tags cannot reassemble.
- (P5-04) Provider errors and exceptions both become `masthead_ai_invalid`, with the provider code kept in the error data. An unknown tier maps to `strong`. Fenced JSON is rejected, not repaired.
- (P5-06) The idea context window is anchored on the target day. Out-of-lane ideas are dropped one by one instead of failing the whole reply.
- (P5-08) `draft-share-copy` refuses a non-published post with a 409 before any AI call.
- (P5-09) The first part's date is the first day within a week of the start whose lane lists the category, or else the start itself. An empty `parts` list is invalid.
- (P5-10) `accept-series` stores the cadence only when one is given. A part's time is the cadence time, or else the lane time. On any failure it rolls back the drafts and then the term.
- (P5-12) Settings shows the last model used per tier, or "does not report which model was used" until one is recorded.
- (P6-02) `readme.txt` names no person (it has no `Contributors:` line). No git tag was created.
- (R1-01) Overrides for `isomorphic-git ^1.44.12` and `markdown-it ^14.3.3` (a major jump for markdownlint), plus `minimatch ^3.1.5` scoped to `markdownlint-cli`. The lockfile was regenerated from scratch.

## Assumptions still in play

Every default lives only in `Config::defaults()`. Each key below was measured where it could be, and every default was kept.

| Key | Default | Status |
|---|---|---|
| `calendar.max_posts_per_range` | 500 | tuned (P1-16), kept |
| `desk.horizon_days` | 21 | tuned (P4-11), kept |
| `desk.ghost_days` | 14 | tuned (P4-11), kept |
| `desk.gap_days` | 45 | tuned (P4-11), kept |
| `desk.reshare_min_age_days` | 90 | still a guess: the sample statistics could not judge it |
| `desk.reshare_cooldown_days` | 180 | tuned (P4-11), kept |
| `desk.series_window_days` | 3 | tuned (P4-11), kept |
| `swap.lookahead_days` | 60 | tuned (P4-17), kept |
| `swap.min_score_delta` | 0.25 | tuned (P4-17), kept |
| `swap.candidate_window_days` | 3 | tuned (P4-17), kept |
| `swap.weights` | 0.5 / 0.3 / 0.2 | tuned (P4-17), kept |
| `stats.cache_ttl` | 21600 | tuned (P3-06), kept |
| `ai.monthly_request_cap` | 300 | tuned (P5-13), kept (covers 95 days of the stated usage pattern) |
| `ai.context_max_posts` | 60 | tuned (P5-13), kept (about 7120 estimated tokens) |
| `ai.max_tokens` | 1200 | measured as too small: a worst-case 12-part series needs about 2565 tokens. Kept anyway |
| `ai.models.fast`, `ai.models.strong` | Anthropic ids | still a guess: not measured, needs a real provider |
| `ai.temperature` | 0.4 | still a guess: not measured, needs a real provider |

## Spec issues

Edits to make to SPEC.md. Plan-time issues (PLAN.md, each resolved in the build as described):

1. SPEC §8 makes Phase 0 one task. The plan split it into P0-01..P0-08.
2. "Primary category" is never defined. The build uses the lowest `term_id`, the only simple rule under which §6.3's acceptance holds for post 10194.
3. `Budget` sits in `Desk/` but `WpAiClient` must call it (§4.2 against §6.6). Resolved with the `BudgetedAi` decorator. `Desk` may import `Ai\Prompts` and `Ai\Schemas` by name.
4. The `masthead_settings` shape (§5.1) omits the settable `swap` and `stats` groups.
5. `swap.lookahead_days` and `desk.horizon_days` both contradict the §6.3 acceptance (findings 35, 63 and 91 days out). The nightly run covers the lookahead and follows a series through its last part.
6. The pinned clock cannot pin WordPress core's `future` → `publish` flip. Statuses are pinned during writes, `DISABLE_WP_CRON` is set, and there is no e2e test for "Save as: Scheduled".
7. `MASTHEAD_STATS_FIXTURE` in `.wp-env.json` (§7) conflicts with the Phase 4 acceptance. The constant moved to an option-gated mu-plugin, and a loader was added for the mu-plugin subdirectory.
8. `wp_insert_post` confinement against the seeder: the seeder goes through `Slots::insert()` and `Slots::delete()`.
9. One finding option needs no ability: the lane exception is written as term meta over `wp/v2`, and options that move several posts carry an `also` list.
10. Outline generation has no ability. The build added `masthead/draft-outline`.
11. Additive response fields: `unscheduled`, `truncated`, `modified`, `category`, `cadence`, `reason` and `ai.error`.
12. `usePlan` takes all plan data from `get-plan`, not from core-data posts.
13. The Config keys added outside §5.4, listed under Decisions.
14. Storage SPEC does not name: `masthead_dismissed`, `masthead_seed_now`, `masthead_e2e_stats`, and the suggestion key forms `series:` and `gap:`.
15. `hard-coded-tunable` is a line pattern, with the `const` exemption and a second rule, `tunable-by-name`.
16. `seed` runs only when the environment type is `local`.
17. §7 command details, and audits running in CI and at phase ends.
18. Rhythm defaults on a site that lacks the categories turn into any-category lanes, as written.
19. Fixture privacy scope: the mockups and git history are left to the owner.
20. The tag is left to a human after merge.
21. Commit signing is set to `required`.

Raised in review (rounds 1 and 2). All are still open and need an owner decision:

- **`prompt_ai` does not exist in WordPress 7.1.4** (§3.4, §6.1, §4.4). No role holds it, so on a stock site every AI feature is denied to every user. SPEC should say whether Masthead maps it (for example to `manage_options` through `user_has_cap`) or whether the site must grant it.
- **`ai.max_tokens` = 1200 cannot hold a worst-case series proposal** (12 parts, about 2565 tokens), and the key is not settable. Raise the default, make the key settable, or lower `ai.series_max_parts`.
- **§5.2 and §2 conflict on public REST.** Series term meta registered with `show_in_rest` adds a `meta` object to `wp/v2/series` responses, which the theme may read.
- **CLAUDE.md "Masthead never changes a post's `post_status`" vs SPEC §6.7 "Save as … scheduled".** Narrow the CLAUDE.md wording to the server-side write paths.
- **§3.5 `npm audit --audit-level=high`** (round 1, conditional). R1-01 resolved every high advisory, so nothing is needed now. If a future advisory has no fix an override can reach, the owner must decide (for example on `--omit=dev`).

Known-gap candidates: none. The final REVIEW.md lists none ("No `Known-gap candidates`: this flight did not run in clean-room mode.").

## Manual checks owed

None has been performed. All are in `docs/manual-checks.md`.

- **Phase 0:** check that the CI and release workflows run on GitHub (they are currently failing on the PR; see the top of this summary) and that the release path works end to end.
- **Phase 1:** seed and run `wp masthead plan`. Check MCP discovery with the MCP Adapter. List the `UNCONFIRMED` spike facts.
- **Phase 2:** compare the calendar and settings with the mockups at 1440 and 390 px. Drag with a mouse. Use the keyboard "Move to".
- **Phase 3:** on a real site, run `stats:probe` and `social:probe`. Share one post and try a queued share.
- **Phase 4:** compare the swap check, swap panel and Day plan with the mockups. Confirm that no number appears without the "Jetpack Stats" attribution. Judge the kept Desk and swap defaults.
- **Phase 5:** grant `prompt_ai` first. With a real provider, ask for "a 4-part security series starting the first Thursday in January" using the new "Start on" field (R1-04), and accept it. Check that ideas, outlines and share copy make no traffic, date or history claims. Check the OpenRouter default model. Judge `ai.temperature` and the model lists. Try a 12-part series, which is expected to fail on `ai.max_tokens`. Check the budget message at cap 1.
- **Phase 6:** tag the signed `v0.1.0` after merge and pin it in the site image. Before making the repository public, decide on the git history, the mockups, "The Mantle", the slug and the repository owner.
- **Round 1:** ask for a series with a "Start on" date on a live site.

## Review history

- **Round 1:** CHANGES REQUESTED. 6 findings (F1–F6). 6 fix tasks: 5 from review, 1 from mutation, 0 from blocked. 0 unblocked. Converging. 31 mutants, 2 survived (Reshare).
- **Round 2:** APPROVED. 0 findings, 0 fix tasks, no recurrence: none of the R1 task ids reappeared. This was a notes-only approval, because REVIEW.md carries a `## Notes` section: the `markdown-it` 12→14 override matters only if markdown linting is added later; the R1-06 fixture uses `-07:00` where November in Los Angeles is `-08:00`, which is harmless; and three round 1 advisories still apply (see the next sections).

## Pipeline friction

None (`feedbackCount` 0). The R1 handoff did note that R1-06's mutants could not be re-run because no `kukulu_mutate` tool was available in that implement session. The round 2 reviewer re-ran them and both were killed.

## Suggested CLAUDE.md additions

From HANDOFF.md `## Codebase notes`:

- **Constraint regexes match prose.** They scan docblocks and strings too. `hard-coded-tunable` flags any digit of 2 or more (even "ISO 8601" or `strlen('Y-m-d')`), so use named private consts (`BAD_REQUEST` and similar) or `Config::get()`. `one-clock` flags "date (" in prose. `writes-confined` flags post-write function names in comments. `stats-confined` and `social-confined` flag the words `WPCOM_Stats` and `publicize`. `dangerous-php` flags `system(`, so the prompt helper is called `instructions()`.
- **Module boundaries** are enforced by `tests/unit/BoundariesTest.php`. `Desk` may import `Plan`, `Config`, `Support`, and only `StatsSource`, `ShareSource`, `AiClient`, `Prompts` and `Schemas`. `Cli` may not import `Ai`.
- **One AI client, built once in `Plugin::boot()`:** `Ai\Factory::make()` wrapped in `Desk\BudgetedAi`. The `masthead_ai_client` filter is honoured only when `wp_get_environment_type()` is exactly `local`.
- **Unit tests** (PHPUnit 9.6 and Brain Monkey) never load WordPress. The first `Filters\expectApplied` for a hook wins. Stub `wp_date` when a class uses `Clock::format()`. VIP sniffs forbid `mkdir`, `file_put_contents` and `unlink` even in tests, so use committed fixtures. Tuning benches are `*Bench.php` in group `tuning`.
- **Integration tests** run in wp-env and must call `->register()` themselves. The e2e mu-plugins (fake AI, `prompt_ai` grant) are active there too. Use far-future dates with the real clock.
- **E2E:** pin the clock with `masthead_seed_now`, switch stats with `masthead_e2e_stats`, and turn off the fake AI with `masthead_e2e_ai=0`. Set `start_of_week` to 0. Run `npm run build` first. Run the full suite in the background. Playwright specs cannot use `import.meta`.
- **Jest:** `@wordpress/core-data` is stubbed through `moduleNameMapper` and `TextEncoder` is polyfilled. Avoid `@wordpress/components` in unit-tested code. `jest.mock` factories may only reference `mock*` variables. `uuid` is overridden to `^11.1.1`.
- **Lint quirks:** `label-has-associated-control` needs both `htmlFor`/`id` and nesting. axe's `label-content-name-mismatch` needs the visible text inside the `aria-label`. Prettier can make scripted replacements silently miss. Format only `tests/js` and `tests/e2e`.
- **Settings** store only changed keys, and `Config::sanitize_overrides()` drops invalid values. Boot value `settingsDefaults` carries the effective values.
- **Release:** `version-agree` in `scripts/forbidden-patterns.sh` compares the plugin header, `Masthead\VERSION`, `package.json`, the `readme.txt` Stable tag and the newest `CHANGELOG.md` heading. `pack.mjs` requires `readme.txt`, `README.md` and `CHANGELOG.md` for any version other than 0.0.0.

## Advisory mutation survivors

None. Both round 1 survivors were in the diff, were queued as R1-06, and were killed in round 2. The advisory code notes carried in REVIEW.md `## Notes` are not mutation survivors, but they are copied here verbatim:

- The advisory notes from round 1 still apply and need no task: `PlanReader::range()` counts undated drafts toward the cap before filtering them out; `SwapCheck` shows a category missing from the top posts as `0`; `SeriesSource` registers for `post` rather than `Config::get( 'post_types' )`.

## Breaking call

Breaking: no. CHANGELOG.md decides: its newest entry, `[0.1.0] - 2026-11-09`, has no `### Breaking changes` section. This is the first release, so no existing user relies on earlier behaviour.
