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
