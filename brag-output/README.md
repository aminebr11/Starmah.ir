# ویدیوی آموزشیِ «کلاسِ آنلاین» برای بچه‌ها

موشن‌گرافیکِ عمودی (۱۰۸۰×۱۹۲۰، حدودِ ۹۲ ثانیه) با نریشنِ فارسی و «ماهو»؛ ساخته‌شده با HyperFrames.

- `brag-plan.md` — داستان، صحنه‌ها و متنِ نریشن
- `build.py` — همه‌ی صحنه‌ها، زمان‌بندی و صداها را از یک‌جا می‌سازد (`python3 build.py`)
- `composition/` — پروژه‌ی HyperFrames (`npx hyperframes check`، `npx hyperframes render --quality delivery -o ../brag.mp4`)

## صداها (در مخزن نیستند)

- موسیقی: `cp ../.claude/skills/brag/assets/music/happy-beats-business-moves-vol-1-by-ende-dot-app.mp3 composition/assets/music/bed.mp3`
- نریشن: مدلِ آزادِ محلیِ Piper، صدای `fa_IR-amir-medium` (Kokoro فارسی ندارد). هر خطِ «Voiceover script» در
  `brag-plan.md` جدا ساخته و بعد یکسان‌سازی می‌شود:

```bash
pip install piper-tts   # و دانلودِ fa_IR-amir-medium.onnx(.json) از huggingface.co/rhasspy/piper-voices
echo "<متنِ خط>" | python -m piper -m fa_IR-amir-medium.onnx --sentence-silence 0.25 -f raw.wav
ffmpeg -i raw.wav -af "silenceremove=start_periods=1:start_threshold=-45dB:start_silence=0.03,areverse,silenceremove=start_periods=1:start_threshold=-45dB:start_silence=0.05,areverse,highpass=f=70,loudnorm=I=-16:TP=-1.5:LRA=7" -ar 48000 -ac 1 composition/assets/voice/s01.wav
```

اگر صدای ضبط‌شده‌ی یک گوینده‌ی واقعی جایگزین شود، طولِ هر فایل را در `VOICE_LEN` و مکث‌ها را در `PAUSES`ِ `build.py` به‌روز کنید.
