# P1-09 Spike: Abilities REST execution and exposure flags

## Question

Can `client/` run a `masthead/*` ability through a core REST route with cookie and nonce authentication, and what exactly must the request and the registration look like?

## Method

Read the WordPress 7.1.4 source in wp-env (`npx wp-env run cli`, paths below relative to `/var/www/html/wp-includes`) and ran one core ability (`core/get-site-info`) through `rest_do_request()` with `wp eval-file` as an administrator, as an anonymous user, with the wrong method and with invalid input. WordPress version confirmed from `version.php:19` (`7.1.4`).

## Facts

1. Run route. Namespace `wp-abilities/v1`, base `abilities`; route `/wp-abilities/v1/abilities/<name>/run` where `<name>` is the full ability name with its slash left as a slash (`masthead/get-plan` is `/wp-abilities/v1/abilities/masthead/get-plan/run`), regex `[a-zA-Z0-9\-\/]+?`. `rest-api/endpoints/class-wp-rest-abilities-v1-run-controller.php:27,35,45-47`.
2. Method per ability, from `meta.annotations` (`validate_request_method()`, same file, around line 117): `readonly: true` requires `GET`; `destructive: true` together with `idempotent: true` requires `DELETE`; every other ability requires `POST`. A wrong method returns 405 `rest_ability_invalid_method` (observed). Input goes in the `input` query parameter for `GET` and `DELETE`, and in the JSON body as `{ "input": ... }` for `POST` (`get_input_from_request()`, lines 214-223). Input is coerced to the ability's input schema only when it already validates (lines 241-302). Consequence for the SPEC: an ability that is not read-only and not destructive-idempotent must be called with POST, so `reschedule` and `create-planned-draft` need annotations that do not set `readonly`, and `reschedule` must not carry both `destructive` and `idempotent`.
3. Responses (observed with `rest_do_request`): success is HTTP 200 with the ability's output as the body (the output value itself, no envelope); permission failure for an anonymous user is 401 `rest_ability_cannot_execute` ("Sorry, you are not allowed to execute this ability."), and a logged-in user without the capability gets `rest_authorization_required_code()` (403); invalid input is 400 `ability_invalid_input` with the schema reason in `message`; unknown or non-REST ability is 404 `rest_ability_not_found`; wrong method is 405. A `WP_Error` returned by the ability's `execute_callback` is returned as is, so its own `data.status` is the HTTP status.
4. REST exposure flag: `meta.show_in_rest` (bool). Default: the value of `meta.public` when that is set, otherwise `false` (`abilities-api/class-wp-ability.php:370`, constants at lines 24 and 32). `check_ability_permissions()` returns 404 for an ability whose `show_in_rest` is false (run controller, line ~139). Consequence: Masthead must set `show_in_rest => true` on every ability the client runs, including `masthead/share-post` when `abilities.expose_share` is false, because the admin UI runs it; `meta.public` is a separate flag and can stay false there.
5. MCP flag: core defines `meta.public` (bool, default false, "meant to be available to clients", `class-wp-ability.php:171-174`). Whether the MCP Adapter plugin reads `meta.public` or an adapter-specific key is UNCONFIRMED (the adapter is not installed in wp-env). Fallback: set `meta.public` as the SPEC says, and treat the adapter's own key as an open risk.
6. Registration. Categories: `wp_register_ability_category( string $slug, array $args )` on the action `wp_abilities_api_categories_init` (`abilities-api.php:613-645`). Abilities: `wp_register_ability( string $name, array $args )` on the action `wp_abilities_api_init` (`abilities-api.php:290`); `$args` must contain a `category` string (`abilities-api.php:297`); `meta` holds `annotations` (`readonly`, `destructive`, `idempotent`, each `bool|null`), `show_in_rest`, `public`. Both registries fire their `*_init` action lazily on first access (`class-wp-abilities-registry.php:313`, `class-wp-ability-categories-registry.php:229`). Helpers: `wp_has_ability()`, `wp_get_ability()`, `wp_get_abilities()`, `wp_unregister_ability()`.
7. JavaScript client. Core ships `@wordpress/abilities` only as a script module (`js/dist/script-modules/abilities/index.min.js`, exports `executeAbility`, `getAbilities`, `getAbility`, `registerAbility` and others), not as a classic script. A classic script built by `wp-scripts` cannot depend on it by handle. `wp.apiFetch` is available (`wp-api-fetch`) and core installs the nonce middleware for it (`script-loader.php:368`), so cookie-authenticated `apiFetch( { path: '/wp-abilities/v1/abilities/<name>/run', method, ... } )` works with no extra code.
8. Connectors admin screen: `wp-admin/options-connectors.php`, registered at `wp-admin/menu.php:407` as the `Connectors` item under Settings (`options-general.php`), capability `manage_options`. URL: `options-connectors.php` relative to `wp-admin/`.

## Decision

Decision: core-endpoint

## Open risk

- The MCP Adapter's exposure key is UNCONFIRMED (fact 5); `meta.public` is used as the SPEC says.
- `client/data/useAbility` must choose the HTTP method from the ability's annotations (GET with `input` as a query parameter when read-only; POST with a JSON body otherwise). Nested objects in a GET `input` query parameter rely on PHP bracket syntax from `apiFetch`'s `addQueryArgs`; abilities that take nested input should avoid being read-only unless that is tested in e2e.
- `masthead/share-post` has `meta.public` false unless `abilities.expose_share`, but still needs `show_in_rest` true for the admin UI.
