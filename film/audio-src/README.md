# STARMAH / ستاره ماه: promo audio (55.000 s)

Everything here is synthesized by the scripts in this folder: score, sound design, mix and master.
No music or SFX samples are used anywhere. The only external asset is the Piper TTS voice
`fa_IR-amir-medium`, which reads the narration.

## Re-run

```bash
PY=/tmp/claude-0/-home-user/d9b15659-30bb-5d97-b55e-4e81155c08ab/scratchpad/tts/.venv/bin/python   # numpy, scipy, soundfile, piper
$PY make_audio.py              # music + sfx + VO processing + mix/master + m4a + cues.json   (about 70 s)
$PY make_audio.py --revoice    # also re-run Piper for every line first (takes are random, see below)
$PY qa.py                      # per-second loudness of each stem, peaks, DC, click scan
./vo_transcribe.sh 1 2 3       # Whisper large-v3 check of the chosen takes -> vo_raw/transcripts/
python3 vo_check.py            # compare the transcripts with the script
```

| file | role |
|---|---|
| `make_audio.py` | entry point: renders the stems, processes the VO, ducks, masters, exports, writes `cues.json` |
| `dsp.py` | DSP toolkit: filters/EQ, band-limited wavetables, stereo convolution reverb (synthetic decaying-noise IR, 5-band frequency-dependent decay), compressor, 4x-oversampled look-ahead true-peak limiter |
| `instruments.py` | synth instruments: FM celesta, music box (comb-tine modes), glockenspiel, Risset-style bells (major-third partial set so they sit in D), marimba, additive piano (inharmonic partials, 2 detuned strings, two-stage decay, felt hammer), Karplus-Strong pizzicato and harp (fractional-delay allpass tuning, within ±1 cent), detuned vibrato string/pad/brass ensemble, kick, toms, taiko, shaker, rim, snare, cymbal swell, crash |
| `music.py` | the score (`python music.py --analyse` prints each instrument's level per scene) |
| `sfx.py` | sound design. Each event is logged with its sync time |
| `vo_lines.py` | narration script: display text, the spelling actually sent to Piper, start time and deadline |
| `vo_synth.py` | Piper rendering. Picks the slowest take that fits each line's window |
| `vo_pick.py` | pronunciation search: voices respelling variants, transcribes each with Whisper and keeps the closest take |
| `vo_raw/` | the chosen raw takes (`L1..L7.wav`, 22.05 kHz), `takes.json`, `choices.json`, Whisper transcripts, `pick.log` |
| `spectrogram.png` | spectrogram of `mix.wav` |

## Music

- **Key and tempo:** D major. Scene 1 sits on B minor (Bm(add9) → Gmaj7 → Em9 swell → Asus4 → A → D).
  - Scene 1 (0–6 s) is in free time.
  - From 6.0 s the tempo is **120 BPM** (beat 0.5 s) instead of the ~100 BPM in the brief. At 120 BPM, with two 2/4 bars (12–13 s and 43–44 s), every scene boundary (13/21/29/37/44 s), the countdown (31–35 s, every second beat) and the hits at 6.0, 35.0 and 48.0 land exactly on the grid. 100 BPM could not do that.
- **Star theme:** a 4-bar motif over D–Bm–G–A, F#5 A5 D6 C#6 B5 | A5 F#5 D5 B5 … The answer phrase runs over G–A–F#m–Bm. The motif appears:
  - as music-box hints in scene 1;
  - on celesta + music box in scene 2;
  - on legato strings with a harp arpeggio in scene 3;
  - as a pizzicato game motif in scene 4;
  - as a triumphant head at the 35.0 burst;
  - in augmentation on piano in scene 6;
  - fully at the 48.0 climax (strings in octaves, celesta, glockenspiel). The climax cadence is G → Asus4 → A → D(add9) at 51.0, and that chord rings out to silence at about 54.7 s.
- **Scene arc:**
  - Scene 1: drone, pads and dust-like glockenspiel particles.
  - Scene 2: theme with pizzicato bass and off-beat plucks.
  - Scene 3: soft kick, shaker and tom fills on the island changes.
  - Scene 4: playful kick, rim, shaker and marimba.
  - Scene 5: taiko and staccato strings. During the countdown it drops to an A pedal pulse with rising violins and a snare roll, then a tutti D burst with crash at 35.0.
  - Scene 6: piano arpeggios with strings and a cello line.
  - Scene 7 build (44–48): filter-opening string crescendo, accelerating glockenspiel shimmer, accelerating taiko/tom roll and a cymbal swell into the 48.0 tutti, plus the SFX bell and boom.
- A per-scene gain map (`SECTION_GAIN_DB` in `music.py`) shapes the dynamics. Each ramp ends before its boundary, so the hits land at full level.

## Narration

Each line is voiced separately with Piper `fa_IR-amir-medium` (noise 0.6, noise-w 0.7).
- **Timing:**
  - Lines are split at «…» into separate Piper calls with 0.3–0.46 s of real silence between them. Line 6 is the exception: it is one call with «؛», which makes Piper pause by itself (measured 0.39 s).
  - `vo_synth.py` steps the length-scale down from a per-line maximum until a take fits the window.
  - The speech onset is placed exactly on the cue time.
- **Processing:**
  - EQ: high-pass 80 Hz (4th order), +1.5 dB at 180 Hz (warmth), −1.5 dB at 420 Hz, +2.5 dB at 3 kHz (presence), −2 dB shelf at 7.5 kHz.
  - Dynamics: split-band de-esser (5–10 kHz), 2.6:1 compressor plus a peak catcher, per-line loudness matching, look-ahead peak limiting.
  - Space: 0.35 s room reverb at 7 % wet.
  - Format: resampled to 48 kHz and centred.
- **Ducking:** music only (SFX stay as they are). −8 dB with ~200 ms attack and ~300 ms release, a 250 ms hold so it does not pump between words, and the duck starts 60 ms early.

### Final VO timing and Whisper large-v3 (fa) transcripts of the chosen takes

| # | start | end | deadline | length-scale | Whisper transcript (sim.*) |
|---|---|---|---|---|---|
| 1 | 0.500 | 4.978 | 5.9 | 0.98 | تا حالا فکر کردی یه سؤال ساده میتونه در یه دنیا شگفت انگیز رو باز کنه. (0.99) |
| 2 | 8.200 | 12.208 | 12.9 | 0.81 | خوش اومدی به ستاره ما. جایی که یادگیری یه ماجراجوی تازه است. (0.97) |
| 3 | 13.600 | 19.449 | 20.8 | 1.00 | از کشف رازای علوم و دنیا کلمات تا الگوهای ریاضی و بازی های حیجان انگیز (0.97) |
| 4 | 21.800 | 26.513 | 28.8 | 1.04 | اینجا هر بازی فرصتی برای فکر کردن، تجربه کردن و کشف کردن. (0.99) |
| 5 | 29.600 | 35.847 | 36.8 | 1.00 | مسابقه های زنده، چالش های جذاب و کلی حیجان تا یاد گرفتن شیرین تر بشن. (0.96) |
| 6 | 37.400 | 43.378 | 43.9 | 0.95 | چون برای ما فقط جواب درست مهم نیست تلاش کردن، پیشرفت و لذت یاد گرفتنه که عرضش داره (0.97) |
| 7 | 48.600 | 52.590 | 52.8 | 0.78 | ستاره هما جایی که هر کودک میتونه ستاره یادگیری خودش باشد (0.95) |

\* Character similarity to the script, ignoring spaces, ZWNJ, punctuation and diacritics.
- **Remaining differences are spelling or homophones, not missing words:**
  - ماه→ما, ارزش→عرضش and هیجان→حیجان sound the same.
  - Whisper writes colloquial «بشه/کردنه» in formal form, and once as «بشن».
  - In line 7 the brand reads as «ستاره هما»: the h of ماه gets attached across the word boundary. The words are all there.
- **Respellings that fixed real errors:**
  - «شِگِفت‌انگیز»: was heard as «شیفتنگیز».
  - «رازای عُلوم» and «اُلگوهای»: were heard as «کشاراس های عروم» and «علبوهای».
  - «هیجان انگیز» written as two words.
  - «کَشف کَردَنه»: the f was dropped.
  - «جایی کِه»: was heard as «جک».
  - Line 6 voiced in one call with «؛»: when split, «تلاش» kept coming out as «پرش/ترش».
  - More pre-roll in the trim: it had been clipping the «ت» of «تا» at the start of a segment.

  Each variant was voiced and transcribed, and the closest take kept (`vo_raw/pick.log`).
- **Check on the finished mix:** Whisper was also run on the final `mix.wav` (with music and SFX playing). It recognised every line, with the same transcripts as above. That includes line 5 over the 35.0 hit and line 7 over the climax.

## SFX

There are 49 events, all listed in `../assets/audio/cues.json` with name and time. For sounds with length, `time` is the sync or peak point, and `start`/`end` are given too.
- **Taps and chimes:** UI taps, wrong answer (soft two-note marimba, A4→F#4, not a buzzer), correct chimes, progress ticks.
- **Countdown:** soft A5 beeps at 31/32/33/34 s and a D6+A6 final beep at 35.0 s.
- **Crowd cheer:** a swarm of synthetic hand-clap grains plus formant-filtered noise. No human voices.
- The leap is a springy pitch glide plus celesta. No laugh.

## Master

- **Gain staging:** stems are loudness-staged (music −18.5, SFX −23.5, VO −15.5 LUFS before master gain), music is ducked under the VO, then glue compression (1.6:1).
- **Loudness:** iterative gain to −14 LUFS into the 4x-oversampled true-peak limiter (ceiling −1.3 dBTP). Maximum gain reduction is about 2.4 dB.
- **Measured with ffmpeg ebur128:**
  - `mix.wav`: −14.0 LUFS integrated, −1.3 dBTP, LRA 3.4 LU.
  - `mix.m4a` (AAC 256k): −14.1 LUFS, −1.3 dBTP.
- **Other checks:**
  - No clipping, DC ≤ 3e-5.
  - The music never drops below −60 dB between 0.14 s and 54.55 s.
  - The last 0.3 s is below −84 dBFS.
  - The click scan is clean on the music stem. The only flags are inside the confetti/applause texture (intended grains) and one glottal voice onset at 13.6 s.
  - The spectrogram shows no aliasing. The very faint lines near 21–23 kHz (at about −100 dB) are resampling residue of the 22 kHz TTS.

## Limitations

- **Voice:** the narrator is a TTS model (Piper, `fa_IR-amir-medium`), not a human actor.
  - Intonation is serviceable rather than truly expressive, and some colloquial words are slightly clipped.
  - Lines 2 and 7 had to be voiced fast (length-scale 0.81 and 0.78) to fit their windows.
  - A human recording would be noticeably better for the final release.
- **Randomness:** Piper/VITS durations and pronunciations change from take to take. The chosen takes are cached in `vo_raw/` so a plain `make_audio.py` run reproduces the same mix. `--revoice` produces different takes, which then need the Whisper check again.
- **Stems:** they are written at the gain they have in the mix, with no limiting, and `music.wav` is the un-ducked score. The mix also has the ducking, bus compression and limiter applied, so the three stems summed are close to, but not sample-identical with, `mix.wav`. The music stem was also scaled down 1.3 dB at export to stay below 0 dBFS.
- **Tempo:** 120 BPM rather than ~100 BPM, for the grid reasons above.
- **No listening test:** the music and SFX were checked by measurement only: levels per scene and instrument, chroma per bar to confirm the harmony, spectrogram, and the click scan. They have not been listened to. A listening pass to fine-tune the balance is still recommended.
