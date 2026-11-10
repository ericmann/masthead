=== Masthead ===
Tags: editorial calendar, content planning, scheduling, jetpack, ai
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An editorial calendar with a weekly rhythm, suggestions for empty days, a nightly swap check and re-sharing through Jetpack Social.

== Description ==

Masthead puts your publishing plan on one screen. Under Posts > Calendar you see published, scheduled and dated draft posts by month, week or list, colored by category, with a tray of drafts that have no date yet.

* **Weekly rhythm.** Give each weekday a lane: the categories that belong on that day and a default publishing time.
* **Drag to reschedule.** Move a scheduled post or dated draft to another day, or use the keyboard-friendly "Move to" date picker. Masthead never changes a post's status unless you ask it to.
* **The Desk.** Select an empty day and the Desk suggests what could go there: a draft that fits the lane, the next part of a series, a published post worth sharing again, or a category that has gone quiet. Every suggestion says why.
* **Swap check.** A nightly read-only check flags scheduled posts that sit in the wrong lane, collide with a similar post, break a series cadence, or could trade places with a better fit.
* **Series.** Give a series a cadence ("every 4 weeks") and the Desk knows when the next part is due.
* **Jetpack Stats and Social.** With Jetpack connected, traffic numbers (always labeled with their source) inform the Desk and the swap check, and you can share a published post from the calendar. Without Jetpack the calendar, lanes and series still work, and no number is ever invented.
* **AI, only when asked.** With an AI provider configured, the Desk can suggest post ideas for an empty day, draft an outline, draft share copy and propose a multi-part series whose dates Masthead chooses. The AI writes titles, angles, outlines and share copy only, never post bodies, and states no fact about traffic, dates or history. Nothing is created or shared without a click.
* **Abilities.** Every action is also a WordPress Ability under `masthead/*`, so WP-CLI, other plugins and MCP clients use the same code as the screen.

Masthead stores no API key. The AI provider and its key are configured in WordPress under Settings > Connectors.

== Installation ==

1. Download the release zip (`masthead.zip`) and check it against `masthead.zip.sha256`.
2. In the WordPress admin choose Plugins > Add New > Upload Plugin, upload the zip and activate it.
3. Open Posts > Calendar. Adjust the weekly rhythm under Settings > Calendar.
4. Optional: install and connect Jetpack for traffic numbers and sharing.
5. Optional: configure an AI provider under Settings > Connectors for ideas, outlines, share copy and series proposals. Anyone who can publish posts can use them (see the FAQ).

Masthead needs WordPress 7.1 or later and PHP 8.3 or later.

== Frequently Asked Questions ==

= Does it work without Jetpack? =

Yes. The calendar, weekly rhythm, series, drag to reschedule and the swap check all work. Traffic numbers and sharing are shown as unavailable, with the reason, until Jetpack is connected.

= Does it work without an AI provider? =

Yes. The Desk still suggests drafts, the next series part, posts worth sharing again and quiet categories. The AI buttons appear only when a provider supports text generation.

= Who can use the AI features? =

People who have both `edit_posts` and the `prompt_ai` capability. WordPress 7.1 does not assign `prompt_ai` to any role (this was checked on 7.1.4), so Masthead grants it to anyone who has `publish_posts` (administrators, editors and authors) as long as nothing else has decided it. If a role, a user or another plugin grants or denies `prompt_ai` explicitly, that decision wins. A developer can change the capability Masthead checks with the `masthead_config` filter (key `ai.fallback_capability`).

= Does the AI write my posts? =

No. It suggests titles and one-sentence angles, short outlines, share messages and series proposals. You edit and accept everything, and post bodies are never written. Only titles and excerpts of your posts are sent to the provider, never post content.

= Is there a limit on AI requests? =

Yes. A monthly request cap (300 by default) is enforced before every AI call and can be changed under Settings > Calendar. A cap of 0 turns the Desk's AI off.

= Where is the data kept? =

In your own database: the weekly rhythm and settings as options, plans and outlines as post meta, series cadence as term meta. Nothing is sent to a Masthead service.

= Can MCP clients use it? =

Calendar abilities can be exposed to MCP clients from Settings > Calendar. Sharing is a separate switch and is off by default.

== Changelog ==

= 0.1.0 =
* First release: calendar (month, week, list), weekly rhythm, draft tray, drag and keyboard rescheduling, series cadence.
* The Desk: suggestions for empty days, each with its reason; Day plan with Create draft.
* Nightly swap check with a Review/Fits verdict and options.
* Jetpack Stats and Social integration with honest "unavailable" states.
* AI ideas, outlines, share copy and series proposals, behind a monthly request cap.
* Abilities under `masthead/*` and WP-CLI commands.
