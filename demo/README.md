# Masthead demo video

A narrated, three-minute product demo of Masthead for the README and the release page. HyperFrames
composes it from high-DPI stills of the plugin running on a fictional demo site; ElevenLabs reads the
script (voice Alice) and makes a quiet music bed (`music_v1`). Nothing in this directory ships in the
plugin: `scripts/release/pack.mjs` packs an allowlist, and `demo/` is not on it.

| File | What it is |
|---|---|
| `site/fixture.json` | The demo site, Northfield Notes: a `wp masthead seed` fixture. Every title and date is invented; The Mantle keeps its name from `docs/fixtures/`. |
| `site/stats.json` | Sample Jetpack Stats payloads keyed to the demo post IDs (local fixture stats only). |
| `site/ai/*.json` | Fixed AI answers (ideas, outline, series proposal, share copy) for the e2e fake AI client, so the recording needs no provider. |
| `site/seed.mjs` | Seeds the demo into the running wp-env (`--restore` puts the e2e defaults back). |
| `capture.mjs` | Walks the story in the browser and saves stills (1600×900 at 2×) plus `manifest.json`, the boxes of every control the video points at, and real `wp masthead plan` / `masthead/get-plan` output. |
| `script.json` | Narration per scene, voice, music prompt and levels. Edit words here. |
| `tts.mjs` | Voice-over per scene (with character timings) and the music bed. |
| `lib/plan.mjs` | The shot list: per scene, which still, where the camera and pointer go, and on which words. |
| `build.mjs` | Downscales the stills, resolves the plan into timed GSAP operations, writes them, the MCP panels, timing and audio into `index.html`. Warns if the camera ever shows past a still's edge, and lines up the music bed's last chord with the last frame. |
| `index.html` | The composition. Title, "honest by design", MCP and end cards are written by hand; the footage scenes are generated between the `footage` markers. |
| `master.mjs` | Masters the render's sound to -16 LUFS (true peak under -1 dBTP) without re-encoding the picture. |
| `assets/fonts/` | Newsreader and IBM Plex Sans/Mono (SIL Open Font License, `OFL-*.txt`). |

Generated and ignored: `node_modules/`, `audio/`, `assets/shots/`, `assets/frames/`, `assets/vendor/`,
`renders/`, `snapshots/`, `.hyperframes/`.

## Rebuild

From the repository root, start wp-env and build the plugin once (`npm run build && npx wp-env start`),
then in `demo/`:

```sh
npm ci
node capture.mjs        # reseeds the demo site, then shoots every still (about a minute)
node tts.mjs            # needs the ElevenLabs key; skip for a silent draft with estimated timing
node build.mjs
npx hyperframes check   # lint, runtime, layout and contrast
npx hyperframes snapshot --at 30,75,137 --describe false   # spot-check frames
npm run render          # renders/masthead-demo-raw.mp4, then master.mjs -> renders/masthead-demo.mp4
```

The render takes about six minutes on eight cores. `renders/masthead-demo.mp4` is the file to publish:
1920×1080, 30 fps, H.264 and AAC, about 3:04.

`tts.mjs` reads the key from `~/.config/news-blog/elevenlabs-key` and sends it only as the
`xi-api-key` header. Changing a scene's words means `node tts.mjs <scene>` then `node tts.mjs music`
(the bed is sized to the narration), then `node build.mjs`.

Afterwards, `node site/seed.mjs --restore` drops the demo's options and names. The e2e suite reseeds
its own posts and clears the demo options before every spec, so `npm run test:e2e` passes either way.

## What is real

Every frame of footage is the plugin as built from this repository, on the demo site, shot by
`capture.mjs`. The pointer, click ripples, outlines and camera moves are drawn by the composition on
top of those stills. Three things are stand-ins, and the video says so with a "Demo site · sample
data" tag: the posts are invented, the traffic numbers are sample Jetpack Stats payloads, and the AI
answers are fixed files. Jetpack Social is not connected in wp-env, so the video shows no share flow
and mentions re-share suggestions only as something the Desk adds with Jetpack connected.
