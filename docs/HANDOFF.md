# Handoff: Masthead 0.1.0 initial build

- Branch: `build/2026-11-10`
- Base: `main` at `b579920de20347a0aed89b93b6f31b1d8d041fe9`
- Head before this file: `5f829832a3af19de78addfca9b26a479d1dd1223` (`kukulu_run_finish` commits this file on top)
- Run policies: signing required (every commit is GPG-signed), push on, pull request draft.
- Tasks: 85 total, 85 done, 0 blocked, 0 skipped.
- Final `kukulu_verify` (no files): constraints 18 of 18 pass; `composer lint`, `composer test:unit` (298 tests), `npm run lint`, `npm run test:unit` (152 tests) and `bash scripts/forbidden-patterns.sh` pass.
- Also run during the build, outside `kukulu_verify`: integration suite 126 tests (wp-env, WordPress 7.1.4, PHP 8.5, Jetpack 16.4), end-to-end suite 106 passed and 2 skipped (desktop and mobile, with axe; run before the P6-02 to P6-05 changes, which touch no client or e2e code except the screenshot spec), `npm run release:pack` (99 entries, checksum verified), `npm run screenshots`. `composer audit` reports no advisories; `npm audit --omit=dev` reports 0; the full `npm audit` reports 57 (17 low, 35 moderate, 5 high), all transitive dev tooling with no non-breaking fix.

## Blocked tasks

None.

## What to look at first

1. **The `prompt_ai` capability does not exist in WordPress 7.1.4.** Nothing under `wp-includes` or `wp-admin` defines or grants it and no default role (administrator included) holds it (`docs/spikes/P5-01-ai-client.md`, fact 7). Every AI ability and every AI button requires `prompt_ai` as the SPEC says, so on a stock site no user sees any AI feature. The tests grant it through the e2e mu-plugin `tests/e2e/mu/fake-ai.php`. `README.md`, `readme.txt` and `docs/DEPLOYMENT.md` tell site owners to grant it. Decide whether Masthead should map it (for example administrators through `user_has_cap`) in a follow-up; this build did not, because the SPEC makes it a core capability.
2. **No AI behaviour was exercised against a real provider.** The WordPress AI Client was read and probed in wp-env, where no provider is configured. The shape of a JSON-constrained reply and the fallback when no configured model id matches are `UNCONFIRMED` in the spike. `WpAiClient` decodes the text itself and rejects anything that is not a JSON object or that fails `Schemas::validate()`; fenced JSON is rejected, never repaired.
3. **`ai.max_tokens` (1200) cannot hold a worst-case series proposal** (12 parts of six 120-character lines is about 2565 estimated tokens), and `docs/tuning/P5-13.md` found no measured value that fits. The default was kept. A reply cut off by the limit is invalid JSON, so the Desk reports an invalid reply and creates nothing. `ai.max_tokens` is not a settable key; a site can change it only through the `masthead_config` filter.
4. **Workflows have never run on GitHub** (`.github/workflows/ci.yml` and `release.yml`). They are checked only by a jest test that parses them.

## Interpretation choices

Each is a place where the plan left room and this build chose.

| Task | Choice |
|---|---|
| P0-01 | `autoload-dev` also maps `Masthead\Tests\Unit\`, `Masthead\Tests\Integration\` and (P5-04) `Masthead\Tests\Support\` to their lowercase directories. Planner keys such as `calendar.max_range_days` are not settable. |
| P1-03 | The primary category of a post is the category term with the lowest term ID. |
| P1-06 | An invalid explicit time is `masthead_invalid_date`; an empty time means the lane's time. |
| P1-08 | `PlanReader::unscheduled()` uses a temporary `posts_where` filter so `desk.candidate_pool` bounds undated drafts only; `range()` always includes them. |
| P1-09, P1-13 | Decision `core-endpoint`: the admin UI runs abilities over core's `/wp-abilities/v1/abilities/<name>/run` (GET for read-only, POST otherwise, DELETE only for destructive and idempotent). `show_in_rest` is always true; `public` follows `abilities.expose_mcp` (or `expose_share` for `share-post`). A read-only ability with only optional input needs `default => array()` on its input schema. The MCP Adapter's own exposure key stays `UNCONFIRMED` (fallback `meta.public`). |
| P1-11 | An empty title is `masthead_missing_title` (400). |
| P1-14 | The seeded post author is the first administrator. |
| P2-03 | The day cell's accessible post count uses visible posts only. |
| P2-04 | Plain `notice notice-*` markup instead of `@wordpress/components` `Notice` (the package is ESM-only under jest). |
| P2-09 | Core's `wp/v2/settings` rejects unknown keys on an object setting with 400, so the test asserts that; the sanitiser still drops them on other write paths. |
| P2-10, P2-11 | Settings read categories from boot data and exposure from a boot value, because the settings entity holds overrides only. |
| P3-01 | Decision `categories-from-top-posts`: views by category are derived from the top-posts payload and the post's primary category; there is no native per-category call. |
| P3-02 | `by_weekday` omits weekdays with no rows. |
| P3-03 | Decision `share-and-schedule` over Jetpack Social's internal REST routes; non-chosen connection IDs are passed as `skipped_connections`. |
| P3-07 | A Jetpack error code matching a fixed pattern (not connected, plan, quota, no route and similar) becomes "unavailable". |
| P4-01, P4-06 | `final` was removed from `PlanReader`, `SeriesSource`, `DraftMatch`, `SeriesNext`, `Reshare`, `CategoryGap`, `Dismissals` (and later `Reasons`, `Suggester`, `SwapCheck`, `Ideas`, `Budget`, `ShareCopy`, `SeriesPlanner`) so unit tests can subclass them. |
| P4-03 | A category is a gap when days since its last publish is strictly greater than `desk.gap_days`; never-published scores 1.0. |
| P5-01 | Decision `model-id-exposed`: the model id is read from `generate_text_result()` (`getModelMetadata()->getId()`), not `generate_text()`. |
| P5-02 | The schema validator is strict: integers are `is_int` (no coercion), enums use strict comparison, an empty array is a valid empty object. |
| P5-03 | `Prompts::untrusted()` strips the tags repeatedly so nested tags cannot reassemble. |
| P5-04 | Provider errors and exceptions both become `masthead_ai_invalid` (the provider code is kept in the error data); an unknown tier maps to `strong`; the fake client is required lazily because mu-plugins load before the plugin autoloader. |
| P5-05 | Budget reservation lives in a Desk decorator (`BudgetedAi`) because `Ai/` may not import `Desk/`; cap 0 disables AI and writes nothing. |
| P5-06 | The idea context window is anchored on the target day; ideas are validated against every category and out-of-lane ideas are then dropped one by one rather than failing the whole reply. |
| P5-07 | `masthead/draft-outline` is annotated read-only (it saves nothing) with `idempotent=false` (it spends budget). |
| P5-08 | `masthead/draft-share-copy` refuses a non-published post with 409 before any AI call; the button confirms before replacing text. |
| P5-09 | `SeriesPlanner` takes an optional clock; the first part's date is the first day within a week of the start whose lane lists the category, else the start. An empty `parts` list is invalid. |
| P5-10 | `accept-series` stores the cadence only when one is given; a part's time is the cadence time, else the lane time; it rolls back drafts then the term on any failure. |
| P5-12 | With `model-id-exposed`, Settings shows the last model per tier, and shows "does not report which model was used" while none is recorded yet. Number inputs keep their typed text so a field can be cleared and retyped. |
| P6-01 | Synthetic titles were chosen to keep the same alphabetical order so title tie-breaks do not change; `stats-sample.json` also had three real titles and was changed too. "The Mantle" and its part titles were kept. |
| P6-02 | `readme.txt` names no site or person (no `Contributors:` line). No git tag was created. |

## ASSUMPTION config keys

Defaults are in `Config::defaults()` only. "Tuned" means a harness measured it and a decision rule was applied; every default was kept.

| Key | Default | Settable | Tuning | Result |
|---|---|---|---|---|
| `calendar.max_posts_per_range` | 500 | no | P1-16 | kept |
| `desk.horizon_days` | 21 | yes | P4-11 | kept |
| `desk.ghost_days` | 14 | yes | P4-11 | kept |
| `desk.gap_days` | 45 | yes | P4-11 | kept |
| `desk.reshare_min_age_days` | 90 | yes | P4-11 | kept (the sample statistics could not judge it) |
| `desk.reshare_cooldown_days` | 180 | yes | P4-11 | kept |
| `desk.series_window_days` | 3 | yes | P4-11 | kept |
| `swap.lookahead_days` | 60 | yes | P4-17 | kept |
| `swap.min_score_delta` | 0.25 | yes | P4-17 | kept |
| `swap.candidate_window_days` | 3 | yes | P4-17 | kept |
| `swap.weights` | 0.5 / 0.3 / 0.2 | yes | P4-17 | kept |
| `stats.cache_ttl` | 21600 | no | P3-06 | kept |
| `ai.monthly_request_cap` | 300 | yes | P5-13 | kept (covers 95 days of the stated pattern) |
| `ai.context_max_posts` | 60 | no | P5-13 | kept (about 7120 estimated tokens) |
| `ai.max_tokens` | 1200 | no | P5-13 | kept; the worst-case series reply fits no measured value (see above) |
| `ai.models.fast`, `ai.models.strong` | Anthropic ids | yes | P5-13 | NOT MEASURED (needs a configured AI provider; human) |
| `ai.temperature` | 0.4 | no | P5-13 | NOT MEASURED (needs a configured AI provider; human) |

## Manual checks

All are in `docs/manual-checks.md` and none was performed. Each phase-end task logged "Manual check: NOT VERIFIED (human)".

- Phase 0: workflows run on GitHub; the release path.
- Phase 1: seed and `wp masthead plan`; MCP discovery with the MCP Adapter; list `UNCONFIRMED` spike facts.
- Phase 2: compare the calendar and settings with the mockups at 1440 and 390 px; drag with a mouse; keyboard "Move to".
- Phase 3: on a real site run `stats:probe` and `social:probe`; share one post; try a queued share.
- Phase 4: swap check and panel against the mockups; confirm no number appears without "Jetpack Stats"; Day plan against the mockups; judge the kept Desk and swap defaults.
- Phase 5: a real provider: ask for a 4-part security series and accept it; ideas, outline and share copy contain no traffic, date or history claim; the OpenRouter default model; judge `ai.temperature` and the model lists; try a 12-part series; the budget message at cap 1. Grant `prompt_ai` first.
- Phase 6: tag `v0.1.0` after merge; pin it in the site image; decide on history, mockups, "The Mantle", the slug and the repository owner before making the repository public.

## Codebase notes

- **Constraint regexes match prose.** They scan docblocks and strings too. `hard-coded-tunable` flags any digit of 2 or more (even "ISO 8601" or `strlen('Y-m-d')`); use named private consts (HTTP codes use `BAD_REQUEST` and similar) or `Config::get()`. `one-clock` flags the words "date (" in prose; `writes-confined` flags the post-write function names in comments; `stats-confined` and `social-confined` flag the words `WPCOM_Stats` and `publicize`; `dangerous-php` flags `system(` (the prompt helper is `instructions()`).
- **Module boundaries** are enforced by `tests/unit/BoundariesTest.php`. `Desk` may import `Plan`, `Config`, `Support` and only `StatsSource`, `ShareSource`, `AiClient`, `Prompts`, `Schemas`. `Cli` may not import `Ai`, so `AiStatusCommand` takes `Desk\Ideas`.
- **One AI client, built once in `Plugin::boot()`:** `Ai\Factory::make()` wrapped in `Desk\BudgetedAi`. The `masthead_ai_client` filter is honoured only when `wp_get_environment_type()` is exactly `local`. Nothing reads or stores a credential.
- **Unit tests** (PHPUnit 9.6 and Brain Monkey) never load WordPress. The first `Filters\expectApplied` for a hook wins, so vary config through a property read inside one filter. Stub `wp_date` when a class uses `Clock::format()`. The WordPress VIP sniffs forbid `mkdir`, `file_put_contents` and `unlink` even in tests, so use committed fixtures. Tuning harnesses are `*Bench.php` in group `tuning` (`--group tuning`).
- **Integration tests** run in wp-env and must call `->register()` themselves because meta is cleared between tests. The e2e mu-plugins also load in the integration environment, so the fake AI client and the `prompt_ai` grant are active there. Use far-future dates when a test uses the real clock.
- **End-to-end tests** pin the clock with option `masthead_seed_now` (`tests/e2e/mu/pin-now.php`), switch fixture stats with `masthead_e2e_stats`, and the fake AI client can be switched off with `masthead_e2e_ai=0`. wp-env's default `start_of_week` is Monday, so specs set it to 0. Run `npm run build` before e2e. The Bash tool backgrounds commands after about 120 s, so run the full e2e in the background. Playwright specs cannot use `import.meta`.
- **Jest:** `@wordpress/core-data` is stubbed through a `moduleNameMapper`, `TextEncoder` is polyfilled, avoid `@wordpress/components` in unit-tested code, and `jest.mock` factories may only reference variables prefixed `mock`. `package.json` overrides `uuid` to `^11.1.1` (v14 is ESM-only).
- **Lint quirks:** `jsx-a11y/label-has-associated-control` needs both `htmlFor`/`id` and nesting; axe's `label-content-name-mismatch` needs the visible text inside the `aria-label`; prettier reformats files, so scripted string replacements can silently miss (assert the match). `wp-scripts format` also rewrites JSON fixtures, so format only `tests/js` and `tests/e2e`.
- **Settings** store only changed keys (`masthead_settings` is nested by dotted path); `Config::sanitize_overrides()` drops invalid values so the default applies. Boot value `settingsDefaults` carries the effective values of the settable keys.
- **Release:** `scripts/forbidden-patterns.sh` `version-agree` compares the plugin header, `Masthead\VERSION`, `package.json`, the `readme.txt` Stable tag and the newest `CHANGELOG.md` heading. `scripts/release/pack.mjs` requires `readme.txt`, `README.md` and `CHANGELOG.md` for any version other than 0.0.0. `npm run screenshots` rewrites `docs/screenshots/*.png`; the normal e2e run skips it.

## Reviewer notes

- Read `src/Abilities/` for permission callbacks (`prompt_ai` on `draft-outline`, `draft-share-copy`, `propose-series` and on `suggest-for-date` with `include_ideas`), `src/Cron/Nightly.php` for the read-only rule, and `src/Ai/WpAiClient.php` (the only caller of the AI Client) with `src/Desk/BudgetedAi.php`.
- AI-written text reaches the UI only after `Schemas::validate()` accepts it; every post-derived string in a prompt is wrapped in `<untrusted-post-data>` and only titles and excerpts are sent, never content.
- Series dates are computed by `Desk\SeriesPlanner` and `Plan\Cadence`, never by the AI. `accept-series` validates everything before writing and rolls back on any failure.
- `docs/mockups/`, `docs/SPEC.md` and `docs/PLAN.md` still name the real site and show real planned titles. They were not changed (the task forbade it); see the Phase 6 manual checks before making the repository public. Git history still contains the real fixture titles.
- Two end-to-end tests are skipped by design: a project-scoped skip in `tests/e2e/responsive.spec.js` and one in `tests/e2e/reschedule.spec.js`. The screenshot capture spec skips itself unless `MASTHEAD_SCREENSHOTS=1`.
