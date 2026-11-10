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
