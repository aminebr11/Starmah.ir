"""Synthesize narration takes with Piper (fa_IR-amir-medium) and pick the best-fitting take per line.

Piper/VITS durations are stochastic, so we render several takes per length-scale and keep the
slowest (most natural) one that fits the line's time window. Chosen takes are cached as
vo_raw/L{id}.wav (+ vo_raw/takes.json) so the mix is reproducible; re-run this script only to
re-voice lines:  python vo_synth.py [line ids...]
"""
import os, subprocess, sys, json, itertools
import soundfile as sf
import numpy as np
HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from vo_lines import LINES, MARGIN
TTS = os.environ.get("STARMAH_TTS_DIR", "/tmp/claude-0/-home-user/d9b15659-30bb-5d97-b55e-4e81155c08ab/scratchpad/tts")
PIPER = os.path.join(TTS, ".venv/bin/piper")
MODEL = os.path.join(TTS, "fa_IR-amir-medium.onnx")
OUT = os.path.join(HERE, "vo_raw")
TMP = os.path.join(OUT, "tmp")
TAKES = int(os.environ.get("TAKES", 3))

def trim(x, sr, thr_db=-52):
    env = np.convolve(np.abs(x), np.ones(64) / 64, mode="same")
    thr = 10 ** (thr_db / 20) * max(env.max(), 1e-9)
    idx = np.where(env > thr)[0]
    if len(idx) == 0: return x
    a = max(idx[0] - int(0.05 * sr), 0); b = min(idx[-1] + int(0.08 * sr), len(x))
    y = x[a:b].copy()
    f = int(0.008 * sr); y[:f] *= np.linspace(0, 1, f); y[-f:] *= np.linspace(1, 0, f)
    return y

def synth(text, ls, path):
    subprocess.run([PIPER, "-m", MODEL, "-f", path, "--length-scale", f"{ls:.3f}",
                    "--noise-scale", "0.6", "--noise-w-scale", "0.7"],
                   input=text.encode("utf-8"), check=True, capture_output=True)
    x, sr = sf.read(path)
    return trim(x, sr), sr

def do_line(L):
    target = L["end_by"] - L["start"] - L.get("margin", MARGIN)
    ls = L["ls_max"]
    while ls >= 0.72:
        takes = []
        for k, t in enumerate(L["segs"]):
            takes.append([synth(t, ls, os.path.join(TMP, f"L{L['id']}_{k}_{j}.wav")) for j in range(TAKES)])
        best = None
        for combo in itertools.product(*takes):
            sr = combo[0][1]
            dur = sum(len(c[0]) for c in combo) / sr + L["pause"] * (len(combo) - 1)
            if dur <= target and (best is None or dur > best[0]):
                best = (dur, combo)
        if best:
            dur, combo = best
            sr = combo[0][1]
            gap = np.zeros(int(L["pause"] * sr))
            y = combo[0][0]
            for c in combo[1:]: y = np.concatenate([y, gap, c[0]])
            sf.write(os.path.join(OUT, f"L{L['id']}.wav"), y, sr)
            return dict(length_scale=round(ls, 3), dur=round(len(y) / sr, 3), target=round(target, 3), sr=sr)
        ls -= 0.02
    raise RuntimeError(f"line {L['id']} does not fit")

def main(only=None):
    os.makedirs(TMP, exist_ok=True)
    jp = os.path.join(OUT, "takes.json")
    rep = json.load(open(jp)) if os.path.exists(jp) else {}
    for L in LINES:
        if only and L["id"] not in only: continue
        rep[str(L["id"])] = do_line(L)
        print(L["id"], rep[str(L["id"])], flush=True)
    json.dump(rep, open(jp, "w"), indent=1)

if __name__ == "__main__":
    main([int(a) for a in sys.argv[1:]] or None)
