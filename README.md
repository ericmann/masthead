# Masthead

An editorial calendar for WordPress. Under Posts > Calendar you see every published, scheduled and planned post on a month, week or list view, colored by category, with a weekly **rhythm** that gives each weekday a lane (for example Business on Monday and Fiction on Saturday). A side panel called the **Desk** suggests what to put on empty days, flags scheduled posts that fit their day badly, and re-shares posts through Jetpack Social.

Masthead adds nothing to public pages. Every action is a WordPress Ability under `masthead/*`, so the admin screens, WP-CLI and MCP clients all run the same code.

## Requirements

- WordPress 7.1 or later and PHP 8.3 or later.
- Optional: [Jetpack](https://jetpack.com/) for traffic numbers (Jetpack Stats) and sharing (Jetpack Social). Without it the calendar, lanes and series still work, and Masthead never invents a number.
- Optional: an AI provider configured under Settings > Connectors for post ideas, outlines, share copy and series proposals. They need the `prompt_ai` capability, which WordPress 7.1 does not grant to any role (checked on 7.1.4), so Masthead grants it to users who can `publish_posts` unless a role or another plugin grants or denies `prompt_ai` explicitly. The capability it checks is the `ai.fallback_capability` key of the `masthead_config` filter.

## Screenshots

The month view with a day selected and the Desk suggesting what could go on it:

![Month view with the Desk rail](docs/screenshots/calendar-month.png)

The Day plan turns a suggestion into a dated draft:

![Day plan](docs/screenshots/day-plan.png)

The swap check explains why a scheduled post fits its day badly and offers a fix:

![Swap check panel](docs/screenshots/swap-panel.png)

Settings > Calendar holds the weekly rhythm, series cadence, Desk, AI and exposure settings:

![Settings](docs/screenshots/settings.png)

The images are generated from the seeded fixture with `npm run screenshots`.

## Installation

1. Download `masthead.zip` and `masthead.zip.sha256` from a release and check the archive with `sha256sum -c masthead.zip.sha256`.
2. In the WordPress admin choose Plugins > Add New > Upload Plugin, upload the zip and activate it.
3. Open Posts > Calendar, and adjust the weekly rhythm under Settings > Calendar.

Releases are built by the release workflow from a `v*` tag and are not submitted to WordPress.org.

## Abilities

Registered in the `masthead` category. Read-only abilities run over `GET` and the rest over `POST` on `/wp-abilities/v1/abilities/<name>/run`. Whether they are visible to MCP clients is set under Settings > Calendar; `masthead/share-post` has its own switch and is off by default.

| Ability | What it does | Capability |
|---|---|---|
| `masthead/get-plan` | Days in a range with their lane, posts and open slots | `edit_posts` |
| `masthead/suggest-for-date` | Desk suggestions for a date, optionally with AI ideas | `edit_posts` (+ `prompt_ai` for ideas) |
| `masthead/dismiss-suggestion` | Hides a suggestion for the current user | `edit_posts` |
| `masthead/create-planned-draft` | Creates a dated draft, optionally with an outline | `edit_posts` |
| `masthead/reschedule` | Moves a scheduled post or dated draft | `edit_post` on that post |
| `masthead/swap-check` | Returns the latest swap-check findings | `edit_posts` |
| `masthead/stats-summary` | Jetpack Stats summary, or why it is unavailable | `edit_posts` |
| `masthead/share-connections` | Lists Jetpack Social connections | `publish_posts` |
| `masthead/share-post` | Shares a published post now or at a date | `publish_posts` |
| `masthead/draft-outline` | AI outline for a post title | `edit_posts` + `prompt_ai` |
| `masthead/draft-share-copy` | AI share message for a published post | `edit_posts` + `prompt_ai` |
| `masthead/propose-series` | AI series proposal with dates chosen by Masthead | `edit_posts` + `prompt_ai` |
| `masthead/accept-series` | Creates a proposed series and its dated drafts | `edit_posts` |

## WP-CLI

| Command | What it does |
|---|---|
| `wp masthead plan <from> <to>` | Prints the plan for a date range |
| `wp masthead suggest <date> [--ideas]` | Prints the Desk suggestions for a date |
| `wp masthead swap-check [--write]` | Runs the swap check; `--write` stores the findings |
| `wp masthead ai:status` | Shows whether an AI provider is available, the budget left and the model lists |
| `wp masthead stats:probe` | Prints scrubbed Jetpack Stats payload shapes |
| `wp masthead social:probe` | Prints scrubbed Jetpack Social payload shapes |
| `wp masthead seed --fixture=<file> [--now=<datetime>] [--reset]` | Loads the acceptance fixture (development) |

## Development

Requirements: PHP 8.3+, Composer, Node.js and Docker (for wp-env). Run `composer install` and `npm install` once.

| Purpose | Command |
|---|---|
| PHP lint | `composer lint` |
| PHP unit tests | `composer test:unit` |
| One PHP test class | `vendor/bin/phpunit -c phpunit.xml.dist --filter <Class>` |
| JS and CSS lint | `npm run lint` |
| JS unit tests | `npm run test:unit` |
| Build | `npm run build` (output `build/`, not committed) |
| Structural checks | `bash scripts/forbidden-patterns.sh` |
| Integration tests | `npm run test:integration` (wp-env) |
| End-to-end tests | `npm run test:e2e` (wp-env and Playwright) |
| README screenshots | `npm run screenshots` |
| Release zip | `npm run release:pack` (output `dist/masthead.zip` and `.sha256`) |

`docs/SPEC.md` is the specification and `CLAUDE.md` lists the rules the code follows.

## License

GPL-2.0-or-later. See `LICENSE`.
