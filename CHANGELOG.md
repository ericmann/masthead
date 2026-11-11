# Changelog

All notable changes to Masthead are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [1.0.2] - 2026-11-11

### Security

- Development dependencies only; nothing in the release zip changes. `qs` is pinned to `~6.16.2` (GHSA-6pq8-jhm2-2vcw), and every `minimatch` from 7.0.0 up to 11.0.0 resolves to 11.x (GHSA-g3hv-75wg-m249), both through `overrides` in `package.json`.
- Not fixed, and accepted as dev-only risks: `deepmerge` 4.3.1 (GHSA-gcqx-w48m-w2h4), which has no patched release and reaches the plugin's scripts only through WordPress core's own `wp-data` and `wp-components`, and `glob` 10 and 13 (GHSA-3g3m-2fjr-6pw4), patched only in 14.0.2. Nothing in the toolchain cancels a glob walk with an `AbortSignal`, which is the only case that bug affects.

## [1.0.1] - 2026-11-10

### Fixed

- AI requests with the Anthropic provider. Masthead always sent a temperature, but Claude 5 models reject sampling parameters, and the Anthropic plugin doesn't list `temperature` for them. A required option a model doesn't list removes it from the WordPress AI Client's candidates, so the configured `claude-haiku-5-5` and `claude-sonnet-5-5` were skipped for an older model, or for none at all. `ai.temperature` now defaults to `null`, which means none is sent; a number set through `masthead_config` is still sent.
- AI requests carried JSON Schema keywords that Anthropic's structured outputs reject with a 400 (`minLength`, `minimum`, `maxItems`). The provider now gets `Schemas::wire()`, which leaves them out and restates each one in the field's `description`. `Schemas::validate()` still checks every reply against the full schema.
- The AI provider counted as available when it could generate any text at all. It now counts only when a model takes a system instruction and a JSON schema, which every Masthead request needs. With OpenRouter's 1.0.0 plugin, whose models declare neither, Settings › Calendar says *Provider: none Masthead can use* and the AI buttons stay off, instead of every request failing.

### Changed

- README and readme.txt list which AI provider plugins work with Masthead, and why.
- The no-provider notice says which features are off; the rest of the Desk keeps working.
- The README plays the three-minute demo inline (a GitHub video attachment), in place of a poster that linked to the release.

## [1.0.0] - 2026-11-10

### Added

- `prompt_ai` fallback: WordPress 7.1 neither defines nor grants `prompt_ai`, so the AI features were denied to everyone on a stock site. When no role, user or plugin has set `prompt_ai`, Masthead now grants it to users who hold the capability named by the new non-settable Config key `ai.fallback_capability` (default `publish_posts`). An explicit grant or deny of `prompt_ai` always wins. The AI section of Settings > Calendar says who has access.

### Changed

- `ai.max_tokens` is set per request kind: 4000 for series proposals and 1200 for ideas, outlines, share copy and plain text. A worst-case 12-part proposal (about 2565 estimated tokens) was cut off and rejected at the old single limit of 1200. A `masthead_config` filter that sets `ai.max_tokens` to one number still applies it to every kind. Measurements are in `docs/tuning/P5-13.md`.
- The e2e fake AI mu-plugin no longer grants `prompt_ai`; the tests rely on Masthead's own fallback.
- `CLAUDE.md` limits "never changes a post's status" to the server-side write paths (`Plan\Slots`, `Plan\Reschedule`); the Day plan's "Save as scheduled" sets `future` after a click.
- The design mockups in `docs/mockups/` use the synthetic post titles from the test fixtures instead of real ones.
- The calendar, Desk, Day plan and Settings screens follow the design mockups in `docs/mockups/`. The toolbar has arrow buttons, a Month/Week/List group and the legend in one row. The header shows Add New Post and the weekly rhythm. The grid is a bordered card with muted lane names and lane-coloured suggestion cards. The Desk is one card with compact actions. Settings is grouped into cards with a category picker per weekday. Long action labels in the rail wrap instead of overflowing. "Fill open days" says why when it is disabled. The legend hides the site's default category until something uses it.
- README rewritten for people evaluating the plugin; the month-view screenshot now shows the full wp-admin screen.

### Fixed

- The Desk's series reason named the lane's weekday as the thing that was due ("Thursday is due around Nov 17…"); it now reads "Part 3 is due around Nov 17 (every 2 weeks) and isn't on the calendar yet."
- Categories without a stored colour showed grey everywhere: core's `sanitize_hex_color('')` returns `''`, not `null`, so the empty term meta was taken as a valid colour and the slug defaults and palette were never used.
- Series planning meta (`masthead_cadence`, `masthead_lane_exception`) no longer appears in public `wp/v2/<series>` responses. With ttm-core's public `series` taxonomy those responses are the ones the theme uses. The keys are returned only when the request's `context` is `edit`, which needs the `edit_terms` capability; the Settings screen and the swap panel read and write them in that context, and writes are unchanged.

## [0.1.0] - 2026-11-09

### Added

- Posts > Calendar with month, week and list views, category colors, a Draft tray and drag or keyboard rescheduling that never changes a post's status.
- A weekly rhythm (lanes) editable under Settings > Calendar, and series cadence per series with an optional lane exception.
- The Desk: suggestions for empty days (matching drafts, next series part, posts worth sharing again, quiet categories), each with a reason, plus the Day plan to create a dated draft with an outline.
- A nightly, read-only swap check (lane mismatch, collision, cadence break, better candidate) with a Review or Fits verdict.
- Jetpack Stats and Jetpack Social integration, with an honest unavailable state when Jetpack is not connected.
- AI features behind a monthly request cap: post ideas, outlines, share copy, and Ask the Desk series proposals whose dates Masthead computes. All AI output is validated against a JSON schema before use; nothing is written or shared without a click.
- WordPress Abilities under `masthead/*` (calendar, planning, stats, sharing, suggestions, swap check, AI) and WP-CLI commands (`seed`, `plan`, `suggest`, `swap-check`, `ai:status`, `stats:probe`, `social:probe`).
- Release zip with a SHA-256 checksum, built by the release workflow from a `v*` tag.
