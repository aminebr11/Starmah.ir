# Hyperframes Composition Brief: کلاسِ آنلاینِ ستاره ماه (kids' tutorial)

## Output
- Composition: `brag-output/composition/` · render `brag-output/brag.mp4`
- Vertical 1080×1920, 30 fps, ~90.8 s (voice-led; user asked for a complete tutorial)

## Source material
- `resources/js/Pages/Online/Room.jsx`, `resources/js/online/parts.jsx`, `resources/css/online.css`
  (class top bar gradient #2e1a7a→#6d28d9, ground #f4f1ff, ink #231a45, accent #7c3aed, green #22c55e,
  bottom-bar buttons #f1edfb with emoji + label, lobby card on a night-sky purple with star dots)
- Mascot «ماهو»: `resources/js/Components/Mascot.jsx` (SVG reused as-is, moods driven by the timeline)
- Font: Vazirmatn (site font, `public/fonts/vazirmatn`), emblem `public/brand/hero-emblem-560.webp`
- Verbatim copy: «ورود به کلاس», «کلاسِ آنلاین», «دست بالا», «میکروفون», «دوربین», «واکنش»,
  «گفت‌وگو», «آزمایشِ اتصال», «فیلمِ کلاس‌های گذشته», «کلاس شروع شده! بدو بیا 🎉», «🎧 هدفون بگذار تا صدا بهتر باشد.»

## Creative direction
- Tone: default (playful, warm) → kid-friendly guide; RTL Persian everywhere.
- The first rule (VPN off) is the hook and is repeated in scenes 2, 11 and 12.
- Avoid: generic glow/particles, bullet slides, text moving while read, click rings.

## Storyboard
See `brag-plan.md` (12 scenes, start times and narration).

## Audio
- Voice: 12 clips `assets/voice/s01..s12.wav` (Piper fa_IR-amir, loudness-normalised −16 LUFS).
- Music: `assets/music/bed.mp3` at ~0.13 under voice, fade out at the end.
- SFX on taps / toggle / stars / chat / outro only (`assets/sfx/`).
