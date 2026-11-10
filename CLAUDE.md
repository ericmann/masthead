# Masthead

WordPress plugin (PHP 8.3+, WordPress 7.1+): an editorial calendar under Posts › Calendar with a weekly rhythm, Desk suggestions, a nightly swap check and Jetpack Social re-sharing. `docs/SPEC.md` is the specification; section numbers below refer to it.

## Principles

- [ ] No front-end output. Admin assets load only on Masthead's two screens (§3.1).
- [ ] One implementation per capability: a WordPress Ability under `masthead/*`. The admin UI, WP-CLI and MCP all call it (§2, §6.1).
- [ ] All network traffic goes through Jetpack (Stats, Social) or the WordPress AI Client. Masthead never reads, stores or logs a credential (§3.1).
- [ ] Nothing writes without a user click or an explicit ability call. Cron reads and caches only (§2, §3.1).
- [ ] Deterministic: one clock (`Support\Clock`), no randomness, stable tie-breaks (kind, score, post ID, title) (§3.2).
- [ ] Every tunable is a `Config` key with its default only in `Config::defaults()`. A number of 2 or more anywhere else in PHP is either `Config::get( '…' )` or a named `const` that is not a tunable (§3.2, §5.4).
- [ ] Every query is bounded and sets `no_found_rows => true` unless it paginates. Use `get_posts()` so unit tests can stub it (§3.2).
- [ ] Every traffic number comes from `StatsSource`; unavailable means `null` and "Unavailable", never an estimate. The UI shows a stat only through `client/components/StatValue.js` (§3.3).
- [ ] "Why" lines and findings are `Desk\Reasons` templates filled from data. AI writes only idea titles, angles, outlines and share copy, validated against a JSON schema; an invalid or out-of-lane item is dropped, never coerced (§3.3).
- [ ] Every ability has a `permission_callback`; settings saves go through `register_setting` with a `sanitize_callback`; all output is escaped (§3.4).
- [ ] `declare(strict_types=1);` in every file under `src/`; PSR-4 `Masthead\` → `src/`; every user-facing string translatable with text domain `masthead` (§3.5).
- [ ] Unit tests never load WordPress (PHPUnit + Brain Monkey). Code that unit tests exercise accepts `object` where WordPress would pass a `WP_Post` or `WP_Term`. Integration tests run in wp-env (§3.5).
- [ ] Tests are written in the same task as the code they cover.
- [ ] `package.json` `dependencies` stays `{}`; both lockfiles are committed (§3.5).
- [ ] Commits and tags are GPG-signed. Never pass `--no-gpg-sign` (§3.5).

## Commands

| Purpose | Command |
|---|---|
| PHP lint | `composer lint` |
| PHP unit tests | `composer test:unit` |
| One PHP test class | `vendor/bin/phpunit -c phpunit.xml.dist --filter <Class>` |
| JS and CSS lint | `npm run lint` |
| JS unit tests | `npm run test:unit` |
| Build | `npm run build` (output `build/`, gitignored) |
| Structural checks | `bash scripts/forbidden-patterns.sh` |
| Integration (when `src/`, `masthead.php` or `tests/integration/` changes) | `npm run test:integration` |
| One integration class | `npx wp-env run tests-cli --env-cwd=wp-content/plugins/masthead vendor/bin/phpunit -c phpunit.integration.xml.dist --filter <Class>` |
| End to end (when `client/` or `tests/e2e/` changes) | `npm run test:e2e` |
| Release zip | `npm run release:pack` (output `dist/masthead.zip` and `.sha256`) |

## Module map

A module may import only the modules listed for it (§4.2). `tests/unit/BoundariesTest.php` enforces the table.

| Module | Holds | May import |
|---|---|---|
| `Plugin.php` | composition root: builds every service, wires every hook | everything |
| `Admin/` | `CalendarPage`, `SettingsPage`, `Assets`, `Notices` | `Config`, `Support`, `Plan` |
| `Cli/` | `Commands`, one class per `wp masthead` command, `Seeder` | `Abilities`, `Desk`, `Plan`, `Stats`, `Social`, `Config`, `Support` |
| `Abilities/` | `Ability` interface, `Registrar`, one class per ability | `Desk`, `Plan`, `Stats`, `Social`, `Ai`, `Config`, `Support` |
| `Cron/` | `Nightly` | `Desk`, `Stats`, `Config`, `Support` |
| `Desk/` | `Candidates/*`, `Suggester`, `Ranker`, `Reasons`, `Dismissals`, `Scoring`, `SwapCheck`, `Budget`, `BudgetedAi`, `Ideas`, `ShareCopy`, `SeriesPlanner` | `Plan`, `Config`, `Support`, and only `Stats\StatsSource`, `Social\ShareSource`, `Ai\AiClient`, `Ai\Prompts`, `Ai\Schemas` |
| `Ai/` | `AiClient`, `WpAiClient`, `Prompts`, `Schemas`, `Factory` | `Config`, `Support` |
| `Social/` | `ShareSource`, `JetpackSocial`, `NullShare`, `Factory` | `Config`, `Support` |
| `Stats/` | `StatsSource`, `JetpackStats`, `FixtureStats`, `NullStats`, `Payloads`, `Cache`, `Factory` | `Config`, `Support` |
| `Plan/` | `Rhythm`, `Lanes`, `Slots`, `Reschedule`, `SeriesSource`, `Cadence`, `Colors`, `PlanReader` | `Config`, `Support` |
| `Support/` | `Clock`, `Dates`, `Json`, `Categories` | `Config` |
| `Config.php` | `defaults()`, `get()`, filter `masthead_config` | nothing |

Client (`client/`): `index.js` (mounts by root element), `calendar/`, `settings/`, `components/`, `data/`. JS unit tests live in `tests/js/`, never under `client/`.

## Constraints

Each line with an id in brackets is enforced by the `constraints` entry of that id in `docs/kukulu.json`.

- No front-end hook name (`wp_head`, `wp_footer`, `wp_enqueue_scripts`, `template_redirect`, `the_content`, `the_excerpt`, `render_block*`, `pre_get_posts`) in `src/` or `masthead.php` [no-front-end].
- `WPCOM_Stats` and `Automattic\Jetpack\Stats` only in `src/Stats/JetpackStats.php` [stats-confined].
- `publicize` (any case) only in `src/Social/JetpackSocial.php` [social-confined].
- `wp_ai_client_prompt` only in `src/Ai/WpAiClient.php` [ai-confined].
- No `wp_remote_*`, `wp_safe_remote_*`, `curl_*`, `fsockopen`, or `file_get_contents`/`fopen` on a URL in PHP [no-direct-http].
- No `wpcom/`, `jetpack/` or `admin-ajax.php` under `client/` [client-endpoints].
- `wp_insert_post`, `wp_update_post`, `wp_delete_post` only in `src/Plan/Slots.php` and `src/Plan/Reschedule.php` [writes-confined].
- Nothing under `src/Cron/` writes a post, post meta or term, runs an ability or a share, or imports `Plan`, `Abilities`, `Social`, `Ai`, `Cli` or `Admin` [cron-read-only]; reviewer also reads `src/Cron/Nightly.php`.
- No option, meta or transient name containing `key`, `token`, `secret` or `password` [no-secrets-stored]; reviewer also reads names built at runtime.
- No `ANTHROPIC_API_KEY`, `OPENROUTER_API_KEY`, `getenv(`, `$_ENV` or `process.env` [no-key-reads]. Use `wp_get_environment_type()`.
- No PHP clock or date function, no `new DateTime*`, no `createFromFormat()` without a leading `!` outside `src/Support/Clock.php` [one-clock]; reviewer also reads for `'now'` passed to `->modify()`.
- No `Date.now()`, argument-less `new Date()` or `performance.now()` under `client/` outside `client/data/clock.js` [client-clock].
- No `rand`, `mt_rand`, `wp_rand`, `shuffle`, `array_rand`, `random_int`, `uniqid` in PHP [no-rng].
- No bare numeric literal of 2 or more in PHP outside `src/Config.php`, except HTTP status codes on a `'status'` or `'response'` line, array indices, `7` and a named `const` [hard-coded-tunable]; reviewer reads every numeric `const` outside `src/Config.php` and rejects a tunable.
- No Config tunable assigned a literal by name in PHP or client JS [tunable-by-name].
- No `posts_per_page => -1`, `numberposts => -1`, `nopaging => true`, `number => 0`, `per_page: -1` [bounded-queries]; `no_found_rows => true` on every query that does not paginate: reviewer checks by reading.
- No client file other than `client/components/StatValue.js` reads `views`, `visitors` or `visits` [stat-render-confined].
- No `eval`, `unserialize`, `extract`, `create_function`, `assert`, `system`, `exec`, `shell_exec`, `passthru`, `proc_open` in PHP [dangerous-php].
- `declare(strict_types=1);` in every `src/**/*.php`; `package.json` `dependencies` is `{}`; both lockfiles tracked; every `src/Abilities/*.php` except `Registrar.php` and `Ability.php` contains `permission`; plugin header, `Masthead\VERSION` and `package.json` versions agree: `bash scripts/forbidden-patterns.sh` (not line patterns).
- Module imports follow the module map: `tests/unit/BoundariesTest.php`.
- `Admin\Assets::enqueue()` returns early unless the hook suffix is one of the two registered page hooks: reviewer checks by reading `src/Admin/Assets.php`.
- `FixtureStats` is reachable only when the environment type is `local` and `MASTHEAD_STATS_FIXTURE` names a readable file; the `masthead_ai_client` filter is honoured only when the environment type is `local`: reviewer checks by reading `src/Stats/Factory.php` and `src/Ai/Factory.php`.
- No AI prompt asks for, and no `Reasons` template accepts from AI, a sentence about traffic, dates or history; post text sent to the model sits inside `<untrusted-post-data>`: reviewer checks by reading `src/Ai/Prompts.php` and `src/Desk/Reasons.php`.
- `masthead/share-post` has `meta.public` false unless `abilities.expose_share` is true: integration test, and reviewer checks by reading `src/Abilities/Registrar.php`.
- No server-side write path changes a post's `post_status`: `Plan\Slots` and `Plan\Reschedule` keep the status they were given, and the reviewer checks by reading `src/Plan/Slots.php` and `src/Plan/Reschedule.php`. The one status change is the Day plan's "Save as scheduled", which sets `future` through `wp/v2/posts` after a user click (§6.7).

## Task protocols

**Wiring.** `src/Plugin.php` is the only file that calls a `Factory` or constructs a service from another module; every other class receives its collaborators through its constructor. `src/Plugin.php`, `src/Abilities/Registrar.php`, `client/index.js`, `Config::defaults()`, `package.json`, `composer.json` and `.wp-env.json` are shared files: a task changes only the lines it names.

**Numbers.** When a number of 2 or more is needed in PHP: if it is in §5.4 or `Config::defaults()`, read it with `Config::get()`. If it is structural (a hook's argument count, a JSON depth), declare a `private const` in the class and say in its docblock why it is not a tunable. Never add a key to `Config::defaults()` unless the task says so.

**Ability.** One class per ability in `src/Abilities/`, implementing `Abilities\Ability`. Errors are `WP_Error` with a `masthead_*` code and `array( 'status' => <HTTP code> )`. Each ability has `tests/integration/Abilities/<Class>Test.php` with at least `test_allowed`, `test_denied` and `test_happy_path`, run through `wp_get_ability( '<name>' )` so the registered permission callback is what is tested.

**Fixture clock.** Tests pin the clock to `2026-11-09 14:00:00` in `America/Los_Angeles` with the `masthead_now` filter; e2e gets the same pin from `wp masthead seed --now`. WordPress core still uses the wall clock and would publish a `future` post whose date has passed, so tests never create a post with `wp_insert_post` directly when status matters: they go through `Plan\Slots` or the seeder, which pin `post_status`. Never wait for or trigger WP-Cron in a test; call the hooked method.

**wp-env.** `npm run test:integration` and `npm run test:e2e` start Docker and take minutes. Run them once per attempt. While iterating, run one class or one spec with the single-class commands above. Every e2e spec file re-seeds in `beforeAll` with the `seedFixture()` helper and must not depend on another spec's writes.

**Spike.** A spike task answers its stated question inside its time box by reading the installed source in wp-env (`npx wp-env run cli …`) and running read-only probes. It writes the file the task names with the headings Question, Method, Facts, Decision, Open risk; the first line under Decision is `Decision: <token>` using a token the task lists. If the facts cannot be established in the time box, it writes the file anyway with the task's stated fallback, marks each unconfirmed fact `UNCONFIRMED`, and finishes. A spike changes no file under `src/` or `client/`. Later tasks read the spike file and follow its Facts; where it says `UNCONFIRMED` they use the fallback named in their own text.

**Tuning.** A tuning task (1) commits a measurement harness the task names, (2) runs it for the SPEC default ("before") and at least two alternatives, (3) applies the decision rule the task states: keep the default unless the rule fails, and change only the value in `Config::defaults()`, (4) reruns for the chosen value ("after"), (5) writes the table to `docs/tuning/<task id>.md`, and copies the before and after rows into the commit message under `Measurement:` and into the progress log. Fixture acceptance tests must still pass with the chosen value. What cannot be measured without a live service is written as `NOT MEASURED (needs <service>; human)`, never guessed.

**Phase end.** The last task of a phase appends the phase's manual checks to `docs/manual-checks.md`, runs the audits it names, pushes the branch, and records `Manual check: NOT VERIFIED (human)` in the progress log. No later task waits for a manual check.

## Commit template

```text
<ID>: <title>

Goal: <one sentence>
Tests: <test files and names added or changed>
Interpretation: <any reading of SPEC chosen where two were possible, or "none">
Measurement: <before and after rows; tuning tasks only>
Manual check: <"none", or "NOT VERIFIED (human)" with what to check>
```

`docs/SPEC.md` wins over `docs/PLAN.md`, which wins over code comments.
