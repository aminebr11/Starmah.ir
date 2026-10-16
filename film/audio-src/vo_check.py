"""Compare Whisper transcripts (vo_raw/transcripts/L*.json) with the script. Prints transcript +
a normalised character-similarity score (punctuation, ZWNJ and spaces ignored)."""
import json, os, re, sys, difflib
HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from vo_lines import LINES

def words(path):
    out = []
    for line in open(path, encoding="utf-8"):
        line = line.strip()
        if not line.startswith("{"): continue
        try: d = json.loads(line)
        except Exception: continue
        if d.get("type") == "words": out += [w["text"] for w in d["words"]]
    return out

def norm(s):
    s = s.replace("‌", "").replace("ي", "ی").replace("ك", "ک").replace("ؤ", "و").replace("ئ", "ی")
    s = re.sub(r"[ً-ْ]", "", s)            # diacritics
    s = re.sub(r"[^؀-ۿ]", "", s)           # keep Persian letters only
    return s.replace("،", "").replace("؟", "")

def report():
    res = {}
    for L in LINES:
        p = os.path.join(HERE, "vo_raw", "transcripts", f"L{L['id']}.json")
        if not os.path.exists(p): continue
        w = " ".join(words(p))
        sim = difflib.SequenceMatcher(None, norm(L["text"]), norm(w)).ratio()
        res[L["id"]] = dict(transcript=w, similarity=round(sim, 3))
        print(f"L{L['id']} sim={sim:.3f}\n  script: {L['text']}\n  heard : {w}")
    return res

if __name__ == "__main__":
    report()
