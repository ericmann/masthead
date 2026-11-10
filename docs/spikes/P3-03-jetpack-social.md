# P3-03 Spike: Jetpack Social routes, payloads and plan gating

## Question

Which internal REST routes, methods and payloads does `JetpackSocial` dispatch with `rest_do_request()` for (a) the connection list, (b) sharing an already published post now with a custom message to chosen connections, (c) scheduling such a share for a datetime, and how does each report "not available on this plan" or "not connected"?

## Method

Read the Jetpack 16.4 source in wp-env (`npx wp-env run cli`), package `jetpack_vendor/automattic/jetpack-publicize/src/` (paths below are relative to it), and listed the routes registered on an unconnected site with `rest_get_server()->get_routes()` as an administrator. Jetpack is not connected in wp-env, so no response was observed; response shapes come from the controllers' schemas and are marked UNCONFIRMED where the schema was not read in full.

## Facts

1. Namespace. The controllers build their namespace from `$base_api_path` and `$version`; the only publicize route registered on the unconnected site is `/wpcom/v2/publicize/connection-test-results`, so the namespace is `wpcom/v2`. The other routes are registered by the same package but did not appear on an unconnected site (UNCONFIRMED whether they register only when connected). The unit under test therefore treats a missing route as "unavailable".
2. (a) Connection list. `rest-api/class-connections-controller.php`: `GET /wpcom/v2/publicize/connections` (`rest_base = 'publicize/connections'`, line 43; route lines 54-61), optional boolean argument `test_connections`. The permission callback is `publicize_permissions_check()` (`rest-api/class-base-controller.php:83-109`): it returns `WP_Error publicize_not_available` (status from `rest_authorization_required_code()`) when the `$publicize` global is missing, true when `$publicize->current_user_can_access_publicize_data()`, else `WP_Error invalid_user_permission_publicize`. On a self-hosted site `get_items()` proxies the request to WordPress.com as the current user (line 339) and passes the result through the filter `jetpack_publicize_connections`; the response is a list of items with `connection_id` (string), `display_name`, `external_handle`, `external_id`, `profile_link`, `profile_picture`, `service_label`, `service_name`, `shared` (bool), `status` and `template`, plus `X-WP-Total` (lines 225-312, 356-366). Mapping to `ShareSource::connections()`: `id` = `(int) connection_id`, `service` = `service_name`, `display_name`.
3. (b) Share now. `rest-api/class-share-post-controller.php`: `POST /wpcom/v2/publicize/share-post/<postId>` (`rest_base = 'publicize/share-post'`, line 40; route line 90-93). Parameters: `message` (string, required), `skipped_connections` (array of connection ids to skip, optional) and `async` (bool, default false). It is an exclusion list: to share to chosen connections `JetpackSocial` passes every other connection id as `skipped_connections`. Permission: `publicize_permissions_check()` (line 112). Errors seen in the handler: `not_found` (404) and `not_published` (400, "Only published posts can be shared"). On a self-hosted site the handler proxies to WordPress.com (`proxy_request_to_wpcom_as_user`, line 126-131). The success body is the result of the WordPress.com side (UNCONFIRMED shape); Masthead maps any 2xx without a `WP_Error` to `status: "shared"`.
4. (c) Schedule a share. `rest-api/class-scheduled-actions-controller.php`: `POST /wpcom/v2/publicize/scheduled-actions` (`rest_base = 'publicize/scheduled-actions'`, line 41; create route lines 66-98) with `post_id` (integer, required), `connection_id` (integer, required, one connection per action), `message` (string) and `timestamp` (integer GMT Unix seconds; `share_date` is the deprecated alias). Also `GET` list (optional `post_id`), `GET/POST/DELETE /scheduled-actions/<action_id>`. Permission for create (`create_item_permissions_check`, line 321): `edit_posts`, `edit_post` on the post, the post must be published (`WP_Error post_not_published`, 400), the connection must exist (`connection_not_found`, 400), and a user without `edit_others_posts` may only schedule to a shared or own connection (`rest_forbidden`, 403). `share_at` therefore issues one request per connection. Success body: the scheduled action object (id, post_id, connection_id, message, timestamp, status; UNCONFIRMED).
5. Capabilities. Connection list and share-now: `current_user_can_access_publicize_data()` on the `$publicize` object (`class-publicize-base.php:1084`); scheduled actions: `edit_posts` plus `edit_post` (above). Masthead's ability requires `publish_posts` on top of these.
6. Plan gating. No plan check appears in the three controllers read: the gating is applied on WordPress.com when the proxied request is made, so a plan or quota failure comes back as the proxied `WP_Error`. The exact error codes are UNCONFIRMED. `JetpackSocial` reports any `WP_Error` from a proxied call as `status: "unavailable"` with the error message as `reason`, and never retries or emulates.
7. Active and connected. Social is available only when the `$publicize` global exists (`class-publicize-base.php` is the object; `publicize_not_available` is raised when it is missing, `rest-api/class-base-controller.php:85`), Jetpack reports a ready connection (`Jetpack::is_connection_ready()`, `class.jetpack.php:1856`) and the connection-list route is registered (`rest_get_server()->get_routes()` contains the route). `available()` is true only when all three hold and the connection list call did not return a `WP_Error`.

## Decision

Decision: share-and-schedule

Both (b) and (c) have routes in the 16.4 source. Availability on a given site and plan is UNCONFIRMED until `social:probe` runs on a connected site, so the adapter reports `unavailable` on any missing route or error and the UI hides the action with the reason.

## social:probe design

`wp masthead social:probe` prints one JSON object and no names, ids, handles or message text:

- `available`: the value of `JetpackSocial::available()`;
- `connections`: the number of connections returned;
- `routes`: `{ "connections": bool, "share_post": bool, "scheduled_actions": bool }`, each true when `rest_get_server()->get_routes()` holds a route whose path starts with `/wpcom/v2/publicize/connections`, `/wpcom/v2/publicize/share-post` and `/wpcom/v2/publicize/scheduled-actions` respectively.

## Open risk

- Route registration on an unconnected site is UNCONFIRMED (fact 1); a connected site may register more or different routes.
- Success payloads for share-now and scheduling (facts 3 and 4) are UNCONFIRMED; the adapter must not read fields it has not validated.
- Plan-gating error codes are UNCONFIRMED (fact 6); every `WP_Error` is treated as unavailable with its message.
- `share-post` proxies to WordPress.com on self-hosted sites, so a share is an outbound call made by Jetpack, never by Masthead (SPEC section 3.1).
