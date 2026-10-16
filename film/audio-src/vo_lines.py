# -*- coding: utf-8 -*-
# Narration script.
#   text   : display text (exact script, goes into cues.json)
#   segs   : what is actually sent to Piper; a line is split at "…" into separate synth calls
#            joined by `pause` seconds of silence (the ellipsis becomes a real breath/pause).
#   ls_max : slowest length-scale we allow for this line; the take-picker steps down from here
#            until the whole line fits inside (end_by - start - margin).
LINES = [
    dict(id=1, start=0.5, end_by=5.9, ls_max=0.98, pause=0.0,
         text="تا حالا فکر کردی یه سؤال ساده، می‌تونه درِ یه دنیای شگفت‌انگیز رو باز کنه؟",
         segs=["تا حالا فکر کردی یه سؤال ساده، می‌تونه درِ یه دنیای شِگِفت‌انگیز رو باز کنه؟"]),
    dict(id=2, start=8.2, end_by=12.9, ls_max=0.95, pause=0.25,
         text="خوش اومدی به ستاره ماه… جایی که یادگیری، یه ماجراجویی تازه‌ست!",
         segs=["خوش اومدی به سِتارِه ماه.", "جایی کِه یادگیری، یه ماجَراجویی تازه‌ست!"]),
    dict(id=3, start=13.6, end_by=20.8, ls_max=1.0, pause=0.0,
         text="از کشف رازهای علوم و دنیای کلمات، تا الگوهای ریاضی و بازی‌های هیجان‌انگیز!",
         segs=["از کَشفِ رازای عُلوم و دنیای کلمات، تا اُلگوهای ریاضی و بازی‌های هیجان انگیز!"]),
    dict(id=4, start=21.8, end_by=28.8, ls_max=1.04, pause=0.0,
         text="اینجا هر بازی، فرصتی برای فکر کردن، تجربه کردن و کشف کردنه!",
         segs=["اینجا هر بازی، فرصتی برای فکر کردن، تجربه کردن و کَشف کَردَنه!"]),
    dict(id=5, start=29.6, end_by=36.8, ls_max=1.0, pause=0.30,
         text="مسابقه‌های زنده، چالش‌های جذاب و کلی هیجان… تا یاد گرفتن، شیرین‌تر بشه!",
         segs=["مسابقه‌های زنده، چالش‌های جذاب و کلی هیجان.", "تا یادْ گرفتن، شیرین‌تر بِشه!"]),
    dict(id=6, start=37.4, end_by=43.9, ls_max=0.95, pause=0.0,
         text="چون برای ما، فقط جواب درست مهم نیست… تلاش کردن، پیشرفت و لذت یاد گرفتنه که ارزش داره.",
         segs=["چون برای ما فقط جواب درست مهم نیست؛ تلاش کردن، پیشرفت و لذت یاد گرفتنه که ارزش داره."]),
    dict(id=7, start=48.6, end_by=52.8, ls_max=0.88, pause=0.14, margin=0.06,
         text="ستاره ماه… جایی که هر کودک می‌تونه ستاره‌ی یادگیری خودش باشه!",
         segs=["سِتارِه ماه.", "جایی که هر کودک می‌تونه ستاره‌یِ یادگیری خودش باشه!"]),
]
MARGIN = 0.22
# NOTE: the respellings above (diacritics, colloquial «رازای», «اُلگو», split «هیجان انگیز», «؛» in line 6)
# were chosen by vo_pick.py: each variant was voiced and transcribed with Whisper large-v3 and the
# closest-to-script take was kept (see vo_raw/pick.log, vo_raw/choices.json).   # keep this much air before each line's deadline
