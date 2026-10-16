# STARMAH — Where Learning Becomes Magic (فیلم سه‌بعدی معرفی ستاره ماه)

A 55-second cinematic 3D promo, built as code: a time-driven Three.js film (`assets/film.js`,
`assets/film-kit.js`), Persian RTL overlays typeset in Vazirmatn (`assets/overlays.js/.css`),
and an original soundtrack generated programmatically (`audio-src/`, see its README).

## Cuts (`cuts/`)
| Cut | Output | Notes |
|---|---|---|
| `landscape` | 3840×2160, 60 fps, 55 s | cinematic master, no subtitles |
| `vertical` | 1080×1920, 60 fps, 55 s | Instagram Reels, Persian subtitles, UI kept clear of Reels overlays |
| `loop` | 1920×1080, 60 fps, 10 s, silent | seamless website hero loop |

`python3 make_entry.py` regenerates the three entry files from one template.
Render one cut: `cd cuts/<cut> && npx hyperframes render --fps 60 --quality delivery -o ../../renders/<name>.mp4`.

## Renders (`renders/`, large files are git-ignored)
- `starmah-16x9-4k60.mp4` — master (H.264, high bitrate)
- `starmah-16x9-4k60-web-h265.mp4` — 4K web (H.265/hvc1, ~50 MB)
- `starmah-16x9-1080p60-web.mp4` — 1080p web (H.264, ~46 MB)
- `starmah-9x16-reels.mp4` — Reels (cover baked as frame 0)
- `starmah-hero-loop-10s.mp4` — silent loop (committed)
- `starmah-thumbnail-4k.png/.jpg`, `starmah-reels-cover.jpg`

## Honest limitations
- Rendered with software WebGL: the 3D layer is drawn at 1920×1080 and upscaled in the 4K
  master (all text and the logo are native 4K). A GPU machine can raise the internal
  resolution in `make_entry.py` for a sharper 3D layer.
- The Persian narration is a TTS voice (Piper `fa_IR-amir-medium`), checked line by line with
  Whisper. For a broadcast release, record a human narrator over `assets/audio/cues.json`
  timings and remix with `audio-src/make_audio.py`.
