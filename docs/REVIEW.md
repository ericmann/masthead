# Review: build/2026-11-10 (Masthead 0.1.0)
Round: 1

**Verdict: CHANGES REQUESTED**

Branch `build/2026-11-10` against `main` at `b579920`, 174 commits, 85 tasks done, 0 blocked, 0 skipped.

What I ran myself:

- `kukulu_verify` (no files): 18 of 18 constraints pass with no fixture failures. `composer lint`, `composer test:unit` (298), `npm run lint`, `npm run test:unit` (152) and `bash scripts/forbidden-patterns.sh` pass.
- `npm run test:integration` ran inside four mutation calls. All 126 tests passed apart from the mutated mechanic each time.
- `npm run test:e2e` (after `npm run build`): 106 passed, 4 skipped (the by-design skips plus the screenshot capture spec on both projects), exit 0, 8.3 minutes.
- `npm audit --audit-level=high` exits 1. See F1.
- 31 mutants: 29 killed, 2 survived (both in `Reshare.php`, see F6). Every module was sampled: Ranker, SwapCheck x2, Stats/Factory, Ai/Factory, Lanes, Budget, Ideas, Admin/Assets, Cadence, Reshare x5, CategoryGap, Prompts, JetpackSocial, Cron/Nightly, Schemas, Dismissals, Stats/Cache, PlanReader, SeriesNext, Scoring, Payloads, Dates, the SuggestForDate permission (integration), the Registrar `expose_share` setting (integration and unit), the Slots non-floating date (integration and unit), client StatValue and useAbility (jest).

## Findings

### F1. Category 3 (a verification that fails). `npm audit --audit-level=high` fails, so the CI verify job fails
- Where: `.github/workflows/ci.yml:29` (`- run: npm audit --audit-level=high`) and `package.json` `overrides`.
- What is wrong: SPEC §3.5 says "`composer audit` and `npm audit --audit-level=high` pass". On this branch the command exits 1 with 5 high advisories, all in dev dependencies pulled in transitively: `minimatch`, `markdown-it`, `markdownlint-cli`, `isomorphic-git` (through `@wp-playground/cli` / `@wordpress/env`) and `@wordpress/scripts`. Every phase-end task logged this and moved on (P0-02, P0-08, P0-09, P1-17, P2-12, P3-10, P4-18, P5-14, P6-05). Nothing resolved it, and HANDOFF does not list it as an open risk.
- What breaks: the `verify` job in `ci.yml` fails on every push and pull request. The draft PR can never show green checks.
- Minimal fix: add `overrides` entries in `package.json` that pin each vulnerable transitive package to a patched release inside its dependents' ranges, run `npm install` to regenerate `package-lock.json`, and confirm that `npm audit --audit-level=high` exits 0 and the toolchain still runs. If a high advisory has no patched version that an override can reach, do not edit SPEC or CI. Block the task with the advisory ids so the owner can decide on a spec change (for example `--omit=dev`).
- Task: R1-01.

### F2. Category 5 (spec drift). A Journal post on the date hides a real collision
- Where: `src/Desk/SwapCheck.php`, `collisions()`: `if ( array() !== array_intersect( $primaries, $exempt ) ) { continue; }`
- What is wrong: SPEC §6.3 says "`collision` when two non-Journal posts share a date". The code skips the whole date if *any* post on it has an exempt primary category. Two Technology posts plus one Journal post on the same day therefore produce no collision, although two non-Journal posts share the date. PLAN P4-14 worded it as "none of them has a primary category in …", which is the wrong reading. SPEC wins.
- What breaks: a real double-booking is never flagged whenever a Journal entry lands on the same day.
- Minimal fix: drop the exempt posts from each date group first. Raise a collision only when two or more non-exempt posts remain, give one finding to each of them (none to the exempt post), and list only the other non-exempt titles in the "why".
- Test that would have caught it: `SwapCheckTest::test_two_non_exempt_posts_collide_even_when_a_journal_post_shares_the_date`. The existing `test_exempt_category_does_not_collide` uses two Journal posts, so it cannot catch this.
- Task: R1-02.

### F3. Category 5 (spec drift). The ideas prompt tells the model "Allowed categories: none" for a lane that accepts any category
- Where: `src/Desk/Ideas.php` `ideas()` passes the raw `$lane` to `Prompts::ideas()`. `src/Ai/Prompts.php` `ideas()` renders `self::list( $lane['categories'] )`, which prints `none` for an empty list.
- What is wrong: SPEC §5.1 says a lane with `categories: []` accepts any category, and §3.3 says the same for ideas. `Ideas::ideas()` computes `$allowed` correctly (every category for an empty lane) and puts it in the JSON schema. The prompt text still says "Allowed categories: none" and asks for "a category chosen from the allowed list". For the default rhythm that is every Wednesday (Standalone).
- What breaks: on any-category days the instructions contradict the schema. A model may return no ideas or ones that fail validation, and the Desk then shows an AI error instead of ideas.
- Minimal fix: pass the resolved allowed list to the prompt. Either give `Prompts::ideas()` an explicit `$allowed` argument or hand it a lane copy whose `categories` is `$allowed`. Keep every slug wrapped in `<untrusted-post-data>`.
- Test that would have caught it: `IdeasTest::test_any_category_lane_prompt_lists_every_category` asserts that the user prompt sent to the AI lists every category slug and does not contain `Allowed categories: none`.
- Task: R1-03.

### F4. Category 5 (spec drift). Ask the Desk cannot pass `start`, so "starting the first Thursday in January" is planned from tomorrow
- Where: `client/calendar/AskDesk.js:32` runs `masthead/propose-series` with `{ prompt }` only.
- What is wrong: SPEC §6.1 gives `masthead/propose-series` the input `{ prompt, start?: date }`. The Phase 5 manual check asks for "a 4-part security series starting the first Thursday in January". Masthead computes the dates itself (correctly, since the AI must not state dates), but with no `start` it uses tomorrow. The proposal comes back on November Thursdays, and the user has to re-date every part by hand.
- What breaks: the SPEC's Phase 5 acceptance flow cannot be done as written. The ability has a `start` input that the UI never sends.
- Minimal fix: add an optional labelled `<input type="date">` "Start on" next to the Ask box, and send `start` only when it is set. Keep sending `{ prompt }` alone when it is empty.
- Test that would have caught it: `tests/js/calendar/AskDesk.test.js` "a chosen start date is sent to propose-series" and "no start is sent when the field is empty".
- Task: R1-04.

### F5. Category 5 (spec drift). `masthead/suggest-for-date` is annotated idempotent
- Where: `src/Abilities/SuggestForDate.php` `annotations()` returns `'idempotent' => true`.
- What is wrong: the SPEC §6.1 annotation for `suggest-for-date` is `readonly` only. With `include_ideas` the call spends AI budget and returns new ideas each time, so it is not idempotent. P5-07 used exactly this reasoning to mark `draft-outline` `idempotent=false`.
- What breaks: an MCP client that trusts `idempotent` may retry the call freely and spend the monthly AI budget.
- Minimal fix: set `idempotent` to false. `readonly` stays true, so the REST method (GET) does not change.
- Test that would have caught it: `tests/integration/Abilities/SuggestForDateTest.php::test_annotations_match_spec` asserts readonly true, idempotent false and destructive false on the ability returned by `wp_get_ability( 'masthead/suggest-for-date' )`.
- Task: R1-05.

### F6. Category 3 (mutation survivors). The Reshare age and cooldown boundaries are untested
- Where: `src/Desk/Candidates/Reshare.php`, lines 65 and 71.
- Survivors (both with `vendor/bin/phpunit --filter ReshareTest`):
  1. `Dates::diff_days( Dates::to_ymd( $published ), $today ) <= $min_age` changed to `< $min_age`: survived.
  2. `Dates::diff_days( Dates::to_ymd( $when ), $today ) <= $cooldown` changed to `< $cooldown`: survived.
- Sweep of the same file, all killed: dropping the `publish` status check, dropping the empty-lane "any category" branch, and `sort_id` set to 0.
- What breaks: SPEC §4.4 says "older than `desk.reshare_min_age_days`, not shared within `desk.reshare_cooldown_days`". Nothing pins whether a post exactly `min_age` days old, or shared exactly `cooldown` days ago, is offered. An off-by-one change would pass silently.
- Minimal fix: add boundary tests. A post published exactly `desk.reshare_min_age_days` days before today is excluded, and one published a day earlier is included. A post last shared exactly `desk.reshare_cooldown_days` days ago is excluded, and one shared a day earlier is included.
- Task: R1-06.

No category 1 (constraints) or category 2 (boundaries) findings:

- Every mechanical constraint passes, and the fixtures self-test.
- By reading I checked `Admin\Assets::enqueue()` (returns early off Masthead's two screens), `Stats\Factory` (fixture only when the environment is `local` and the file is readable), `Ai\Factory` (filter honoured only when the environment is `local`), `Cron\Nightly` (reads, warms the stats cache, writes only `masthead_findings`), `Plan\Slots` and `Plan\Reschedule` (status pinned to its current value, `publish` refused), the `Abilities/*` permission callbacks against §3.4 and §6.1, `Registrar` exposure of `share-post`, `Ai\Prompts` (every post-derived string inside `<untrusted-post-data>`, and an instruction to state nothing about traffic or dates) and `Desk\Reasons` (templates filled only from data).
- No numeric `const` outside `Config.php` is a tunable. The only Factory callers are `Plugin.php` and the factories themselves.
- `.gitignore`, `.editorconfig` and the workflows were created by the P0 scaffold tasks that called for them. No unrequested edits.

Interpretation choices in HANDOFF: all are consistent with SPEC except the P5-11 omission in F4 and the P4-14 collision reading in F2. Both are findings above.

Blocked and skipped tasks: none.

## Spec issues

- **`prompt_ai` does not exist in WordPress 7.1.4** (§3.4, §6.1, §4.4). Core defines no such capability and grants it to no role, so on a stock site every AI ability and button is denied to everyone, administrators included. The build follows SPEC and documents that the site owner must grant it. SPEC should say whether Masthead maps it (for example to `manage_options` through `user_has_cap`) or keeps requiring the site to grant it.
- **`ai.max_tokens` = 1200 cannot hold a worst-case series proposal** (`ai.series_max_parts` = 12; P5-13 estimates about 2565 tokens). A truncated reply is invalid JSON, so nothing is created (safe, but the feature fails for long series). The key is not settable. SPEC should raise the default, make it settable, or cap the part count lower.
- **§5.2 and §2 conflict on public REST.** §5.2 registers `masthead_cadence` and `masthead_lane_exception` with `show_in_rest` on the series taxonomy, which is ttm-core's public `series` when present. That adds a `meta` object to `wp/v2/series` responses the theme may read, while §2 says Masthead changes no REST response the theme uses. The build follows §5.2.
- **CLAUDE.md "Masthead never changes a post's `post_status`" vs SPEC §6.7 "Save as … scheduled".** The Day plan's "Scheduled" choice sets the new draft to `future` through `wp/v2/posts` after a user click (PLAN P4-10). That satisfies SPEC, and the PHP rule (Slots and Reschedule pin the status) holds. The CLAUDE.md wording should be narrowed to the server-side write paths.
- **§3.5 `npm audit --audit-level=high`.** If R1-01 finds a high advisory that no override can reach, the SPEC requirement needs to change (for example to `--omit=dev`). That is the owner's call, not the build's.
- No `Known-gap candidates`: this flight did not run in clean-room mode.

## Manual checks still owed

Copied from HANDOFF.md (all are in `docs/manual-checks.md`; none was performed):

- Phase 0: the workflows run on GitHub; the release path.
- Phase 1: seed and `wp masthead plan`; MCP discovery with the MCP Adapter; list the `UNCONFIRMED` spike facts.
- Phase 2: compare the calendar and settings with the mockups at 1440 and 390 px; drag with a mouse; keyboard "Move to".
- Phase 3: on a real site, run `stats:probe` and `social:probe`; share one post; try a queued share.
- Phase 4: the swap check and panel against the mockups; confirm no number appears without "Jetpack Stats"; the Day plan against the mockups; judge the kept Desk and swap defaults.
- Phase 5: with a real provider, ask for a 4-part security series and accept it (needs R1-04 to start in January without re-dating); ideas, outline and share copy contain no traffic, date or history claim; the OpenRouter default model; judge `ai.temperature` and the model lists; try a 12-part series; the budget message at cap 1. Grant `prompt_ai` first.
- Phase 6: tag `v0.1.0` after merge; pin it in the site image; decide on the history, the mockups, "The Mantle", the slug and the repository owner before making the repository public.

## Notes

- `prChecks` was `pending` when the review started (no failing check yet). F1 predicts that the `verify` job will fail at the audit step.
- `src/Plan/PlanReader.php` `range()`: undated drafts (whose `post_date` falls in the range) count toward the `calendar.max_posts_per_range` cap before they are filtered out, so `truncated` can trip slightly early. Harmless at 500.
- `src/Desk/SwapCheck.php` `better_candidates()`: a category missing from the top-posts payload, or a weekday with no rows, shows as `0` under "Jetpack Stats". That is consistent with how `Scoring` treats an absent key, and the number comes from Jetpack data, but "not in the top posts" and "zero views" are not the same thing.
- `src/Plan/SeriesSource.php` registers `masthead_series` for `array( 'post' )` rather than `Config::get( 'post_types' )`. This is equivalent while `post_types` cannot be changed.
- `swap-check`, `stats-summary` and `share-connections` add `idempotent: true` on top of SPEC's `readonly`. That is accurate for them, unlike F5.
