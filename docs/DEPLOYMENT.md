# Deployment

Masthead is built and released as a zip, and the site pins that zip by tag and checksum when it builds its WordPress image. There is no submodule and no subtree. Masthead never stores an API key.

## Release

1. Set the new version everywhere the structural check compares: the `Version:` line in the `masthead.php` header, `Masthead\VERSION` in `src/constants.php`, `version` in `package.json` (and `package-lock.json`; `npm version <version> --no-git-tag-version` updates both), `Stable tag:` in `readme.txt`, and the newest `## [<version>]` heading in `CHANGELOG.md`. `bash scripts/forbidden-patterns.sh` fails when they disagree.
2. Run the full checks (`composer lint`, `composer test:unit`, `npm run lint`, `npm run test:unit`, `npm run test:integration`, `npm run test:e2e`) and `npm run release:pack`. The last one writes `dist/masthead.zip` and `dist/masthead.zip.sha256`, and fails if `readme.txt`, `README.md` or `CHANGELOG.md` is missing.
3. Merge the pull request.
4. Create a signed tag `v<version>` on the merge commit and push it. Never bypass commit or tag signing.
5. The release workflow (`.github/workflows/release.yml`) checks the tag against `package.json` (`node scripts/release/pack.mjs --check-tag`), builds the zip and attaches `masthead.zip` and `masthead.zip.sha256` to the GitHub release.

Check a downloaded release with `sha256sum -c masthead.zip.sha256` (run it in the directory holding both files).

## Pinning into the site image

The site repository builds an immutable WordPress image. Add this fragment to its Dockerfile, with `<owner>` and the plugins directory filled in for your repository and image:

```dockerfile
ARG MASTHEAD_VERSION=0.1.0
ARG MASTHEAD_SHA256=<from dist/masthead.zip.sha256>
RUN curl -fsSL -o /tmp/masthead.zip \
      "https://github.com/<owner>/masthead/releases/download/v${MASTHEAD_VERSION}/masthead.zip" \
 && echo "${MASTHEAD_SHA256}  /tmp/masthead.zip" | sha256sum -c - \
 && unzip -q /tmp/masthead.zip -d "<plugins dir used for ttm-core>" \
 && rm /tmp/masthead.zip
```

Upgrading Masthead is a two-line change in the site repository: the version and the checksum. Your deploy tooling then rolls the new image.

## AI provider

The AI provider is a WordPress.org plugin. Add `ai-provider-for-anthropic` (or `ai-provider-for-openrouter`) to the site's Composer file with the other plugins, and supply the provider's API key to the pod from a Kubernetes Secret, using the environment variable the provider plugin documents. Masthead never sees, reads or stores the key; the WordPress AI Client does. Configure and check the provider under Settings > Connectors.

Two things to know before relying on the AI features:

- Users need the `prompt_ai` capability. WordPress 7.1 does not assign it to any role (checked on 7.1.4), so grant it deliberately, for example in a small site plugin that adds it to the administrator role through the `user_has_cap` filter.
- The default model ids (`ai.models.fast`, `ai.models.strong`) are Anthropic ids. With another provider they will not match and that provider's default model is used.

## Private repository

While the repository is private, the image build needs a read token to download the release asset. Provide it to the build as a build secret, never in the Dockerfile or the image. Making the repository public before the first tag avoids this.

## Jetpack

Traffic numbers need the Jetpack Stats module and sharing needs a Jetpack Social connection. Without them Masthead shows "Unavailable" with the reason and never estimates a number; the calendar, lanes, series and the swap check's non-traffic findings still work.

## After the first deploy

1. `wp masthead stats:probe` and `wp masthead social:probe` print scrubbed payload shapes. Compare them with `docs/spikes/P3-01-jetpack-stats.md` and `docs/spikes/P3-03-jetpack-social.md`, and list any fact still marked `UNCONFIRMED`.
2. `wp masthead ai:status` shows whether a provider supports text generation, the requests left this month, the monthly cap and the configured model lists.
3. Read the files in `docs/tuning/` and decide whether the kept defaults suit the site. The settable keys (Desk, swap check, stats window and AI) are under Settings > Calendar.
4. Walk through `docs/manual-checks.md`.

## Cron

Masthead schedules one event, `masthead_nightly`, at the site-local hour in `cron.nightly_hour`. It only reads and caches (the swap-check findings and Jetpack Stats responses); it never writes a post, shares anything or calls the AI. On sites that disable WP-Cron, trigger `wp cron event run masthead_nightly` from a system scheduler. Deactivating the plugin clears the event.
