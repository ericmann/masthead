# P5-01 Spike: WordPress AI Client surface and model metadata

## Question

What is the exact call chain for a JSON-constrained text generation with a system instruction, model preference, temperature and token limit, how are failures reported, and does the result expose the model id used?

## Method

Read the WordPress 7.1.4 source in wp-env (`npx wp-env run cli`, paths relative to `/var/www/html/wp-includes`) and ran read-only probes with `wp eval-file`: the support check, a generation call on a site with no provider, and the capability of every default role. No provider is configured in wp-env, so nothing was generated and no network call was made.

## Facts

1. Entry point. `ai-client.php:60`: `function wp_ai_client_prompt( $prompt = null ): WP_AI_Client_Prompt_Builder` (string, message, parts or null). It returns `new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry(), $prompt )`. `wp_supports_ai()` (same file, filter `wp_supports_ai`, default true) turns every prompt off when false.
2. Builder. `ai-client/class-wp-ai-client-prompt-builder.php` wraps `php-ai-client/src/Builders/PromptBuilder.php`. Its `__call()` (line 293) maps snake_case names onto the wrapped camelCase methods, so the names are: `using_system_instruction( string )` (PromptBuilder line 336), `using_model_preference( ...$models )` (line 256), `using_temperature( float )` (line 362), `using_max_tokens( int )` (line 349), `as_json_response( ?array $schema )` (line 607), `is_supported_for_text_generation(): bool` (line 727), `generate_text(): string|WP_Error` and `generate_text_result(): GenerativeAiResult|WP_Error` (docblock lines 90-97, wrapped `generateText` at line 972). Setters return the builder, so they chain.
3. Several model ids. `using_model_preference( ...$preferredModels )` is variadic: each argument is a model id string, a model instance, or a `[ provider id, model id ]` tuple; at least one is required (`InvalidArgumentException` otherwise, turned into a `WP_Error` at generation). So an `ai.models.<tier>` list is passed with `using_model_preference( ...$ids )`. The provider's default is used when none match (PromptBuilder docblock).
4. Support check. `is_supported_for_text_generation()` makes no network call. Observed in wp-env with no provider: `false`. When AI is disabled by `wp_supports_ai` or by the filter `wp_ai_client_prevent_prompt` it also returns `false` (`__call`, lines 308-330).
5. Failure reporting. Setters never throw to the caller: an exception is stored and returned as a `WP_Error` by the first generating method (`__call`, lines 293-368; `exception_to_wp_error()`, line 372 onward). Codes and HTTP statuses: `prompt_prevented` 503 (AI off or prevented by filter), `prompt_network_error` 503, `prompt_client_error` (the HTTP status of the upstream client error, else 400), `prompt_upstream_server_error` (upstream status, else 500), `prompt_token_limit_reached` 400, `prompt_invalid_argument` 400, `prompt_builder_error` 500; each carries `status` and `exception_class` in its data. Observed: with no provider, `generate_text()` returns `prompt_invalid_argument` "No models found that support text_generation for this prompt."
6. Model metadata. `generate_text_result()` returns a `GenerativeAiResult` (`php-ai-client/src/Results/DTO/GenerativeAiResult.php`) with `getModelMetadata(): ModelMetadata` (line 142), `getProviderMetadata()` (line 131), `getTokenUsage()` (line 120) and `toText()` (line 187). `ModelMetadata::getId()` (line 82) is the model id and `getName()` the display name. `generate_text()` returns only the string, so a caller that wants the model id must use `generate_text_result()` and read `->toText()` and `->getModelMetadata()->getId()`.
7. Capability. The string `prompt_ai` appears nowhere under `wp-includes` or `wp-admin`, and no default role holds it: observed `has_cap( 'prompt_ai' )` is `no` for administrator, editor, author, contributor and subscriber, and `current_user_can( 'prompt_ai' )` is `false` for user 1. So on a stock 7.1.4 site nobody has it unless a plugin or the site owner grants it.

## Decision

Decision: model-id-exposed

## Open risk

- `prompt_ai` is not defined or granted by core 7.1.4 (fact 7). SPEC requires `edit_posts` plus `prompt_ai` for the AI abilities, so on a stock site every AI ability is denied. Masthead must either leave granting it to the site owner (document it in the readme and the Settings AI section) or map it in a later task; the e2e fake (P5-03) grants it through `user_has_cap`. This is recorded for the reviewer and in the handoff.
- Nothing was generated, so the real shape of a JSON-constrained response (and whether `as_json_response` is honoured by every provider) is UNCONFIRMED. `WpAiClient` must decode and validate the returned text itself and treat anything else as an invalid item.
- The behaviour of a model preference that matches no model on a configured provider is UNCONFIRMED; PromptBuilder documents falling back to the provider's default.
