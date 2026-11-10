# Changelog

All notable changes to Masthead are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-11-09

### Added

- Posts > Calendar with month, week and list views, category colors, a Draft tray and drag or keyboard rescheduling that never changes a post's status.
- A weekly rhythm (lanes) editable under Settings > Calendar, and series cadence per series with an optional lane exception.
- The Desk: suggestions for empty days (matching drafts, next series part, posts worth sharing again, quiet categories), each with a reason, plus the Day plan to create a dated draft with an outline.
- A nightly, read-only swap check (lane mismatch, collision, cadence break, better candidate) with a Review or Fits verdict.
- Jetpack Stats and Jetpack Social integration, with an honest unavailable state when Jetpack is not connected.
- AI features behind a monthly request cap: post ideas, outlines, share copy, and Ask the Desk series proposals whose dates Masthead computes. All AI output is validated against a JSON schema before use; nothing is written or shared without a click.
- WordPress Abilities under `masthead/*` (calendar, planning, stats, sharing, suggestions, swap check, AI) and WP-CLI commands (`seed`, `plan`, `suggest`, `swap-check`, `ai:status`, `stats:probe`, `social:probe`).
- Release zip with a SHA-256 checksum, built by the release workflow from a `v*` tag.
