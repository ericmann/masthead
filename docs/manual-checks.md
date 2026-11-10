# Manual checks

Nothing here blocks a later task. Each item is `NOT VERIFIED (human)` until someone ticks it.

## Phase 0

- [ ] Run `npx wp-env start`, open Posts > Calendar and see the empty app shell with the heading "Calendar" and no console errors.
- [ ] Push a `v0.0.1` tag on a scratch fork and see `masthead.zip` and `masthead.zip.sha256` attached to the release.
- [ ] Confirm the CI run for this branch is green.

## Phase 1

- [ ] Seed the fixture (`npx wp-env run cli wp masthead seed --fixture=wp-content/masthead-fixtures/queue-2026-11-09.json --now="2026-11-09 14:00:00" --reset`) and run `wp masthead plan 2026-11-01 2026-11-30 --format=table`; the table shows two published posts on Nov 9 and scheduled posts on Nov 11 and Nov 12.
- [ ] In Claude Code with the MCP Adapter connected to the site, discover abilities and see `masthead/get-plan`, `masthead/create-planned-draft` and `masthead/reschedule`.
- [ ] Read `docs/spikes/P1-09-abilities-rest.md` and confirm no fact is marked `UNCONFIRMED` (fact 5, the MCP Adapter's exposure key, is currently UNCONFIRMED).

## Phase 2

- [ ] With the fixture seeded, open Posts > Calendar and `docs/mockups/Main.dc.html` side by side at 1440 px and at 390 px and compare the toolbar, grid, chip styles and Draft tray (the Desk rail arrives in Phase 4).
- [ ] Drag a scheduled post to another day with a mouse and confirm it persists after reload.
- [ ] Operate "Move to..." with the keyboard only.
- [ ] Compare Settings > Calendar with `docs/mockups/Settings.dc.html`.

## Phase 3

To be done on eric.mann.blog after deploying a pre-release build.

- [ ] Run `wp masthead stats:probe` and compare the printed shapes with `docs/fixtures/stats-sample.json` and `docs/spikes/P3-01-jetpack-stats.md`.
- [ ] Run `wp masthead social:probe` and compare with `docs/spikes/P3-03-jetpack-social.md`.
- [ ] Share one post to one connection from the Share panel and confirm it arrives and `masthead_last_shared` is set.
- [ ] Try "Queue for this date" and confirm it either queues or reports the plan reason.
- [ ] Read both spike files and list every fact still marked `UNCONFIRMED`.

## Phase 4

- [ ] With the fixture seeded, run `wp masthead swap-check --write --now="2026-11-09 14:00:00"`, open the list view, select The Mantle Part 2 and read the swap panel against `docs/mockups/PostDetail.dc.html`.
- [ ] Confirm no number appears anywhere in the calendar, rail or panels without "Jetpack Stats" beside it, first with stats unavailable and then with `wp option update masthead_e2e_stats 1`.
- [ ] Select Nov 18 and compare the Desk rail and Day plan with `docs/mockups/Main.dc.html` and `docs/mockups/DayPlan.dc.html`.
- [ ] Read `docs/tuning/P4-11.md` and `docs/tuning/P4-17.md` and decide whether the kept defaults suit the real site (the reshare ages in particular could not be judged from the sample statistics).

## Phase 5

Needs a real AI provider on a local site, so none of this could be done by the build. Core 7.1.4 does not define the `prompt_ai` capability (see `docs/spikes/P5-01-ai-client.md`); since 1.0.0 Masthead grants it to users with `publish_posts`, so test with an administrator, editor or author.

- [ ] Install AI Provider for Anthropic on a local site with a real key. Ask the Desk for "a 4-part security series starting the first Thursday in January", accept it, and see four dated drafts on Thursdays.
- [ ] Press "Suggest ideas" on an open day and "Draft an outline" in the Day plan, and confirm no reply states a traffic number, a date or a claim about history.
- [ ] Press "Draft with AI" in the Share panel for a published post; confirm nothing is shared until you click Share now.
- [ ] Repeat one request with AI Provider for OpenRouter. Confirm the provider's default model is used when the configured ids are not offered, and that Settings reports the last model used as `docs/spikes/P5-01-ai-client.md` predicts.
- [ ] Judge `ai.temperature` and the two model lists, which `docs/tuning/P5-13.md` records as NOT MEASURED. Also try a series request near the maximum (12 parts) and confirm the proposal arrives whole: since 1.0.0 series proposals get 4000 tokens (the `series_proposal` entry of `ai.max_tokens`; see the 1.0-02 addendum in `docs/tuning/P5-13.md`). If the reply is still cut off, the Desk reports an invalid reply and creates nothing.
- [ ] Set the monthly cap to 1 in Settings, make two requests and see the budget message ("The monthly AI request limit has been reached.") on the second; set it back afterwards.
- [ ] Read `docs/spikes/P5-01-ai-client.md` and list every fact still marked `UNCONFIRMED` (the JSON-constrained response shape and model-preference fallback).

## Phase 6

- [ ] After the pull request merges, create and push the signed tag `v1.0.0` (`git tag -s v1.0.0`; 0.1.0 was never tagged), and confirm the release has `masthead.zip` and `masthead.zip.sha256` and that `sha256sum -c masthead.zip.sha256` passes. The build did not create a tag.
- [ ] Bump the site repository's pinned Masthead version and checksum as described in `docs/DEPLOYMENT.md`, build the image, roll it, and plan one real week from the calendar.
- [ ] Before making the repository public:
  - Decide whether to rewrite git history, which still contains the real post titles that P6-01 replaced in the fixture.
  - Decide whether to scrub or remove `docs/mockups/`, which still show real planned titles, series ideas and the blog's name (the SPEC and PLAN also name the site and owner).
  - Confirm that keeping "The Mantle" and its four part titles in the fixture is acceptable.
  - Check that `masthead` is free as a WordPress.org slug (SPEC section 9, question 1), and settle the repository owner used in the release URL.
- [ ] Read `README.md`, `readme.txt` and the four screenshots in `docs/screenshots/`.
- [ ] Run through every earlier section of this file that still reads NOT VERIFIED (Phases 0 to 5).
