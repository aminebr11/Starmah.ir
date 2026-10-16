"""Pronunciation search: for a line, try respelling variants, transcribe each fitted take with
Whisper large-v3 and keep the take whose transcript is closest to the script.
    python vo_pick.py <line id>      (variants are defined in VARIANTS below)"""
import os, sys, json, shutil, subprocess, difflib, copy
HERE = os.path.dirname(os.path.abspath(__file__)); sys.path.insert(0, HERE)
import vo_synth
from vo_lines import LINES
from vo_check import words, norm

HF = os.environ.get("HF_DIR", "/tmp/claude-0/-home-user/d9b15659-30bb-5d97-b55e-4e81155c08ab/scratchpad/hf")
CAND = os.path.join(HERE, "vo_raw", "candidates")

VARIANTS = {
    2: [dict(segs=["خوش اومدی به سِتارِه ماه.", "جایی کِه یادگیری، یه ماجَراجویی تازه‌ست!"]),
        dict(segs=["خوش اومدی به سِتارِه ماه.", "جایی که یادگیری یه ماجراجویی تازه‌ست!"])],
    3: [dict(segs=["از کشفِ رازهای عُلوم و دنیای کلمات، تا اُلگوهای ریاضی و بازی‌های هیجان انگیز!"]),
        dict(segs=["از کَشفِ رازای عُلوم و دنیای کلمات، تا اُلگوهای ریاضی و بازی‌های هیجان انگیز!"]),
        dict(segs=["از کشف رازهای علوم، و دنیای کلمات، تا اُلگوهای ریاضی و بازی‌های هیجان انگیز!"])],
    5: [dict(segs=["مسابقه‌های زنده، چالش‌های جذاب و کلی هیجان. تا یاد گرفتن، شیرین‌تر بشه!"], pause=0.0, ss=0.38),
        dict(segs=["مسابقه‌های زنده، چالش‌های جذاب و کلی هیجان.", "تا یادْ گرفتن، شیرین‌تر بِشه!"]),
        dict(segs=["مسابقه‌های زنده، چالش‌های جذاب و کلی هیجان؛ تا یاد گرفتن، شیرین‌تر بشه!"], pause=0.0)],
    6: [dict(segs=["چون برای ما، فقط جواب درست مهم نیست. تلاش کردن، پیشرفت و لذت یاد گرفتنه که ارزش داره."], pause=0.0,
             ss=0.3),
        dict(segs=["چون برای ما، فقط جواب درست مهم نیست.", "تَلاش کَردن، پیشرَفت و لذت یاد گرفتنه که ارزش داره."]),
        dict(segs=["چون برای ما فقط جواب درست مهم نیست؛ تلاش کردن، پیشرفت و لذت یاد گرفتنه که ارزش داره."], pause=0.0)],
    7: [dict(segs=["ستاره ماه.", "جایی که هر کودک می‌تونه ستاره‌یِ یادگیری خودش باشه!"])],
}


def transcribe(wav):
    d = os.path.splitext(wav)[0] + "_tr"
    shutil.rmtree(d, ignore_errors=True); os.makedirs(d)
    w16 = os.path.join(d, "in.wav")
    subprocess.run(["ffmpeg", "-v", "error", "-y", "-i", wav, "-ar", "16000", "-ac", "1", w16], check=True)
    r = subprocess.run(["npx", "--no-install", "hyperframes", "transcribe", w16, "-d", os.path.join(d, "out"), "-m",
                        "large-v3", "-l", "fa", "--engine", "whisper", "--json", "--timeout", "900000"],
                       cwd=HF, capture_output=True, text=True)
    p = os.path.join(d, "tr.json")
    open(p, "w").write(r.stderr + "\n" + r.stdout)
    return p


def tr_words(p):
    """words from the CLI's transcript.json (falls back to the JSON-lines log)."""
    tj = os.path.join(os.path.dirname(p), "out", "transcript.json")
    if os.path.exists(tj):
        d = json.load(open(tj))
        return [w["text"] for w in (d if isinstance(d, list) else d.get("words", []))]
    return words(p)


def pick(lid):
    os.makedirs(CAND, exist_ok=True)
    base = next(L for L in LINES if L["id"] == lid)
    results = []
    cur = os.path.join(HERE, "vo_raw", "transcripts", f"L{lid}.json")
    if os.path.exists(cur):
        w = " ".join(words(cur))
        results.append((difflib.SequenceMatcher(None, norm(base["text"]), norm(w)).ratio(), "current", None, w, cur))
    for vi, var in enumerate(VARIANTS.get(lid, [])):
        L = copy.deepcopy(base); L.update(var); L["takes"] = 2
        wav = os.path.join(CAND, f"L{lid}_v{vi}.wav")
        meta = wav + ".json"
        if os.path.exists(meta) and os.path.exists(os.path.join(CAND, f"L{lid}_v{vi}_tr", "out", "transcript.json")):
            info = json.load(open(meta)); p = os.path.join(CAND, f"L{lid}_v{vi}_tr", "tr.json")
        else:
            info = vo_synth.do_line(L, wav)
            json.dump(info, open(meta, "w"))
            p = transcribe(wav)
        w = " ".join(tr_words(p))
        sim = difflib.SequenceMatcher(None, norm(base["text"]), norm(w)).ratio()
        results.append((sim, f"v{vi}", dict(var=var, **info), w, p))
        print(f"L{lid} v{vi} sim={sim:.3f} ls={info['length_scale']} dur={info['dur']}  heard: {w}", flush=True)
    best = max(results, key=lambda r: r[0])
    print(f"L{lid} BEST {best[1]} sim={best[0]:.3f}: {best[3]}", flush=True)
    if best[1] != "current":
        shutil.copy(os.path.join(CAND, f"L{lid}_{best[1]}.wav"), os.path.join(HERE, "vo_raw", f"L{lid}.wav"))
        tj = os.path.join(os.path.dirname(best[4]), "out", "transcript.json")
        json.dump(dict(type="words", words=json.load(open(tj))), open(cur, "w"), ensure_ascii=False)
        ch = os.path.join(HERE, "vo_raw", "choices.json")
        d = json.load(open(ch)) if os.path.exists(ch) else {}
        d[str(lid)] = best[2]
        json.dump(d, open(ch, "w"), ensure_ascii=False, indent=1)


if __name__ == "__main__":
    for a in sys.argv[1:]:
        pick(int(a))
