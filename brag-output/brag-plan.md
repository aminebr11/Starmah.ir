# Brag Plan: کلاسِ آنلاینِ ستاره ماه — آموزش برای بچه‌ها

## What is this app?
The online classroom of «ستاره ماه» (Laravel + React, mobile-first): a live class with voice/video,
a smart board with book pages, raised hands, quick quizzes with stars, chat and emoji reactions,
class recordings and a connection check — used by primary-school kids on their phones.

## The angle
Not a launch brag: a kids' how-to the school sends to families. «ماهو» (the site's own mascot, a
little golden moon in a graduation cap) walks a child through one real class on a phone, from the
very first rule — **VPN off** — to the end. Every scene is the real app UI (purple class bar,
white rounded cards, the bottom bar of emoji buttons, the exact Persian labels), acted out with a
finger tapping it.

User overrides of /brag defaults (explicit in the request):
- Length ~91 s, not 15–25 s: "a complete tutorial" covering 10 topics.
- Vertical 1080×1920 (sent to kids' phones).
- Persian narration. Kokoro (the /brag voice engine) has no Persian voice, so narration is generated
  locally with Piper `fa_IR-amir-medium` (open model, no text leaves the machine).

## Hook (first 2–3 seconds)
Frame 0 already shows the rule: a big VPN toggle card «🛡️ فیلترشکن» switched ON (red) with the
headline «⛔ قانونِ اول: فیلترشکن خاموش!». Mahoo flies in waving on «سلام بچه‌ها!», and on the word
«خاموش» flips the switch OFF (green) — click + stamp.

## Storyboard (voice is the clock; times are scene-local)
| # | Start | Dur | Scene | What must be seen |
|---|---|---|---|---|
| 1 | 0.0 | 9.7 | Rule #1 | VPN toggle ON→OFF on «فیلترشکن باید خاموش باشد», stamp «✅ خاموش» |
| 2 | 9.7 | 7.8 | Why | Phone in class: VPN ON → «🔇 صدا قطع شد», frozen picture; toggle OFF → «🟢 وصل» |
| 3 | 17.5 | 7.7 | Enter | Site menu chip «کلاسِ آنلاین» tapped → «کلاس شروع شده! بدو بیا 🎉» card → «ورود 🚀» tapped |
| 4 | 25.2 | 6.2 | Lobby | Lobby card: «🎧 هدفون بگذار…», «🚀 ورود به کلاس» tapped → zoom through into the class |
| 5 | 31.4 | 7.1 | Hand + mic | «✋ دست بالا» tapped → hand pops; «✅ معلم اجازه داد» → «🎤 میکروفون» turns green |
| 6 | 38.5 | 8.0 | Speaking + camera | Camera tiles; the speaker's tile gets the green frame; «📷 دوربین» tapped → own tile |
| 7 | 46.5 | 8.6 | Board | Book page on the board; finger writes; double tap → «🔍 ۲٫۵×» zoom |
| 8 | 55.1 | 6.3 | Quick quiz | «۳ + ۴ = ؟» card, tap «۷» → green, «⭐ +۵ امتیاز» stars burst |
| 9 | 61.4 | 6.5 | Chat | Bubbles arrive, emoji reactions float up, chip «🙂 مؤدب باش» |
| 10 | 67.9 | 6.7 | Recordings | «🎬 فیلمِ کلاس‌های گذشته» cards, tap ▶ |
| 11 | 74.6 | 7.3 | Connection check | «🔍 آزمایشِ اتصال»: «فیلترشکن خاموش ✅», «اینترنت ✅», «صدا ✅», «سرورِ کلاس ✅» |
| 12 | 81.9 | 8.9 | Outro | Mahoo cheering, chips «⛔ فیلترشکن خاموش» «🎧 هدفون» , «بزن بریم کلاس! 🚀», site emblem |

Transitions: the phone stays put from scene 2 to 11 so changes read as the screen changing
(blur swap / whip inside the screen; one zoom-through the «ورود به کلاس» button). The phone rises
in at scene 2 and drops away at scene 12.

## Voiceover script
1. سلام بچه‌ها! من ماهو هستم. قبل از هر کاری، یک قانونِ خیلی مهم: فیلترشکن باید خاموش باشد!
2. اگر فیلترشکن روشن باشد، صدا و تصویرِ کلاس قطع می‌شود. پس همیشه، اول خاموشش کن.
3. حالا وارد سایتِ ستاره ماه شو و «کلاسِ آنلاین» را بزن. وقتی کلاس شروع شد، دکمه‌ی ورود را بزن.
4. هدفونت را بگذار و روی «ورود به کلاس» بزن. آفرین! تو در کلاس هستی.
5. می‌خواهی حرف بزنی؟ دستت را بالا کن. وقتی معلم اجازه داد، میکروفون را روشن کن و صحبت کن.
6. هر کس حرف بزند، دورِ تصویرش سبز می‌شود. اگر معلم گفت، دوربینت را هم روشن کن.
7. اگر معلم اجازه‌ی تخته داد، با انگشتت روی تخته بنویس. برای دیدنِ بهترِ کتاب، دو بار روی صفحه بزن.
8. سؤالِ سریع آمد؟ زود جواب بده! جوابِ درست، ستاره و امتیاز دارد.
9. در گفت‌وگو پیام بنویس و با شکلک‌ها شادی‌ات را نشان بده. یادت نرود، همیشه مؤدب باش.
10. کلاس را از دست دادی؟ فیلمِ کلاس‌های گذشته، در همان صفحه‌ی «کلاسِ آنلاین» است.
11. صدا وصل نشد؟ اول ببین فیلترشکن خاموش باشد. بعد، «آزمایشِ اتصال» را بزن.
12. فیلترشکن خاموش، هدفون روی گوش. بزن بریم کلاس! ستاره ماه منتظرِ توست.

## Audio
- Bed: `happy-beats-business-moves-vol-1` (120 BPM), ducked to ~0.13 under the voice, up to 0.3 on
  the final hold, fade out.
- SFX only on visible actions: soft clicks on finger taps, a switch on the VPN toggle, an error
  buzz on «قطع شد», a glass sparkle on stars, a card slide on chat bubbles, one bell on the outro.
- Music cue guidance: bundled preset (120.19 BPM, beats every ~0.5 s); voice is the clock, so cues
  are ignored except the outro title, which may land on a beat.
- Audio-reactive treatment: none (voice-led tutorial).

## Share copy
بچه‌ها، «ماهو» در ۹۰ ثانیه ورود به کلاسِ آنلاینِ ستاره ماه را یادتان می‌دهد — و قانونِ اول: فیلترشکن خاموش! 🌙
