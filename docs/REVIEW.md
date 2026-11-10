# Review: build/2026-11-10 (Masthead 0.1.0)
Round: 2

**Verdict: APPROVED**

Branch `build/2026-11-10` against `main` at `b579920`. 91 tasks done, 0 blocked, 0 skipped. This round reviews the fix round R1-01 to R1-06 (`e8df7c6..b5866e0`). Round 1 already reviewed the rest of the branch and found nothing else.

What I ran myself:

- `kukulu_verify` (no files): 18 of 18 constraints pass, with no fixture failures. `composer lint`, `composer test:unit` (302 tests), `npm run lint`, `npm run test:unit` (154 tests) and `bash scripts/forbidden-patterns.sh` pass.
- `npm audit --audit-level=high` exits 0, so the round 1 F1 is fixed. `composer audit` finds no advisories.
- `npm run test:integration`: 127 of 127 pass on the unmutated tree (this ran as the `confirmKill` re-run of the SuggestForDate mutant).
- `npm run build` then `npm run test:e2e`: 106 passed and 4 skipped, exit 0, 8.7 minutes. The 4 skips are the by-design skips and the screenshot capture spec, on both projects. `ask.spec.js`, including its axe check of `.masthead-ask` with the new "Start on" field, passes on desktop and mobile.
- `prChecks`: `pending`, with no failing check. That is not a finding. The checks had not finished when this review started.

Mutants, all on files changed in the last fix round (`git diff --name-only e8df7c6..HEAD`), plus the two R1-06 survivors that the task was written to kill. Every kill was confirmed by re-running the failing command on the unmutated tree, and each failure tail names the expected test:

| File | Mutation | Result | Test that failed |
|---|---|---|---|
| `src/Desk/SwapCheck.php` | drop the per-date filter that removes exempt posts | killed | `SwapCheckTest::test_two_non_exempt_posts_collide_even_when_a_journal_post_shares_the_date`, `test_exempt_category_does_not_collide` |
| `src/Desk/Ideas.php` | pass the raw `$lane` to `Prompts::ideas()` | killed | `IdeasTest::test_any_category_lane_prompt_lists_every_category` |
| `src/Desk/Candidates/Reshare.php` | min-age `<=` changed to `<` (survived in round 1) | killed | `ReshareTest::test_post_exactly_min_age_days_old_is_excluded_and_one_day_older_is_included` |
| `src/Desk/Candidates/Reshare.php` | cooldown `<=` changed to `<` (survived in round 1) | killed | `ReshareTest::test_post_shared_exactly_cooldown_days_ago_is_excluded_and_one_day_earlier_is_included` |
| `client/calendar/AskDesk.js` | delete `input.start = start;` | killed | AskDesk "a chosen start date is sent to propose-series" |
| `client/calendar/AskDesk.js` | always send `start` | killed | AskDesk "no start is sent when the field is empty" and "asking runs propose-series and lists the dated parts" |
| `src/Abilities/SuggestForDate.php` | `idempotent` set back to true | killed | `SuggestForDateTest::test_annotations_match_spec` (integration) |

## Findings

None.

How I checked each fix task:

- **R1-01** (`6afc136`). Only the `overrides` block of `package.json` changed: `isomorphic-git ^1.44.12`, `markdown-it ^14.3.3`, and `minimatch ^3.1.5` scoped to `markdownlint-cli`. `devDependencies` did not change. The top-level toolchain versions in the lockfile did not change either (`@wordpress/scripts` 35.0.0, `@wordpress/env` 12.1.0, `@playwright/test` 1.65.0, eslint, jest, webpack). `dependencies` is still absent, which `forbidden-patterns.sh` accepts as empty, as it did before this round. The CI workflow and SPEC were not touched. `npm audit --audit-level=high` exits 0.
- **R1-02** (`78556e3`). Each date group drops posts whose primary category is exempt before it is counted. The "why" line lists only the other non-exempt titles. The exempt list still comes from `Config::get( 'swap.collision_exempt_categories' )`. This matches SPEC §6.3 ("two non-Journal posts share a date"). The integration test `SwapCheckMantleTest` still passes as part of the 127.
- **R1-03** (`e897585`). `Ideas` passes a copy of the lane with `categories` set to the resolved `$allowed` list. `Prompts` is unchanged and still wraps every slug in `<untrusted-post-data>`, and the new test asserts that wrapping. The schema and the prompt now agree.
- **R1-04** (`1105401`). The new "Start on" control is a real `<input type="date">` with a label tied to it by `htmlFor`/`id`. `start` is sent only when the field is set, and the request is exactly `{ prompt }` when it is empty. No clock call was added. The request still goes through `runAbility`.
- **R1-05** (`3826271`). Only the `idempotent` annotation changed. `readonly` is still true, so the REST transport stays GET (`RestTransportTest` passes).
- **R1-06** (`22f8a64`). Tests only. Both boundaries read their values from `Config`, and both round 1 survivors are now killed (table above). The helper `days_before_today()` uses `new \DateTimeImmutable` in a unit test. The `one-clock` rule covers source code only, and this helper is fixed test arithmetic from the pinned fixture date, not a clock read.

Constraints and boundaries: the fix diffs add no module import, no numeric literal outside `Config.php`, no clock call, and no edit to `.gitignore`, `.gitattributes`, an editor config or CI config. Every commit is signed (`G`), and every commit message follows the template.

Blocked and skipped tasks: none.

## Spec issues

These were raised in round 1 and are still open. They are for the owner and are not build defects:

- **`prompt_ai` does not exist in WordPress 7.1.4** (§3.4, §6.1, §4.4). No role holds it, so on a stock site every AI feature is denied to every user. SPEC should say whether Masthead maps it (for example to `manage_options` through `user_has_cap`) or whether the site must grant it.
- **`ai.max_tokens` = 1200 cannot hold a worst-case series proposal** (12 parts, estimated at about 2565 tokens), and the key is not settable. SPEC should raise the default, make the key settable, or lower `ai.series_max_parts`.
- **§5.2 and §2 conflict on public REST.** Series term meta registered with `show_in_rest` adds a `meta` object to `wp/v2/series` responses, which the theme may read.
- **CLAUDE.md "Masthead never changes a post's `post_status`" vs SPEC §6.7 "Save as … scheduled".** The CLAUDE.md wording should be narrowed to the server-side write paths.
- No `Known-gap candidates`: this flight did not run in clean-room mode.

## Manual checks still owed

Copied from HANDOFF.md. All are listed in `docs/manual-checks.md`, and none has been performed:

- Phase 0: the workflows run on GitHub; the release path.
- Phase 1: seed and `wp masthead plan`; MCP discovery with the MCP Adapter; list the `UNCONFIRMED` spike facts.
- Phase 2: compare the calendar and settings with the mockups at 1440 and 390 px; drag with a mouse; keyboard "Move to".
- Phase 3: on a real site, run `stats:probe` and `social:probe`; share one post; try a queued share.
- Phase 4: the swap check and panel against the mockups; confirm no number appears without "Jetpack Stats"; the Day plan against the mockups; judge the kept Desk and swap defaults.
- Phase 5: with a real provider, ask for "a 4-part security series starting the first Thursday in January" and accept it, using the new "Start on" field (R1-04); ideas, outline and share copy contain no traffic, date or history claim; the OpenRouter default model; judge `ai.temperature` and the model lists; try a 12-part series; the budget message at cap 1. Grant `prompt_ai` first.
- Phase 6: tag `v0.1.0` after merge; pin it in the site image; decide on the history, the mockups, "The Mantle", the slug and the repository owner before making the repository public.
- Round 1: ask for a series with a "Start on" date on a live site.

## Notes

- R1-01's `markdown-it ^14.3.3` override moves `markdownlint`'s dependency from markdown-it 12 to 14, which is a major version jump. No script or workflow runs `wp-scripts lint-md-docs` or markdownlint, so nothing in the toolchain uses it today. If markdown linting is added later, check that markdownlint 0.25 still works with markdown-it 14.
- R1-06's cooldown fixture writes the share time as `T08:00:00-07:00`, but Los Angeles is on PST (`-08:00`) in November. The day boundary is unaffected, because the time is mid-morning either way.
- The advisory notes from round 1 still apply and need no task: `PlanReader::range()` counts undated drafts toward the cap before filtering them out; `SwapCheck` shows a category missing from the top posts as `0`; `SeriesSource` registers for `post` rather than `Config::get( 'post_types' )`.
