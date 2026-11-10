# Masthead build plan

Derived from docs/SPEC.md v0.1 on 2026-11-09. SPEC.md wins over this file.

## Decisions

Open questions from SPEC §9:

- 1. Repository and slug → use `masthead` everywhere, held in one constant `Masthead\SLUG` (`src/constants.php`); Composer name `ericmann/masthead` (the `origin` remote). A rename stays a one-line change plus the text-domain literals PHPCS requires.
- 2. Abilities run endpoint from JS → spike P1-09 (60-minute time box, outcome `docs/spikes/P1-09-abilities-rest.md`). P1-13 adds the thin `masthead/v1` bridge only if the spike writes `Decision: bridge`. The facts cannot be known without reading the WordPress 7.1.4 source.
- 3. Jetpack Stats coverage → spike P3-01 (90 minutes, outcome `docs/spikes/P3-01-jetpack-stats.md`, the file name SPEC §6.2 gives). Fallback: categories aggregated from `top_posts`.
- 4. Jetpack Social plan gating → spike P3-03 (90 minutes, outcome `docs/spikes/P3-03-jetpack-social.md`). Unavailable actions are hidden with a reason; nothing is emulated.
- 5. Model ids under OpenRouter → spike P5-01 (60 minutes, outcome `docs/spikes/P5-01-ai-client.md`). If the AI Client does not expose the model used, Settings says so instead of guessing.
- 6. Rhythm changes over time → one rhythm for all dates in 0.1; no `effective_from`. SPEC defers it.
- 7. Fixture privacy → P6-01 replaces the six non-series post titles in `docs/fixtures/queue-2026-11-09.json` with synthetic ones and keeps "The Mantle", which SPEC §6.3 names and which is already published. The mockups and git history still hold real titles and planned ideas; scrubbing them is a human decision listed in the Phase 6 manual checks.

Planner decisions (each is restated in the task that needs it):

- Phases → SPEC §8's seven phases, in order. Phase 0's single SPEC task "P0-01 scaffolds everything" is split into P0-01..P0-08 because one commit may hold about 400 lines; SPEC's "all constraints active from P0-01" is kept.
- Spike ids → Phase 3 is ordered so the two spikes are still P3-01 and P3-03, the ids SPEC's file names use. SPEC's P3-02 and P3-04 are each split.
- Streams → none. Almost every task touches `src/` or `client/`, which trigger wp-env commands that cannot run twice at once.
- Primary category → the post's category with the lowest `term_id` (`Support\Categories::primary()`). SPEC never defines it; this rule is deterministic and is the only simple rule under which SPEC §6.3's acceptance holds for post 10194.
- Autoloading → `masthead.php` registers its own PSR-4 autoloader; the release zip has no `vendor/`. There are no runtime Composer packages.
- Status pinning → `Plan\Slots` and `Plan\Reschedule` pin `post_status` with a temporary `wp_insert_post_data` filter, because WordPress core flips `future` to `publish` by the wall clock, which the pinned test clock cannot control. See Spec issues 6.
- Pinned clock in e2e → `wp masthead seed --now` stores the pin in option `masthead_seed_now`; only the test mu-plugin `tests/e2e/mu/pin-now.php` reads it. Production code never reads that option.
- Stats in wp-env → `MASTHEAD_STATS_FIXTURE` is defined by the mu-plugin `tests/e2e/mu/fixture-stats.php` only when option `masthead_e2e_stats` is `1`, not in `.wp-env.json`, so the default e2e state is "stats unavailable" as SPEC's Phase 4 acceptance requires. See Spec issues 7.
- Budget placement → `Desk\BudgetedAi` (an `AiClient` decorator) calls `Budget::reserve()` before every AI call, because `Ai/` may not import `Desk/`. See Spec issues 3.
- One ability added → `masthead/draft-outline` (Phase 5), because SPEC requires outline generation in the Day plan and lists no ability that can produce one. See Spec issues 10.
- Lane exception from a finding → written through `wp/v2/<series rest base>/<id>` term meta, which SPEC already lists as a client endpoint; no ability is added for it. See Spec issues 9.
- Config keys added → `calendar.max_range_days` (92, from §6.1), `desk.candidate_pool` (50), `stats.top_posts_limit` (10), `ai.series_max_parts` (12), `swap.collision_exempt_categories` (`['journal']`). None is settable. They exist because SPEC forbids numeric literals and unbounded queries outside `Config`.
- JS unit tests → in `tests/js/`, so client constraints never scan test fixtures; `npm run lint` therefore also lints `tests/js`.
- `npm run test:e2e` and `npm run test:integration` → each starts with `npm run build`, because `build/` is gitignored and wp-env serves the working tree.
- Commit signing → `policies.signing` is `required` in `docs/kukulu.json`, because SPEC §3.5 forbids unsigned commits; the default would fall back to unsigned.
- Version tag → P6-02 sets version `0.1.0`; creating and pushing the signed `v0.1.0` tag is a human step after the pull request merges, because the tag triggers the release workflow and must sit on a reviewed commit.

## Conventions

- One task is one commit, titled `<ID>: <title>`, with the body in CLAUDE.md's commit template.
- "Protocol" in a task means the section of that name under `## Task protocols` in CLAUDE.md.
- Test names are exact. PHPUnit: `Class::method`. Jest and Playwright: the quoted `it`/`test` title.
- Unit tests are under `tests/unit/` (namespace `Masthead\Tests\Unit`), integration tests under `tests/integration/`, JS unit tests under `tests/js/`, e2e specs under `tests/e2e/`.
- "The fixture" is `docs/fixtures/queue-2026-11-09.json`; "pinned now" is `2026-11-09 14:00:00` in `America/Los_Angeles`.
- Dates are `YYYY-MM-DD` in site time; datetimes are ISO 8601 with offset (SPEC §6.1).
- A task that touches `src/`, `masthead.php` or `tests/integration/` is also verified by `npm run test:integration` (from P0-05); one that touches `client/` or `tests/e2e/` by `npm run build` and `npm run test:e2e` (from P1-14). These run through `kukulu_verify` with their own timeouts; tasks do not repeat them under Verification.
- Every phase ends with a serial task that pushes and lists manual checks.

## Phase 0 — Toolchain is real

### P0-01: PHP toolchain, plugin bootstrap, Config, Clock and Plugin

**Goal:** Create a Composer-managed plugin skeleton whose `composer lint` and `composer test:unit` pass, with `Config`, `Clock` and an empty `Plugin` booted from `masthead.php`.

**Files touched:** `composer.json`, `composer.lock`, `phpcs.xml.dist`, `phpunit.xml.dist`, `.gitignore`, `.editorconfig`, `masthead.php`, `src/constants.php`, `src/Config.php`, `src/Support/Clock.php`, `src/Plugin.php`, `tests/unit/bootstrap.php`, `tests/unit/TestCase.php`, `tests/unit/stubs/WP_Error.php`, `tests/unit/ConfigTest.php`, `tests/unit/Support/ClockTest.php`

**Design constraints:**

- SPEC §4.1, §4.2, §5.4, §3.2, §3.5, §7. Global invariants that bind this and every later PHP task: one clock, no randomness, no numeric literal of 2 or more outside `src/Config.php`, `declare(strict_types=1);` in every `src/` file, bounded queries, no direct HTTP (CLAUDE.md `## Constraints`).
- `composer.json`: name `ericmann/masthead`, license `GPL-2.0-or-later`, `require` only `"php": ">=8.3"`, `config.platform.php` `8.3.0`. `require-dev`: `phpunit/phpunit` `^9.6`, `brain/monkey`, `yoast/phpunit-polyfills`, `squizlabs/php_codesniffer`, `wp-coding-standards/wpcs`, `automattic/vipwpcs`, `phpcompatibility/phpcompatibility-wp`, `dealerdirect/phpcodesniffer-composer-installer` (allowed in `config.allow-plugins`). Autoload PSR-4 `Masthead\\` → `src/` plus `files: ["src/constants.php"]`; autoload-dev `Masthead\\Tests\\` → `tests/`. Scripts: `lint` runs `php -l` over every `*.php` in `masthead.php`, `src/` and `tests/` and then `phpcs`; `test:unit` runs `phpunit -c phpunit.xml.dist`. PHPUnit stays on 9.x unless the WordPress 7.1 test library proves to need another major in P0-05.
- `phpcs.xml.dist`: standards `WordPress-Extra`, `WordPress-Docs`, `WordPressVIPMinimum`, `PHPCompatibilityWP` with `testVersion` `8.3-`; `minimum_wp_version` `7.1`; text domain `masthead`; files `masthead.php`, `src`, `tests`; excludes `vendor`, `node_modules`, `build`, `dist`. Exclude the `WordPress.Files.FileName` sniff (PSR-4 file names) and, for `tests/` only, the Docs standard. Warnings do not fail the run; errors do.
- `masthead.php`: plugin header (`Plugin Name: Masthead`, `Version: 0.0.0`, `Requires at least: 7.1`, `Requires PHP: 8.3`, `License: GPL-2.0-or-later`, `Text Domain: masthead`), `declare(strict_types=1);`, an `ABSPATH` guard, a PSR-4 autoloader closure for `Masthead\` → `src/` (no `vendor/` at runtime), `require` of `src/constants.php`, and `add_action( 'plugins_loaded', array( \Masthead\Plugin::class, 'boot' ) )`. Nothing else (SPEC §4.1: header and bootstrap only).
- `src/constants.php`: `namespace Masthead; const SLUG = 'masthead'; const VERSION = '0.0.0';` (SPEC §9 question 1). Use `SLUG` for menu slugs, the script handle and the ability category; text-domain arguments stay the literal `'masthead'`.
- `Config` (imports nothing): `defaults(): array` is pure and returns a flat map keyed by the dotted keys of SPEC §5.4 with exactly the defaults listed there, plus the planner keys `calendar.max_range_days` = 92, `desk.candidate_pool` = 50, `stats.top_posts_limit` = 10, `ai.series_max_parts` = 12, `swap.collision_exempt_categories` = `['journal']`. `calendar.week_starts_on` defaults to `null`, which `get()` resolves as `(int) get_option( 'start_of_week' )`. `settable(): array` lists the keys §5.4 marks settable. `get( string $key )` resolves: defaults, then for settable keys the value in option `masthead_settings` (nested by dotted path: `desk.horizon_days` is `$option['desk']['horizon_days']`, `ai.models.fast` is `$option['ai']['models']['fast']`), ignoring a value whose type differs from the default's (int accepted where the default is float), then `apply_filters( 'masthead_config', $flat )`. An unknown key throws `\InvalidArgumentException`. `all(): array` returns the resolved map. Every ⚠️ ASSUMPTION key of §5.4 (`calendar.max_posts_per_range` 500, `desk.horizon_days` 21, `desk.ghost_days` 14, `desk.gap_days` 45, `desk.reshare_min_age_days` 90, `desk.reshare_cooldown_days` 180, `desk.series_window_days` 3, `swap.lookahead_days` 60, `swap.min_score_delta` 0.25, `swap.candidate_window_days` 3, `swap.weights`, `stats.cache_ttl` 21600, `ai.monthly_request_cap` 300, `ai.models.fast`, `ai.models.strong`, `ai.temperature` 0.4, `ai.max_tokens` 1200, `ai.context_max_posts` 60) has its default here and nowhere else.
- `Support\Clock` (the only file allowed PHP clock calls): `now(): \DateTimeImmutable` builds the real time in `wp_timezone()` and passes it through `apply_filters( 'masthead_now', $real )`; a returned `DateTimeImmutable` is converted to the site zone, a returned `Y-m-d H:i:s` string is read as site time, anything else is ignored. `today(): string` is `now()` as `Y-m-d`. `format( \DateTimeImmutable $dt, string $format ): string` wraps `wp_date( $format, $dt->getTimestamp(), wp_timezone() )` and is the only way other modules produce localised weekday and month names.
- `Plugin::boot(): void` is idempotent and, in this task, registers no hooks. Later tasks add their wiring here (Wiring protocol).
- Unit bootstrap: Composer autoload, Brain Monkey set up and torn down in `tests/unit/TestCase.php`, translation functions stubbed, `wp_timezone()` stubbed to `America/Los_Angeles`, and a minimal `WP_Error` stub class (code, message, data) loaded only when the class does not exist. WordPress is never loaded.
- `.gitignore`: `vendor/`, `node_modules/`, `build/`, `dist/`, Playwright output.

**Acceptance tests:**

- `tests/unit/ConfigTest.php`: `ConfigTest::test_defaults_contain_every_spec_key`, `ConfigTest::test_assumption_defaults_match_spec`, `ConfigTest::test_planner_keys_have_documented_defaults`, `ConfigTest::test_get_unknown_key_throws`, `ConfigTest::test_option_overrides_only_settable_keys`, `ConfigTest::test_option_override_of_wrong_type_is_ignored`, `ConfigTest::test_masthead_config_filter_wins`, `ConfigTest::test_week_starts_on_reads_start_of_week_option`.
- `tests/unit/Support/ClockTest.php`: `ClockTest::test_now_is_in_site_timezone`, `ClockTest::test_filter_pins_now_with_datetime`, `ClockTest::test_filter_pins_now_with_site_time_string`, `ClockTest::test_invalid_filter_value_is_ignored`, `ClockTest::test_today_uses_pinned_now`, `ClockTest::test_format_delegates_to_wp_date`.

**Out of scope:** `package.json` and any JS; wp-env; admin pages; abilities; the activation hook; `scripts/forbidden-patterns.sh`; any module other than `Config`, `Support\Clock` and `Plugin`.

**Verification:** `composer install`, then `composer lint` and `composer test:unit`, both exit 0. `composer audit` reports no advisory.

**Depends on:** none

### P0-02: JS toolchain and client shell

**Goal:** Add the Node toolchain so `npm run lint`, `npm run test:unit` and `npm run build` pass on a client that renders an empty Calendar shell.

**Files touched:** `package.json`, `package-lock.json`, `.nvmrc`, `jest.config.js`, `.eslintrc.js`, `client/index.js`, `client/calendar/App.js`, `client/style.scss`, `tests/js/index.test.js`, `tests/js/calendar/App.test.js`

**Design constraints:**

- SPEC §4.3, §3.5, §7. Global invariant: `dependencies` in `package.json` is `{}`; everything, including the `@wordpress/*` packages the code imports (`element`, `components`, `data`, `core-data`, `api-fetch`, `i18n`, `date`, `dom-ready`), is a `devDependency`, because WordPress provides them at runtime as externals.
- `package.json`: name `masthead`, version `0.0.0`, `private: true`, license `GPL-2.0-or-later`. Scripts: `build` = `wp-scripts build client/index.js --output-path=build`; `lint` = `wp-scripts lint-js client tests/js tests/e2e scripts --no-error-on-unmatched-pattern && wp-scripts lint-style 'client/**/*.scss'`; `test:unit` = `wp-scripts test-unit-js --config jest.config.js`. `.nvmrc` holds the major Node version in use.
- One webpack entry, `client/index.js`, producing `build/index.js`, `build/index.asset.php` and `build/index.css`. It holds a `ROUTES` map from root element id to app component and mounts each app whose root exists; this task registers `masthead-calendar-root` → `calendar/App`. Later tasks add routes here (Wiring protocol).
- `client/calendar/App.js` renders a wrapper with class `masthead-calendar` containing an `h1` "Calendar" (translatable, text domain `masthead`). No data calls yet.
- Jest collects only `tests/js/**/*.test.js`.

**Acceptance tests:**

- `tests/js/index.test.js`: "mounts the calendar app on #masthead-calendar-root", "does nothing when no root element exists".
- `tests/js/calendar/App.test.js`: "renders the Calendar heading".

**Out of scope:** Playwright, wp-env scripts, the release script, any real calendar UI, the settings app.

**Verification:** `npm install` (writes the lockfile), `npm run lint`, `npm run test:unit`, `npm run build` (then `build/index.js` and `build/index.asset.php` exist). Run `npm audit --audit-level=high`; if it reports an advisory in a transitive development package, fix it with an `overrides` entry, and if no fixed version exists record the advisory id in the progress log.

**Depends on:** none

### P0-03: Structural checks script and module boundaries test

**Goal:** Add `scripts/forbidden-patterns.sh` for the SPEC §3 rules that are not line patterns, and a unit test that enforces the module import table.

**Files touched:** `scripts/forbidden-patterns.sh`, `tests/unit/BoundariesTest.php`

**Design constraints:**

- SPEC §3.5, §4.2, §7 ("constraints: `bash scripts/forbidden-patterns.sh`").
- The script takes an optional `--root <dir>` (default: repository root), prints one line `FAIL [<id>] <path>: <message>` per violation, exits 1 if any, otherwise prints `forbidden-patterns: ok` and exits 0. Checks: `strict-types` (every `*.php` under `src/` contains `declare(strict_types=1);`), `deps-empty` (`package.json` `dependencies` is absent or `{}`), `lockfiles` (`composer.lock` and `package-lock.json` exist), `ability-permission` (every `src/Abilities/*.php` except `Registrar.php` and `Ability.php` contains the word `permission`), `version-agree` (the `Version:` header of `masthead.php`, `const VERSION` in `src/constants.php` and `version` in `package.json` are equal), `no-gpg-bypass` (no `no-gpg-sign` in `.github/`, `scripts/`, `package.json`, `composer.json`, excluding this script).
- Before checking the real tree the script self-tests: it builds a good and a bad tree in a `mktemp -d` directory, runs every check against both, and exits 1 with `SELF-TEST FAILED [<id>]` if a check misses its bad tree or flags the good one. `--self-test` runs only that part.
- Bash, `grep`, `find` and `node` (for JSON) only.
- `BoundariesTest` scans every `src/**/*.php` for `use Masthead\…` statements and fully qualified `\Masthead\…` references and asserts the table: `Plugin.php` may import everything; `Admin` → `Config`, `Support`, `Plan`; `Cli` → `Abilities`, `Desk`, `Plan`, `Stats`, `Social`, `Config`, `Support`; `Abilities` → `Desk`, `Plan`, `Stats`, `Social`, `Ai`, `Config`, `Support`; `Cron` → `Desk`, `Stats`, `Config`, `Support`; `Desk` → `Plan`, `Config`, `Support` and only the names `Stats\StatsSource`, `Social\ShareSource`, `Ai\AiClient`, `Ai\Prompts`, `Ai\Schemas`; `Ai`, `Social`, `Stats`, `Plan` → `Config`, `Support`; `Support` → `Config`; `Config.php` → nothing. A module may always import itself. `Masthead\SLUG` and `Masthead\VERSION` are allowed everywhere. The scanner is a method that takes a path and source text so it can be tested on a string.

**Acceptance tests:**

- `tests/unit/BoundariesTest.php`: `BoundariesTest::test_every_source_file_respects_the_module_map`, `BoundariesTest::test_scanner_reports_a_forbidden_import`, `BoundariesTest::test_scanner_allows_desk_to_import_only_named_interfaces`, `BoundariesTest::test_scanner_reports_fully_qualified_references`.
- `bash scripts/forbidden-patterns.sh --self-test` exits 0.

**Out of scope:** any check that `docs/kukulu.json` `constraints` already covers; changing source files to satisfy a check (there should be nothing to fix).

**Verification:** `bash scripts/forbidden-patterns.sh` prints `forbidden-patterns: ok`; `composer test:unit`.

**Depends on:** P0-01, P0-02

### P0-04: Posts › Calendar page and asset loading

**Goal:** Register the Posts › Calendar admin page that renders the React root, and load the built assets and boot data only on Masthead screens.

**Files touched:** `src/Admin/CalendarPage.php`, `src/Admin/Assets.php`, `src/Admin/Notices.php`, `src/Plugin.php`, `tests/unit/Admin/CalendarPageTest.php`, `tests/unit/Admin/AssetsTest.php`, `tests/unit/Admin/NoticesTest.php`

**Design constraints:**

- SPEC §3.1 (admin assets only on Masthead's own screens; no front-end hooks), §4.2 (`Admin` imports only `Config`, `Support`, `Plan`), §6.7.
- `CalendarPage::register()` runs on `admin_menu` and calls `add_submenu_page( 'edit.php', …, 'edit_posts', \Masthead\SLUG, … )`; it passes the returned hook suffix to `Assets::add_page_hook()`. The page prints `<div class="wrap"><div id="masthead-calendar-root"></div></div>`, escaped.
- `Assets::enqueue( string $hook_suffix )` runs on `admin_enqueue_scripts` and returns before doing anything unless the suffix was registered with `add_page_hook()`. It reads `build/index.asset.php` for dependencies and version, enqueues `build/index.js` (handle `\Masthead\SLUG`), `build/index.css` when present and the `wp-components` style, calls `wp_set_script_translations`, and prints boot data with `wp_add_inline_script( …, 'window.mastheadBoot = ' . wp_json_encode( $data ) . ';', 'before' )`.
- Boot data is assembled from providers: `Assets::add_boot_data( string $name, callable $provider )`. This task registers `version`, `now` (ISO 8601 from `Clock::now()`), `today`, `timezone` (`wp_timezone_string()`) and `weekStartsOn` (`Config::get( 'calendar.week_starts_on' )`). `Plugin` adds providers for data that `Admin` may not import.
- `Notices` shows an error notice on Masthead screens only, when `build/index.asset.php` is missing, telling the reader to run `npm run build`.
- `Plugin::boot()` constructs the three classes and adds the `admin_menu`, `admin_enqueue_scripts` and `admin_notices` hooks.

**Acceptance tests:**

- `tests/unit/Admin/CalendarPageTest.php`: `CalendarPageTest::test_registers_submenu_under_posts_with_edit_posts`, `CalendarPageTest::test_render_outputs_the_react_root`.
- `tests/unit/Admin/AssetsTest.php`: `AssetsTest::test_enqueue_returns_early_on_foreign_screen`, `AssetsTest::test_enqueue_loads_script_and_boot_data_on_registered_screen`, `AssetsTest::test_boot_data_merges_registered_providers`, `AssetsTest::test_enqueue_does_nothing_when_build_is_missing`.
- `tests/unit/Admin/NoticesTest.php`: `NoticesTest::test_notice_shown_only_on_masthead_screen_when_build_missing`.

**Out of scope:** the settings page (P2-09); any data beyond the five boot values; any front-end hook.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P0-01

### P0-05: wp-env and the integration test harness

**Goal:** Make `npm run test:integration` start wp-env (WordPress 7.1.4, PHP 8.5, Jetpack 16.4 unconnected) and run PHPUnit integration tests that prove the plugin loads and its page and assets behave.

**Files touched:** `.wp-env.json`, `phpunit.integration.xml.dist`, `package.json`, `tests/integration/bootstrap.php`, `tests/integration/TestCase.php`, `tests/integration/PluginLoadsTest.php`, `tests/integration/AdminPagesTest.php`

**Design constraints:**

- SPEC §7 (`.wp-env.json`, `npm run test:integration`), §3.5.
- `.wp-env.json`: `core` `WordPress/WordPress#7.1.4`, `phpVersion` `8.5`, `plugins` `[".", "https://downloads.wordpress.org/plugin/jetpack.16.4.zip"]`, `mappings` `{"wp-content/masthead-fixtures": "./docs/fixtures"}`, `config` `{"WP_ENVIRONMENT_TYPE": "local", "DISABLE_WP_CRON": true}`. Do not add `MASTHEAD_STATS_FIXTURE` here (PLAN Decisions: it is defined by an e2e mu-plugin in P3-05) and do not add the `tests/e2e/mu` mapping yet (P0-06 creates that directory).
- `package.json` script `test:integration` = `npm run build && wp-env start && wp-env run tests-cli --env-cwd=wp-content/plugins/masthead vendor/bin/phpunit -c phpunit.integration.xml.dist`.
- `tests/integration/bootstrap.php` locates the WordPress test library from the `WP_TESTS_DIR` environment variable of the `tests-cli` container (confirm with `npx wp-env run tests-cli printenv WP_TESTS_DIR`), loads `masthead.php` and `jetpack/jetpack.php` on `muplugins_loaded`, and loads the Yoast polyfills. If the test library rejects PHPUnit 9, change the PHPUnit major in `composer.json` to one both the library and Brain Monkey accept and record the versions in the progress log.
- `tests/integration/TestCase.php` extends the WordPress test case and pins the clock in `set_up()` with `add_filter( 'masthead_now', … )` returning `2026-11-09 14:00:00`, and sets `timezone_string` to `America/Los_Angeles` (Fixture clock protocol).
- `phpunit.integration.xml.dist` excludes the group `tuning`.

**Acceptance tests:**

- `tests/integration/PluginLoadsTest.php`: `PluginLoadsTest::test_plugin_is_loaded_with_expected_version`, `PluginLoadsTest::test_wordpress_and_php_meet_spec_minimums`, `PluginLoadsTest::test_environment_type_is_local`, `PluginLoadsTest::test_jetpack_is_loaded`, `PluginLoadsTest::test_clock_is_pinned_in_tests`.
- `tests/integration/AdminPagesTest.php`: `AdminPagesTest::test_calendar_page_is_registered_under_posts`, `AdminPagesTest::test_assets_enqueue_only_on_masthead_screen`, `AdminPagesTest::test_subscriber_cannot_open_calendar_page`.

**Out of scope:** Playwright; seeding; any change under `src/`.

**Verification:** `npm run test:integration` through `kukulu_verify` (it is the `extraVerify` command for `tests/integration/`, with a 25-minute timeout because the first start downloads WordPress and Jetpack). Do not run it in a foreground loop (wp-env protocol).

**Depends on:** P0-01, P0-02, P0-04

### P0-06: Playwright and axe smoke harness, e2e mu-plugin loader

**Goal:** Add the Playwright + axe harness with a smoke spec that proves the empty app shell renders accessibly at 1280 and 390 pixels, and make wp-env load test-only mu-plugins.

**Files touched:** `package.json`, `package-lock.json`, `.wp-env.json`, `.eslintrc.js`, `tests/e2e/playwright.config.mjs`, `tests/e2e/global-setup.mjs`, `tests/e2e/helpers/wp.mjs`, `tests/e2e/helpers/axe.mjs`, `tests/e2e/mu-loader.php`, `tests/e2e/mu/pin-now.php`, `tests/e2e/smoke/shell.spec.js`

**Design constraints:**

- SPEC §4.1 (`tests/e2e/`, `tests/e2e/mu/`), §6.7 (axe serious/critical = 0 at 1280 and 390), §7.
- Add `@playwright/test` and `@axe-core/playwright` as devDependencies. Scripts: `test:e2e:smoke` = `npm run build && wp-env start && wp-scripts test-playwright --config tests/e2e/playwright.config.mjs tests/e2e/smoke`; `test:e2e` = `npm run build && wp-env start && wp-env run cli wp masthead seed --fixture=wp-content/masthead-fixtures/queue-2026-11-09.json --now="2026-11-09 14:00:00" --reset && wp-scripts test-playwright --config tests/e2e/playwright.config.mjs` (SPEC §7 with the build step first; it first runs in P1-14).
- Playwright config: `testDir` is `tests/e2e`, one worker, not fully parallel, base URL `http://localhost:8888`, two Chromium projects named `desktop` (1280×800) and `mobile` (390×844), global setup that logs in as `admin` / `password` and saves storage state.
- `helpers/axe.mjs` exports `expectNoSeriousViolations( page, rootSelector )`, which runs axe scoped to the Masthead root element and fails on any `serious` or `critical` violation. `helpers/wp.mjs` exports `wpCli( args )` (runs `npx wp-env run cli wp …` and returns stdout) and `seedFixture()` (the seed command above; used from P1-14 on).
- WordPress does not load mu-plugins from a subdirectory. Add to `.wp-env.json` `mappings`: `"wp-content/mu-plugins/masthead-e2e": "./tests/e2e/mu"` (SPEC §7) and `"wp-content/mu-plugins/masthead-e2e-loader.php": "./tests/e2e/mu-loader.php"`; the loader requires every `*.php` in `masthead-e2e/` in name order.
- `tests/e2e/mu/pin-now.php`: when option `masthead_seed_now` holds a `Y-m-d H:i:s` string, add a `masthead_now` filter returning it. This file is test code and is never packaged.

**Acceptance tests:**

- `tests/e2e/smoke/shell.spec.js`: "calendar page renders the app shell", "calendar page has no serious or critical axe violations", "e2e mu-plugins are loaded" (asserts through `wpCli` that the `masthead_now` filter has a callback after setting the option, then deletes the option).

**Out of scope:** any spec that needs seeded content; CI; changes under `src/` or `client/`.

**Verification:** `npx playwright install chromium` once, then `npm run test:e2e:smoke` through `kukulu_verify` (the `extraVerify` command for `tests/e2e/smoke/`, 40-minute timeout); `npm run lint`.

**Depends on:** P0-04, P0-05

### P0-07: Release packer and LICENSE

**Goal:** Make `npm run release:pack` produce `dist/masthead.zip` and `dist/masthead.zip.sha256` with exactly the shippable files.

**Files touched:** `scripts/release/pack.mjs`, `package.json`, `LICENSE`, `tests/js/release/pack.test.js`

**Design constraints:**

- SPEC §2 (release zip pinned by tag, GPL-2.0-or-later), §7 (release), Appendix B (the checksum line is consumed by `sha256sum -c`).
- Script `release:pack` = `npm run build && node scripts/release/pack.mjs`. The zip has one top-level directory `masthead/` containing `masthead.php`, `src/`, `build/`, `LICENSE`, and `readme.txt`, `README.md`, `CHANGELOG.md` when they exist. It never contains `tests/`, `docs/`, `client/`, `scripts/`, `vendor/`, `node_modules/`, dotfiles or tool configuration.
- `pack.mjs` stages the files in a temporary directory in sorted order, zips with the system `zip -X`, writes `dist/masthead.zip.sha256` as `<hex>  masthead.zip`, then lists the archive and exits non-zero if a forbidden path is present or `masthead/build/index.js` or `masthead/masthead.php` is missing. `node scripts/release/pack.mjs --check-tag <tag>` exits 0 only when `<tag>` is `v` followed by `package.json`'s version, and packs nothing.
- Export pure functions for tests: `shouldInclude( relativePath )`, `tagMatches( tag, version )`, `checksumLine( hex )`.
- `LICENSE` is the verbatim GNU GPL version 2 text. Do not type it from memory: download `https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt` with `curl -fsSL`, and confirm it contains `Version 2, June 1991`.

**Acceptance tests:**

- `tests/js/release/pack.test.js`: "includes plugin code and build output", "excludes tests, docs, client sources and tool configuration", "tag must equal v plus the package version", "checksum line is sha256sum compatible".

**Out of scope:** GitHub workflows; `readme.txt`, `README.md`, `CHANGELOG.md` (Phase 6); creating tags.

**Verification:** `npm run release:pack` (the `extraVerify` command for `scripts/release/`), then `cd dist && sha256sum -c masthead.zip.sha256` reports OK; `npm run lint`; `npm run test:unit`.

**Depends on:** P0-02

### P0-08: CI and release workflows

**Goal:** Add GitHub Actions workflows that run the SPEC §7 commands on every push and publish the release zip on a `v*` tag.

**Files touched:** `.github/workflows/ci.yml`, `.github/workflows/release.yml`, `tests/js/ci/workflows.test.js`

**Design constraints:**

- SPEC §8 Phase 0 (CI runs §7 verify + integration + e2e; release on `v*` tags checks the tag against `package.json` and uploads `dist/masthead.zip` and `.sha256`), §3.5 (audits).
- `ci.yml` on `push` and `pull_request`, three jobs. `verify`: PHP 8.3 and the `.nvmrc` Node version, `composer install`, `npm ci`, then `composer lint`, `composer test:unit`, `npm run lint`, `npm run test:unit`, `bash scripts/forbidden-patterns.sh`, `npm run build`, `composer audit`, `npm audit --audit-level=high`. `integration`: `npm run test:integration`. `e2e`: `npx playwright install --with-deps chromium`, then `npm run test:e2e:smoke` (P1-14 changes this line to `npm run test:e2e`), uploading the Playwright report on failure.
- `release.yml` on tags matching `v*`, `permissions: contents: write`: `npm ci`, `node scripts/release/pack.mjs --check-tag "$GITHUB_REF_NAME"`, `npm run release:pack`, then `gh release create "$GITHUB_REF_NAME" dist/masthead.zip dist/masthead.zip.sha256 --generate-notes`.
- No step passes `--no-gpg-sign`. Third-party actions are limited to `actions/*` and `shivammathur/setup-php`.

**Acceptance tests:**

- `tests/js/ci/workflows.test.js` (reads the YAML as text): "ci runs every verify command from the spec", "ci runs integration and e2e jobs", "ci runs both audits", "release checks the tag before packing", "release uploads the zip and its checksum".

**Out of scope:** running the workflows; pushing a tag; branch protection.

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P0-02

### P0-09: Phase 0 push and manual checks

**Goal:** Push the branch and record what a human must check for Phase 0.

**Files touched:** `docs/manual-checks.md`

**Design constraints:** SPEC §8 Phase 0 manual check. Follow the Phase end protocol. Create `docs/manual-checks.md` with a `## Phase 0` section listing: (1) `npx wp-env start`, open Posts › Calendar, see the empty app shell with the heading "Calendar" and no console errors; (2) push a `v0.0.1` tag on a scratch fork and see `masthead.zip` and `masthead.zip.sha256` attached to the release; (3) confirm the CI run for this branch is green.

**Acceptance tests:** none (documentation only); the full `verify` set passes.

**Out of scope:** performing the manual checks; pushing any tag; fixing anything found by a human.

**Verification:** `composer audit` and `npm audit --audit-level=high`, results recorded in the progress log; push the branch; write `Manual check: NOT VERIFIED (human)` in the progress log.

**Depends on:** none

## Phase 1 — Plan model and read abilities

### P1-01: Support\Dates and Support\Json

**Goal:** Add the site-timezone date helpers and JSON helpers every later module uses, so no other file needs a clock or date function.

**Files touched:** `src/Support/Dates.php`, `src/Support/Json.php`, `tests/unit/Support/DatesTest.php`, `tests/unit/Support/JsonTest.php`

**Design constraints:**

- SPEC §4.2 (`Support` imports only `Config`), §3.2 (one clock), §6.1 (dates `YYYY-MM-DD` in site time; datetimes ISO 8601 with offset).
- `Dates` is static and pure given `wp_timezone()`. It never asks for the current time (that is `Clock`). It parses only with `\DateTimeImmutable::createFromFormat( '!…', $value, wp_timezone() )` (the leading `!` is required by the `one-clock` constraint) and rejects a value that does not round-trip. Methods: `parse_date( string $ymd ): ?\DateTimeImmutable`, `parse_local( string $mysql ): ?\DateTimeImmutable` (`Y-m-d H:i:s`), `parse_iso( string $iso ): ?\DateTimeImmutable` (with offset), `is_date( string ): bool`, `is_time( string ): bool` (`HH:MM`, 24-hour), `combine( string $ymd, string $hhmm ): string` (local `Y-m-d H:i:00`), `to_ymd`, `to_mysql`, `to_iso( \DateTimeImmutable ): string`, `local_to_gmt( string $mysql ): string`, `weekday( string $ymd ): int` (0 = Sunday, PHP `w`), `add_days( string $ymd, int $days ): string`, `diff_days( string $from, string $to ): int` (`$to` minus `$from`), `range( string $from, string $to ): array` (inclusive; empty when `$to` is before `$from`), `time_of( string $mysql ): string` (`HH:MM`).
- `Dates::DAYS_IN_WEEK = 7` is a public constant; later tasks write `Dates::DAYS_IN_WEEK - 1` for the highest weekday.
- `Json`: `encode( mixed ): string` (unescaped slashes and unicode), `decode_object( string ): ?array` (associative array, or `null` when the text is not a JSON object), `string_list( mixed ): array` (keeps trimmed non-empty strings, re-indexed). The decode depth is a named `private const`.
- Daylight-saving boundaries: `add_days` and `diff_days` work on calendar dates, not on 24-hour spans.

**Acceptance tests:**

- `tests/unit/Support/DatesTest.php`: `DatesTest::test_parse_date_rejects_invalid_and_overflowing_dates`, `DatesTest::test_parse_does_not_depend_on_the_wall_clock`, `DatesTest::test_weekday_of_2026_11_09_is_monday`, `DatesTest::test_add_days_crosses_dst_boundary_by_calendar_day`, `DatesTest::test_diff_days_is_signed`, `DatesTest::test_range_is_inclusive_and_empty_when_reversed`, `DatesTest::test_combine_and_time_of_round_trip`, `DatesTest::test_local_to_gmt_uses_site_timezone`, `DatesTest::test_to_iso_carries_the_site_offset`, `DatesTest::test_is_time_accepts_only_24_hour_hh_mm`.
- `tests/unit/Support/JsonTest.php`: `JsonTest::test_decode_object_returns_null_for_invalid_or_non_object_json`, `JsonTest::test_string_list_drops_non_strings_and_blanks`, `JsonTest::test_encode_keeps_unicode_and_slashes`.

**Out of scope:** anything that needs the current time; localised formatting (that is `Clock::format()`).

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P0-01

### P1-02: Rhythm option, activation defaults and Lanes

**Goal:** Store and sanitise the weekly rhythm, install its defaults on activation, and answer "which lane is this date".

**Files touched:** `src/Plan/Rhythm.php`, `src/Plan/Lanes.php`, `src/Plugin.php`, `masthead.php`, `tests/unit/Plan/RhythmTest.php`, `tests/unit/Plan/LanesTest.php`, `tests/integration/Plan/RhythmActivationTest.php`

**Design constraints:**

- SPEC §5.1 (`masthead_rhythm`), §2 (rhythm goal), §6.8 (`masthead_lane_for_date`), §6.3 (lane term), §9 question 6 (one rhythm for all dates).
- `Rhythm::DEFAULTS` is a PHP constant equal to `docs/fixtures/rhythm-defaults.json` (the release zip has no `docs/`); a unit test compares the two. Times are strings, so no numeric literal is involved.
- `Rhythm::get(): array` returns the sanitised option, or `DEFAULTS` when the option is missing. `Rhythm::sanitize( mixed $raw ): array` always returns the seven keys `"0"`..`"6"` (PHP `w` weekdays), each `{ lane (sanitize_title), label (sanitize_text_field), categories: [slug], time: "HH:MM" }`, falling back per field to `DEFAULTS`. `Rhythm::install_defaults(): void` writes the option only when it does not exist, keeping for each lane only the category slugs that exist as `category` terms (SPEC §5.1: "applied on activation only for categories that exist"). `Rhythm::reinstall_defaults(): void` does the same after deleting the option (the seeder uses it).
- `masthead.php` registers `register_activation_hook( __FILE__, array( \Masthead\Plugin::class, 'activate' ) )`; `Plugin::activate()` calls `Rhythm::install_defaults()`.
- `Lanes::for_date( string $ymd ): array` returns `{ slug, label, categories, time, weekday }` for that date's weekday and passes it through `apply_filters( 'masthead_lane_for_date', $lane, $ymd )`. A lane with `categories: []` accepts any category.
- `Lanes::fit( ?string $primary, array $categories, array $lane ): float` implements SPEC §6.3's lane term without the series exception: `1.0` when the lane has no categories or contains the primary category, `0.5` when it contains any of the post's categories, else `0.0`.
- `Lanes::nearest_date_for_category( string $ymd, string $category, int $window ): ?string` returns the nearest other date within ±`$window` days whose lane's `categories` explicitly contains `$category` (an empty-category lane is not a target); the earlier date wins a tie; `null` when there is none.

**Acceptance tests:**

- `tests/unit/Plan/RhythmTest.php`: `RhythmTest::test_defaults_equal_the_fixture_file`, `RhythmTest::test_sanitize_returns_seven_weekdays`, `RhythmTest::test_sanitize_rejects_bad_time_and_keeps_default`, `RhythmTest::test_install_defaults_keeps_only_existing_categories`, `RhythmTest::test_install_defaults_does_not_overwrite`.
- `tests/unit/Plan/LanesTest.php`: `LanesTest::test_2026_11_18_is_the_standalone_lane`, `LanesTest::test_filter_can_replace_the_lane`, `LanesTest::test_fit_is_one_for_primary_match_and_for_empty_lane`, `LanesTest::test_fit_is_half_for_secondary_match`, `LanesTest::test_fit_is_zero_without_match`, `LanesTest::test_nearest_date_for_writing_from_monday_2026_12_14_is_saturday_2026_12_12`, `LanesTest::test_nearest_date_prefers_earlier_on_tie`, `LanesTest::test_nearest_date_ignores_empty_category_lanes`.
- `tests/integration/Plan/RhythmActivationTest.php`: `RhythmActivationTest::test_activation_installs_defaults_for_existing_categories`.

**Out of scope:** the settings UI for the rhythm (P2-10); a dated rhythm history.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P1-01

### P1-03: Primary category and Colors

**Goal:** Define a post's primary category and resolve every category's colour.

**Files touched:** `src/Support/Categories.php`, `src/Plan/Colors.php`, `src/Plugin.php`, `tests/unit/Support/CategoriesTest.php`, `tests/unit/Plan/ColorsTest.php`

**Design constraints:**

- SPEC §5.2 (category term meta `masthead_color`, defaults by slug, palette, 4.5:1 contrast), §2 (coloured by primary category).
- Interpretation: SPEC never defines "primary category". It is the post's `category` term with the lowest `term_id`. `Support\Categories` (in `Support` so that `Stats` can use the same rule) has `primary( array $terms ): ?string` (pure, takes objects with `term_id` and `slug`), `for_post( int $post_id ): array` returning `{ slugs: [slug sorted by term_id], primary: ?slug }` from `get_the_terms()`, and `all(): array` returning `[{ term_id, slug, name }]` from `get_terms()` bounded by `Config::get( 'desk.candidate_pool' )`.
- `Colors::DEFAULTS` maps the seven slugs of SPEC §5.2 to their hex values; `Colors::PALETTE` is those seven hex values in SPEC's order. `Colors::for_term( int $term_id, string $slug ): string` returns the term meta `masthead_color` when it is a valid hex colour, else `DEFAULTS[ $slug ]`, else `PALETTE[ $term_id % count( PALETTE ) ]`. `Colors::legend(): array` returns `[{ slug, name, color }]` for `Categories::all()`.
- `Colors::register_meta()` (hooked on `init` from `Plugin`) registers term meta `masthead_color` on `category`: string, single, `show_in_rest`, `sanitize_callback` `sanitize_hex_color`, `auth_callback` requiring `manage_categories`.
- The contrast calculation lives in the unit test, not in `src/`.

**Acceptance tests:**

- `tests/unit/Support/CategoriesTest.php`: `CategoriesTest::test_primary_is_lowest_term_id`, `CategoriesTest::test_primary_is_null_without_categories`, `CategoriesTest::test_for_post_sorts_slugs_by_term_id`, `CategoriesTest::test_all_is_bounded_by_candidate_pool`.
- `tests/unit/Plan/ColorsTest.php`: `ColorsTest::test_term_meta_wins_when_valid_hex`, `ColorsTest::test_default_by_slug`, `ColorsTest::test_unknown_slug_uses_palette_by_term_id`, `ColorsTest::test_every_default_and_palette_colour_has_contrast_of_at_least_4_5_on_white`.

**Out of scope:** a colour picker UI; any primary-category meta or filter.

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P0-01

### P1-04: Cadence

**Goal:** Implement the series cadence value and its date arithmetic.

**Files touched:** `src/Plan/Cadence.php`, `tests/unit/Plan/CadenceTest.php`

**Design constraints:**

- SPEC §5.2 (`masthead_cadence`: `{ every_weeks: int ≥ 1, weekday: 0–6 | null, time: "HH:MM" }`, `weekday: null` means the same weekday as the previous part), §6.3 (`cadence_break`).
- Pure static class using `Support\Dates`. `sanitize( mixed $raw ): ?array` returns a valid cadence or `null`. `from_json( string ): ?array` and `to_json( array ): string` use `Support\Json`; `sanitize_json( mixed ): string` is the term-meta sanitise callback (returns `''` for an invalid value).
- `expected_date( string $previous_ymd, array $cadence ): string`: the base date is the previous date plus `every_weeks × Dates::DAYS_IN_WEEK` days; when `weekday` is set and differs from the base date's weekday, the result is the date nearest the base that has that weekday (it is unique within three days either side).
- `is_break( string $previous_ymd, string $actual_ymd, array $cadence, int $window ): bool` is true when the actual date differs from `expected_date()` by more than `$window` days. Callers pass `Config::get( 'desk.series_window_days' )` (⚠️ ASSUMPTION, default 3); the number is never written here.
- `schedule( string $start_ymd, array $cadence, int $count ): array` returns `$count` dates starting at `$start_ymd`.

**Acceptance tests:**

- `tests/unit/Plan/CadenceTest.php`: `CadenceTest::test_sanitize_rejects_zero_weeks_bad_weekday_and_bad_time`, `CadenceTest::test_json_round_trip`, `CadenceTest::test_expected_date_same_weekday_four_weeks_after_2026_11_09_is_2026_12_07`, `CadenceTest::test_expected_date_moves_to_named_weekday`, `CadenceTest::test_mantle_part_two_on_2026_12_14_is_a_break_with_window_three`, `CadenceTest::test_mantle_part_three_on_2027_01_11_after_2026_12_14_is_not_a_break`, `CadenceTest::test_schedule_returns_requested_number_of_dates`.

**Out of scope:** reading or writing term meta (P1-05); findings (Phase 4).

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P1-01

### P1-05: SeriesSource adapter and series term meta

**Goal:** Read series from ttm-core's `series` taxonomy when it exists, otherwise from Masthead's own `masthead_series`, and register Masthead's series term meta.

**Files touched:** `src/Plan/SeriesSource.php`, `src/Plugin.php`, `tests/unit/Plan/SeriesSourceTest.php`, `tests/integration/Plan/SeriesSourceTest.php`

**Design constraints:**

- SPEC §5.2 (series source, part and total meta names, `masthead_cadence`, `masthead_lane_exception`), §6.8 (`masthead_series_taxonomy`), §2 (Masthead never replaces ttm-core's series UI).
- `taxonomy(): string` is `apply_filters( 'masthead_series_taxonomy', taxonomy_exists( 'series' ) ? 'series' : 'masthead_series' )`; a filtered value that is not a registered taxonomy falls back to `masthead_series`.
- `register()` is hooked on `init` at priority `PHP_INT_MAX` (so ttm-core has registered first; no numeric literal). When the resolved taxonomy is `masthead_series` and it is not registered, register it for `post`: non-hierarchical, `public` false, `show_ui` true, `show_in_rest` true, `rewrite` false. Then register on the resolved taxonomy the term meta `masthead_cadence` (string holding JSON, single, `show_in_rest`, sanitised by `Cadence::sanitize_json`) and `masthead_lane_exception` (boolean, single, `show_in_rest`), both with an `auth_callback` requiring `manage_categories`. When the taxonomy is `masthead_series` also register post meta `masthead_series_part` (integer, single, `show_in_rest` false) and term meta `masthead_total_parts` (integer).
- The part number is post meta `ttm_series_part` when the taxonomy is `series`, else `masthead_series_part`. The total is term meta `ttm_total_parts` or `masthead_total_parts`.
- Methods: `for_post( int $post_id ): ?array` → `{ term_id, name, slug, part: ?int, total: ?int }`; `terms(): array` (bounded by `Config::get( 'desk.candidate_pool' )`); `parts( int $term_id ): array` → `[{ post_id, part, status, date: ?string (local MySQL), dated: bool }]` ordered by part then post id, bounded by `Config::get( 'calendar.max_posts_per_range' )`; `cadence( int $term_id ): ?array`; `lane_exception( int $term_id ): bool`; `rest_base(): string`; `set_part( int $post_id, int $term_id, int $part ): void` (assigns the term and the part meta).
- A draft is "dated" when its `post_date_gmt` is not `0000-00-00 00:00:00` (SPEC §5.3).

**Acceptance tests:**

- `tests/unit/Plan/SeriesSourceTest.php`: `SeriesSourceTest::test_taxonomy_is_series_when_registered`, `SeriesSourceTest::test_taxonomy_falls_back_to_masthead_series`, `SeriesSourceTest::test_filter_value_must_be_a_registered_taxonomy`, `SeriesSourceTest::test_meta_names_follow_the_taxonomy`.
- `tests/integration/Plan/SeriesSourceTest.php`: `SeriesSourceTest::test_registers_masthead_series_when_series_is_absent`, `SeriesSourceTest::test_uses_stub_series_taxonomy_and_ttm_meta_when_present` (registers a stub `series` taxonomy, then unregisters it), `SeriesSourceTest::test_term_meta_is_exposed_in_rest`, `SeriesSourceTest::test_parts_are_ordered_and_report_dated_flag`.

**Out of scope:** any series editing UI (P2-11); cadence findings (Phase 4).

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P1-04, P0-05

### P1-06: Slots (non-floating dated drafts)

**Goal:** Create planned slots as real drafts with a fixed date, and own every post insert and delete in the plugin.

**Files touched:** `src/Plan/Slots.php`, `src/Plugin.php`, `tests/unit/Plan/SlotsTest.php`, `tests/integration/Plan/SlotsTest.php`

**Design constraints:**

- SPEC §2 (planned slots are dated drafts), §5.3 (post meta; "Dated drafts are non-floating"), §6.8 (`masthead_planned_draft_created`), §3.1 (`writes-confined`: `wp_insert_post` and `wp_delete_post` only here and in `Reschedule.php`).
- `Slots::insert( array $postarr, string $status ): int|\WP_Error` is the only `wp_insert_post` call. For its duration it adds a `wp_insert_post_data` filter that sets `post_status` to `$status`, and removes it in a `finally` block. Reason (Fixture clock protocol): WordPress core flips `future` and `publish` by the wall clock; Masthead never changes a status it was not asked to set.
- `Slots::delete( int $post_id ): bool` is the only `wp_delete_post` call (force delete; used by the seeder's reset).
- `Slots::create( array $input ): int|\WP_Error` takes `{ date, time?, title, category, series?: { term_id, part }, outline?: [string] }`. It validates `date` and `time` with `Support\Dates` (`masthead_invalid_date`, status 400), defaults `time` to the lane's time for that date (`Lanes::for_date()`), requires `category` to be an existing category slug (`masthead_unknown_category`, status 400), and inserts a `post` in `draft` status with `post_date`, an explicit `post_date_gmt` (`Dates::local_to_gmt()`) and `edit_date => true`. It then assigns the category, sets post meta `masthead_planned` to true, stores `outline` as JSON in `masthead_outline` and as block markup in the post content (a `core/group` with class `masthead-outline` containing a `core/list`, each item escaped), assigns the series term and part through `SeriesSource::set_part()`, and fires `do_action( 'masthead_planned_draft_created', $post_id )`.
- `Slots::is_dated( object $post ): bool` is true when `post_date_gmt` is not `0000-00-00 00:00:00`.
- `Slots::register_meta()` (hooked on `init`) registers post meta `masthead_planned` (boolean), `masthead_outline` (string) and `masthead_last_shared` (string), each single, with `show_in_rest` false: SPEC §2 forbids changing the REST responses the theme uses.
- Post types come from `Config::get( 'post_types' )`.

**Acceptance tests:**

- `tests/unit/Plan/SlotsTest.php`: `SlotsTest::test_create_rejects_invalid_date`, `SlotsTest::test_create_rejects_unknown_category`, `SlotsTest::test_create_defaults_time_to_the_lane_time`, `SlotsTest::test_outline_markup_is_a_group_with_a_list_and_escaped_items`, `SlotsTest::test_insert_pins_status_and_removes_its_filter`, `SlotsTest::test_is_dated_is_false_for_zero_gmt_date`.
- `tests/integration/Plan/SlotsTest.php`: `SlotsTest::test_created_draft_is_dated_planned_and_categorised`, `SlotsTest::test_dated_draft_keeps_both_dates_after_rest_title_update` (SPEC §5.3's test: update the title through `wp/v2/posts/<id>` and assert `post_date` and `post_date_gmt` are unchanged), `SlotsTest::test_series_term_and_part_are_set`, `SlotsTest::test_action_fires_with_post_id`, `SlotsTest::test_insert_keeps_future_status_for_a_date_in_the_wall_clock_past`.

**Out of scope:** the ability (P1-11); rescheduling (P1-07); post types other than `post`.

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P1-02, P1-03, P1-05, P0-05

### P1-07: Reschedule

**Goal:** Move a `future` post or a draft to another date without ever changing its status.

**Files touched:** `src/Plan/Reschedule.php`, `tests/unit/Plan/RescheduleTest.php`, `tests/integration/Plan/RescheduleTest.php`

**Design constraints:**

- SPEC §6.1 (`masthead/reschedule`: idempotent, refuses `publish`), §5.4 (`calendar.drag_published` false), §6.8 (`masthead_rescheduled`), §3.1 (`writes-confined`: this file holds the only `wp_update_post` call), §2 (no automatic publishing).
- `Reschedule::move( int $post_id, string $date, ?string $time ): array|\WP_Error` returns `{ post_id, date (ISO 8601 with offset), status }`.
- Refusals (`WP_Error` with a `status`): post missing or not in `Config::get( 'post_types' )` → `masthead_not_found` (404); status `publish` → `masthead_published_locked` (409), always, whatever `calendar.drag_published` says (that key only tells the client whether to offer a drag); any status other than `future` or `draft` → `masthead_unsupported_status` (409); invalid date or time → `masthead_invalid_date` (400); a `future` post whose new local datetime is not after `Clock::now()` → `masthead_past_date` (400). A draft may take any date.
- Time: the given `time`; else the post's current local time when it is dated; else the lane's default time for the target date. An undated draft therefore becomes a dated draft.
- The write sets `post_date`, an explicit `post_date_gmt` and `edit_date => true`, with `post_status` pinned to its current value by the same temporary `wp_insert_post_data` filter technique as `Slots::insert()`. When the date and time already match, nothing is written and the current values are returned.
- After a write, fire `do_action( 'masthead_rescheduled', $post_id, $old, $new )` with local MySQL datetimes (`$old` is `null` for a draft that was undated).

**Acceptance tests:**

- `tests/unit/Plan/RescheduleTest.php`: `RescheduleTest::test_refuses_published_post`, `RescheduleTest::test_refuses_unsupported_status`, `RescheduleTest::test_refuses_past_datetime_for_future_post`, `RescheduleTest::test_keeps_time_when_time_is_omitted`, `RescheduleTest::test_undated_draft_takes_lane_time`, `RescheduleTest::test_same_date_and_time_writes_nothing`.
- `tests/integration/Plan/RescheduleTest.php`: `RescheduleTest::test_future_post_moves_and_stays_future`, `RescheduleTest::test_dated_draft_moves_and_stays_draft`, `RescheduleTest::test_undated_draft_becomes_dated`, `RescheduleTest::test_action_reports_old_and_new_dates`, `RescheduleTest::test_published_post_is_refused_and_unchanged`.

**Out of scope:** the ability (P1-12); swapping two posts; drag UI.

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P1-02, P1-06

### P1-08: PlanReader

**Goal:** Read every post in a date range, and the unscheduled drafts, as `PlanPost` records grouped by day with each day's lane.

**Files touched:** `src/Plan/PlanReader.php`, `tests/unit/Plan/PlanReaderTest.php`, `tests/integration/Plan/PlanReaderTest.php`

**Design constraints:**

- SPEC §6.1 (`get-plan` output, `PlanPost` type), §5.3 (an undated draft is unscheduled and never on the grid), §5.4 (`calendar.max_posts_per_range`, ⚠️ ASSUMPTION, default 500: the cap for one call; read it with `Config::get()`), §3.2 (bounded queries).
- `PlanReader::range( string $from, string $to ): array` returns `{ days: [Day], unscheduled: [PlanPost], truncated: bool }`. One `get_posts()` call: post types from `Config`, statuses `publish`, `future`, `draft`, an inclusive `date_query` on the local date, ordered by date then ID ascending, `posts_per_page` = `Config::get( 'calendar.max_posts_per_range' )`, `no_found_rows => true`, `suppress_filters => false`. Undated drafts are dropped from the range in PHP. `truncated` is true when the query returned exactly the cap.
- `Day` is `{ date, lane: { slug, label, categories }, posts: [PlanPost], open: bool }` for every date in the range; `open` is true when the day has no post and the date is `Clock::today()` or later.
- `PlanPost` is `{ id, title, status, date (ISO 8601 with offset, or null when undated), categories: [slug], primary_category, color, series?: { term_id, name, part, total }, planned: bool, edit_url }`; `primary_category` comes from `Support\Categories`, `color` from `Colors::for_term()`, `series` from `SeriesSource::for_post()`, `planned` from post meta `masthead_planned`, `edit_url` from `get_edit_post_link( $id, 'raw' )`. Undated drafts additionally carry `modified` (ISO 8601).
- `PlanReader::unscheduled(): array` returns undated drafts ordered by modified date descending, at most `Config::get( 'desk.candidate_pool' )`.
- `PlanReader::post( int $post_id ): ?array` returns one `PlanPost`.
- `PlanReader::scheduled_between( string $from, string $to ): array` returns the `future` posts and dated drafts in the range (used by the swap check).
- The mapping from a post object to `PlanPost` is a separate method that unit tests call with plain objects.

**Acceptance tests:**

- `tests/unit/Plan/PlanReaderTest.php`: `PlanReaderTest::test_range_returns_one_day_per_date_with_lane`, `PlanReaderTest::test_undated_drafts_are_not_on_any_day`, `PlanReaderTest::test_open_is_false_for_past_days_and_days_with_posts`, `PlanReaderTest::test_query_is_bounded_by_config_and_sets_no_found_rows`, `PlanReaderTest::test_truncated_when_cap_is_reached`, `PlanReaderTest::test_plan_post_shape`.
- `tests/integration/Plan/PlanReaderTest.php`: `PlanReaderTest::test_range_includes_publish_future_and_dated_draft`, `PlanReaderTest::test_unscheduled_lists_undated_drafts_newest_edit_first`, `PlanReaderTest::test_posts_on_the_last_day_of_the_range_are_included`.

**Out of scope:** suggestions; statistics; any write.

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P1-02, P1-03, P1-05, P1-06

### P1-09: Spike — Abilities REST execution and exposure flags

**Goal:** Establish, from the WordPress 7.1.4 source in wp-env, how an ability is run over REST from admin JavaScript and which `meta` flags expose an ability to REST and to MCP.

**Files touched:** `docs/spikes/P1-09-abilities-rest.md`

**Design constraints:**

- SPEC §9 question 2, §6.1 (`meta.annotations`, `meta.public`), §3.1 (the client may call only `wp/v2/*` and the core Abilities run endpoint). Follow the Spike protocol; time box 60 minutes.
- Question: can `client/` run a `masthead/*` ability through a core REST route with cookie and nonce authentication, and what exactly must the request and the registration look like?
- Facts to record, each with the file and line it was read from: (1) the run route (namespace, path, how the ability name is encoded); (2) the HTTP method required for read-only and for other abilities, and where `input` goes for each; (3) the response shape for success, for a permission failure and for invalid input; (4) the `meta` key that exposes an ability over REST and its default; (5) the `meta` key the MCP Adapter or core reads for MCP exposure (SPEC calls it `meta.public`); (6) the functions and hooks for registering an ability category and an ability; (7) whether core ships a JavaScript client for abilities usable from a classic script built by `wp-scripts`, and its handle; (8) the path of the Connectors admin screen.
- Method: read `wp-includes/abilities-api*` and the abilities REST controllers with `npx wp-env run cli`, then run one core ability through `rest_do_request()` with `wp eval` as an administrator.
- Decision tokens: `Decision: core-endpoint` (the core route works for cookie-authenticated admin JavaScript) or `Decision: bridge` (it does not; state why).
- Fallback when a fact cannot be established: route `/wp-abilities/v1/abilities/<name>/run`; `GET` with an `input` query parameter for read-only abilities and `POST` with a JSON body `{ "input": … }` otherwise; REST flag `meta.show_in_rest`; MCP flag `meta.public`; hook `wp_abilities_api_init`, category hook `wp_abilities_api_categories_init`; no core JavaScript client; `Decision: core-endpoint`.

**Acceptance tests:** none. The file exists with the five headings and a `Decision:` line holding one of the two tokens.

**Out of scope:** any change under `src/` or `client/`; registering abilities; the MCP Adapter itself.

**Verification:** `grep -E '^Decision: (core-endpoint|bridge)$' docs/spikes/P1-09-abilities-rest.md` prints one line.

**Depends on:** P0-05

### P1-10: Abilities Registrar, category and masthead/get-plan

**Goal:** Register the `masthead` ability category and the first ability, `masthead/get-plan`, through a registrar every later ability plugs into.

**Files touched:** `src/Abilities/Ability.php`, `src/Abilities/Registrar.php`, `src/Abilities/GetPlan.php`, `src/Plugin.php`, `tests/unit/Abilities/RegistrarTest.php`, `tests/integration/Abilities/GetPlanTest.php`

**Design constraints:**

- SPEC §6.1 (category `masthead`, registered on `wp_abilities_api_init`; every ability has `input_schema`, `output_schema`, `permission_callback`, `meta.annotations`, `meta.public`), §3.4, §5.4 (`abilities.expose_mcp`, `abilities.expose_share`). Follow the Ability protocol. Read `docs/spikes/P1-09-abilities-rest.md` and use the function, hook and `meta` names in its Facts; where a fact is `UNCONFIRMED` or the file is missing, use the fallback names listed in P1-09 (`wp_abilities_api_init`, `wp_abilities_api_categories_init`, `meta.show_in_rest`, `meta.public`).
- `Abilities\Ability` is an interface: `name(): string`, `label(): string`, `description(): string`, `input_schema(): array`, `output_schema(): array`, `annotations(): array` (`readonly`, `destructive`, `idempotent` booleans), `permission( mixed $input ): bool|\WP_Error`, `execute( mixed $input ): mixed`.
- `Registrar::__construct( Ability ...$abilities )`. `register_category()` registers the category `masthead`. `register()` registers each ability with its schemas, `permission_callback` → `permission()`, `execute_callback` → `execute()`, and `meta`: the annotations, REST exposure true for every ability (the admin UI needs it), and MCP exposure equal to `Config::get( 'abilities.expose_share' )` for `masthead/share-post` and `Config::get( 'abilities.expose_mcp' )` for every other ability. `get( string $name ): ?Ability` returns the object (the CLI calls `execute()` on it directly). `describe(): array` returns `{ <name>: { readonly: bool } }`.
- `Plugin` builds the abilities, passes them to `Registrar`, hooks both registration methods, and adds two boot-data providers: `abilities` = `Registrar::describe()` and `run` = the route template, methods and input placement recorded by the spike.
- `GetPlan`: input `{ from, to }`, both required `YYYY-MM-DD`; `to` not before `from`; the inclusive number of days at most `Config::get( 'calendar.max_range_days' )` (92, SPEC §6.1), else `masthead_range_too_long` (400). Output is `PlanReader::range()`: `{ days, unscheduled, truncated }`. Annotations: read-only, idempotent. Permission: `edit_posts`.

**Acceptance tests:**

- `tests/unit/Abilities/RegistrarTest.php`: `RegistrarTest::test_registers_category_and_every_ability`, `RegistrarTest::test_share_post_exposure_follows_expose_share`, `RegistrarTest::test_other_abilities_follow_expose_mcp`, `RegistrarTest::test_describe_reports_readonly_flag`.
- `tests/integration/Abilities/GetPlanTest.php`: `GetPlanTest::test_allowed`, `GetPlanTest::test_denied`, `GetPlanTest::test_happy_path`, `GetPlanTest::test_range_longer_than_max_range_days_is_refused`, `GetPlanTest::test_reversed_range_is_refused`, `GetPlanTest::test_ability_is_in_the_masthead_category_with_annotations`.

**Out of scope:** the other abilities; REST transport tests (P1-13); any client code.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P1-08, P0-05

### P1-11: masthead/create-planned-draft ability

**Goal:** Expose `Slots::create()` as the `masthead/create-planned-draft` ability.

**Files touched:** `src/Abilities/CreatePlannedDraft.php`, `src/Plugin.php`, `tests/integration/Abilities/CreatePlannedDraftTest.php`

**Design constraints:** SPEC §6.1 row `masthead/create-planned-draft`: input `{ date, time?, title, category, series?: { term_id, part }, outline?: [string] }`, output `{ post_id, edit_url, date }` (`date` ISO 8601 with offset), no annotations set to true, capability `edit_posts`. Follow the Ability protocol. `title` is required and non-empty after `sanitize_text_field`. The class only validates, calls `Slots::create()` and shapes the output; it contains no `wp_insert_post` call (`writes-confined`). `series.term_id` must be a term of `SeriesSource::taxonomy()` (`masthead_unknown_series`, 400). Add the class to the `Registrar` arguments in `Plugin`.

**Acceptance tests:** `tests/integration/Abilities/CreatePlannedDraftTest.php`: `CreatePlannedDraftTest::test_allowed`, `CreatePlannedDraftTest::test_denied`, `CreatePlannedDraftTest::test_happy_path`, `CreatePlannedDraftTest::test_outline_is_stored_in_meta_and_content`, `CreatePlannedDraftTest::test_unknown_category_is_refused_without_creating_a_post`, `CreatePlannedDraftTest::test_unknown_series_is_refused_without_creating_a_post`.

**Out of scope:** creating `future` posts (the Day plan does that through `wp/v2/posts` in P4-10); AI outlines.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P1-06, P1-10

### P1-12: masthead/reschedule ability

**Goal:** Expose `Reschedule::move()` as the `masthead/reschedule` ability.

**Files touched:** `src/Abilities/Reschedule.php`, `src/Plugin.php`, `tests/integration/Abilities/RescheduleTest.php`

**Design constraints:** SPEC §6.1 row `masthead/reschedule`: input `{ post_id, date, time? }`, output `{ post_id, date, status }`, annotation idempotent, capability `edit_post` on that post (`current_user_can( 'edit_post', $post_id )`), refuses `publish` status. Follow the Ability protocol. The class validates, calls `Plan\Reschedule::move()` and returns its result or its `WP_Error`; import the Plan class under an alias to avoid the name clash. Add the class to the `Registrar` arguments in `Plugin`.

**Acceptance tests:** `tests/integration/Abilities/RescheduleTest.php`: `RescheduleTest::test_allowed`, `RescheduleTest::test_denied` (an author on another author's post), `RescheduleTest::test_happy_path`, `RescheduleTest::test_published_post_is_refused`, `RescheduleTest::test_repeating_the_call_is_idempotent`.

**Out of scope:** drag UI (P2-07); swapping.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P1-07, P1-10

### P1-13: REST contract for running abilities

**Goal:** Prove with integration tests that the admin client can run Masthead abilities over REST by the path the spike recorded, adding the thin bridge only if the spike requires it.

**Files touched:** `tests/integration/Abilities/RestTransportTest.php`, `src/Abilities/RestBridge.php`, `src/Plugin.php`

**Design constraints:**

- SPEC §9 question 2, §3.4 (any non-ability REST route checks a capability and a nonce). Read `docs/spikes/P1-09-abilities-rest.md`.
- When it says `Decision: core-endpoint`, or the file is missing: do not create `src/Abilities/RestBridge.php` and do not change `src/Plugin.php`; this task adds only the test file, which runs the abilities through `rest_do_request()` on the core route with the method and input placement the spike recorded (fallbacks as listed in P1-09).
- When it says `Decision: bridge`: create `RestBridge`, registering `POST /masthead/v1/run/<name>` whose permission callback requires a logged-in user and whose handler does nothing but `wp_get_ability( $name )->execute( $input )` for names starting `masthead/`, returning the result or the `WP_Error`; hook it from `Plugin` on `rest_api_init`; set the `run` boot-data provider to this route; and point the tests at it. Record the choice under Interpretation in the commit message.

**Acceptance tests:** `tests/integration/Abilities/RestTransportTest.php`: `RestTransportTest::test_editor_runs_get_plan_over_rest`, `RestTransportTest::test_subscriber_is_denied_over_rest`, `RestTransportTest::test_editor_runs_create_planned_draft_over_rest`, `RestTransportTest::test_invalid_input_returns_a_client_error`, `RestTransportTest::test_boot_data_run_route_matches_the_route_under_test`.

**Out of scope:** client code (`useAbility` is P2-01); any REST route beyond the single bridge route.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P1-10, P1-11

### P1-14: wp masthead seed

**Goal:** Add `wp masthead seed`, which loads the acceptance fixture into a local site, and switch the e2e run to the seeded scenario.

**Files touched:** `src/Cli/Commands.php`, `src/Cli/SeedCommand.php`, `src/Cli/Seeder.php`, `src/Plugin.php`, `.github/workflows/ci.yml`, `tests/unit/Cli/SeederTest.php`, `tests/integration/Cli/SeederTest.php`, `tests/e2e/seed.spec.js`

**Design constraints:**

- SPEC §6.4 (`seed --fixture=<path> [--now=<datetime>] [--reset]`, local environments only), §3.5 (the acceptance scenario is seeded with `wp masthead seed` and a pinned `--now`), §7 (`npm run test:e2e`), §3.1 (`writes-confined`: the seeder inserts and deletes posts only through `Slots::insert()` and `Slots::delete()`).
- `Cli\Commands::register( array $commands )` calls `WP_CLI::add_command( 'masthead <name>', $callable )` for each entry and is invoked from `Plugin` only when `WP_CLI` is defined and true. Every command accepts `--now=<Y-m-d H:i:s>` and applies it with a `masthead_now` filter for that process (a shared method on `Commands`).
- `SeedCommand` refuses with `WP_CLI::error()` unless `wp_get_environment_type()` is `local` (this covers SPEC's "refuses when production"), and when the fixture path is not a readable file or not a JSON object.
- `Seeder::reset()`: deletes every post of the configured post types in batches of `Config::get( 'calendar.max_posts_per_range' )`, deletes every term of `SeriesSource::taxonomy()`, deletes the options `masthead_rhythm`, `masthead_findings`, `masthead_ai_usage`, `masthead_settings`, `masthead_seed_now` and all user meta `masthead_dismissed`.
- `Seeder::seed( array $fixture, ?string $now )`, in this order: set option `timezone_string` to the fixture's `timezone`; create each category by slug and name (fixture term ids are not reproduced); call `Rhythm::reinstall_defaults()` so lanes see the new categories; create each series term in `SeriesSource::taxonomy()` with term meta `masthead_cadence` and the total-parts meta; insert each post through `Slots::insert()` with `import_id` set to the fixture id, the fixture status pinned, `post_date` and explicit `post_date_gmt` for dated posts; for a post with `date: null`, pass `post_date` = the fixture's `modified` and no `post_date_gmt`, so the draft is undated and its modified date is the fixture's; assign categories, tags and series part; call `wp_clear_scheduled_hook( 'publish_future_post', array( $id ) )` for every `future` post; finally store `$now` in option `masthead_seed_now`, or delete that option when `--now` was not given.
- Production code never reads `masthead_seed_now`; the mu-plugin `tests/e2e/mu/pin-now.php` (P0-06) does.
- In `.github/workflows/ci.yml`, change the e2e job's command from `npm run test:e2e:smoke` to `npm run test:e2e`.

**Acceptance tests:**

- `tests/unit/Cli/SeederTest.php`: `SeederTest::test_post_array_for_dated_post_has_explicit_gmt_date`, `SeederTest::test_post_array_for_undated_draft_uses_modified_and_no_gmt_date`, `SeederTest::test_import_id_is_the_fixture_id`.
- `tests/integration/Cli/SeederTest.php`: `SeederTest::test_seeds_ten_posts_with_fixture_ids_and_statuses`, `SeederTest::test_future_posts_stay_future_and_have_no_publish_event`, `SeederTest::test_draft_10178_is_undated_with_modified_2026_10_29`, `SeederTest::test_mantle_series_has_four_parts_and_cadence`, `SeederTest::test_rhythm_defaults_are_installed_for_seeded_categories`, `SeederTest::test_reset_removes_previous_posts_and_options`, `SeederTest::test_seeding_twice_with_reset_is_stable`.
- `tests/e2e/seed.spec.js`: "seeded post 12177 is scheduled for 2026-11-11" (reads `wp/v2/posts/12177?context=edit` with the logged-in request context), "the pinned clock is 2026-11-09" (asserts `window.mastheadBoot.today` on the Calendar page).

**Out of scope:** the other CLI commands; synthetic titles (P6-01); any change to the fixture file.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`, `npm run lint`. This is the first task for which `npm run test:e2e` runs (through `kukulu_verify`, 40-minute timeout).

**Depends on:** P1-02, P1-05, P1-06, P0-06

### P1-15: wp masthead plan

**Goal:** Add `wp masthead plan <from> <to> [--format=table|json]`, printing the plan from the same ability the UI uses.

**Files touched:** `src/Cli/PlanCommand.php`, `src/Plugin.php`, `tests/unit/Cli/PlanCommandTest.php`

**Design constraints:** SPEC §6.4, §2 (one implementation per capability). The command gets the `masthead/get-plan` object from `Registrar::get()` and calls its `execute()` directly: shell access is trusted, so no WordPress user is required. A `WP_Error` becomes `WP_CLI::error()`. `--format=json` prints the ability output unchanged. `--format=table` (default) prints one row per post with the columns `date`, `weekday`, `lane`, `status`, `id`, `title`, and for a day with no post one row whose `status` is `open` and whose `title` is empty; rows are built by a pure method that unit tests call. Accepts `--now` through `Commands`. Register it in `Plugin` beside `seed`.

**Acceptance tests:** `tests/unit/Cli/PlanCommandTest.php`: `PlanCommandTest::test_rows_have_one_row_per_post_and_one_per_open_day`, `PlanCommandTest::test_json_format_prints_ability_output_unchanged`, `PlanCommandTest::test_ability_error_is_reported_as_cli_error`.

**Out of scope:** `suggest`, `swap-check` and the probe commands (later phases).

**Verification:** `composer lint`, `composer test:unit`, then by hand once: `npx wp-env run cli wp masthead plan 2026-11-01 2026-11-30 --format=table --now="2026-11-09 14:00:00"` prints rows for posts 12155, 10193, 12177 and 10194.

**Depends on:** P1-10, P1-14

### P1-16: Tune calendar.max_posts_per_range

**Goal:** Measure `PlanReader::range()` at the SPEC default cap and at alternatives, and keep or change `calendar.max_posts_per_range`.

**Files touched:** `tests/integration/Tuning/PlanRangeBench.php`, `docs/tuning/P1-16.md`, `src/Config.php`

**Design constraints:** SPEC §5.4: `calendar.max_posts_per_range`, ⚠️ ASSUMPTION, default 500, cap for one `get-plan` call. Follow the Tuning protocol. The harness is a PHPUnit class in the group `tuning` (excluded from the normal integration run) that, for each cap in 250, 500 and 1000, creates that many posts spread over `Config::get( 'calendar.max_range_days' )` days through `Slots::insert()`, sets the cap with the `masthead_config` filter, and records the median wall time of five `PlanReader::range()` calls over the whole range and the peak memory, printing one table row per cap. Decision rule: keep 500 unless its median exceeds one second or its peak memory exceeds 64 MB; in that case choose the largest measured cap that passes. Change `src/Config.php` only if the rule says so.

**Acceptance tests:** `tests/integration/Tuning/PlanRangeBench.php`: `PlanRangeBench::test_measure_range_at_each_cap` (group `tuning`; asserts only that each run returned the expected number of posts). `docs/tuning/P1-16.md` holds the table with a before row and an after row.

**Out of scope:** optimising `PlanReader`; any other key.

**Verification:** `npx wp-env run tests-cli --env-cwd=wp-content/plugins/masthead vendor/bin/phpunit -c phpunit.integration.xml.dist --group tuning --filter PlanRangeBench` once; copy the before and after rows into the commit message and the progress log.

**Depends on:** P1-08, P0-05

### P1-17: Phase 1 push and manual checks

**Goal:** Push the branch and record what a human must check for Phase 1.

**Files touched:** `docs/manual-checks.md`

**Design constraints:** SPEC §8 Phase 1 manual check. Follow the Phase end protocol. Append a `## Phase 1` section listing: (1) seed the fixture (`npx wp-env run cli wp masthead seed --fixture=wp-content/masthead-fixtures/queue-2026-11-09.json --now="2026-11-09 14:00:00" --reset`) and run `wp masthead plan 2026-11-01 2026-11-30 --format=table`; the table shows two published posts on Nov 9 and scheduled posts on Nov 11 and Nov 12; (2) in Claude Code with the MCP Adapter connected to the site, discover abilities and see `masthead/get-plan`, `masthead/create-planned-draft` and `masthead/reschedule`; (3) read `docs/spikes/P1-09-abilities-rest.md` and confirm no fact is marked `UNCONFIRMED`.

**Acceptance tests:** none (documentation only); the full `verify` set passes.

**Out of scope:** performing the manual checks.

**Verification:** `composer audit` and `npm audit --audit-level=high`, results recorded in the progress log; push the branch; write `Manual check: NOT VERIFIED (human)` in the progress log.

**Depends on:** none

## Phase 2 — Calendar UI and settings

### P2-01: Boot data and the client data layer

**Goal:** Give the client everything it needs to read the plan: complete boot data, a clock that follows the pinned time, date helpers, `useAbility` and `usePlan`.

**Files touched:** `src/Admin/Assets.php`, `src/Plugin.php`, `client/data/boot.js`, `client/data/clock.js`, `client/data/dates.js`, `client/data/useAbility.js`, `client/data/usePlan.js`, `tests/unit/Admin/AssetsTest.php`, `tests/js/data/dates.test.js`, `tests/js/data/useAbility.test.js`, `tests/js/data/usePlan.test.js`

**Design constraints:**

- SPEC §4.3 (`data/`), §3.1 (`client-endpoints`), §3.2 (determinism; client constraint `client-clock`), §9 question 2. Client invariants for this and every later client task: no `wpcom/`, `jetpack/` or `admin-ajax.php` strings; no `Date.now()` or argument-less `new Date()` outside `client/data/clock.js`; no Config tunable written as a literal (read it from boot data); only `client/components/StatValue.js` reads a stat value; every string translatable with text domain `masthead`.
- Boot data added in `Assets` (it may import `Config`, `Support`, `Plan`): `config` = `{ ghostDays, horizonDays, maxRangeDays, maxPostsPerRange, maxSuggestions, dragPublished, statsWindowDays }` from the matching `Config` keys; `caps` = `{ editPosts, editOthersPosts, publishPosts, promptAi, manageOptions }` from `current_user_can()`; `urls` = `{ newPost, editPost (a template containing `%d`), settings, connectors }` (the Connectors path comes from the P1-09 spike, fallback `options-general.php`); `series` = `{ taxonomy, restBase }` from `SeriesSource`; `categories` = `Colors::legend()`; `rhythm` = `Rhythm::get()`; `ai` = `{ supported: false, remaining: 0 }` (Phase 5 replaces this provider). `abilities` and `run` already come from `Plugin` (P1-10).
- `boot.js` exports `getBoot()`, reading `window.mastheadBoot` once. `clock.js` exports `now()` and `today()` from boot data and is the only client file that may construct a date without arguments (it should not need to).
- `dates.js` works on `YYYY-MM-DD` strings with `Date.UTC`: `addDays`, `diffDays`, `weekday`, `monthGrid( ymd, weekStartsOn )` (whole weeks covering the month), `weekRange( ymd, weekStartsOn )`, `monthLabel`, `dayLabel` (long weekday, month and day, through `@wordpress/date`), `timeLabel( iso )`.
- `useAbility.js` exports `runAbility( name, input )` and the hook `useAbility( name )` returning `{ run, isRunning, error }`. It builds the request from boot `run` and `abilities[ name ].readonly` exactly as the P1-09 spike recorded (fallback: `GET /wp-abilities/v1/abilities/<name>/run` with `input` as a query parameter for read-only abilities, `POST` with `{ input }` otherwise) through `@wordpress/api-fetch`, and rejects with `{ code, message }`.
- `usePlan( from, to )` returns `{ days, unscheduled, truncated, isLoading, error, refresh, applyMove, rollback }`. Interpretation of SPEC §4.3: `masthead/get-plan` is the single source of plan data, because `wp/v2/posts` cannot tell an undated draft from a dated one; `@wordpress/core-data` is used for categories, series terms and settings, and `usePlan.refresh()` also invalidates core-data's post records. `applyMove( postId, date )` moves a post optimistically and returns a token for `rollback( token )`.

**Acceptance tests:**

- `tests/unit/Admin/AssetsTest.php`: `AssetsTest::test_boot_data_has_config_caps_urls_series_categories_rhythm_and_ai`.
- `tests/js/data/dates.test.js`: "monthGrid for November 2026 starting Sunday has five weeks from Nov 1 to Dec 5", "monthGrid honours a Monday week start", "weekday of 2026-11-09 is 1", "addDays crosses a month boundary", "weekRange returns seven days".
- `tests/js/data/useAbility.test.js`: "read-only abilities use the read-only method and input placement from boot data", "other abilities post the input", "a REST error rejects with code and message".
- `tests/js/data/usePlan.test.js`: "loads days and unscheduled drafts for the range", "applyMove moves a post between days and rollback restores it", "refresh refetches the plan".

**Out of scope:** any visible component; suggestions; settings.

**Verification:** `composer lint`, `composer test:unit`, `npm run lint`, `npm run test:unit`.

**Depends on:** P0-04, P1-10, P1-14

### P2-02: Shared components: StatValue, LaneDot, CategoryLegend

**Goal:** Add the three shared components, including the only component allowed to render a statistic.

**Files touched:** `client/components/StatValue.js`, `client/components/LaneDot.js`, `client/components/CategoryLegend.js`, `client/components/style.scss`, `client/style.scss`, `tests/js/components/StatValue.test.js`, `tests/js/components/CategoryLegend.test.js`

**Design constraints:** SPEC §3.3 (every stat value renders through `StatValue`, which shows the value, its `source` and `fetchedAt`; unavailable shows "Unavailable"), §4.3, §6.7 (legend toggles visibility; real buttons). `StatValue` takes `numbers` (`{ source, fetched_at, values }` or `null`), `name` (a key of `values`) and `label`; it renders the label, the value formatted with `Intl.NumberFormat`, the source and the fetched time, or the label and "Unavailable" when `numbers` is null or the value is null or missing. It never renders a number without the source beside it. `LaneDot` takes `color` and an optional `label`; the dot is `aria-hidden`. `CategoryLegend` takes `categories` (`[{ slug, name, color }]`), `hidden` (a set of slugs) and `onToggle( slug )`, and renders one `<button aria-pressed>` per category. Client invariants as in P2-01.

**Acceptance tests:**

- `tests/js/components/StatValue.test.js`: "renders the value with its source and fetched time", "renders Unavailable when numbers is null", "renders Unavailable when the named value is missing", "never renders a number without the source".
- `tests/js/components/CategoryLegend.test.js`: "renders one pressed button per visible category", "calls onToggle with the slug", "marks hidden categories as not pressed".

**Out of scope:** wiring the legend into the calendar (P2-06); any stats request.

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P1-14

### P2-03: PostChip, OpenSlot and DayCell

**Goal:** Render one day of the calendar: its select button, its post chips in the three status styles, and the open-slot marker.

**Files touched:** `client/calendar/PostChip.js`, `client/calendar/OpenSlot.js`, `client/calendar/DayCell.js`, `client/calendar/style.scss`, `client/style.scss`, `tests/js/calendar/PostChip.test.js`, `tests/js/calendar/DayCell.test.js`

**Design constraints:**

- SPEC §6.7 (chip styles; day-cell accessibility), `docs/mockups/Main.dc.html` for sizes and colours. Client invariants as in P2-01.
- `PostChip` takes a `PlanPost` and `onSelect`. It is a `<button>` with `data-post-id` and `data-status`. `publish`: solid chip in the category colour with white text and the meta line "✓ Published · <time>". `future`: white background, text and 1.5 px border in the category colour, a 4 px left bar and a clock glyph, meta line "◷ Scheduled · <time>". Dated `draft`: a tint of the category colour with the meta line "Draft · <time>". The title is clamped to two lines and also given as `title`.
- `OpenSlot` renders "Open · <lane label>" in a dashed neutral box.
- `DayCell` takes a `Day`, flags `isToday`, `isSelected`, `inMonth`, the set of hidden category slugs, and `onSelectDay`, `onSelectPost`. It is a container holding (a) a `<button>` covering the day header with the day number and `aria-label` "<Weekday>, <Month> <day>: open <lane label> slot" for an open day, "<Weekday>, <Month> <day>: <n> posts" (or "1 post") otherwise, and `aria-pressed` for selection; (b) one `PostChip` per post whose primary category is not hidden; (c) `OpenSlot` when `day.open`. Chips are siblings of the day button, never nested inside it. Today shows a "TODAY" marker. The cell has `data-date`.

**Acceptance tests:**

- `tests/js/calendar/PostChip.test.js`: "published chip is solid in the category colour", "scheduled chip is outlined with a clock glyph", "dated draft chip is tinted and labelled Draft", "clicking a chip selects the post".
- `tests/js/calendar/DayCell.test.js`: "open day button is labelled Wednesday, November 18: open Standalone slot", "day with two posts is labelled with the count", "hidden categories are not rendered", "chips are not nested inside the day button", "today is marked".

**Out of scope:** drag and drop (P2-07); ghost cards (P4-08).

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P2-02

### P2-04: Calendar app: toolbar, hash routing and MonthGrid

**Goal:** Show the seeded month on Posts › Calendar with previous/next, Today and the Month/Week/List switch routed by URL hash.

**Files touched:** `client/calendar/App.js`, `client/calendar/Toolbar.js`, `client/calendar/MonthGrid.js`, `client/calendar/style.scss`, `tests/js/calendar/App.test.js`, `tests/js/calendar/MonthGrid.test.js`, `tests/e2e/calendar.spec.js`

**Design constraints:**

- SPEC §4.3 (`App` routes by `#month`, `#week`, `#list`), §6.7 (toolbar), §8 Phase 2 e2e, `docs/mockups/Main.dc.html`. Client invariants as in P2-01.
- `App` keeps the `h1` "Calendar" (the smoke spec asserts it). State: view (from `location.hash`, default `#month`, updated on `hashchange`), anchor date (starts at `clock.today()`), selection (`{ type: 'day' | 'post', … }` or none), hidden categories. It loads the visible range with `usePlan` and shows a notice when `truncated` is true or the request fails.
- `Toolbar`: "Previous month" / "Next month" buttons (the words follow the view), the range title ("November 2026"), "Today", and a view group of three `<button aria-pressed>` (Month, Week, List) that set the hash. The legend (P2-06) and "Fill open days" (P4-08) are added later; do not render placeholders for them.
- `MonthGrid`: a header row of weekday names each followed by that weekday's lane label from boot `rhythm` ("Sun · Faith"), then `monthGrid()` weeks of `DayCell`. The first weekday follows boot `weekStartsOn`. Days outside the month are muted. The grid is one `section` with `aria-label` equal to the month title.
- Week and List views render a placeholder element with `data-view` until P2-05.
- Layout at 1440 px follows the mock: grid on the left, a right-hand column (class `masthead-rail`) that is empty until Phase 3.

**Acceptance tests:**

- `tests/js/calendar/App.test.js`: "renders the Calendar heading" (kept), "defaults to the month view", "switches view when the hash changes", "Today returns the anchor to the boot date", "next and previous move the anchor by one month in month view".
- `tests/js/calendar/MonthGrid.test.js`: "renders five weeks for November 2026", "header shows each weekday's lane label", "marks days outside the month".
- `tests/e2e/calendar.spec.js` (re-seeds in `beforeAll`): "November 2026 shows two published chips on Nov 9", "Nov 11 and Nov 12 each show one scheduled chip", "Nov 9 is marked as today", "view buttons change the URL hash", "month view has no serious or critical axe violations".

**Out of scope:** Week and List content, legend, Draft tray, drag, Desk rail, ghost cards.

**Verification:** `npm run lint`, `npm run test:unit`. `npm run build` and `npm run test:e2e` run through `kukulu_verify`.

**Depends on:** P2-01, P2-03

### P2-05: WeekGrid and ListView

**Goal:** Add the week view and the list (queue) view.

**Files touched:** `client/calendar/WeekGrid.js`, `client/calendar/ListView.js`, `client/calendar/App.js`, `client/calendar/style.scss`, `tests/js/calendar/WeekGrid.test.js`, `tests/js/calendar/ListView.test.js`, `tests/e2e/calendar.spec.js`

**Design constraints:** SPEC §4.3, §6.7, `docs/mockups/DayPlan.dc.html` (week view) and `docs/mockups/PostDetail.dc.html` (list view). Client invariants as in P2-01. `WeekGrid` shows the seven days of `weekRange( anchor )` as columns of `DayCell`, each headed "<Sun 15> · <lane label>"; previous/next move by one week. `ListView` is the upcoming queue: it loads `[ anchor, anchor + boot.config.maxRangeDays − 1 ]` in one `usePlan` call and lists every post in date order as a row with a category colour bar, "<Wed Nov 11> · <time> · <category names>", the title and the status; rows are `<button>`s that select the post; a heading counts the scheduled posts ("Scheduled (5)"); previous/next move by the same span. With the fixture and the pinned clock the list from Nov 9 reaches Feb 8, so it contains posts 12156, 12157 and 12158. Verdict badges are added in P4-16.

**Acceptance tests:**

- `tests/js/calendar/WeekGrid.test.js`: "renders seven day columns with lane labels", "next moves the anchor by seven days".
- `tests/js/calendar/ListView.test.js`: "lists posts in date order with status", "counts scheduled posts in the heading", "selecting a row selects the post".
- `tests/e2e/calendar.spec.js`: "week view shows Nov 8 to Nov 14 with the scheduled posts", "list view lists the five scheduled posts including The Mantle parts 2 to 4".

**Out of scope:** verdict badges, the Day plan, the Post panel.

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P2-04

### P2-06: Legend visibility toggles and Draft tray

**Goal:** Let the category legend hide and show categories, and list unscheduled drafts in a Draft tray.

**Files touched:** `client/calendar/DraftTray.js`, `client/calendar/Toolbar.js`, `client/calendar/App.js`, `client/calendar/style.scss`, `tests/js/calendar/DraftTray.test.js`, `tests/js/calendar/App.test.js`, `tests/e2e/calendar.spec.js`

**Design constraints:** SPEC §6.7 (category legend that toggles visibility; Draft tray), §5.3 (an undated draft appears in the Draft tray, never on the grid), `docs/mockups/DayPlan.dc.html` ("Unscheduled drafts (1)"). Client invariants as in P2-01. `Toolbar` renders `CategoryLegend` with boot `categories`; toggling a slug adds it to or removes it from the hidden set in `App`, which hides chips and list rows whose primary category is hidden (the state is not persisted). `DraftTray` takes `usePlan().unscheduled` and renders the heading "Unscheduled drafts (<n>)" and one row per draft: a `LaneDot` in the category colour, the title, "edited <Oct 29>" from `modified`, and an "Open draft" link to its `edit_url`; it renders "No unscheduled drafts." when empty. Each row has `data-post-id`.

**Acceptance tests:**

- `tests/js/calendar/DraftTray.test.js`: "shows the count and one row per draft", "shows the edited date", "shows an empty message".
- `tests/js/calendar/App.test.js`: "toggling a legend category hides its chips".
- `tests/e2e/calendar.spec.js`: "the Draft tray holds post 10178 and it is not on the grid", "toggling Technology hides the Nov 11 chip and toggling again shows it".

**Out of scope:** dragging a draft onto a day (P2-07).

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P2-02, P2-04

### P2-07: Drag to reschedule and the "Move to…" alternative

**Goal:** Reschedule a `future` post or draft by dragging it to another day, or from the keyboard with a "Move to…" date picker, with an optimistic update and rollback.

**Files touched:** `client/calendar/useReschedule.js`, `client/calendar/MoveTo.js`, `client/calendar/PostChip.js`, `client/calendar/DayCell.js`, `client/calendar/DraftTray.js`, `client/calendar/App.js`, `client/calendar/style.scss`, `tests/js/calendar/useReschedule.test.js`, `tests/js/calendar/MoveTo.test.js`, `tests/e2e/reschedule.spec.js`

**Design constraints:**

- SPEC §6.7 (dragging a `future` post or dated draft calls `masthead/reschedule` with optimistic update and rollback on error; keyboard alternative "Move to…" on each chip), §5.4 (`calendar.drag_published` false: published posts cannot be dragged; read it as boot `config.dragPublished`), §2. Client invariants as in P2-01.
- Native HTML5 drag and drop only; no library (`dependencies` stays `{}`). A chip is `draggable` when its status is `future` or `draft`; a published chip is never draggable and has no "Move to…" control, whatever `dragPublished` says (the ability refuses it). Draft tray rows are draggable too.
- `useReschedule( plan )` returns `move( post, date )`: it calls `plan.applyMove()`, runs `masthead/reschedule` with `{ post_id, date }` (no `time`, so the server keeps the time), then `plan.refresh()`; on error it calls `plan.rollback()` and shows the error message in a dismissible notice.
- `DayCell` accepts drops and shows a drop highlight. Dropping on the post's current date does nothing.
- `MoveTo` is a small `<button>` on each movable chip and tray row, labelled "Move “<title>” to another day", that opens a labelled `<input type="date">` and a "Move" button; submitting calls the same `move()`.

**Acceptance tests:**

- `tests/js/calendar/useReschedule.test.js`: "moves optimistically then confirms with the ability", "rolls back and reports the error when the ability fails", "does nothing when the date is unchanged".
- `tests/js/calendar/MoveTo.test.js`: "published chips have no move control", "submitting a date calls move", "the control is reachable and operable from the keyboard".
- `tests/e2e/reschedule.spec.js` (re-seeds in `beforeAll`): "dragging post 12177 to Nov 10 calls reschedule and persists after reload" (`desktop` project only), "Move to… moves post 10194 to Nov 13 from the keyboard", "a published chip cannot be dragged".

**Out of scope:** swapping two posts; changing a time; rescheduling published posts.

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P1-12, P2-04

### P2-08: Responsive layout at 390 px and accessibility gates

**Goal:** Make every calendar view stack to one column at 390 px and pass axe at both widths.

**Files touched:** `client/calendar/style.scss`, `client/components/style.scss`, `client/style.scss`, `tests/e2e/responsive.spec.js`

**Design constraints:** SPEC §4.3 (matches the mockups at 1440 px and stacks to one column at 390 px), §6.7 (every control is a real `<button>`, `<input>` or `<a>`; axe serious/critical = 0 at 1280 and 390). CSS only, plus markup fixes axe demands. At widths up to the WordPress admin mobile breakpoint the month grid becomes a single column of days in date order (days outside the month hidden, weekday and lane shown in each day header), the toolbar wraps, the rail column moves below the grid, and nothing causes horizontal page scroll; the week view may scroll inside its own container. Colours on tinted and outlined chips must keep 4.5:1 contrast.

**Acceptance tests:** `tests/e2e/responsive.spec.js` (re-seeds in `beforeAll`; runs in both projects): "month view has no horizontal page overflow", "week view has no serious or critical axe violations", "list view has no serious or critical axe violations", "the Draft tray has no serious or critical axe violations"; in the `mobile` project only: "month view shows one day per row".

**Out of scope:** new features; pixel comparison with the mockups (a manual check).

**Verification:** `npm run lint`.

**Depends on:** P2-04

### P2-09: Settings › Calendar page and registered settings

**Goal:** Register the Settings › Calendar page and the two settings (`masthead_rhythm`, `masthead_settings`) with sanitisers, exposed through `wp/v2/settings`.

**Files touched:** `src/Admin/SettingsPage.php`, `src/Config.php`, `src/Plugin.php`, `tests/unit/Admin/SettingsPageTest.php`, `tests/unit/ConfigTest.php`, `tests/integration/Admin/SettingsTest.php`

**Design constraints:**

- SPEC §3.4 (settings use the Settings API with `register_setting` and a `sanitize_callback`; every settings save checks a capability and a nonce), §5.1, §5.4 (settable keys), §6.7 (Settings › Calendar).
- `SettingsPage::register()` on `admin_menu` adds `add_options_page( …, 'manage_options', \Masthead\SLUG . '-settings', … )`, registers the hook suffix with `Assets::add_page_hook()`, and prints `<div class="wrap"><div id="masthead-settings-root"></div></div>`.
- `SettingsPage::register_settings()` on both `admin_init` and `rest_api_init` calls `register_setting( 'masthead', 'masthead_rhythm', … )` with `sanitize_callback` `Rhythm::sanitize` and a `show_in_rest` schema (an object with the properties `"0"`..`"6"`), and `register_setting( 'masthead', 'masthead_settings', … )` with `sanitize_callback` `Config::sanitize_overrides` and a `show_in_rest` schema built from `Config::settable()`. The core `wp/v2/settings` endpoint supplies the `manage_options` and nonce checks; Masthead adds no REST route.
- `Config::sanitize_overrides( mixed $raw ): array` returns a nested array holding only settable keys, grouped by the first segment of the dotted key (`desk`, `swap`, `stats`, `ai`, `abilities`): integers are non-negative (`ai.monthly_request_cap` may be 0; every `*_days` key and `desk.max_suggestions`, `desk.ideas_per_request` are at least 1), `swap.min_score_delta` and each `swap.weights` entry are floats from 0 to 1, model lists are lists of non-empty strings, booleans are booleans; anything else is dropped so the default applies. No default value is repeated in this method.
- SPEC §5.1 lists only `ai`, `abilities` and `desk` in the option's shape, but §5.4 marks `swap.*` and `stats.window_days` settable; the option therefore also holds `swap` and `stats` groups (PLAN Spec issues 4).

**Acceptance tests:**

- `tests/unit/Admin/SettingsPageTest.php`: `SettingsPageTest::test_page_requires_manage_options`, `SettingsPageTest::test_registers_both_settings_with_sanitizers_and_rest_schema`, `SettingsPageTest::test_render_outputs_the_settings_root`.
- `tests/unit/ConfigTest.php`: `ConfigTest::test_sanitize_overrides_drops_non_settable_keys`, `ConfigTest::test_sanitize_overrides_enforces_types_and_bounds`, `ConfigTest::test_sanitized_overrides_are_read_back_by_get`.
- `tests/integration/Admin/SettingsTest.php`: `SettingsTest::test_admin_saves_rhythm_through_rest_settings`, `SettingsTest::test_editor_cannot_save_settings`, `SettingsTest::test_saved_desk_override_changes_config_get`, `SettingsTest::test_non_settable_key_in_request_is_ignored`, `SettingsTest::test_settings_assets_load_only_on_the_settings_screen`.

**Out of scope:** the settings client (P2-10, P2-11); AI settings fields (P5-12).

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P0-04, P1-02, P0-05

### P2-10: Settings app and RhythmTable

**Goal:** Mount the settings app and let an administrator edit the weekly rhythm.

**Files touched:** `client/index.js`, `client/settings/App.js`, `client/settings/RhythmTable.js`, `client/settings/useSettings.js`, `client/settings/style.scss`, `client/style.scss`, `tests/js/settings/RhythmTable.test.js`, `tests/js/settings/useSettings.test.js`, `tests/e2e/settings.spec.js`

**Design constraints:** SPEC §4.3 (`settings/`), §5.1 (`masthead_rhythm`), §6.7 (rhythm table), `docs/mockups/Settings.dc.html`. Client invariants as in P2-01. Add the route `masthead-settings-root` → `settings/App` in `client/index.js`. `useSettings()` reads and saves the site settings entity through `@wordpress/core-data` (`getEditedEntityRecord`, `editEntityRecord` and `saveEditedEntityRecord` for `root` / `site`), exposing `{ rhythm, settings, setRhythm, setSetting, save, isSaving, error }`. `App` renders the `h1` "Calendar Settings", a "Save changes" button, a success or error notice after saving, and its sections in mock order; this task adds the "Weekly rhythm" section. `RhythmTable` shows one row per weekday starting at the site's week start, with a labelled text input for the lane label, a labelled multi-select of categories (from core-data's category records; an empty selection is shown as "Any category"), and a labelled `<input type="time">` for the default time; the lane slug is derived from the label on change.

**Acceptance tests:**

- `tests/js/settings/RhythmTable.test.js`: "renders seven weekday rows with label, categories and time", "an empty category selection reads Any category", "editing a time calls setRhythm with the new value".
- `tests/js/settings/useSettings.test.js`: "save persists the edited site settings", "a failed save reports the error".
- `tests/e2e/settings.spec.js` (re-seeds in `beforeAll`): "Settings › Calendar shows the seeded rhythm", "changing Friday's time and saving persists after reload", "settings page has no serious or critical axe violations".

**Out of scope:** series cadence, exposure toggles and the AI section (P2-11).

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P2-01, P2-09

### P2-11: Settings: SeriesCadence, Exposure and AI provider status

**Goal:** Complete the Phase 2 settings: series cadence and lane exception, the two MCP exposure toggles, and an AI section that only reports provider status.

**Files touched:** `client/settings/SeriesCadence.js`, `client/settings/Exposure.js`, `client/settings/AiSettings.js`, `client/settings/App.js`, `client/settings/style.scss`, `tests/js/settings/SeriesCadence.test.js`, `tests/js/settings/Exposure.test.js`, `tests/js/settings/AiSettings.test.js`, `tests/e2e/settings.spec.js`

**Design constraints:**

- SPEC §5.2 (`masthead_cadence`, `masthead_lane_exception` term meta), §5.4 (`abilities.expose_mcp` default true, `abilities.expose_share` default false; read the current values from the settings entity, never write the defaults in the client), §6.7 (series cadence + lane exception; exposure toggles; notice when no AI provider supports text generation, linking to Settings › Connectors), §8 Phase 2 ("AI section shows provider status only"). Client invariants as in P2-01.
- `SeriesCadence` lists the terms of boot `series.taxonomy` through core-data (`taxonomy` entity, with `meta`), each with labelled inputs for "Every N weeks" (number, minimum 1), "Weekday" (a select of "Same as previous part" plus the seven weekdays) and "Time", and a "Lane exception" checkbox; it saves each changed term's `meta` (`masthead_cadence` as a JSON string, `masthead_lane_exception`) through core-data when "Save changes" is pressed. It renders "No series yet." when there are none. It does not create or delete series.
- `Exposure` has two checkboxes bound to `abilities.expose_mcp` ("Expose calendar abilities to MCP clients") and `abilities.expose_share` ("Also expose sharing to MCP clients"), stored in `masthead_settings`.
- `AiSettings` shows "Provider: available" or "Provider: none active" from boot `ai.supported`, and when it is false a warning notice "No AI provider is configured, so Desk suggestions are off. The calendar, lanes and series still work." with a link "Set up in Connectors" to boot `urls.connectors`. It has no editable field in this phase.

**Acceptance tests:**

- `tests/js/settings/SeriesCadence.test.js`: "lists each series with its cadence fields", "Same as previous part saves weekday null", "toggling Lane exception edits the term meta", "shows an empty message without series".
- `tests/js/settings/Exposure.test.js`: "toggles expose_mcp and expose_share in the settings".
- `tests/js/settings/AiSettings.test.js`: "shows the Connectors notice when no provider is supported", "shows available when a provider is supported".
- `tests/e2e/settings.spec.js`: "The Mantle shows every 4 weeks and can be marked as a lane exception", "the exposure toggles persist after reload", "the AI section links to Connectors when no provider is configured".

**Out of scope:** AI and Desk tunable fields (P5-12); adding series.

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P1-05, P2-10

### P2-12: Phase 2 push and manual checks

**Goal:** Push the branch and record what a human must check for Phase 2.

**Files touched:** `docs/manual-checks.md`

**Design constraints:** SPEC §8 Phase 2 manual check. Follow the Phase end protocol. Append a `## Phase 2` section listing: (1) with the fixture seeded, open Posts › Calendar and `docs/mockups/Main.dc.html` side by side at 1440 px and at 390 px and compare the toolbar, grid, chip styles and Draft tray (the Desk rail arrives in Phase 4); (2) drag a scheduled post to another day with a mouse and confirm it persists; (3) operate "Move to…" with the keyboard only; (4) compare Settings › Calendar with `docs/mockups/Settings.dc.html`.

**Acceptance tests:** none (documentation only); the full `verify` set passes.

**Out of scope:** performing the manual checks.

**Verification:** `composer audit` and `npm audit --audit-level=high`, results recorded in the progress log; push the branch; write `Manual check: NOT VERIFIED (human)` in the progress log.

**Depends on:** none

## Phase 3 — Jetpack adapters

### P3-01: Spike — Jetpack Stats methods, payloads and probe design

**Goal:** Establish from the Jetpack 16.4 source which `WPCOM_Stats` methods and payload shapes back each `StatsSource` method, design `wp masthead stats:probe`, and write a shape-true synthetic fixture.

**Files touched:** `docs/spikes/P3-01-jetpack-stats.md`, `docs/fixtures/stats-sample.json`

**Design constraints:**

- SPEC §6.2, §9 question 3, §8 Phase 3. Follow the Spike protocol; time box 90 minutes. Jetpack in wp-env is not connected, so no real payload can be fetched: facts come from reading the source and docblocks under `wp-content/plugins/jetpack` with `npx wp-env run cli`.
- Question: for each of `views_by_category`, `views_by_weekday`, `top_posts` and `post_views`, which `Automattic\Jetpack\Stats\WPCOM_Stats` method and arguments provide the data, what does it return on success and on failure, and are per-category views available at all?
- Facts to record, each with file and line: the class file and constructor; the method, arguments and return shape for visits by day, top posts, views of one post, and tags or categories; how the class reports an error; whether it caches by itself and for how long; how to tell that the site is connected and the Stats module is active.
- Decision tokens: `Decision: categories-native` (a method returns views per category) or `Decision: categories-from-top-posts` (`views_by_category` aggregates `top_posts` by primary category, the fallback SPEC §6.2 names).
- The document also specifies `stats:probe`: for each method it calls, the output key and the scrubbing rule (every number replaced by `0`, every string value by `""`, keys kept).
- `docs/fixtures/stats-sample.json`: `{ "_synthetic": true, "_note": "…numbers are synthetic…", "fetched_at": "2026-11-09T13:00:00-08:00", "payloads": { "<WPCOM_Stats method name>": <payload in the documented shape> } }`, using post ids from the fixture (12155, 10193, 10192, 10191) and invented numbers. It carries no real traffic number.
- Fallback when the source cannot be read: record the shapes of the public WordPress.com REST v1.1 stats endpoints (`stats/visits`, `stats/top-posts`, `stats/post/<id>`) as `UNCONFIRMED`, and use `Decision: categories-from-top-posts`.

**Acceptance tests:** none. Both files exist; the fixture parses as JSON and has `_synthetic: true`.

**Out of scope:** any change under `src/` or `client/`; calling WordPress.com.

**Verification:** `grep -E '^Decision: (categories-native|categories-from-top-posts)$' docs/spikes/P3-01-jetpack-stats.md` prints one line; `node -e "const f=require('./docs/fixtures/stats-sample.json'); if(f._synthetic!==true||!f.payloads) process.exit(1)"`.

**Depends on:** P0-05

### P3-02: Stats core: StatsSource, NullStats, Payloads, FixtureStats, Cache, Factory

**Goal:** Add the stats interface and every implementation except the Jetpack one, with a factory whose production branch can never return fixture numbers.

**Files touched:** `src/Stats/StatsSource.php`, `src/Stats/NullStats.php`, `src/Stats/Payloads.php`, `src/Stats/FixtureStats.php`, `src/Stats/Cache.php`, `src/Stats/Factory.php`, `tests/unit/Stats/NullStatsTest.php`, `tests/unit/Stats/PayloadsTest.php`, `tests/unit/Stats/FixtureStatsTest.php`, `tests/unit/Stats/CacheTest.php`, `tests/unit/Stats/FactoryTest.php`

**Design constraints:**

- SPEC §6.2 (the interface, verbatim), §3.3 (honesty of numbers; `FixtureStats` only when the environment type is `local` and `MASTHEAD_STATS_FIXTURE` names a readable file; unit test covers the production branch), §5.4 (`stats.cache_ttl`, ⚠️ ASSUMPTION, default 21600 seconds; read it with `Config::get()`), §3.1 (`stats-confined`: none of these files may contain `WPCOM_Stats` or `Jetpack\Stats`).
- `StatsSource` is exactly the interface in SPEC §6.2. `NullStats`: `available()` false, every other method `null`.
- `Payloads` is pure: it turns the raw payload shapes documented in `docs/spikes/P3-01-jetpack-stats.md` into the interface's return shapes: `by_weekday( array $raw ): array` (weekday 0–6 → average daily views), `top_posts( array $raw, int $limit ): array` (post id → views, highest first, ties by post id), `post_views( array $raw ): ?int`, `by_category( array $raw ): array` when the spike decided `categories-native`, and `by_category_from_top_posts( array $top_posts, callable $primary_of ): array` (sums views by the slug `$primary_of( $post_id )` returns; `Factory` passes `Support\Categories` for it). Malformed input yields `null`, never a guess. If the spike file is missing or marks shapes `UNCONFIRMED`, implement the shapes used in `docs/fixtures/stats-sample.json`.
- `FixtureStats( string $path, callable $primary_of )` reads the JSON file once, maps its `payloads` through `Payloads` (using `$primary_of` when categories are aggregated from top posts), reports `fetched_at()` from the file and `available()` true only when the file parsed.
- `Cache::remember( string $name, array $args, callable $producer ): mixed` stores `{ value, fetched_at }` in a transient named `masthead_stats_` plus a hash of name and arguments for `Config::get( 'stats.cache_ttl' )` seconds, never stores `null`, and `fetched_at( … )` returns the stored time. `fetched_at` is written from `Clock::now()`.
- `Factory::make( ?string $environment = null, ?string $fixture_path = null ): StatsSource`: the arguments default to `wp_get_environment_type()` and the `MASTHEAD_STATS_FIXTURE` constant when defined. It returns `FixtureStats` only when the environment is exactly `local` and the path is a readable file; otherwise `NullStats` (P3-04 inserts the Jetpack branch before it). `Plugin` is the only caller.

**Acceptance tests:**

- `tests/unit/Stats/NullStatsTest.php`: `NullStatsTest::test_is_unavailable_and_returns_null_everywhere`.
- `tests/unit/Stats/PayloadsTest.php`: `PayloadsTest::test_by_weekday_averages_daily_views_per_weekday`, `PayloadsTest::test_top_posts_orders_by_views_then_id_and_respects_limit`, `PayloadsTest::test_by_category_from_top_posts_sums_by_primary_category`, `PayloadsTest::test_malformed_payload_yields_null`, `PayloadsTest::test_sample_fixture_maps_without_loss`.
- `tests/unit/Stats/FixtureStatsTest.php`: `FixtureStatsTest::test_reads_sample_fixture`, `FixtureStatsTest::test_unreadable_file_is_unavailable`.
- `tests/unit/Stats/CacheTest.php`: `CacheTest::test_second_call_uses_the_cached_value`, `CacheTest::test_null_is_not_cached`, `CacheTest::test_ttl_comes_from_config`, `CacheTest::test_fetched_at_is_the_clock_time_of_the_fetch`.
- `tests/unit/Stats/FactoryTest.php`: `FactoryTest::test_production_never_returns_fixture_stats_even_with_a_readable_fixture`, `FactoryTest::test_staging_and_development_never_return_fixture_stats`, `FactoryTest::test_local_with_readable_fixture_returns_fixture_stats`, `FactoryTest::test_local_without_fixture_returns_null_stats`.

**Out of scope:** `JetpackStats` (P3-04); the ability (P3-05); wiring into `Plugin` beyond constructing the source.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P1-03

### P3-03: Spike — Jetpack Social routes, payloads and plan gating

**Goal:** Establish from the Jetpack 16.4 source how to list connections, re-share a published post and schedule a share through Jetpack's own REST controllers, and what plan gating applies.

**Files touched:** `docs/spikes/P3-03-jetpack-social.md`

**Design constraints:**

- SPEC §6.5, §9 question 4, §2 (sharing goes through Jetpack Social or is unavailable; nothing is emulated). Follow the Spike protocol; time box 90 minutes. Jetpack in wp-env is not connected: facts come from reading the source with `npx wp-env run cli` and from `rest_get_server()->get_routes()`.
- Question: which internal REST routes, methods and payloads does `JetpackSocial` dispatch with `rest_do_request()` for (a) the connection list, (b) sharing an already published post now with a custom message to chosen connections, (c) scheduling such a share for a datetime, and how does each report "not available on this plan" or "not connected"?
- Facts to record, each with file and line: route, method, parameters and response shape for (a), (b) and (c); the capability each route's permission callback requires; how plan features are checked; the error codes for unconnected and for plan-gated; how to tell that Jetpack Social is active and connected; the design of `wp masthead social:probe` (it prints `available`, the connection count, and for each of the three routes whether it is registered, with no names, ids or message text).
- Decision tokens: `Decision: share-and-schedule` (both (b) and (c) have routes), `Decision: share-only` (no scheduling route: `share_at` always reports unavailable), `Decision: unavailable` (no usable route for (b): `JetpackSocial::available()` is always false and only `NullShare` behaviour ships).
- Fallback when the source cannot be read: `Decision: share-only`, with the connection-list and share routes marked `UNCONFIRMED`.

**Acceptance tests:** none. The file exists with the five headings and a `Decision:` line holding one of the three tokens.

**Out of scope:** any change under `src/` or `client/`; contacting WordPress.com; any own social-network integration.

**Verification:** `grep -E '^Decision: (share-and-schedule|share-only|unavailable)$' docs/spikes/P3-03-jetpack-social.md` prints one line.

**Depends on:** P0-05

### P3-04: JetpackStats and wp masthead stats:probe

**Goal:** Implement the one production `StatsSource` on top of Jetpack's `WPCOM_Stats`, and the probe command that prints scrubbed payload shapes.

**Files touched:** `src/Stats/JetpackStats.php`, `src/Stats/Factory.php`, `src/Cli/StatsProbeCommand.php`, `src/Plugin.php`, `tests/unit/Stats/JetpackStatsTest.php`, `tests/unit/Stats/FactoryTest.php`, `tests/unit/Cli/StatsProbeCommandTest.php`, `tests/integration/Stats/JetpackStatsTest.php`

**Design constraints:**

- SPEC §6.2, §6.4 (`stats:probe [--days=30]`: raw WPCOM payload shapes with numbers replaced by `0`), §3.1 (`stats-confined`: this is the only file that may name `WPCOM_Stats` or `Automattic\Jetpack\Stats`; `no-direct-http`), §3.3 (unavailable is `null`). Read `docs/spikes/P3-01-jetpack-stats.md` and use the methods and shapes in its Facts and its Decision; where it says `UNCONFIRMED`, call the methods named in `docs/fixtures/stats-sample.json`'s `payloads` keys.
- `JetpackStats::__construct( Cache $cache, callable $primary_of, ?object $client = null )`; the Jetpack client object is created lazily when none is injected, so unit tests pass a stub and never need the Jetpack class. `JetpackStats::usable(): bool` (static) is true only when the Jetpack stats class exists, the site is connected and the Stats module is active, as the spike recorded.
- Each interface method fetches through `Cache::remember()`, maps with `Payloads`, and returns `null` when the client returns a `WP_Error`, throws, or returns a malformed payload. `available()` is `usable()` for the real client. `fetched_at()` is the oldest fetch time among the cached entries used, or `null`.
- `probe( int $days ): array` returns, per method, the raw payload with every number replaced by `0` and every string value by `""` (keys kept), bypassing the cache, or `{ error: <code> }`.
- `Factory::make()` returns `JetpackStats` when `JetpackStats::usable()` and the fixture branch did not apply. The fixture branch still requires the `local` environment.
- `StatsProbeCommand` (`wp masthead stats:probe [--days=<n>]`, default `Config::get( 'stats.window_days' )`) prints `JetpackStats::probe()` as JSON, or the line "Jetpack Stats is not available on this site." when the source is not `JetpackStats`. It reaches `probe()` through a `Stats\Factory`-built object passed in by `Plugin`.

**Acceptance tests:**

- `tests/unit/Stats/JetpackStatsTest.php`: `JetpackStatsTest::test_top_posts_maps_the_client_payload`, `JetpackStatsTest::test_views_by_weekday_maps_the_client_payload`, `JetpackStatsTest::test_views_by_category_follows_the_spike_decision`, `JetpackStatsTest::test_client_error_yields_null`, `JetpackStatsTest::test_results_are_cached_between_calls`, `JetpackStatsTest::test_probe_scrubs_numbers_and_strings`.
- `tests/unit/Stats/FactoryTest.php`: `FactoryTest::test_production_with_usable_jetpack_returns_jetpack_stats`.
- `tests/unit/Cli/StatsProbeCommandTest.php`: `StatsProbeCommandTest::test_reports_unavailable_without_jetpack_stats`, `StatsProbeCommandTest::test_days_default_comes_from_config`.
- `tests/integration/Stats/JetpackStatsTest.php`: `JetpackStatsTest::test_unconnected_jetpack_is_not_usable_and_factory_returns_null_stats`.

**Out of scope:** the ability (P3-05); any UI; any analytics source other than Jetpack Stats.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P3-02, P1-14

### P3-05: masthead/stats-summary ability and the e2e stats switch

**Goal:** Expose the stats summary as an ability, and give e2e tests a switch between "stats unavailable" (the default) and fixture stats.

**Files touched:** `src/Abilities/StatsSummary.php`, `src/Plugin.php`, `tests/e2e/mu/fixture-stats.php`, `tests/e2e/helpers/wp.mjs`, `tests/integration/Abilities/StatsSummaryTest.php`, `tests/e2e/stats.spec.js`

**Design constraints:**

- SPEC §6.1 row `masthead/stats-summary`: input `{ days?: int }` (minimum 1, default `Config::get( 'stats.window_days' )`), output `{ available: true, source: "Jetpack Stats", fetched_at, window_days, by_category: [{ slug, views }], by_weekday: [{ weekday, avg_views }], top_posts: [{ post_id, views }] }` or `{ available: false, reason }`; read-only; capability `edit_posts`. Follow the Ability protocol. `top_posts` is limited to `Config::get( 'stats.top_posts_limit' )`. When the source is unavailable, or any of the three values is `null`, the output is `{ available: false, reason }` with a translatable reason; it never mixes real and missing numbers. The class receives a `StatsSource` from `Plugin`.
- `tests/e2e/mu/fixture-stats.php`: when option `masthead_e2e_stats` is `1` and `MASTHEAD_STATS_FIXTURE` is not defined, define it as `WP_CONTENT_DIR . '/masthead-fixtures/stats-sample.json'`. SPEC §7 puts this constant in `.wp-env.json`; it is defined here instead so that the default e2e state is "stats unavailable", which SPEC's Phase 4 acceptance requires (PLAN Spec issues 7). Add `setFixtureStats( on )` to `tests/e2e/helpers/wp.mjs` (it sets or deletes the option through `wpCli`), and make `seedFixture()` delete the option.

**Acceptance tests:**

- `tests/integration/Abilities/StatsSummaryTest.php`: `StatsSummaryTest::test_allowed`, `StatsSummaryTest::test_denied`, `StatsSummaryTest::test_happy_path` (with a `FixtureStats` on the sample file injected), `StatsSummaryTest::test_unavailable_source_returns_available_false_with_reason`, `StatsSummaryTest::test_top_posts_respects_the_config_limit`.
- `tests/e2e/stats.spec.js` (re-seeds in `beforeAll`, restores the switch in `afterAll`): "stats-summary reports unavailable by default", "stats-summary reports Jetpack Stats as the source when fixture stats are on".

**Out of scope:** any stats UI (numbers first appear in P4-16); changing `.wp-env.json`.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`, `npm run lint`.

**Depends on:** P3-02, P1-10, P1-14

### P3-06: Tune stats.cache_ttl

**Goal:** Measure how many upstream stats calls and how much staleness the SPEC default cache lifetime gives, and keep or change `stats.cache_ttl`.

**Files touched:** `tests/unit/Tuning/StatsCacheBench.php`, `docs/tuning/P3-06.md`, `src/Config.php`, `phpunit.xml.dist`

**Design constraints:** SPEC §5.4: `stats.cache_ttl`, ⚠️ ASSUMPTION, default 21600 seconds. Follow the Tuning protocol. The harness is a unit test in the group `tuning` (excluded from the default run in `phpunit.xml.dist`) that simulates one day against `Stats\Cache` with an in-memory transient store that honours expiry and a clock advanced through the `masthead_now` filter: one nightly warm at `Config::get( 'cron.nightly_hour' )`, then one read of each of the three summary methods every 30 minutes from 06:00 to 22:00. For each lifetime in 3600, 21600 and 86400 it prints the number of upstream calls per day and the greatest age in hours of a value served. Decision rule: keep 21600 unless it makes more than 12 upstream calls per day or serves a value older than 6 hours; then choose the measured lifetime that satisfies both, or the one with the smaller greatest age if none does.

**Acceptance tests:** `tests/unit/Tuning/StatsCacheBench.php`: `StatsCacheBench::test_measure_calls_and_staleness_per_ttl` (group `tuning`; asserts only that each simulated day completed). `docs/tuning/P3-06.md` holds the table with a before row and an after row.

**Out of scope:** changing `Cache`; any other key.

**Verification:** `vendor/bin/phpunit -c phpunit.xml.dist --group tuning --filter StatsCacheBench`; copy the before and after rows into the commit message and the progress log.

**Depends on:** P3-02

### P3-07: Social adapters and wp masthead social:probe

**Goal:** Add the share interface, the Jetpack Social implementation that dispatches to Jetpack's own REST controllers, the null implementation, the factory and the probe command.

**Files touched:** `src/Social/ShareSource.php`, `src/Social/NullShare.php`, `src/Social/JetpackSocial.php`, `src/Social/Factory.php`, `src/Cli/SocialProbeCommand.php`, `src/Plugin.php`, `tests/unit/Social/NullShareTest.php`, `tests/unit/Social/JetpackSocialTest.php`, `tests/unit/Social/FactoryTest.php`, `tests/integration/Social/JetpackSocialTest.php`

**Design constraints:**

- SPEC §6.5 (the interface, verbatim; internal `rest_do_request()` calls to Jetpack's own controllers; nothing is emulated), §6.4 (`social:probe`), §3.1 (`social-confined`: the word `publicize` in any case only in `src/Social/JetpackSocial.php`; `no-direct-http`), §2 (no own social-network integration). Read `docs/spikes/P3-03-jetpack-social.md` and use the routes, payloads and error codes in its Facts and its Decision.
- `ShareSource` is exactly the interface in SPEC §6.5. Result shape for both share methods: `{ status, results }` where `status` is `shared`, `queued`, `unavailable` or `error`, and `results` is a list of `{ connection_id, ok: bool, message }`; for `unavailable` and `error` the first result's `message` is the reason.
- `NullShare`: `available()` false, `connections()` empty, both share methods return `{ status: 'unavailable', results: [ { connection_id: 0, ok: false, message: <"Jetpack Social is not connected on this site."> } ] }`.
- `JetpackSocial::__construct( ?callable $dispatch = null )`: the callable takes method, route and parameters and returns the decoded response data or a `WP_Error`; the default implementation builds a `WP_REST_Request` and calls `rest_do_request()`. Unit tests inject the callable. `usable(): bool` (static) follows the spike. `connections()` maps the route's response to `{ id, service, display_name }`. `share_now()` and `share_at()` dispatch the routes the spike recorded and map plan-gated and unconnected errors to `status: 'unavailable'` with the reason. Under `Decision: share-only`, `share_at()` returns `unavailable` without dispatching; under `Decision: unavailable`, `usable()` returns false. If the spike file is missing, implement `share-only` and mark the two route strings with a comment naming the spike.
- `probe(): array` returns `{ available, connection_count, routes: { connections: bool, share: bool, schedule: bool } }` with no names, ids or text.
- `Factory::make(): ShareSource` returns `JetpackSocial` when `JetpackSocial::usable()`, else `NullShare`.
- `SocialProbeCommand` (`wp masthead social:probe`) prints `probe()` as JSON, or "Jetpack Social is not available on this site."

**Acceptance tests:**

- `tests/unit/Social/NullShareTest.php`: `NullShareTest::test_is_unavailable_with_a_reason`.
- `tests/unit/Social/JetpackSocialTest.php`: `JetpackSocialTest::test_connections_maps_id_service_and_display_name`, `JetpackSocialTest::test_share_now_dispatches_message_and_connections_and_reports_shared`, `JetpackSocialTest::test_plan_gated_error_becomes_unavailable_with_reason`, `JetpackSocialTest::test_share_at_follows_the_spike_decision`, `JetpackSocialTest::test_dispatch_error_becomes_error_status`, `JetpackSocialTest::test_probe_contains_no_names_or_ids`.
- `tests/unit/Social/FactoryTest.php`: `FactoryTest::test_returns_null_share_when_jetpack_social_is_not_usable`.
- `tests/integration/Social/JetpackSocialTest.php`: `JetpackSocialTest::test_unconnected_jetpack_yields_null_share_from_the_factory`.

**Out of scope:** the abilities (P3-08); the Share panel (P3-09); writing `masthead_last_shared`.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P1-14, P0-05

### P3-08: masthead/share-connections and masthead/share-post abilities

**Goal:** Expose sharing as two abilities, record a successful share on the post, and keep `share-post` out of MCP unless the owner opts in.

**Files touched:** `src/Abilities/ShareConnections.php`, `src/Abilities/SharePost.php`, `src/Plugin.php`, `tests/unit/Abilities/SharePostTest.php`, `tests/integration/Abilities/ShareConnectionsTest.php`, `tests/integration/Abilities/SharePostTest.php`

**Design constraints:**

- SPEC §6.1 rows `masthead/share-connections` and `masthead/share-post`, §3.4 (`share-post` is not exposed to MCP unless `abilities.expose_share` is true, default false), §5.3 (`masthead_last_shared`), §6.8 (`masthead_shared`), §2 (every write is a click or an explicit ability call). Follow the Ability protocol. Both classes receive a `ShareSource` from `Plugin`.
- `ShareConnections`: input `{}`, output `{ available, connections: [{ id, service, display_name }], reason? }` (`reason` is present only when `available` is false), read-only, capability `publish_posts`.
- `SharePost`: input `{ post_id, message, connection_ids: [int], at?: datetime }`, output `{ status: "shared" | "queued", results }`, no annotation set to true, capability `publish_posts`. Refusals: post missing → `masthead_not_found` (404); status not `publish` → `masthead_not_published` (409); empty `message` after `sanitize_textarea_field`, or empty `connection_ids` → `masthead_invalid_input` (400); a connection id not in `connections()` → `masthead_unknown_connection` (400); `at` not a valid ISO 8601 datetime with offset or not after `Clock::now()` → `masthead_invalid_date` (400). With `at`, call `share_at()`, else `share_now()`. A `status` of `unavailable` or `error` from the source becomes `WP_Error( 'masthead_share_unavailable' | 'masthead_share_failed', <reason>, array( 'status' => 409 | 502 ) )`.
- Only when the status is `shared`: `update_post_meta( $post_id, 'masthead_last_shared', <Clock::now() as ISO 8601> )`. For `shared` and `queued`: `do_action( 'masthead_shared', $post_id, $result )`.
- Registration is unchanged in `Registrar` (it already keys MCP exposure of `masthead/share-post` on `abilities.expose_share`); add both classes to its arguments.

**Acceptance tests:**

- `tests/unit/Abilities/SharePostTest.php`: `SharePostTest::test_shared_sets_last_shared_and_fires_action`, `SharePostTest::test_queued_fires_action_without_setting_last_shared`, `SharePostTest::test_unavailable_source_becomes_error_and_writes_nothing`, `SharePostTest::test_unknown_connection_is_refused`, `SharePostTest::test_past_at_is_refused`.
- `tests/integration/Abilities/ShareConnectionsTest.php`: `ShareConnectionsTest::test_allowed`, `ShareConnectionsTest::test_denied`, `ShareConnectionsTest::test_happy_path` (with a stub `ShareSource`), `ShareConnectionsTest::test_unconnected_site_reports_unavailable_with_reason`.
- `tests/integration/Abilities/SharePostTest.php`: `SharePostTest::test_allowed`, `SharePostTest::test_denied`, `SharePostTest::test_happy_path` (with a stub `ShareSource`), `SharePostTest::test_non_published_post_is_refused`, `SharePostTest::test_share_post_is_not_public_by_default`, `SharePostTest::test_share_post_is_public_when_expose_share_is_true`.

**Out of scope:** AI share copy (P5-08); the Share panel (P3-09).

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P3-07, P1-10

### P3-09: SharePanel without AI copy

**Goal:** Show a Share panel for a selected published post: an editable message, connection checkboxes, Share now and Queue for a date, or the reason sharing is unavailable.

**Files touched:** `client/calendar/SharePanel.js`, `client/calendar/App.js`, `client/calendar/style.scss`, `tests/js/calendar/SharePanel.test.js`, `tests/e2e/share.spec.js`

**Design constraints:** SPEC §6.7 (Share panel: editable message, connection checkboxes, Share now / Queue for date), §6.5 (when sharing or scheduling is unavailable the UI hides the action and shows the reason), §2 (no automatic sharing), `docs/mockups/PostDetail.dc.html`. Client invariants as in P2-01. When the selection is a published post, `App` renders `SharePanel` in the rail column (`masthead-rail`) with the post's title and "Published <Nov 9>". The panel is shown only when boot `caps.publishPosts` is true. It runs `masthead/share-connections` on mount. When `available` is false it shows "Sharing is unavailable: <reason>" and renders no message field, checkbox or button. Otherwise it renders a labelled `<textarea>` "Message" (starts empty), one labelled checkbox per connection (none checked), "Share now", and a labelled `<input type="datetime-local">` with "Queue for this date"; both buttons are disabled until the message is non-empty and a connection is checked. They run `masthead/share-post` (with `at` as ISO 8601 with the site offset for the queue) and show the result or the error message; if the error code is `masthead_share_unavailable` after a queue attempt, the queue controls are hidden for the session and the reason shown. The "Draft with AI" button is added in P5-08.

**Acceptance tests:**

- `tests/js/calendar/SharePanel.test.js`: "shows the reason and no controls when sharing is unavailable", "lists connections as unchecked checkboxes", "buttons are disabled until there is a message and a connection", "Share now runs share-post with the message and connection ids", "Queue sends at with the site offset", "a scheduling refusal hides the queue controls and shows the reason".
- `tests/e2e/share.spec.js` (re-seeds in `beforeAll`): "selecting the published post 12155 shows that sharing is unavailable with a reason" (Jetpack in wp-env is not connected), "the share panel has no serious or critical axe violations".

**Out of scope:** AI-drafted copy; sharing from a suggestion (P4-09); the swap panel.

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P3-08, P2-04

### P3-10: Phase 3 push and manual checks

**Goal:** Push the branch and record what a human must check for Phase 3.

**Files touched:** `docs/manual-checks.md`

**Design constraints:** SPEC §8 Phase 3 manual check. Follow the Phase end protocol. Append a `## Phase 3` section listing, to be done on eric.mann.blog after deploying a pre-release build: (1) run `wp masthead stats:probe` and compare the printed shapes with `docs/fixtures/stats-sample.json` and `docs/spikes/P3-01-jetpack-stats.md`; (2) run `wp masthead social:probe` and compare with `docs/spikes/P3-03-jetpack-social.md`; (3) share one post to one connection from the Share panel and confirm it arrives and `masthead_last_shared` is set; (4) try "Queue for this date" and confirm it either queues or reports the plan reason; (5) read both spike files and list every fact still marked `UNCONFIRMED`.

**Acceptance tests:** none (documentation only); the full `verify` set passes.

**Out of scope:** performing the manual checks; deploying.

**Verification:** `composer audit` and `npm audit --audit-level=high`, results recorded in the progress log; push the branch; write `Manual check: NOT VERIFIED (human)` in the progress log.

**Depends on:** none

## Phase 4 — Desk (deterministic) and swap check

### P4-01: Candidate shape and DraftMatch

**Goal:** Define the candidate record every Desk source produces, and the first source: unscheduled drafts that fit the day's lane.

**Files touched:** `src/Desk/Candidates/Candidate.php`, `src/Desk/Candidates/DraftMatch.php`, `tests/unit/Desk/Candidates/CandidateTest.php`, `tests/unit/Desk/Candidates/DraftMatchTest.php`

**Design constraints:**

- SPEC §4.4 step 2 (`DraftMatch`: unscheduled drafts whose categories intersect the lane's), §6.1 (`Suggestion`, `Suggestion.key`), §3.2 (no randomness), §4.2 (`Desk` imports `Plan`, `Config`, `Support` and only the named interfaces). Desk invariants for this and every later Desk task: deterministic output for the same data and clock; every query bounded by a `Config` key; no number of 2 or more written in the file.
- `Candidate` is a small final class with a static constructor `make( string $kind, string $key, string $title, float $score, array $fields )` and `to_array()`. Fields: `kind` (`draft`, `series`, `reshare`, `gap`, `idea`), `key`, `title`, `score` (0 to 1), `sort_id` (post id, or 0), `tiebreak` (string), and optional `category`, `post_id`, `series` (`{ term_id, part }`), `outline`, `data` (values for `Reasons`). Keys: `draft:<post_id>`, `reshare:<post_id>`, `series:<term_id>:<part>`, `gap:<category term_id>`, `idea:<sha1 of title>` (SPEC gives only the post-id and idea forms; the series and gap forms are this plan's).
- `DraftMatch::__construct( PlanReader $reader )`; `for_date( string $date, array $lane ): array` returns one candidate per draft from `PlanReader::unscheduled()` (already bounded by `desk.candidate_pool`) whose categories intersect the lane's categories, or every such draft when the lane has no categories. `score` is `Lanes::fit()` for the draft against the lane; `sort_id` is the post id; `tiebreak` is the title; `data` holds `modified` (ISO 8601) and `edit_url`.

**Acceptance tests:**

- `tests/unit/Desk/Candidates/CandidateTest.php`: `CandidateTest::test_to_array_omits_absent_optional_fields`, `CandidateTest::test_idea_key_is_sha1_of_title`.
- `tests/unit/Desk/Candidates/DraftMatchTest.php`: `DraftMatchTest::test_empty_lane_accepts_every_unscheduled_draft`, `DraftMatchTest::test_draft_outside_lane_categories_is_excluded`, `DraftMatchTest::test_primary_match_scores_higher_than_secondary_match`, `DraftMatchTest::test_key_is_draft_colon_post_id`.

**Out of scope:** ranking, reasons, dismissals, the ability.

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P1-02, P1-08

### P4-02: SeriesNext candidates

**Goal:** Suggest the next part of a series when its cadence lands on or near the date and that part is not yet dated.

**Files touched:** `src/Desk/Candidates/SeriesNext.php`, `tests/unit/Desk/Candidates/SeriesNextTest.php`

**Design constraints:** SPEC §4.4 step 2 (`SeriesNext`), §5.2, §5.4 (`desk.series_window_days`, ⚠️ ASSUMPTION, default 3: a part is "due" within ± this many days of its cadence date; read it with `Config::get()`). Desk invariants as in P4-01. `SeriesNext::__construct( SeriesSource $series )`; `for_date( string $date, array $lane ): array`. For each term from `SeriesSource::terms()` that has a cadence: take the dated part with the highest part number; the next part number is that plus one; skip the series when a total is known and the next part exceeds it, or when a dated post already holds the next part; the expected date is `Cadence::expected_date()` from the last dated part's date; emit a candidate when `|date − expected|` is at most the window. `score` is `1 − distance / ( window + 1 )`. If an undated draft holds the next part, the candidate carries its `post_id` and title; otherwise the title is "<Series name>: Part <n>" (translatable) and there is no `post_id`. `sort_id` is the term id, `tiebreak` the series name, `category` the primary category of the last dated part, `data` holds `expected`, `every_weeks`, `series_name` and `part`.

**Acceptance tests:** `tests/unit/Desk/Candidates/SeriesNextTest.php`: `SeriesNextTest::test_due_series_yields_next_part_on_expected_date`, `SeriesNextTest::test_date_outside_window_yields_nothing`, `SeriesNextTest::test_series_with_all_parts_dated_yields_nothing` (The Mantle in the fixture), `SeriesNextTest::test_series_past_its_total_yields_nothing`, `SeriesNextTest::test_undated_draft_for_the_next_part_supplies_post_id`, `SeriesNextTest::test_score_falls_with_distance`, `SeriesNextTest::test_window_comes_from_config`.

**Out of scope:** creating series parts; cadence findings (P4-13).

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P1-04, P1-05, P4-01

### P4-03: CategoryGap candidates

**Goal:** Suggest categories that have gone too long without a published post.

**Files touched:** `src/Desk/Candidates/CategoryGap.php`, `tests/unit/Desk/Candidates/CategoryGapTest.php`

**Design constraints:** SPEC §4.4 step 2 (`CategoryGap`: the lane's categories, or every category when the lane has none, with no publish in `desk.gap_days`; ordered by days since last publish descending, never-published first, then slug), §5.4 (`desk.gap_days`, ⚠️ ASSUMPTION, default 45; read it with `Config::get()`). Desk invariants as in P4-01. `CategoryGap::for_date( string $date, array $lane ): array`. For each candidate category (from `Support\Categories::all()`, bounded by `desk.candidate_pool`), find the latest `publish` post in it dated on or before `Clock::today()` with one `get_posts()` call per category (`posts_per_page` 1 is below the literal rule's threshold; `no_found_rows => true`). A category is a gap when it has no such post, or the post is older than the gap window measured from `Clock::today()`. `score` is `1.0` for never-published, else `days / ( days + gap_days )`; `sort_id` is 0 and `tiebreak` is the slug, which together with the ranker gives SPEC's order. Title: "Something under <Category name>" (translatable). `data` holds `last_published` (date or null) and `category_name`. `gaps(): array` returns the same list for every category regardless of lane (Phase 5 sends it to the AI as context).

**Acceptance tests:** `tests/unit/Desk/Candidates/CategoryGapTest.php`: `CategoryGapTest::test_never_published_category_is_a_gap_with_score_one`, `CategoryGapTest::test_category_published_within_the_window_is_not_a_gap`, `CategoryGapTest::test_older_gap_scores_higher`, `CategoryGapTest::test_lane_categories_restrict_the_candidates`, `CategoryGapTest::test_empty_lane_considers_every_category`, `CategoryGapTest::test_gap_window_comes_from_config`, `CategoryGapTest::test_key_is_gap_colon_term_id`.

**Out of scope:** AI topic ideas for a gap (Phase 5).

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P1-03, P4-01

### P4-04: Reshare candidates

**Goal:** Suggest published posts worth sharing again, ranked by Jetpack views, and nothing when stats are unavailable.

**Files touched:** `src/Desk/Candidates/Reshare.php`, `tests/unit/Desk/Candidates/ReshareTest.php`

**Design constraints:** SPEC §4.4 step 2 (`Reshare`: published posts in lane categories, older than `desk.reshare_min_age_days`, not shared within `desk.reshare_cooldown_days`, ranked by Jetpack views in `stats.window_days`; yields nothing when stats are unavailable), §5.4 (`desk.reshare_min_age_days` ⚠️ ASSUMPTION default 90; `desk.reshare_cooldown_days` ⚠️ ASSUMPTION default 180; both read with `Config::get()`), §3.3 (numbers only from `StatsSource`), §5.3 (`masthead_last_shared`). Desk invariants as in P4-01. `Reshare::__construct( StatsSource $stats, ShareSource $share )`; `for_date( string $date, array $lane ): array`. Return `[]` when `$stats->available()` is false, when `$share->available()` is false, or when `top_posts( window, Config::get( 'desk.candidate_pool' ) )` is `null`. Otherwise keep each top post that is `publish`, has a category in the lane (any category when the lane has none), was published more than the minimum age before `Clock::today()`, and whose `masthead_last_shared` is empty or older than the cooldown. `score` is the post's views divided by the highest views among the kept posts; `sort_id` is the post id; `tiebreak` the title; `data` holds `published` (date). The candidate carries no view count: the reason line states no number.

**Acceptance tests:** `tests/unit/Desk/Candidates/ReshareTest.php`: `ReshareTest::test_unavailable_stats_yield_nothing`, `ReshareTest::test_unavailable_sharing_yields_nothing`, `ReshareTest::test_post_younger_than_min_age_is_excluded`, `ReshareTest::test_post_shared_within_cooldown_is_excluded`, `ReshareTest::test_post_outside_lane_categories_is_excluded`, `ReshareTest::test_score_is_relative_to_the_most_viewed_candidate`, `ReshareTest::test_ages_come_from_config`.

**Out of scope:** sharing itself; showing view counts.

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P3-02, P3-07, P4-01

### P4-05: Ranker and Dismissals

**Goal:** Order candidates deterministically, drop dismissed ones, truncate to the configured maximum, and persist dismissals per user.

**Files touched:** `src/Desk/Ranker.php`, `src/Desk/Dismissals.php`, `tests/unit/Desk/RankerTest.php`, `tests/unit/Desk/DismissalsTest.php`

**Design constraints:** SPEC §3.2 (ties break by kind order, then score, then post ID ascending, then title; unit tests assert order), §4.4 step 3, §5.4 (`desk.max_suggestions` default 3, read with `Config::get()`), §6.1 (`masthead/dismiss-suggestion { date, key }`). Desk invariants as in P4-01. `Ranker::rank( array $candidates, array $dismissed_keys ): array` removes candidates whose key is dismissed, sorts with `usort` by kind order (`draft`, `series`, `reshare`, `gap`, `idea`), then score descending, then `sort_id` ascending, then `tiebreak` ascending (`strcmp`), and returns the first `Config::get( 'desk.max_suggestions' )`. `Dismissals` stores, in user meta `masthead_dismissed`, a map of date → list of keys: `dismiss( int $user_id, string $date, string $key ): void` is idempotent and removes dates before `Clock::today()` on every write; `for_date( int $user_id, string $date ): array`. SPEC names no storage for dismissals; user meta is used because a dismissal is one person's choice and the name contains none of the forbidden words (`no-secrets-stored`).

**Acceptance tests:**

- `tests/unit/Desk/RankerTest.php`: `RankerTest::test_orders_by_kind_before_score`, `RankerTest::test_orders_by_score_within_a_kind`, `RankerTest::test_ties_break_by_id_then_tiebreak`, `RankerTest::test_gap_candidates_order_never_published_first_then_slug`, `RankerTest::test_dismissed_keys_are_dropped_before_truncation`, `RankerTest::test_truncates_to_max_suggestions_from_config`, `RankerTest::test_same_input_in_any_order_gives_the_same_output` (a property test over every permutation of a five-candidate list).
- `tests/unit/Desk/DismissalsTest.php`: `DismissalsTest::test_dismiss_is_idempotent`, `DismissalsTest::test_dismissals_are_per_date`, `DismissalsTest::test_past_dates_are_pruned_on_write`.

**Out of scope:** the abilities; AI ideas.

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P4-01

### P4-06: Reasons templates and the Suggester

**Goal:** Fill every non-AI "why" line from data, and assemble the deterministic suggestion list for a date.

**Files touched:** `src/Desk/Reasons.php`, `src/Desk/Suggester.php`, `tests/unit/Desk/ReasonsTest.php`, `tests/unit/Desk/SuggesterTest.php`

**Design constraints:**

- SPEC §4.4 (steps 1, 2, 3 and 5), §3.3 ("why" lines come from `Reasons` templates filled from WordPress or Jetpack data), §6.1 (`Suggestion`), §6.8 (`masthead_suggestions`), §8 Phase 4 acceptance string. Desk invariants as in P4-01.
- `Reasons::for_candidate( array $candidate, string $date, array $lane ): string`, with these translatable templates (weekday and month names through `Clock::format()`; no traffic number in any of them): draft → "%1$s is your %2$s slot. This draft isn't on the calendar; last edited %3$s." (weekday name, lane label, modified date as `M j`), which for the fixture gives exactly "Wednesday is your Standalone slot. This draft isn't on the calendar; last edited Oct 29."; series → "%1$s is due around %2$s (every %3$d weeks). Part %4$d isn't on the calendar."; reshare → "%1$s is your %2$s slot and nothing new is queued. Published %3$s; it ranks among your most viewed posts in Jetpack Stats."; gap, never published → "Nothing has been published under %s yet."; gap otherwise → "Nothing has been published under %1$s since %2$s."
- `Suggester::__construct( DraftMatch, SeriesNext, Reshare, CategoryGap, Ranker, Reasons, Dismissals, PlanReader )`; `suggest( string $date, int $user_id, array $kinds = array() ): array` returns `{ date, lane: { slug, label, categories }, suggestions: [Suggestion], ai: { used: false, remaining: 0 } }`. It returns an empty `suggestions` list when the date is before `Clock::today()` or the day already has a post (suggestions are for empty days). Otherwise it collects the four sources in the order draft, series, reshare, gap (restricted to `$kinds` when given), ranks them with the user's dismissals for that date, converts each to a `Suggestion` with its `why` and `actions`, and passes the list through `apply_filters( 'masthead_suggestions', $list, $date )`.
- `actions` by kind: draft → `schedule`, `open`, `dismiss`; series → `schedule`, `open`, `dismiss` when it has a `post_id`, else `create_draft`, `dismiss`; reshare → `share`, `open`, `dismiss`; gap → `create_draft`, `suggest_topics`, `dismiss`.
- `Suggester::horizon_end(): string` returns `Clock::today()` plus `Config::get( 'desk.horizon_days' )` (⚠️ ASSUMPTION, default 21). Interpretation: the horizon bounds batch work ("Fill open days"); a single date beyond it is still answered.

**Acceptance tests:**

- `tests/unit/Desk/ReasonsTest.php`: `ReasonsTest::test_draft_reason_for_fixture_draft_on_2026_11_18_is_the_spec_sentence`, `ReasonsTest::test_gap_reason_never_published`, `ReasonsTest::test_gap_reason_with_last_published_date`, `ReasonsTest::test_series_reason_names_cadence_and_part`, `ReasonsTest::test_reshare_reason_contains_no_digit_other_than_the_date`.
- `tests/unit/Desk/SuggesterTest.php`: `SuggesterTest::test_day_with_a_post_gets_no_suggestions`, `SuggesterTest::test_past_date_gets_no_suggestions`, `SuggesterTest::test_sources_are_ordered_draft_series_reshare_gap`, `SuggesterTest::test_dismissed_suggestion_is_absent`, `SuggesterTest::test_kinds_filter_restricts_sources`, `SuggesterTest::test_filter_masthead_suggestions_can_change_the_list`, `SuggesterTest::test_actions_per_kind`, `SuggesterTest::test_output_is_identical_on_repeat_calls`.

**Out of scope:** AI ideas (P5-06); finding reasons (P4-13); the ability.

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P4-02, P4-03, P4-04, P4-05

### P4-07: suggest-for-date and dismiss-suggestion abilities, wp masthead suggest

**Goal:** Expose the Suggester and dismissals as abilities and as a CLI command, and prove the SPEC's Nov 18 scenario on the fixture.

**Files touched:** `src/Abilities/SuggestForDate.php`, `src/Abilities/DismissSuggestion.php`, `src/Cli/SuggestCommand.php`, `src/Plugin.php`, `tests/integration/Abilities/SuggestForDateTest.php`, `tests/integration/Abilities/DismissSuggestionTest.php`, `tests/unit/Cli/SuggestCommandTest.php`

**Design constraints:** SPEC §6.1 rows `masthead/suggest-for-date` (input `{ date, include_ideas?: bool = false, kinds?: [kind] }`, output `{ date, lane, suggestions, ai: { used, remaining } }`, read-only, `edit_posts`) and `masthead/dismiss-suggestion` (input `{ date, key }`, output `{ ok }`, idempotent, `edit_posts`), §6.4 (`suggest <date> [--ideas]`), §8 Phase 4 acceptance. Follow the Ability protocol. In this task `include_ideas` is accepted and ignored (`ai.used` is false); P5-06 implements it. `SuggestForDate` passes `get_current_user_id()` to the Suggester. `SuggestCommand` executes the ability object from `Registrar::get()` with user id 0, prints one line per suggestion (`kind`, `title`, `why`), accepts `--now`, and reports "--ideas needs Phase 5" as a warning when `--ideas` is passed. `Plugin` builds the Desk services with the `StatsSource` and `ShareSource` from the factories and registers the abilities and the command. Integration tests seed the fixture with `Cli\Seeder`, use `NullStats`, and rely on the pinned clock.

**Acceptance tests:**

- `tests/integration/Abilities/SuggestForDateTest.php`: `SuggestForDateTest::test_allowed`, `SuggestForDateTest::test_denied`, `SuggestForDateTest::test_happy_path`, `SuggestForDateTest::test_nov_18_first_suggestion_is_draft_10178_with_the_spec_reason`, `SuggestForDateTest::test_nov_18_lists_a_gap_and_no_reshare_when_stats_are_unavailable`, `SuggestForDateTest::test_nov_11_has_a_post_and_gets_no_suggestions`, `SuggestForDateTest::test_invalid_date_is_refused`.
- `tests/integration/Abilities/DismissSuggestionTest.php`: `DismissSuggestionTest::test_allowed`, `DismissSuggestionTest::test_denied`, `DismissSuggestionTest::test_happy_path`, `DismissSuggestionTest::test_dismissed_draft_no_longer_appears_for_that_user_and_date`, `DismissSuggestionTest::test_dismissing_twice_is_idempotent`.
- `tests/unit/Cli/SuggestCommandTest.php`: `SuggestCommandTest::test_prints_one_line_per_suggestion`.

**Out of scope:** AI ideas; any client code.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P4-06, P1-10, P1-14

### P4-08: Ghost cards and "Fill open days"

**Goal:** Show the first suggestion for each near-term empty day as a ghost card, and let "Fill open days" do the same for every empty day in view.

**Files touched:** `client/calendar/GhostCard.js`, `client/calendar/useSuggestions.js`, `client/calendar/DayCell.js`, `client/calendar/Toolbar.js`, `client/calendar/App.js`, `client/calendar/style.scss`, `tests/js/calendar/GhostCard.test.js`, `tests/js/calendar/useSuggestions.test.js`, `tests/e2e/desk.spec.js`

**Design constraints:** SPEC §6.7 (ghost suggestion = dashed lane-coloured card with kind label; open day = "Open · lane"; "Fill open days" = run suggestions for every empty day in the visible range without ideas), §5.4 (`desk.ghost_days` ⚠️ ASSUMPTION default 14: days ahead that show ghost cards, later empty days show "Open · lane"; `desk.horizon_days` ⚠️ ASSUMPTION default 21; both are read from boot `config.ghostDays` and `config.horizonDays`, never written in the client), `docs/mockups/Main.dc.html`. Client invariants as in P2-01. `useSuggestions()` keeps a cache of `date → suggest-for-date result` for the session with `load( date )`, `loadMany( dates )` (requests run one after another, never in parallel, so the server is not flooded), `get( date )` and `invalidate( date )`; it never passes `include_ideas`. On load and whenever the visible range changes, `App` calls `loadMany` for the open days from today through today + `ghostDays`. "Fill open days" (a toolbar button with that label, disabled while running, announcing progress in an `aria-live` region) calls `loadMany` for every open day in the visible range up to today + `horizonDays`. `DayCell` renders `GhostCard` instead of `OpenSlot` when a result with at least one suggestion is cached for the day. `GhostCard` is a `<button>` that selects the day, with a dashed border in the lane colour (the colour of the lane's first category from boot `categories`, or a neutral grey when the lane has none), the kind label in capitals (DRAFT, SERIES, RESHARE, GAP, IDEA) and the suggestion title clamped to two lines; the day button's `aria-label` becomes "<Weekday>, <Month> <day>: suggested <kind>, <title>".

**Acceptance tests:**

- `tests/js/calendar/GhostCard.test.js`: "renders the kind label and title", "uses the lane colour for the dashed border", "selects the day when clicked".
- `tests/js/calendar/useSuggestions.test.js`: "loadMany requests dates one at a time", "results are cached per date", "invalidate forces a reload", "never requests ideas".
- `tests/e2e/desk.spec.js` (re-seeds in `beforeAll`): "Nov 18 shows a DRAFT ghost card for post 10178", "a day beyond the ghost window shows Open with its lane", "Fill open days adds ghost cards to open days in the visible range".

**Out of scope:** the Desk rail (P4-09); acting on a suggestion; AI ideas.

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P4-07, P2-04

### P4-09: Desk rail

**Goal:** Show the Desk rail: a summary of the current week and, for the selected day, its suggestions with their reasons and actions, including dismissal.

**Files touched:** `client/calendar/DeskRail.js`, `client/calendar/SuggestionCard.js`, `client/calendar/App.js`, `client/calendar/style.scss`, `tests/js/calendar/DeskRail.test.js`, `tests/js/calendar/SuggestionCard.test.js`, `tests/e2e/desk.spec.js`

**Design constraints:** SPEC §6.7 (clicking a day selects it and loads suggestions in the rail), §8 Phase 4 e2e acceptance, §3.3 (the UI shows the server's `why` verbatim), `docs/mockups/Main.dc.html` (the `aside` "Editorial desk"). Client invariants as in P2-01. `App` renders `DeskRail` in the rail column when the selection is a day or nothing (a selected post shows the post panels instead). `DeskRail` has the heading "Desk" with the selected day's short label; a "This week" block computed from the plan (one bar per day of the current week coloured by its first post's category or dashed when empty, and the sentence "<n> of 7 days have a post. Next gap: <Tue Nov 10>."); and for the selected day the title "<Wednesday, Nov 18> · <lane label> lane" followed by one `SuggestionCard` per suggestion, or "This day has a post." / "No suggestions for this day." `SuggestionCard` shows the kind label, title, `why`, and one `<button>` per action in the suggestion's `actions` order: `schedule` → "Place on this day" (runs `masthead/reschedule` for the suggestion's `post_id` and the selected date through `useReschedule`); `open` → a link "Open draft" or "Open post" built from boot `urls.editPost`; `create_draft` → "Plan this" (opens the Day plan, P4-10; until then it is not rendered); `share` → "Share again" (selects the post so the Share panel opens); `suggest_topics` → not rendered until P5-06; `dismiss` → "Dismiss" (runs `masthead/dismiss-suggestion`, then invalidates and reloads that date).

**Acceptance tests:**

- `tests/js/calendar/DeskRail.test.js`: "summarises how many days of the week have a post and names the next gap", "shows the selected day's lane and suggestions", "shows a message for a day that has a post".
- `tests/js/calendar/SuggestionCard.test.js`: "renders the why line verbatim", "Dismiss runs dismiss-suggestion and reloads the date", "Place on this day reschedules the draft to the selected date", "actions the client cannot perform yet are not rendered".
- `tests/e2e/desk.spec.js`: "selecting Nov 18 lists draft 10178 first with the reason Wednesday is your Standalone slot. This draft isn't on the calendar; last edited Oct 29.", "Nov 18 also lists a gap suggestion and no reshare suggestion", "dismissing the draft suggestion persists across a reload", "the Desk rail has no serious or critical axe violations".

**Out of scope:** the Day plan; AI ideas and Ask the Desk; swap findings.

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P4-08, P2-07

### P4-10: Day plan

**Goal:** Let the user turn a chosen suggestion into a planned draft from an expanded rail: title, reason, date and time, and "Save as".

**Files touched:** `client/calendar/DayPlan.js`, `client/calendar/SuggestionCard.js`, `client/calendar/DeskRail.js`, `client/calendar/App.js`, `client/calendar/style.scss`, `tests/js/calendar/DayPlan.test.js`, `tests/e2e/dayplan.spec.js`

**Design constraints:** SPEC §6.7 (Day plan: chosen suggestion with outline, "why this day", date/time and "Save as" (dated draft | scheduled), "Create draft with outline"), §2 (planned slots are dated drafts; every write is a click), `docs/mockups/DayPlan.dc.html`. Client invariants as in P2-01. "Plan this" on a `SuggestionCard` opens `DayPlan` in the rail for that suggestion and date. `DayPlan` shows the heading "Plan <Wednesday, Nov 18>" with the lane label; a labelled text input "Title" (prefilled with the suggestion title, except for a gap suggestion where it starts empty and is required); the category as a chip; the outline as an ordered list when the suggestion has one; "Why this day" with the `why` line; the date (fixed to the selected day) and a labelled `<input type="time">` defaulting to the lane's time from boot `rhythm`; a labelled select "Save as" with "Dated draft" and "Scheduled"; the primary button "Create draft" (labelled "Create draft with outline" when there is an outline); and "Back to suggestions". The primary button runs `masthead/create-planned-draft` with `{ date, time, title, category, series?, outline? }`. When "Scheduled" is chosen it then sets the new post's status to `future` through core-data (`saveEntityRecord( 'postType', 'post', { id, status: 'future' } )`, a `wp/v2/posts` request); if that second step fails the draft is kept and the error shown. Afterwards it refreshes the plan, invalidates that date's suggestions and returns to the rail. The remaining suggestions for the day are listed under "Other options for this day".

**Acceptance tests:**

- `tests/js/calendar/DayPlan.test.js`: "prefills the title and default lane time", "a gap suggestion requires a title", "Create draft runs create-planned-draft with the chosen time and category", "Scheduled sets the new post to future after creating it", "a failed scheduling step keeps the draft and shows the error", "Back to suggestions returns to the rail".
- `tests/e2e/dayplan.spec.js` (re-seeds in `beforeAll`): "planning a gap suggestion on Nov 18 creates a dated draft chip on that day", "the Day plan has no serious or critical axe violations".

**Out of scope:** generating an outline (P5-07); an e2e test of "Scheduled" (WordPress core decides the status by the wall clock; the Jest test covers the request).

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P4-09, P1-11

### P4-11: Tune the Desk assumptions

**Goal:** Measure the Desk's ⚠️ ASSUMPTION defaults against the fixture and synthetic data, and keep or change each.

**Files touched:** `tests/integration/Tuning/DeskBench.php`, `docs/tuning/P4-11.md`, `src/Config.php`

**Design constraints:** SPEC §5.4 keys, all ⚠️ ASSUMPTION: `desk.horizon_days` (21), `desk.ghost_days` (14), `desk.gap_days` (45), `desk.reshare_min_age_days` (90), `desk.reshare_cooldown_days` (180), `desk.series_window_days` (3); also the planner key `desk.candidate_pool` (50). Follow the Tuning protocol. The harness is an integration test class in the group `tuning` that seeds the fixture plus 300 synthetic published posts spread over the previous 400 days across the seven categories (through `Slots::insert()`), uses `FixtureStats` on `docs/fixtures/stats-sample.json`, sets each candidate value with the `masthead_config` filter, and prints one table per key: `ghost_days` in 7, 14, 21 and `horizon_days` in 14, 21, 30 → the wall time of running the Suggester for every open day in that many days (the cost of page load and of "Fill open days"); `gap_days` in 30, 45, 60 → how many categories are gaps on the pinned date; `reshare_min_age_days` in 60, 90, 180 and `reshare_cooldown_days` in 90, 180, 365 → the number of reshare candidates for a Sunday; `series_window_days` in 2, 3, 4 → for The Mantle moved to a two-week cadence, how many dates per period count as due, and whether a Monday part still finds a Saturday target with `Lanes::nearest_date_for_category()`; `candidate_pool` in 25, 50, 100 → Suggester wall time for one date. Decision rules: keep `ghost_days` and `horizon_days` unless the batch takes longer than three seconds, then choose the largest measured value under it; keep `gap_days` unless more than half of the categories that published in the last year are gaps, then choose the smallest measured value for which they are not; keep the reshare ages unless they leave no candidate at all while a looser measured value leaves at least one, then choose that value; keep `series_window_days` at 3 unless the Monday-to-Saturday target is lost; keep `candidate_pool` unless one date takes longer than half a second. Change `src/Config.php` only where a rule says so.

**Acceptance tests:** `tests/integration/Tuning/DeskBench.php`: `DeskBench::test_measure_desk_assumptions` (group `tuning`; asserts only that each measurement ran). `docs/tuning/P4-11.md` holds one table per key with before and after rows. `SuggestForDateTest` still passes with the chosen values.

**Out of scope:** changing Desk logic; swap keys (P4-17).

**Verification:** `npx wp-env run tests-cli --env-cwd=wp-content/plugins/masthead vendor/bin/phpunit -c phpunit.integration.xml.dist --group tuning --filter DeskBench` once; copy the before and after rows into the commit message and the progress log.

**Depends on:** P4-07

### P4-12: Scoring

**Goal:** Implement the swap-check score exactly as SPEC §6.3 defines it.

**Files touched:** `src/Desk/Scoring.php`, `tests/unit/Desk/ScoringTest.php`

**Design constraints:** SPEC §6.3 (the three terms and the weighted mean over the terms present), §5.4 (`swap.weights`, ⚠️ ASSUMPTION, default `{ lane: 0.5, category: 0.3, weekday: 0.2 }`; read with `Config::get()`), §3.3 (when stats are unavailable the score falls back to the non-stats terms). Desk invariants as in P4-01. Pure class. `terms( array $post, string $date, array $lane, bool $lane_exception, ?array $views_by_category, ?array $views_by_weekday ): array` returns `{ lane: float, category: ?float, weekday: ?float }`: `lane` is `1.0` when the exception is set, else `Lanes::fit()`; `category` is the primary category's views divided by the largest category views, or `null` when the map is `null`, the maximum is zero, or the post has no primary category (a primary category absent from the map counts as zero views); `weekday` is that weekday's value divided by the largest weekday value, or `null` when the map is `null` or the maximum is zero. `score( array $terms ): float` is `Σ weight × term / Σ weight` over the non-null terms, and `0.0` if every weight present is zero. The values `1.0`, `0.5` and `0.0` of the lane term are part of the formula, not tunables.

**Acceptance tests:** `tests/unit/Desk/ScoringTest.php`: `ScoringTest::test_lane_term_values`, `ScoringTest::test_lane_exception_forces_lane_term_to_one`, `ScoringTest::test_category_and_weekday_terms_are_relative_to_their_maximum`, `ScoringTest::test_null_stats_drop_both_stats_terms`, `ScoringTest::test_score_without_stats_equals_the_lane_term`, `ScoringTest::test_score_is_the_weighted_mean_of_present_terms`, `ScoringTest::test_weights_come_from_config`, `ScoringTest::test_score_is_always_between_zero_and_one` (a property test over a grid of term values and weights).

**Out of scope:** findings (P4-13, P4-14).

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P1-02

### P4-13: SwapCheck: lane_mismatch and cadence_break with options

**Goal:** Produce `lane_mismatch` and `cadence_break` findings, with reasons and concrete options, and satisfy SPEC §6.3's Mantle acceptance.

**Files touched:** `src/Desk/SwapCheck.php`, `src/Desk/Reasons.php`, `tests/unit/Desk/SwapCheckTest.php`, `tests/unit/Desk/ReasonsTest.php`, `tests/integration/Desk/SwapCheckMantleTest.php`

**Design constraints:**

- SPEC §6.3 (findings, options and the acceptance paragraph), §6.1 (`Finding`), §5.4 (`swap.lookahead_days` ⚠️ ASSUMPTION default 60; `desk.series_window_days` ⚠️ ASSUMPTION default 3; both read with `Config::get()`), §3.3 (every finding's `why` comes from `Reasons`). Desk invariants as in P4-01.
- `SwapCheck::__construct( PlanReader, SeriesSource, Scoring, Reasons, StatsSource )`; `run( ?string $from = null, ?string $to = null ): array` returns findings ordered by date, then post id, then type in the order `lane_mismatch`, `collision`, `cadence_break`, `better_candidate`.
- Examined posts: every `future` post and dated draft from `PlanReader::scheduled_between( from, to )`, where `from` defaults to the day after `Clock::today()` and `to` to `Clock::today()` plus `Config::get( 'swap.lookahead_days' )`; plus every later-dated scheduled part of any series that has a part in that range. This expansion is required: SPEC's acceptance expects findings for The Mantle parts 3 and 4, which lie 63 and 91 days after the pinned date, beyond the 60-day default (PLAN Spec issues 5).
- `Finding` is `{ key: "<type>:<post_id>", post_id, date, type, severity, why, numbers?, options: [{ label, ability, input, also?: [{ ability, input }] }] }`. `also` is this plan's addition: further calls the same option makes after its first.
- `lane_mismatch` (severity `review`): the post's lane term is below 1 and its series, if any, has no lane exception. `why`: "%1$s is on a %2$s, which your rhythm gives to %3$s." (title, weekday name, lane label). Options: (a) when `Lanes::nearest_date_for_category( date, primary, window )` finds a target: for a post outside a series, label "Move to %1$s: %2$s" (weekday name, date) calling `masthead/reschedule` with `{ post_id, date }`; for a series part, the option covers every examined part of that series that has a lane mismatch and a target, in part order: label "Move to %1$s: %2$s" with the plural weekday name of the first target ("Saturdays", from seven translatable strings) and the targets joined by ", ", `input` for this finding's own post, and `also` holding the reschedule calls for the other parts; (b) for a series part: label "Keep %1$s and mark this series as a lane exception" (plural weekday name) with `ability: null` and `input: { rest: { path: "/wp/v2/<rest base>/<term id>", method: "POST", data: { meta: { masthead_lane_exception: true } } } }`, since no ability sets term meta and the series taxonomy endpoint is one SPEC allows the client.
- `cadence_break` (severity `review`): for an examined series part whose series has a cadence and whose previous part (the part numbered one lower, whatever its status) is dated, when `Cadence::is_break( previous date, date, cadence, window )`. `why`: "Part %1$d is on %2$s; the series cadence (every %3$d weeks) expects %4$s." Option: "Move to %s" calling `masthead/reschedule` with the expected date.
- With stats unavailable no finding carries `numbers`.

**Acceptance tests:**

- `tests/unit/Desk/SwapCheckTest.php`: `SwapCheckTest::test_post_in_its_lane_has_no_finding`, `SwapCheckTest::test_lane_mismatch_offers_nearest_fitting_date`, `SwapCheckTest::test_lane_mismatch_without_target_offers_no_move`, `SwapCheckTest::test_series_option_lists_every_remaining_part_and_carries_also_calls`, `SwapCheckTest::test_lane_exception_suppresses_lane_mismatch`, `SwapCheckTest::test_cadence_break_reports_expected_date`, `SwapCheckTest::test_series_parts_beyond_lookahead_are_examined_when_one_part_is_inside`, `SwapCheckTest::test_findings_are_ordered_by_date_post_and_type`.
- `tests/unit/Desk/ReasonsTest.php`: `ReasonsTest::test_lane_mismatch_reason`, `ReasonsTest::test_cadence_break_reason`.
- `tests/integration/Desk/SwapCheckMantleTest.php` (fixture seeded, pinned clock, `NullStats`): `SwapCheckMantleTest::test_parts_two_three_and_four_each_produce_lane_mismatch`, `SwapCheckMantleTest::test_series_option_label_is_move_to_saturdays_with_the_three_dates` (exactly "Move to Saturdays: 2026-12-12, 2027-01-09, 2027-02-06"), `SwapCheckMantleTest::test_part_two_also_produces_cadence_break_expecting_2026_12_07`, `SwapCheckMantleTest::test_lane_exception_clears_lane_mismatches_and_leaves_cadence_break`, `SwapCheckMantleTest::test_posts_12177_and_10194_produce_no_findings`.

**Out of scope:** `collision` and `better_candidate` (P4-14); storing findings (P4-15); any UI.

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P4-06, P4-12, P1-08

### P4-14: SwapCheck: collision and better_candidate

**Goal:** Add the two remaining finding types without disturbing the Mantle acceptance.

**Files touched:** `src/Desk/SwapCheck.php`, `src/Desk/Reasons.php`, `tests/unit/Desk/SwapCheckTest.php`, `tests/unit/Desk/ReasonsTest.php`, `tests/integration/Desk/SwapCheckMantleTest.php`

**Design constraints:**

- SPEC §6.3 (`collision` when two non-Journal posts share a date; `better_candidate` when an unscheduled draft or another scheduled post within `swap.candidate_window_days` scores at least `swap.min_score_delta` higher on the date; "for `better_candidate`, swap the two dates"), §5.4 (`swap.min_score_delta` ⚠️ ASSUMPTION default 0.25; `swap.candidate_window_days` ⚠️ ASSUMPTION default 3; both read with `Config::get()`), §3.3 (numbers only from `StatsSource`, shown with source and fetch time). Desk invariants as in P4-01.
- `collision` (severity `review`): two or more examined posts share a date and none of them has a primary category in `Config::get( 'swap.collision_exempt_categories' )` (default `['journal']`, which is how "non-Journal" is expressed without a literal slug in the code). One finding per post. `why`: "%1$s shares %2$s with %3$s." No options.
- `better_candidate` (severity `info`): for an examined post *p* on date *d*, candidates are every unscheduled draft (`PlanReader::unscheduled()`) and every other examined post dated within ± the candidate window of *d*. Each is scored on *d* with `Scoring` (the stats maps are fetched once per run with `Config::get( 'stats.window_days' )`; `null` when unavailable). When the best candidate's score exceeds *p*'s by at least the delta, emit one finding naming the best candidate (highest score, then lowest post id). `why`: "%1$s fits %2$s better than %3$s." (candidate title, "<weekday name>'s <lane label> lane", *p*'s title), with no number in the sentence. Options: for a scheduled candidate *q*, label "Swap dates with %s" calling `masthead/reschedule` for *p* to *q*'s date with `also` moving *q* to *d*; for an unscheduled draft, label "Put %1$s on %2$s" calling `masthead/reschedule` for the draft to *d*.
- `numbers`, only when stats are available: `{ source: "Jetpack Stats", fetched_at, values: { scheduled_category_views, candidate_category_views, weekday_avg_views } }` using the views behind the two category terms and the weekday term.

**Acceptance tests:**

- `tests/unit/Desk/SwapCheckTest.php`: `SwapCheckTest::test_two_posts_on_one_date_collide`, `SwapCheckTest::test_exempt_category_does_not_collide`, `SwapCheckTest::test_better_candidate_needs_the_minimum_delta`, `SwapCheckTest::test_better_candidate_picks_highest_score_then_lowest_id`, `SwapCheckTest::test_candidate_outside_the_window_is_ignored`, `SwapCheckTest::test_swap_option_carries_the_reverse_move_in_also`, `SwapCheckTest::test_numbers_present_only_with_available_stats`, `SwapCheckTest::test_delta_and_window_come_from_config`.
- `tests/unit/Desk/ReasonsTest.php`: `ReasonsTest::test_collision_reason`, `ReasonsTest::test_better_candidate_reason_contains_no_digit`.
- `tests/integration/Desk/SwapCheckMantleTest.php`: `SwapCheckMantleTest::test_fixture_produces_exactly_four_findings` (three `lane_mismatch` and one `cadence_break`; 12177 and 10194 produce none).

**Out of scope:** storing findings; the panel; applying options.

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P4-13

### P4-15: Nightly cron, masthead/swap-check ability and wp masthead swap-check

**Goal:** Run the swap check nightly, store its findings, and expose them through the ability and the CLI.

**Files touched:** `src/Cron/Nightly.php`, `src/Abilities/SwapCheck.php`, `src/Cli/SwapCheckCommand.php`, `src/Plugin.php`, `masthead.php`, `tests/unit/Cron/NightlyTest.php`, `tests/integration/Cron/NightlyTest.php`, `tests/integration/Abilities/SwapCheckTest.php`

**Design constraints:**

- SPEC §4.2 (`Cron\Nightly`: run the swap check, warm the stats cache, store findings; imports only `Desk`, `Stats`, `Config`, `Support`), §5.1 (`masthead_findings` = `{ generated_at, items: [Finding] }`, written only by `Cron\Nightly`), §5.4 (`cron.nightly_hour` default 3, site-local, read with `Config::get()`), §6.1 row `masthead/swap-check`, §6.4 (`swap-check [--write]`), §6.8 (`masthead_findings_updated`), §3.1 (`cron-read-only`: nothing here writes a post, post meta or term, or runs an ability), §2 (cron only reads and caches).
- `Nightly::__construct( SwapCheck $swap_check, StatsSource $stats )`. `schedule()` (on `init`) schedules the daily event `masthead_nightly` when none is scheduled, first at the next occurrence of the configured hour in site time computed from `Clock::now()`. `run()` (the `masthead_nightly` callback): when stats are available, call `views_by_category`, `views_by_weekday` and `top_posts` once each for `Config::get( 'stats.window_days' )` to warm the cache; run `SwapCheck::run()`; `update_option( 'masthead_findings', { generated_at: <Clock::now() ISO 8601>, items }, false )`; `do_action( 'masthead_findings_updated', count( $items ) )`. `unschedule()` clears the event; `masthead.php` registers it with `register_deactivation_hook`.
- Interpretation: SPEC says the nightly run covers "the horizon" and names both `desk.horizon_days` (21) and `swap.lookahead_days` (60). The nightly swap check covers `swap.lookahead_days`, because SPEC §6.3's acceptance needs findings 35 days out (PLAN Spec issues 5).
- `Abilities\SwapCheck`: input `{ from?, to? }`, output `{ generated_at, findings }`, read-only, `edit_posts`. It reads `masthead_findings`, keeps findings whose `date` lies in the given range, and drops stale ones: the post no longer exists, is no longer `future` or `draft`, is no longer on the finding's date, or (for `lane_mismatch`) its series now has the lane exception. With no stored findings it returns `{ generated_at: null, findings: [] }`. It never runs the check and never writes.
- `SwapCheckCommand`: without `--write` it runs `Desk\SwapCheck::run()` and prints one line per finding (`type`, `post_id`, `date`, `why`) and stores nothing; with `--write` it fires `do_action( 'masthead_nightly' )` (the same code path as the cron; `Cli` may not import `Cron`) and prints the stored count. Accepts `--now`.

**Acceptance tests:**

- `tests/unit/Cron/NightlyTest.php`: `NightlyTest::test_run_warms_stats_stores_findings_and_fires_action`, `NightlyTest::test_run_skips_warming_when_stats_are_unavailable`, `NightlyTest::test_first_run_is_scheduled_at_the_configured_site_hour`, `NightlyTest::test_schedule_does_not_duplicate_an_existing_event`.
- `tests/integration/Cron/NightlyTest.php` (fixture seeded): `NightlyTest::test_running_the_hook_stores_four_findings_with_generated_at`, `NightlyTest::test_running_the_hook_changes_no_post`.
- `tests/integration/Abilities/SwapCheckTest.php`: `SwapCheckTest::test_allowed`, `SwapCheckTest::test_denied`, `SwapCheckTest::test_happy_path`, `SwapCheckTest::test_range_filter`, `SwapCheckTest::test_finding_for_a_moved_post_is_dropped_as_stale`, `SwapCheckTest::test_lane_mismatch_is_dropped_once_the_series_has_the_exception`, `SwapCheckTest::test_no_stored_findings_returns_an_empty_list`.

**Out of scope:** the panel (P4-16); any automatic rescheduling.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`, then by hand once: `npx wp-env run cli wp masthead swap-check --now="2026-11-09 14:00:00"` prints four findings after seeding.

**Depends on:** P4-14, P1-10, P1-14

### P4-16: Swap panel and queue verdicts

**Goal:** Show a scheduled post's findings with their numbers and options, apply an option on click, and mark each row of the list view "Fits" or "Review".

**Files touched:** `client/calendar/SwapPanel.js`, `client/calendar/useFindings.js`, `client/calendar/ListView.js`, `client/calendar/App.js`, `client/calendar/style.scss`, `tests/js/calendar/SwapPanel.test.js`, `tests/js/calendar/useFindings.test.js`, `tests/js/calendar/ListView.test.js`, `tests/e2e/swap.spec.js`

**Design constraints:** SPEC §6.7 (Post panel: swap-check findings with numbers via `StatValue` and options), §3.3 (`stat-render-confined`: pass `finding.numbers` whole to `StatValue`; never read a value from it here), §8 Phase 4 e2e acceptance, §2 (no automatic rescheduling), `docs/mockups/PostDetail.dc.html`. Client invariants as in P2-01. `useFindings()` runs `masthead/swap-check` once per page load and exposes `{ generatedAt, byPost( id ), refresh }`. `ListView` shows on each `future` or draft row "Review" when the post has a finding of severity `review`, else "Fits", and above the list "Swap check ran <date>" or "Swap check has not run yet". When the selection is a `future` post or a draft, `App` renders `SwapPanel` in the rail. `SwapPanel` shows the heading "Swap check · <title>"; "No findings. This post fits its day." when there are none; otherwise for each finding its type label ("Lane mismatch", "Collision", "Cadence break", "Better candidate"), the `why` line, one `StatValue` per value name when `numbers` is present (labels: "Views in this post's category", "Views in the candidate's category", "Average views on this weekday"), and one `<button>` per option. An option with `also` is labelled with its `label` and a count line "Reschedules <n> posts". Clicking an option runs its `ability` with its `input`, then each `also` call in order, stopping at the first error; an option with `ability: null` and `input.rest` is sent with `@wordpress/api-fetch` to that `wp/v2` path. Afterwards it refreshes the plan and the findings and reports the outcome in an `aria-live` region. "Keep as is" closes the panel.

**Acceptance tests:**

- `tests/js/calendar/SwapPanel.test.js`: "shows a no-findings message", "renders why and options for each finding", "renders numbers only through StatValue", "an option with also runs every call in order and stops at the first error", "the lane exception option posts term meta to the series endpoint".
- `tests/js/calendar/useFindings.test.js`: "groups findings by post", "refresh reloads the findings".
- `tests/js/calendar/ListView.test.js`: "rows with a review finding read Review and others read Fits".
- `tests/e2e/swap.spec.js` (re-seeds, then runs `wp masthead swap-check --write --now="2026-11-09 14:00:00"` through `wpCli` in `beforeAll`): "list view marks The Mantle parts 2 to 4 Review and posts 12177 and 10194 Fits", "The Mantle Part 2 shows a lane mismatch with the option Move to Saturdays: 2026-12-12, 2027-01-09, 2027-02-06 and a cadence break", "applying Move to Saturdays moves the three parts", "marking the lane exception and re-running the check leaves only the cadence break" (re-seeds first), "with fixture stats on, every number in the swap panel has Jetpack Stats beside it" (uses `setFixtureStats( true )`; the test first plans a Business draft on Tue Nov 17 and a Technology draft on Mon Nov 16 through `masthead/create-planned-draft`, each on the other's lane day, so a `better_candidate` finding with `numbers` exists after the check is re-run; it restores the switch afterwards), "the swap panel has no serious or critical axe violations".

**Out of scope:** dismissing findings; running the swap check from the browser.

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P4-15, P2-05, P2-02

### P4-17: Tune the swap assumptions

**Goal:** Measure the swap check's ⚠️ ASSUMPTION defaults and keep or change each.

**Files touched:** `tests/integration/Tuning/SwapBench.php`, `docs/tuning/P4-17.md`, `src/Config.php`

**Design constraints:** SPEC §5.4 keys, all ⚠️ ASSUMPTION: `swap.lookahead_days` (60), `swap.min_score_delta` (0.25), `swap.candidate_window_days` (3), `swap.weights` (`{ lane: 0.5, category: 0.3, weekday: 0.2 }`). Follow the Tuning protocol. The harness is an integration test class in the group `tuning` that seeds the fixture plus twenty synthetic scheduled posts over the next 90 days (half in their lane, half not, through `Slots::insert()`), and for each candidate value set with the `masthead_config` filter prints the number of findings by type, once with `NullStats` and once with `FixtureStats` on `docs/fixtures/stats-sample.json`: `lookahead_days` in 30, 60, 92; `min_score_delta` in 0.15, 0.25, 0.35; `candidate_window_days` in 2, 3, 5; weights in the default, `{ lane: 0.6, category: 0.2, weekday: 0.2 }` and `{ lane: 0.4, category: 0.4, weekday: 0.2 }`; it also records the wall time of one run. Decision rules: keep `lookahead_days` unless a run exceeds two seconds (then the largest measured value under it) and never go below the value at which the Mantle acceptance still passes; keep `min_score_delta` unless, with stats unavailable, a post that sits in its own lane receives a `better_candidate` finding (then the smallest measured value at which none does); keep `candidate_window_days` unless more than half of the in-lane synthetic posts receive `better_candidate` with fixture stats (then the largest measured value at which they do not); keep the weights unless, with fixture stats, an in-lane post is outscored on its own day by an out-of-lane post (then the first measured alternative where that does not happen). Change `src/Config.php` only where a rule says so.

**Acceptance tests:** `tests/integration/Tuning/SwapBench.php`: `SwapBench::test_measure_swap_assumptions` (group `tuning`; asserts only that each measurement ran). `docs/tuning/P4-17.md` holds one table per key with before and after rows. `SwapCheckMantleTest` still passes with the chosen values.

**Out of scope:** changing `Scoring` or `SwapCheck` logic.

**Verification:** `npx wp-env run tests-cli --env-cwd=wp-content/plugins/masthead vendor/bin/phpunit -c phpunit.integration.xml.dist --group tuning --filter SwapBench` once; copy the before and after rows into the commit message and the progress log.

**Depends on:** P4-14

### P4-18: Phase 4 push and manual checks

**Goal:** Push the branch and record what a human must check for Phase 4.

**Files touched:** `docs/manual-checks.md`

**Design constraints:** SPEC §8 Phase 4 manual check. Follow the Phase end protocol. Append a `## Phase 4` section listing: (1) with the fixture seeded, run `wp masthead swap-check --write --now="2026-11-09 14:00:00"`, open the list view, select The Mantle Part 2 and read the swap panel against `docs/mockups/PostDetail.dc.html`; (2) confirm no number appears anywhere in the calendar, rail or panels without "Jetpack Stats" beside it, first with stats unavailable and then with `wp option update masthead_e2e_stats 1`; (3) select Nov 18 and compare the Desk rail and Day plan with `docs/mockups/Main.dc.html` and `docs/mockups/DayPlan.dc.html`; (4) read `docs/tuning/P4-11.md` and `docs/tuning/P4-17.md` and decide whether the kept defaults suit the real site.

**Acceptance tests:** none (documentation only); the full `verify` set passes.

**Out of scope:** performing the manual checks.

**Verification:** `composer audit` and `npm audit --audit-level=high`, results recorded in the progress log; push the branch; write `Manual check: NOT VERIFIED (human)` in the progress log.

**Depends on:** none

## Phase 5 — AI

### P5-01: Spike — WordPress AI Client surface and model metadata

**Goal:** Establish from the WordPress 7.1.4 source the exact AI Client calls `WpAiClient` needs, and whether a result reveals the model that produced it.

**Files touched:** `docs/spikes/P5-01-ai-client.md`

**Design constraints:**

- SPEC §6.6, §9 question 5, §2 (Masthead never reads or stores credentials). Follow the Spike protocol; time box 60 minutes. No provider is configured in wp-env, so nothing is generated: facts come from reading `wp-includes` with `npx wp-env run cli`.
- Question: what is the exact call chain for a JSON-constrained text generation with a system instruction, model preference, temperature and token limit, how are failures reported, and does the result expose the model id used?
- Facts to record, each with file and line: the signature of `wp_ai_client_prompt()`; the builder method names for the system instruction, model preference (and how several ids are passed), temperature, maximum tokens, JSON response schema, the support check that makes no network call, and text generation; what each generation method returns on success and on failure (`WP_Error` or exception); whether a result object carries provider and model metadata and how to read the model id; the capability checked for prompting (SPEC names `prompt_ai`) and which roles hold it by default.
- Decision tokens: `Decision: model-id-exposed` or `Decision: model-id-not-exposed`.
- Fallback when a fact cannot be established: the names in SPEC §6.6 (`using_model_preference`, `using_temperature`, `using_max_tokens`, `as_json_response`, `is_supported_for_text_generation`), `using_system_instruction` for the system text, `generate_text` for generation returning a string or `WP_Error`, capability `prompt_ai`, and `Decision: model-id-not-exposed`.

**Acceptance tests:** none. The file exists with the five headings and a `Decision:` line holding one of the two tokens.

**Out of scope:** any change under `src/` or `client/`; configuring a provider; any network call.

**Verification:** `grep -E '^Decision: model-id-(exposed|not-exposed)$' docs/spikes/P5-01-ai-client.md` prints one line.

**Depends on:** P0-05

### P5-02: AiClient interface, Schemas and the schema validator

**Goal:** Define the AI interface and the four JSON schemas, with a validator strict enough that invalid AI output is rejected, never repaired.

**Files touched:** `src/Ai/AiClient.php`, `src/Ai/Schemas.php`, `tests/unit/Ai/SchemasTest.php`

**Design constraints:** SPEC §6.6 (the interface, verbatim; schemas `ideas`, `outline`, `share_copy`, `series_proposal`), §3.3 (AI output is validated against a JSON schema before use; an out-of-lane idea is dropped, not coerced), §5.4 (`desk.ideas_per_request` default 3; planner key `ai.series_max_parts` default 12; read with `Config::get()`), §4.2 (`Ai` imports only `Config`, `Support`). AI invariants for this and every later AI task: no credential is read or stored; nothing AI-written is used before `Schemas::validate()` accepts it; the AI is never asked for, and never trusted on, traffic, dates or history. `AiClient` is exactly the interface in SPEC §6.6 (`$tier` is `'fast'` or `'strong'`). `Schemas` is static and pure; every schema is an object with `additionalProperties: false`, a `title` equal to its name, and all properties required: `ideas( array $allowed_categories, int $count )` → `{ ideas: [ { title, angle, category } ] }` with at most `$count` items and `category` an `enum` of `$allowed_categories`; `outline()` → `{ outline: [string] }` with at least one item; `share_copy()` → `{ message: string }`; `series_proposal( array $allowed_categories, int $max_parts )` → `{ name, category (enum), every_weeks (integer, minimum 1), parts: [ { title, outline: [string] } ] }` with at most `$max_parts` parts. Strings have `minLength` 1. `Schemas::validate( mixed $data, array $schema ): true|string` implements the subset used (`type` object, array, string, integer; `required`; `properties`; `additionalProperties`; `items`; `enum`; `minItems`; `maxItems`; `minLength`; `minimum`) and returns `true` or the path of the first violation; it never changes the data.

**Acceptance tests:** `tests/unit/Ai/SchemasTest.php`: `SchemasTest::test_valid_ideas_pass`, `SchemasTest::test_idea_with_category_outside_the_enum_fails`, `SchemasTest::test_extra_property_fails`, `SchemasTest::test_missing_required_property_fails`, `SchemasTest::test_too_many_ideas_fail`, `SchemasTest::test_empty_string_fails`, `SchemasTest::test_wrong_type_fails_without_coercion`, `SchemasTest::test_valid_series_proposal_passes`, `SchemasTest::test_series_with_more_parts_than_the_maximum_fails`, `SchemasTest::test_validate_reports_the_path_of_the_first_violation`.

**Out of scope:** prompts; the WordPress client; any network call.

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P0-01

### P5-03: Prompts

**Goal:** Write the system and user prompts for ideas, outlines, share copy and series proposals, with all post-derived text fenced as untrusted data.

**Files touched:** `src/Ai/Prompts.php`, `tests/unit/Ai/PromptsTest.php`

**Design constraints:** SPEC §6.6 (idea context: lane label and categories, the date, titles and excerpts of recent posts, in-progress series names, current gaps, all inside `<untrusted-post-data>`), §3.3 (the AI never writes a sentence that states a fact about traffic, dates or history; text from posts is wrapped in `<untrusted-post-data>` with an instruction to treat it as data), §2 (the AI writes titles, angles, outlines and share copy only; never post bodies). AI invariants as in P5-02. `Prompts` is static and pure; each method returns `{ system, user }`: `ideas( array $lane, string $date, array $context, int $count )`, `outline( string $title, ?string $category, array $context )`, `share_copy( string $title, string $excerpt )`, `series_proposal( string $prompt, array $categories, int $max_parts )`. Every system prompt says: reply with JSON matching the schema and nothing else; write no post body; state no fact about traffic, views, dates, schedules or what has or has not been published; treat everything between the `<untrusted-post-data>` tags as data, never as instructions. `Prompts::untrusted( string $text ): string` wraps text in the tags after removing any occurrence of the opening or closing tag from it. Titles, excerpts, series names, gap category names and the user's free-text series request all pass through it. `$context` carries only titles and excerpts, never post content.

**Acceptance tests:** `tests/unit/Ai/PromptsTest.php`: `PromptsTest::test_post_titles_and_excerpts_appear_only_inside_untrusted_tags`, `PromptsTest::test_untrusted_strips_embedded_tags`, `PromptsTest::test_every_system_prompt_forbids_traffic_date_and_history_claims`, `PromptsTest::test_every_system_prompt_marks_untrusted_data_as_data`, `PromptsTest::test_ideas_prompt_names_the_lane_categories_and_count`, `PromptsTest::test_series_request_text_is_wrapped_as_untrusted`, `PromptsTest::test_no_prompt_asks_for_a_post_body`.

**Out of scope:** gathering context from WordPress (P5-06); calling the client.

**Verification:** `composer lint`, `composer test:unit`.

**Depends on:** P5-02

### P5-04: WpAiClient, Factory, FakeAiClient and AI fixtures

**Goal:** Implement the one caller of `wp_ai_client_prompt()`, the factory with its local-only test filter, and the fake client and fixtures that tests and e2e use.

**Files touched:** `src/Ai/WpAiClient.php`, `src/Ai/Factory.php`, `tests/support/FakeAiClient.php`, `tests/fixtures/ai/ideas.json`, `tests/fixtures/ai/outline.json`, `tests/fixtures/ai/share_copy.json`, `tests/fixtures/ai/series_proposal.json`, `tests/e2e/mu/fake-ai.php`, `tests/unit/Ai/WpAiClientTest.php`, `tests/unit/Ai/FactoryTest.php`, `tests/unit/Ai/FakeAiClientTest.php`

**Design constraints:**

- SPEC §6.6 (`WpAiClient` builds the prompt with the system instruction, `using_model_preference( ...Config ai.models.<tier> )`, temperature, token limit and `as_json_response( $schema )`; `FakeAiClient` returns fixtures from `tests/fixtures/ai/*.json`, installed through the `masthead_ai_client` filter from `tests/e2e/mu/fake-ai.php`; `Ai\Factory` ignores the filter unless the environment is `local`), §3.1 (`ai-confined`: `wp_ai_client_prompt` only in `src/Ai/WpAiClient.php`; `no-key-reads`), §5.4 (`ai.models.fast`, `ai.models.strong`, `ai.temperature` 0.4, `ai.max_tokens` 1200, all ⚠️ ASSUMPTION; read with `Config::get()`, never written here). AI invariants as in P5-02. Read `docs/spikes/P5-01-ai-client.md` and use its method names; where it says `UNCONFIRMED` or is missing, use the fallback names listed in P5-01.
- `WpAiClient::__construct( ?callable $prompt_factory = null, ?callable $on_model = null )`: the factory defaults to `wp_ai_client_prompt` when that function exists; unit tests inject a stub builder. `supported()` is false when the function is missing, otherwise the builder's no-network support check. `json()` builds the call, decodes the returned text with `Support\Json::decode_object()`, and returns `WP_Error( 'masthead_ai_invalid' )` when the provider fails, throws, or returns text that is not a JSON object; it does not validate against the schema's content (callers do, so the fake is held to the same check). `text()` returns the string or a `WP_Error`. When the spike decided `model-id-exposed`, each successful call passes the tier and model id to `$on_model`.
- `Factory::make( ?string $environment = null ): AiClient` returns `WpAiClient`, or, only when the environment is exactly `local`, the result of `apply_filters( 'masthead_ai_client', $default )` if it is an `AiClient`.
- `tests/support/FakeAiClient.php` (namespace `Masthead\Tests\Support`, implements `AiClient`): `json()` returns the decoded `tests/fixtures/ai/<schema title>.json`; `text()` returns a fixed sentence; `supported()` is true; a constructor flag makes every call return `WP_Error( 'masthead_ai_invalid' )`, and another makes `json()` return a named alternative fixture, for tests of invalid output.
- Fixtures (all schema-valid): `ideas.json` has three invented ideas for the Standalone lane in categories the fixture defines (`opinion`, `technology`, `security`), with titles written for this file and taken from no mockup; `outline.json` has four outline lines; `share_copy.json` has one message; `series_proposal.json` is a four-part series in category `security` with `every_weeks` 1.
- `tests/e2e/mu/fake-ai.php` requires the fake from the mapped plugin directory, adds the `masthead_ai_client` filter returning it, and grants `prompt_ai` to administrators through `user_has_cap`.

**Acceptance tests:**

- `tests/unit/Ai/WpAiClientTest.php`: `WpAiClientTest::test_unsupported_when_the_core_function_is_missing`, `WpAiClientTest::test_json_passes_system_models_temperature_tokens_and_schema_to_the_builder`, `WpAiClientTest::test_tier_selects_the_model_list_from_config`, `WpAiClientTest::test_non_json_text_becomes_wp_error`, `WpAiClientTest::test_provider_error_becomes_wp_error`.
- `tests/unit/Ai/FactoryTest.php`: `FactoryTest::test_filter_is_ignored_in_production`, `FactoryTest::test_filter_is_honoured_in_local`, `FactoryTest::test_filter_returning_a_non_client_is_ignored`.
- `tests/unit/Ai/FakeAiClientTest.php`: `FakeAiClientTest::test_every_fixture_validates_against_its_schema`.

**Out of scope:** budget (P5-05); any ability; configuring a real provider.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P5-02

### P5-05: Budget and the BudgetedAi decorator

**Goal:** Enforce the monthly AI request cap before every AI call.

**Files touched:** `src/Desk/Budget.php`, `src/Desk/BudgetedAi.php`, `src/Plugin.php`, `tests/unit/Desk/BudgetTest.php`, `tests/unit/Desk/BudgetedAiTest.php`

**Design constraints:** SPEC §6.6 (before each call `Budget::reserve()` checks `ai.monthly_request_cap`; a refusal returns `WP_Error( 'masthead_ai_budget' )`), §5.1 (`masthead_ai_usage` = `{ month: "YYYY-MM", requests: int }`, incremented per AI request, reset when the month changes), §5.4 (`ai.monthly_request_cap`, ⚠️ ASSUMPTION, default 300, `0` disables AI; read with `Config::get()`). AI invariants as in P5-02; Desk invariants as in P4-01. `Budget::reserve(): bool` reads the option, starts a new month when `Clock::now()`'s month differs, returns false without writing when the cap is 0 or reached, otherwise increments and returns true. `remaining(): int` never goes below 0. `note_model( string $tier, string $model ): void` stores the last model id per tier under `models` in the same option (used only when the P5-01 spike decided `model-id-exposed`); `models(): array`. `BudgetedAi implements AiClient` wraps another `AiClient`: `supported()` delegates; `json()` and `text()` call `Budget::reserve()` first and return `WP_Error( 'masthead_ai_budget', …, array( 'status' => 429 ) )` when it refuses. SPEC puts the reservation inside `WpAiClient`, but `Ai/` may not import `Desk/` (§4.2), so the decorator does it (PLAN Spec issues 3). `Plugin` wraps `Ai\Factory::make()` in `BudgetedAi` once and hands only the wrapped client to every consumer, and replaces the `ai` boot-data provider with `{ supported, remaining }`.

**Acceptance tests:**

- `tests/unit/Desk/BudgetTest.php`: `BudgetTest::test_reserve_increments_until_the_cap`, `BudgetTest::test_cap_of_zero_always_refuses_and_writes_nothing`, `BudgetTest::test_new_month_resets_the_count`, `BudgetTest::test_remaining_never_goes_negative`, `BudgetTest::test_cap_comes_from_config`.
- `tests/unit/Desk/BudgetedAiTest.php`: `BudgetedAiTest::test_exhausted_budget_returns_error_without_calling_the_inner_client`, `BudgetedAiTest::test_each_call_reserves_exactly_once`, `BudgetedAiTest::test_supported_does_not_reserve`.

**Out of scope:** any ability or UI; per-user budgets.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P5-04

### P5-06: Ideas and include_ideas in suggest-for-date

**Goal:** Append validated AI ideas to a date's suggestions when the caller asks for them and is allowed to.

**Files touched:** `src/Desk/Ideas.php`, `src/Desk/Suggester.php`, `src/Abilities/SuggestForDate.php`, `src/Cli/SuggestCommand.php`, `src/Plugin.php`, `client/calendar/useSuggestions.js`, `client/calendar/SuggestionCard.js`, `client/calendar/DeskRail.js`, `tests/unit/Desk/IdeasTest.php`, `tests/unit/Desk/SuggesterTest.php`, `tests/integration/Abilities/SuggestForDateTest.php`, `tests/js/calendar/DeskRail.test.js`, `tests/e2e/ideas.spec.js`

**Design constraints:**

- SPEC §4.4 step 4 (ideas only if `include_ideas` is true, the user has `prompt_ai`, a provider supports text generation and the budget allows), §6.1 (`suggest-for-date`: `edit_posts`, plus `prompt_ai` when `include_ideas`; `ai: { used, remaining }`), §6.6 (idea context), §3.3 (an idea whose category is not one of the lane's categories, or not an existing category for a lane with none, is dropped), §5.4 (`desk.ideas_per_request` default 3; `ai.context_days` default 90; `ai.context_max_posts` ⚠️ ASSUMPTION default 60; `ai.include_draft_excerpts` default true: titles and excerpts of drafts only, bodies never; all read with `Config::get()`), §8 Phase 5 unit tests. AI invariants as in P5-02; Desk invariants as in P4-01; client invariants as in P2-01.
- `Ideas::__construct( AiClient $ai, PlanReader $reader, SeriesSource $series, CategoryGap $gaps, Budget $budget )`. `context( string $date ): array` gathers titles and excerpts (`post_excerpt`, never `post_content`) of posts from the last `ai.context_days` days, at most `ai.context_max_posts`, including drafts only when `ai.include_draft_excerpts` is true; the names of series that still have undated or missing parts; and the gap category names. `ideas( string $date, array $lane, array $dismissed ): array|\WP_Error` builds the prompt with `Prompts::ideas()`, calls `json()` on the `fast` tier with `Schemas::ideas()` (allowed categories: the lane's, or every category slug when the lane has none), returns the `WP_Error` unchanged on failure, returns `WP_Error( 'masthead_ai_invalid' )` when `Schemas::validate()` rejects the reply, and otherwise returns one `idea` candidate per item (key `idea:<sha1 of title>`, `why` = the item's `angle`, `category`, actions `create_draft`, `dismiss`), dropping dismissed keys and duplicates. `status(): array` returns `{ supported, remaining }`.
- `Suggester::suggest()` gains a fourth argument `bool $include_ideas`. Ideas are requested only when it is true and `$ai->supported()`; they are appended after the ranked deterministic list (which keeps its own `desk.max_suggestions` limit). On any `WP_Error` the deterministic list is returned unchanged with `ai.used` false and `ai.error` set to the error code. `ai.remaining` is `Budget::remaining()`.
- `SuggestForDate::permission()` requires `prompt_ai` in addition to `edit_posts` when `include_ideas` is true. `SuggestCommand --ideas` passes it through.
- Client: when boot `ai.supported` and `caps.promptAi` are true, `DeskRail` shows a "Suggest ideas" button for the selected open day, and `SuggestionCard` renders the `suggest_topics` action as "Suggest topics"; both call `useSuggestions().loadIdeas( date )`, which runs the ability with `include_ideas: true` and replaces that date's cached result. Ghost cards and "Fill open days" still never request ideas. An `ai.error` is shown as a notice.

**Acceptance tests:**

- `tests/unit/Desk/IdeasTest.php`: `IdeasTest::test_valid_ideas_become_idea_candidates_with_sha1_keys`, `IdeasTest::test_idea_outside_the_lane_categories_is_dropped_not_coerced`, `IdeasTest::test_invalid_json_returns_wp_error_and_no_candidates`, `IdeasTest::test_budget_exhaustion_returns_the_budget_error`, `IdeasTest::test_context_never_contains_post_content`, `IdeasTest::test_context_omits_drafts_when_draft_excerpts_are_off`, `IdeasTest::test_context_size_is_bounded_by_config`.
- `tests/unit/Desk/SuggesterTest.php`: `SuggesterTest::test_ideas_are_appended_after_deterministic_suggestions`, `SuggesterTest::test_ai_error_leaves_the_deterministic_list_unchanged`, `SuggesterTest::test_ideas_are_not_requested_without_include_ideas`.
- `tests/integration/Abilities/SuggestForDateTest.php`: `SuggestForDateTest::test_include_ideas_requires_prompt_ai`, `SuggestForDateTest::test_include_ideas_appends_fixture_ideas_and_reports_ai_used` (with `FakeAiClient` injected).
- `tests/js/calendar/DeskRail.test.js`: "Suggest ideas is shown only when AI is supported and permitted", "Suggest ideas reloads the date with include_ideas".
- `tests/e2e/ideas.spec.js` (re-seeds in `beforeAll`): "Suggest ideas on Nov 18 adds the three fixture ideas after the draft and gap suggestions".

**Out of scope:** outlines (P5-07); Ask the Desk (P5-11); calling AI from cron or on page load.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`, `npm run lint`, `npm run test:unit`.

**Depends on:** P5-03, P5-05, P4-09

### P5-07: masthead/draft-outline ability and the Day plan outline

**Goal:** Generate an outline for a chosen suggestion on request and carry it into the planned draft.

**Files touched:** `src/Desk/Ideas.php`, `src/Abilities/DraftOutline.php`, `src/Plugin.php`, `client/calendar/DayPlan.js`, `tests/unit/Desk/IdeasTest.php`, `tests/integration/Abilities/DraftOutlineTest.php`, `tests/js/calendar/DayPlan.test.js`, `tests/e2e/dayplan.spec.js`

**Design constraints:** SPEC §8 Phase 5 ("outline generation in Day plan"), §6.6 (`outline` schema; `ai.models.strong` is used for Ask the Desk and outlines), §6.7 ("Create draft with outline"), §5.3 (`masthead_outline`), §2 (the AI writes outlines only when asked; never bodies). SPEC §6.1 lists no ability that returns an outline, so this task adds one, `masthead/draft-outline`, shaped like `masthead/draft-share-copy` (PLAN Spec issues 10): input `{ title, category?, date? }`, output `{ outline: [string] }`, read-only, capability `edit_posts` + `prompt_ai`. Follow the Ability protocol. AI invariants as in P5-02; client invariants as in P2-01. `Ideas::outline( string $title, ?string $category, ?string $date ): array|\WP_Error` uses `Prompts::outline()` with the same bounded context, the `strong` tier and `Schemas::outline()`, and returns `WP_Error( 'masthead_ai_invalid' )` when validation fails. In `DayPlan`, when boot `ai.supported` and `caps.promptAi` are true, show a "Draft an outline" button; it runs the ability with the current title and category and shows the returned lines as an editable list (each line a labelled text input with a remove button, plus "Add line"), with the note "Outline only. The Desk never writes body copy." The primary button then reads "Create draft with outline" and sends the edited `outline` to `masthead/create-planned-draft`. An error from the ability is shown and leaves the form usable.

**Acceptance tests:**

- `tests/unit/Desk/IdeasTest.php`: `IdeasTest::test_outline_uses_the_strong_tier_and_outline_schema`, `IdeasTest::test_invalid_outline_returns_wp_error`.
- `tests/integration/Abilities/DraftOutlineTest.php`: `DraftOutlineTest::test_allowed`, `DraftOutlineTest::test_denied` (an editor without `prompt_ai`), `DraftOutlineTest::test_happy_path` (with `FakeAiClient`), `DraftOutlineTest::test_budget_exhaustion_returns_the_budget_error`.
- `tests/js/calendar/DayPlan.test.js`: "Draft an outline is hidden when AI is unsupported", "the returned outline is editable and sent with create-planned-draft", "an outline error leaves the form usable".
- `tests/e2e/dayplan.spec.js`: "planning the fixture idea with a drafted outline creates a draft whose content has the outline list".

**Out of scope:** writing post bodies; regenerating outlines automatically.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`, `npm run lint`, `npm run test:unit`.

**Depends on:** P5-06, P4-10

### P5-08: masthead/draft-share-copy ability and the Share panel's AI button

**Goal:** Draft share copy for a published post on request.

**Files touched:** `src/Desk/ShareCopy.php`, `src/Abilities/DraftShareCopy.php`, `src/Plugin.php`, `client/calendar/SharePanel.js`, `tests/unit/Desk/ShareCopyTest.php`, `tests/integration/Abilities/DraftShareCopyTest.php`, `tests/js/calendar/SharePanel.test.js`

**Design constraints:** SPEC §6.1 row `masthead/draft-share-copy` (input `{ post_id }`, output `{ message }`, read-only, `edit_posts` + `prompt_ai`), §6.6 (`share_copy` schema), §6.7 (Share panel message with a "Draft with AI" button), §2 (share copy the Desk can draft on request; nothing is shared without a click). Follow the Ability protocol. AI invariants as in P5-02; client invariants as in P2-01. `ShareCopy::__construct( AiClient $ai )`; `draft( int $post_id ): string|\WP_Error` refuses a missing post (`masthead_not_found`, 404) and a post that is not `publish` (`masthead_not_published`, 409), builds `Prompts::share_copy()` from the title and `post_excerpt` only, calls `json()` on the `fast` tier with `Schemas::share_copy()`, and returns the validated `message` or `WP_Error( 'masthead_ai_invalid' )`. In `SharePanel`, when boot `ai.supported` and `caps.promptAi` are true, a "Draft with AI" button beside the message label runs the ability and puts the result in the textarea, where it stays editable; it never shares. If the textarea already has text the button asks for confirmation before replacing it.

**Acceptance tests:**

- `tests/unit/Desk/ShareCopyTest.php`: `ShareCopyTest::test_returns_the_validated_message`, `ShareCopyTest::test_invalid_reply_returns_wp_error`, `ShareCopyTest::test_non_published_post_is_refused_without_an_ai_call`, `ShareCopyTest::test_prompt_contains_title_and_excerpt_but_not_content`.
- `tests/integration/Abilities/DraftShareCopyTest.php`: `DraftShareCopyTest::test_allowed`, `DraftShareCopyTest::test_denied`, `DraftShareCopyTest::test_happy_path` (with `FakeAiClient`).
- `tests/js/calendar/SharePanel.test.js`: "Draft with AI fills the message and does not share", "Draft with AI is hidden when AI is unsupported", "existing text is replaced only after confirmation".

**Out of scope:** sharing automatically; per-network copy.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`, `npm run lint`, `npm run test:unit`.

**Depends on:** P5-03, P5-05, P3-09

### P5-09: SeriesPlanner and masthead/propose-series

**Goal:** Turn a free-text request into a proposed series whose part dates are computed by Masthead, not by the AI.

**Files touched:** `src/Desk/SeriesPlanner.php`, `src/Abilities/ProposeSeries.php`, `src/Plugin.php`, `tests/unit/Desk/SeriesPlannerTest.php`, `tests/integration/Abilities/ProposeSeriesTest.php`

**Design constraints:** SPEC §6.1 row `masthead/propose-series` (input `{ prompt, start?: date }`, output `{ name, lane, parts: [{ date, title, outline: [string] }] }`, read-only, `edit_posts` + `prompt_ai`), §6.6 (`series_proposal` schema; strong tier for Ask the Desk), §3.3 (the AI states no dates), §5.4 (planner key `ai.series_max_parts`, default 12, read with `Config::get()`), §8 Phase 5 manual check (four dated drafts on Thursdays for a security series). Follow the Ability protocol. AI invariants as in P5-02; Desk invariants as in P4-01. `SeriesPlanner::__construct( AiClient $ai )`; `propose( string $prompt, ?string $start ): array|\WP_Error` calls `json()` on the `strong` tier with `Prompts::series_proposal()` and `Schemas::series_proposal()` (allowed categories: every category slug), and returns `WP_Error( 'masthead_ai_invalid' )` when validation fails. Dates are then computed without the AI: the first date is the first day on or after `$start` (default: the day after `Clock::today()`; a `$start` in the past is refused with `masthead_invalid_date`, 400) whose lane's categories explicitly contain the proposed `category`, or `$start` itself when no lane within `Dates::DAYS_IN_WEEK` days does; later parts follow at `every_weeks` weeks through `Cadence::schedule()`. The output adds `category`, `cadence` (`{ every_weeks, weekday, time }` with the first date's weekday and its lane's time) and `lane` (the first date's lane) to SPEC's shape. The request text is the user's and is fenced as untrusted in the prompt. An empty prompt is refused (`masthead_invalid_input`, 400) before any AI call. The ability writes nothing.

**Acceptance tests:**

- `tests/unit/Desk/SeriesPlannerTest.php`: `SeriesPlannerTest::test_security_series_starting_2027_01_01_lands_on_thursdays_from_2027_01_07`, `SeriesPlannerTest::test_dates_follow_every_weeks`, `SeriesPlannerTest::test_category_without_a_lane_starts_on_the_start_date`, `SeriesPlannerTest::test_invalid_reply_returns_wp_error`, `SeriesPlannerTest::test_past_start_is_refused_before_any_ai_call`, `SeriesPlannerTest::test_empty_prompt_is_refused_before_any_ai_call`.
- `tests/integration/Abilities/ProposeSeriesTest.php`: `ProposeSeriesTest::test_allowed`, `ProposeSeriesTest::test_denied`, `ProposeSeriesTest::test_happy_path` (with `FakeAiClient`), `ProposeSeriesTest::test_proposing_creates_no_post_and_no_term`.

**Out of scope:** creating anything (P5-10); the Ask the Desk UI (P5-11).

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P5-03, P5-05, P1-10

### P5-10: masthead/accept-series

**Goal:** Create a proposed series for real: the series term with its cadence and one dated draft per part, all or nothing.

**Files touched:** `src/Desk/SeriesPlanner.php`, `src/Abilities/AcceptSeries.php`, `src/Plugin.php`, `tests/unit/Desk/SeriesPlannerTest.php`, `tests/integration/Abilities/AcceptSeriesTest.php`

**Design constraints:** SPEC §6.1 row `masthead/accept-series` (input `{ name, parts: [...], cadence? }`, output `{ term_id, post_ids: [int] }`, no annotation set to true, `edit_posts`), §8 Phase 5 (accept creates the series term with cadence and dated drafts; "invalid AI JSON → `WP_Error` and no partial writes"), §5.2, §3.1 (`writes-confined`: drafts are created only through `Slots::create()`). Follow the Ability protocol. Desk invariants as in P4-01. Input parts are `{ date, title, outline?: [string] }`; this plan adds an optional `category` slug to the input (SPEC's shape has no way to say which category the drafts get); when absent, the category is the first category of the first part's lane, and when that lane has none the request is refused (`masthead_invalid_input`, 400). `SeriesPlanner::accept( string $name, array $parts, ?array $cadence, ?string $category ): array|\WP_Error` validates everything before writing anything: a non-empty name, between one and `Config::get( 'ai.series_max_parts' )` parts, every date valid and not before `Clock::today()`, every title non-empty, the category existing, the cadence valid when given (`Cadence::sanitize()`). It then creates the term in `SeriesSource::taxonomy()` (refusing an existing term of that name with `masthead_series_exists`, 409), stores `masthead_cadence` and the total-parts meta, and creates each part with `Slots::create()` carrying `series: { term_id, part }` and the outline. If any step fails it deletes the drafts it created with `Slots::delete()` and the term, and returns the error. This ability uses no AI and needs no `prompt_ai`.

**Acceptance tests:**

- `tests/unit/Desk/SeriesPlannerTest.php`: `SeriesPlannerTest::test_accept_validates_every_part_before_writing`, `SeriesPlannerTest::test_accept_rolls_back_drafts_and_term_when_a_part_fails`, `SeriesPlannerTest::test_accept_rejects_more_parts_than_the_maximum`.
- `tests/integration/Abilities/AcceptSeriesTest.php`: `AcceptSeriesTest::test_allowed`, `AcceptSeriesTest::test_denied`, `AcceptSeriesTest::test_happy_path` (four parts create one term with cadence and four dated, planned drafts with part numbers 1 to 4), `AcceptSeriesTest::test_invalid_part_creates_no_term_and_no_post`, `AcceptSeriesTest::test_existing_series_name_is_refused`.

**Out of scope:** the UI (P5-11); editing an existing series.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`.

**Depends on:** P5-09, P1-06

### P5-11: Ask the Desk

**Goal:** Add the "Ask the Desk" box: propose a series from a sentence, let the user edit the dated parts, and create it on Accept.

**Files touched:** `client/calendar/AskDesk.js`, `client/calendar/DeskRail.js`, `client/calendar/style.scss`, `tests/js/calendar/AskDesk.test.js`, `tests/e2e/ask.spec.js`

**Design constraints:** SPEC §8 Phase 5 (`propose-series` + `accept-series` behind Ask the Desk; the proposal is shown as a list of dated parts the user can edit; accept creates the series term with cadence and dated drafts), §4.3 (`AskDesk`), §2 (every write is a click), `docs/mockups/Main.dc.html` ("ASK THE DESK"). Client invariants as in P2-01. `DeskRail` renders `AskDesk` at the bottom when boot `ai.supported` and `caps.promptAi` are true; otherwise it renders the line "Ask the Desk needs an AI provider" with a link to boot `urls.connectors`. `AskDesk` has a labelled text input "Ask the Desk", an "Ask" button, and a line naming where the provider is configured ("Settings › Connectors"); it shows no provider or model name it was not given. Submitting runs `masthead/propose-series` with `{ prompt }`. The proposal is shown as: a labelled input for the series name, the lane label, and one row per part with a labelled `<input type="date">`, a labelled title input and the outline as plain list items, plus "Remove part"; "Accept series" runs `masthead/accept-series` with the edited `{ name, parts, cadence, category }` and "Discard" clears the proposal. After a successful accept it refreshes the plan and reports "Created <n> planned drafts" in an `aria-live` region. Errors (including `masthead_ai_budget`) are shown as a notice and keep the proposal.

**Acceptance tests:**

- `tests/js/calendar/AskDesk.test.js`: "is replaced by a Connectors hint when AI is unsupported", "asking runs propose-series and lists the dated parts", "edited dates and titles are sent to accept-series", "discard clears the proposal without any write", "an error keeps the proposal and shows the message".
- `tests/e2e/ask.spec.js` (re-seeds in `beforeAll`): "accepting the fixture series creates four dated drafts on Thursdays" (asks, accepts, then reads the four chips in the list view and asserts each date is a Thursday), "Ask the Desk has no serious or critical axe violations".

**Out of scope:** free-form chat; any request other than a series proposal.

**Verification:** `npm run lint`, `npm run test:unit`.

**Depends on:** P5-10, P5-06

### P5-12: Settings: Desk and AI fields, and wp masthead ai:status

**Goal:** Let an administrator edit every settable Desk, swap, stats and AI key, see the AI provider and budget state, and read the same state from the CLI.

**Files touched:** `client/settings/AiSettings.js`, `client/settings/DeskSettings.js`, `client/settings/App.js`, `client/settings/style.scss`, `src/Admin/Assets.php`, `src/Cli/AiStatusCommand.php`, `src/Plugin.php`, `tests/js/settings/AiSettings.test.js`, `tests/js/settings/DeskSettings.test.js`, `tests/unit/Cli/AiStatusCommandTest.php`, `tests/e2e/settings.spec.js`

**Design constraints:** SPEC §6.7 (Settings: Desk/AI settings; the mock's "Draft bodies" toggle is `ai.include_draft_excerpts`, relabelled "Draft titles and excerpts"), §8 Phase 5 (Settings AI section: model tiers, cap, context toggles), §5.4 (every key marked settable), §6.4 (`ai:status`: provider supported?, budget), §9 question 5 (Settings shows the model actually used, if the AI Client exposes it), §2 (no key is ever shown or stored). Client invariants as in P2-01: the client never writes a default; each field shows the current value from a new boot value `settingsDefaults` (`Config::all()` restricted to `Config::settable()`, added in `Assets`) overlaid with the saved `masthead_settings`, and saves only values the user changed. `DeskSettings` has labelled number inputs for `desk.horizon_days`, `desk.ghost_days`, `desk.max_suggestions`, `desk.ideas_per_request`, `desk.gap_days`, `desk.reshare_min_age_days`, `desk.reshare_cooldown_days`, `desk.series_window_days`, `swap.lookahead_days`, `swap.candidate_window_days`, `stats.window_days`, and for `swap.min_score_delta` and the three `swap.weights`. `AiSettings` keeps the provider status and Connectors notice from P2-11 and adds: text inputs for `ai.models.fast` and `ai.models.strong` (comma-separated ids, with the help text "Used only if the active provider offers them; otherwise the provider's default model is used."), a number input for `ai.monthly_request_cap` (help: "0 turns the Desk's AI off.") with "<n> requests left this month" from boot `ai.remaining`, a number input for `ai.context_days`, and a checkbox "Draft titles and excerpts" for `ai.include_draft_excerpts`. When `docs/spikes/P5-01-ai-client.md` says `Decision: model-id-exposed`, it shows "Last model used" per tier from a boot value `ai.models` (`Budget::models()`); otherwise it shows "The AI Client does not report which model was used." `AiStatusCommand` (`wp masthead ai:status`) prints `supported`, `remaining`, the cap and the configured model lists from `Ideas::status()` and `Config` (the `Cli` module may not import `Ai`).

**Acceptance tests:**

- `tests/js/settings/DeskSettings.test.js`: "shows the current value of every settable desk and swap key", "saves only the keys the user changed".
- `tests/js/settings/AiSettings.test.js`: "model lists are edited as comma-separated ids", "the draft toggle is labelled Draft titles and excerpts", "shows requests left this month", "shows the model-not-reported message when no model is known".
- `tests/unit/Cli/AiStatusCommandTest.php`: `AiStatusCommandTest::test_prints_support_remaining_and_cap`.
- `tests/e2e/settings.spec.js`: "changing the monthly request cap persists after reload", "Desk settings have no serious or critical axe violations".

**Out of scope:** provider configuration; storing or displaying any credential.

**Verification:** `composer lint`, `composer test:unit`, `bash scripts/forbidden-patterns.sh`, `npm run lint`, `npm run test:unit`.

**Depends on:** P5-06, P2-11

### P5-13: Tune the AI assumptions

**Goal:** Measure what can be measured about the AI ⚠️ ASSUMPTION defaults without a live provider, keep or change them, and record plainly what still needs a human.

**Files touched:** `tests/unit/Tuning/AiBench.php`, `docs/tuning/P5-13.md`, `src/Config.php`

**Design constraints:** SPEC §5.4 keys, all ⚠️ ASSUMPTION: `ai.monthly_request_cap` (300), `ai.models.fast` (`['claude-haiku-5-5']`), `ai.models.strong` (`['claude-sonnet-5-5']`), `ai.temperature` (0.4), `ai.max_tokens` (1200), `ai.context_max_posts` (60). Follow the Tuning protocol. The harness is a unit test in the group `tuning` that prints three tables. (1) `ai.context_max_posts` in 30, 60, 120: the size in characters, and in estimated tokens (characters divided by four), of the `Prompts::ideas()` user prompt built from that many synthetic posts with 80-character titles and 300-character excerpts. (2) `ai.max_tokens` in 800, 1200, 2000: for the largest schema-valid reply each schema allows (ideas at `desk.ideas_per_request`; a series at `ai.series_max_parts` parts with six outline lines of 120 characters each), the estimated tokens and whether it fits. (3) `ai.monthly_request_cap` in 150, 300, 600: the number of whole days of a stated usage pattern it covers (per day: one "Suggest ideas", one outline, one share draft; per week: one series proposal). Decision rules: keep `context_max_posts` unless the prompt exceeds 8000 estimated tokens, then the largest measured value under it; keep `max_tokens` unless the largest series reply does not fit, then the smallest measured value that fits; keep the cap unless it covers fewer than 31 days of the pattern, then the smallest measured value that does. `ai.temperature` and both model lists cannot be measured without a live provider: record each as `NOT MEASURED (needs a configured AI provider; human)`, leave the defaults, and add them to the Phase 5 manual checks. Change `src/Config.php` only where a rule says so.

**Acceptance tests:** `tests/unit/Tuning/AiBench.php`: `AiBench::test_measure_ai_assumptions` (group `tuning`; asserts only that each table was produced). `docs/tuning/P5-13.md` holds the three tables with before and after rows and the two `NOT MEASURED` entries.

**Out of scope:** calling any AI provider; changing prompts or schemas.

**Verification:** `vendor/bin/phpunit -c phpunit.xml.dist --group tuning --filter AiBench`; copy the before and after rows into the commit message and the progress log.

**Depends on:** P5-06

### P5-14: Phase 5 push and manual checks

**Goal:** Push the branch and record what a human must check for Phase 5.

**Files touched:** `docs/manual-checks.md`

**Design constraints:** SPEC §8 Phase 5 manual check. Follow the Phase end protocol. Append a `## Phase 5` section listing: (1) install AI Provider for Anthropic on a local site with a real key, ask the Desk for "a 4-part security series starting the first Thursday in January", accept it, and see four dated drafts on Thursdays; (2) press "Suggest ideas" on an open day and "Draft an outline" in the Day plan, and confirm no reply states a traffic number, a date or a claim about history; (3) press "Draft with AI" in the Share panel for a published post; (4) repeat one request with AI Provider for OpenRouter and confirm the provider's default model is used and Settings reports the model as `docs/spikes/P5-01-ai-client.md` predicts; (5) judge `ai.temperature` and the two model lists, which `docs/tuning/P5-13.md` records as not measured; (6) set the monthly cap to 1, make two requests and see the budget message.

**Acceptance tests:** none (documentation only); the full `verify` set passes.

**Out of scope:** performing the manual checks; configuring a provider.

**Verification:** `composer audit` and `npm audit --audit-level=high`, results recorded in the progress log; push the branch; write `Manual check: NOT VERIFIED (human)` in the progress log.

**Depends on:** none

## Phase 6 — Release and deployment

### P6-01: Synthetic fixture titles

**Goal:** Replace the real post titles in the acceptance fixture with synthetic ones, keeping the scenario intact.

**Files touched:** `docs/fixtures/queue-2026-11-09.json`, `tests/unit/FixtureTest.php`, `tests/unit/`, `tests/integration/`, `tests/e2e/`, `tests/js/`

**Design constraints:** SPEC §9 question 7 (the fixture holds real titles, including one unpublished draft; replace with synthetic titles before the repository is made public), §2 (open-sourceable without edits; no owner-specific data in code). In the fixture change only the `title` of the six posts that are not part of a series (ids 10193, 12177, 10194, 10178, 10192, 10191), the `tags` of 10178, and the `_source` and `_note` lines, which must now say that those titles are synthetic. Each new title is invented for this file, unique, and about as long as the one it replaces. Keep every id, date, status, category, `modified`, `now` and `timezone` exactly. Interpretation: the series name "The Mantle" and its four part titles stay, because SPEC §6.3's acceptance text names the series and its first part is already published; the Phase 6 manual checks list this for the owner to confirm. Update every test that asserts one of the six replaced titles. Change nothing under `src/`, `client/` or `docs/mockups/`, and do not edit `docs/SPEC.md`.

**Acceptance tests:** `tests/unit/FixtureTest.php`: `FixtureTest::test_fixture_declares_synthetic_titles`, `FixtureTest::test_fixture_ids_dates_statuses_and_categories_are_unchanged` (compares against a table of the ten posts written in the test), `FixtureTest::test_series_posts_keep_their_titles`. Every existing unit, integration and e2e test passes.

**Out of scope:** the mockups, which still show real planned titles and series ideas (a human decision, listed in the Phase 6 manual checks); rewriting git history; changing the fixture's structure.

**Verification:** `composer lint`, `composer test:unit`, `npm run lint`, `npm run test:unit`; `npm run test:integration` and `npm run test:e2e` run through `kukulu_verify`. `grep -rn "Craftsmen\|DailyMedToday\|Embeddings Reveal\|Nineteen Years\|Voice to Vectors\|RAG Over Markdown" docs/fixtures tests` prints nothing.

**Depends on:** P1-14

### P6-02: readme.txt, CHANGELOG.md and version 0.1.0

**Goal:** Add the WordPress readme and the changelog, and set the version to 0.1.0 everywhere.

**Files touched:** `readme.txt`, `CHANGELOG.md`, `masthead.php`, `src/constants.php`, `package.json`, `package-lock.json`, `scripts/forbidden-patterns.sh`, `scripts/release/pack.mjs`, `tests/js/release/pack.test.js`

**Design constraints:** SPEC §8 Phase 6 (`readme.txt`: Requires at least 7.1, Tested up to 7.1, Requires PHP 8.3, GPL-2.0-or-later; `CHANGELOG.md`; version `0.1.0`), §2 (release zip pinned by tag; not submitted to WordPress.org in 0.1). `readme.txt` uses the WordPress.org header format with `Stable tag: 0.1.0`, a short description under 150 characters, and the sections Description, Installation (install the release zip; requires Jetpack for stats and sharing and an AI provider under Settings › Connectors for ideas), Frequently Asked Questions and Changelog; it names no site and no person. `CHANGELOG.md` follows Keep a Changelog with one `0.1.0` entry dated with the commit date read from `git log -1 --format=%cs`. Set `0.1.0` in the `masthead.php` header, `Masthead\VERSION` and `package.json` (and the lockfile). Extend the `version-agree` check in `scripts/forbidden-patterns.sh` to include `readme.txt`'s `Stable tag` and the newest `CHANGELOG.md` heading, with self-test trees for both. Make `scripts/release/pack.mjs` fail when `readme.txt`, `README.md` (added in P6-03; until then accept its absence) or `CHANGELOG.md` is missing from the staged files once version is not `0.0.0`. Do not create or push a git tag: tagging `v0.1.0` is a human step after the pull request merges (PLAN Decisions).

**Acceptance tests:** `tests/js/release/pack.test.js`: "includes readme.txt and CHANGELOG.md in the zip". `bash scripts/forbidden-patterns.sh --self-test` exits 0 with the extended `version-agree` check. `node scripts/release/pack.mjs --check-tag v0.1.0` exits 0 and `--check-tag v0.1.1` exits non-zero.

**Out of scope:** creating tags or releases; WordPress.org assets; `README.md`.

**Verification:** `bash scripts/forbidden-patterns.sh`, `npm run lint`, `npm run test:unit`, `composer lint`; `npm run release:pack` and `npm run test:integration` run through `kukulu_verify`.

**Depends on:** P0-03, P0-07

### P6-03: README.md with screenshots generated by Playwright

**Goal:** Write the repository README with screenshots that a Playwright script generates from the seeded fixture.

**Files touched:** `README.md`, `tests/e2e/screenshots/capture.spec.js`, `tests/e2e/playwright.config.mjs`, `package.json`, `docs/screenshots/calendar-month.png`, `docs/screenshots/day-plan.png`, `docs/screenshots/swap-panel.png`, `docs/screenshots/settings.png`, `scripts/release/pack.mjs`

**Design constraints:** SPEC §8 Phase 6 (`README.md` with screenshots generated by Playwright), §2 (open-sourceable; no owner-specific data), §4.1. `capture.spec.js` runs only when the environment variable `MASTHEAD_SCREENSHOTS` is `1` (otherwise every test in it is skipped, so the normal e2e run never rewrites the images); add the script `screenshots` = `npm run build && wp-env start && MASTHEAD_SCREENSHOTS=1 wp-scripts test-playwright --config tests/e2e/playwright.config.mjs tests/e2e/screenshots --project=desktop`. It re-seeds, runs `wp masthead swap-check --write --now="2026-11-09 14:00:00"`, and captures at 1440×1000 the month view with Nov 18 selected, the Day plan for Nov 18, the swap panel for the series' Part 2, and Settings › Calendar, each cropped to the Masthead root. `README.md`: what Masthead is (from SPEC §1, without naming a site), requirements (WordPress 7.1+, PHP 8.3+, Jetpack for stats and sharing, an AI provider under Settings › Connectors for ideas), the four screenshots, installation from the release zip, the `masthead/*` abilities table, the `wp masthead` commands, development commands (CLAUDE.md `## Commands`), and the licence. Remove P6-02's allowance for a missing `README.md` from `scripts/release/pack.mjs`.

**Acceptance tests:** `tests/e2e/screenshots/capture.spec.js`: "captures the four README screenshots" (skipped unless `MASTHEAD_SCREENSHOTS=1`). The four PNG files exist, are non-empty, and each is referenced from `README.md`. `tests/js/release/pack.test.js`: "includes README.md in the zip".

**Out of scope:** WordPress.org banner or icon assets; screenshots at 390 px.

**Verification:** `npm run screenshots` once (allow 30 minutes; if the foreground call is cut off, rerun it in the background and wait for it), `npm run lint`, `npm run test:unit`; `npm run release:pack` and `npm run test:e2e` run through `kukulu_verify`.

**Depends on:** P4-16, P4-10, P2-11, P0-07

### P6-04: docs/DEPLOYMENT.md

**Goal:** Document how Masthead is built, released and pinned into the site image.

**Files touched:** `docs/DEPLOYMENT.md`

**Design constraints:** SPEC Appendix B, §2 (release zip pinned by tag; Masthead never stores API keys), §4.1 (`docs/DEPLOYMENT.md` is written in Phase 6). Sections: Release (bump the version in the four places `scripts/forbidden-patterns.sh` checks, merge, create a signed `v<version>` tag, the release workflow attaches `masthead.zip` and `masthead.zip.sha256`); Pinning into the site image (the Dockerfile fragment from Appendix B verbatim, with `<owner>` and the plugins directory left as placeholders, and the note that an upgrade is a two-line change); AI provider (add `ai-provider-for-anthropic` or `ai-provider-for-openrouter` to the site's Composer file and supply the provider key to the pod from a Kubernetes Secret; Masthead never sees it); Private repository (the image build needs a read token for the release asset until the repository is public); Jetpack (Stats module and Social connection are required for numbers and sharing; without them Masthead shows "Unavailable"); After first deploy (run `wp masthead stats:probe`, `social:probe` and `ai:status`; review `docs/tuning/` and the settable keys); Cron (the nightly event `masthead_nightly` runs at the site-local hour in `cron.nightly_hour` and only reads and caches). No secret, token, hostname or personal name appears in the file.

**Acceptance tests:** none (documentation only). `grep -c "sha256sum -c" docs/DEPLOYMENT.md` is at least 1.

**Out of scope:** changing the site repository; deploying.

**Verification:** the full `verify` set passes; `grep -Ei "api[_-]?key *=|token *=|password *=" docs/DEPLOYMENT.md` prints nothing.

**Depends on:** none

### P6-05: Phase 6 push and manual checks

**Goal:** Push the branch and record what a human must check and do to release 0.1.0.

**Files touched:** `docs/manual-checks.md`

**Design constraints:** SPEC §8 Phase 6 manual check. Follow the Phase end protocol. Append a `## Phase 6` section listing: (1) after the pull request merges, create and push the signed tag `v0.1.0` and confirm the release has `masthead.zip` and `masthead.zip.sha256`; (2) bump the site repository's pinned version and checksum, build the image, roll it, and plan one real week from the calendar; (3) before making the repository public: decide whether to rewrite git history, which still contains the real post titles replaced in P6-01; decide whether to scrub or remove `docs/mockups/`, which still show real planned titles, series ideas and the blog's name; confirm that keeping "The Mantle" in the fixture is acceptable; and check that `masthead` is free as a WordPress.org slug (SPEC §9 question 1); (4) run through every earlier section of this file that still reads NOT VERIFIED.

**Acceptance tests:** none (documentation only); the full `verify` set passes.

**Out of scope:** tagging; deploying; publishing the repository.

**Verification:** `composer audit` and `npm audit --audit-level=high`, results recorded in the progress log; push the branch; write `Manual check: NOT VERIFIED (human)` in the progress log.

**Depends on:** none

## Spec issues

1. **Phase 0 is one task in SPEC, eight here.** SPEC §8 says "P0-01 scaffolds everything". That is several thousand lines. Resolution: P0-01..P0-08, with every `constraints` entry still active from P0-01 and the other verify commands gated with `from` on the task that creates them.
2. **"Primary category" is never defined** (§2, §5.2, §6.1, §6.3). Resolution: lowest `term_id` among the post's categories. WordPress's default (alphabetical) order would make post 10194 primary `faith` and give it a `lane_mismatch` on a Thursday, contradicting §6.3's "12177 and 10194 produce no findings". The `PostDetail` mock colours 10194 as Faith; the mock loses to the SPEC text.
3. **`Budget` is in `Desk/` but `WpAiClient` must call it** (§4.2 against §6.6): `Ai/` may import only `Config` and `Support`. Resolution: `Desk\BudgetedAi` decorates the `AiClient` and reserves before each call. Likewise `Desk` needs `Ai\Prompts` and `Ai\Schemas`, not only the interface; they are pure and are allowed by name in the boundaries test.
4. **`masthead_settings` shape omits settable groups** (§5.1 lists `ai`, `abilities`, `desk`; §5.4 marks `swap.*` and `stats.window_days` settable). Resolution: the option also has `swap` and `stats` groups.
5. **`swap.lookahead_days` (60) and `desk.horizon_days` (21) both contradict the §6.3 acceptance**, which expects findings for posts 35, 63 and 91 days ahead, and "the nightly run covers the horizon" names both keys. Resolution: the swap check and the nightly run use `swap.lookahead_days`, and a series with one part inside the window is examined to its last scheduled part; `desk.horizon_days` bounds only "Fill open days". P4-17 measures the lookahead.
6. **The pinned clock cannot pin WordPress core.** Core turns a `future` post into `publish` when its date has passed by the wall clock, and schedules `publish_future_post`. From 2026-11-11 the fixture's "future" posts are in the real past, so any test that inserts or moves them through core alone would start failing. Resolution: `Slots` and `Reschedule` pin `post_status` during their writes, the seeder clears the publish events, wp-env sets `DISABLE_WP_CRON`, and no e2e test covers "Save as: Scheduled". The same pinning is harmless in production, where `Reschedule` refuses past datetimes for `future` posts.
7. **`MASTHEAD_STATS_FIXTURE` in `.wp-env.json` (§7) conflicts with the Phase 4 acceptance**, which requires "stats unavailable" in the same environment. Resolution: the constant is defined by `tests/e2e/mu/fixture-stats.php` only when option `masthead_e2e_stats` is `1`. Also, WordPress does not load mu-plugins from the subdirectory §7 maps; a one-file loader mapping is added.
8. **`wp_insert_post` confinement against the seeder** (§3.1 against §6.4). `seed` lives in `src/Cli/` and must create posts of every status. Resolution: `Slots::insert()` and `Slots::delete()` are the only raw calls and the seeder uses them.
9. **A finding option that needs no ability.** §6.3 says options "call abilities", but marking a lane exception is a term-meta write and no ability in §6.1 does that. Resolution: that option has `ability: null` and a `rest` descriptor for the `wp/v2` series endpoint, which §3.1 allows the client. Options that move several posts (the series move, a two-post swap) carry the extra calls in an added `also` list.
10. **Outline generation has no ability** (§8 Phase 5 and §6.7 against §6.1). Resolution: a thirteenth ability, `masthead/draft-outline`, modelled on `masthead/draft-share-copy`. If the owner prefers twelve, the alternative is to return outlines inside ideas and drop on-demand outlines.
11. **Additive fields.** `get-plan` also returns `unscheduled` (the Draft tray has no other source: `wp/v2/posts` cannot distinguish an undated draft) and `truncated`; undated `PlanPost`s carry `modified`; `propose-series` also returns `category` and `cadence`; `accept-series` also accepts `category`; `share-connections` also returns `reason`; `suggest-for-date`'s `ai` may carry `error`. No SPEC field is removed or renamed.
12. **`usePlan` data source** (§4.3 says core-data for posts plus `get-plan` for lanes). Resolution: `get-plan` is the single source of plan data for the reason in issue 11; core-data serves categories, series terms, settings and the one status write in the Day plan.
13. **Keys not in §5.4.** `calendar.max_range_days` (92), `desk.candidate_pool` (50), `stats.top_posts_limit` (10), `ai.series_max_parts` (12), `swap.collision_exempt_categories` (`['journal']`) are added because §3.2 forbids literals and unbounded queries and §6.3 says "non-Journal" without a key. `desk.candidate_pool` is measured in P4-11; the others are structural.
14. **Storage SPEC does not name.** Dismissals are user meta `masthead_dismissed`; the e2e clock pin is option `masthead_seed_now`; the e2e stats switch is option `masthead_e2e_stats`. Suggestion keys for series and gaps (`series:<term_id>:<part>`, `gap:<term_id>`) extend §6.1's two forms.
15. **[hard-coded-tunable] as a line pattern.** "No bare numeric literal ≥ 2" cannot tell a tunable from a hook's argument count. Resolution: the `constraints` entry flags every such literal except the SPEC exemptions and a number named by a `const` declaration, and the reviewer reads those constants; a second entry, `tunable-by-name`, catches a Config key assigned a literal in PHP or JS. [no-front-end] is matched as whole words so `get_the_excerpt()` stays legal. [strict-types] and the `package.json` rule are "must be present" rules and live in `scripts/forbidden-patterns.sh`.
16. **`seed` environments** (§6.4 says both "local environments only" and "refuses when production"). Resolution: it runs only when the environment type is `local`.
17. **§7 command details.** `npm run lint` also lints `tests/js`; `test:e2e` and `test:integration` build first; `test:e2e:smoke` exists for Phase 0, before `seed` does. `composer audit` and `npm audit` (§3.5) run in CI and at each phase end rather than after every task, because a new upstream advisory would otherwise block unrelated tasks.
18. **Rhythm defaults on a site without the categories.** §5.1 applies defaults "only for categories that exist", which turns a lane whose categories are all missing into an any-category lane. That is what SPEC says and it is implemented as written; the seeder reinstalls the defaults after creating the categories.
19. **Fixture privacy scope** (§9 question 7). SPEC asks for synthetic titles in the fixture only. The plan replaces the six non-series titles and keeps "The Mantle", which §6.3 names. `docs/mockups/` carry the same real titles plus unpublished series ideas, and git history keeps everything; both are left to the owner.
20. **Tagging.** §8 Phase 6 ends with "tag". The plan sets the version but leaves the signed tag to a human after merge, since the tag publishes a release.
21. **Commit signing.** §3.5 requires signed commits; `docs/kukulu.json` therefore sets `policies.signing` to `required`, so a run refuses to start rather than fall back to unsigned commits.
