"""STARMAH promo audio - single entry point.

    <venv>/bin/python make_audio.py            # render music + sfx, process VO, mix, master, export
    <venv>/bin/python make_audio.py --revoice  # also re-run Piper for all narration lines first

Outputs (48 kHz / 24-bit / stereo / 55.000 s) to ../assets/audio/:
    music.wav  sfx.wav  vo.wav  mix.wav  mix.m4a  cues.json
"""
import os, sys, json, subprocess, time
import numpy as np
import soundfile as sf
from scipy import signal

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
os.chdir(HERE)
import dsp
from dsp import SR, N, hp, lp, bp, peq, shelf, compress, make_ir, convolve_st, true_peak_limiter
from vo_lines import LINES

OUT = os.path.normpath(os.path.join(HERE, "..", "assets", "audio"))

# Target levels (LUFS, measured on each stem alone over its active part)
DUCK_DB = -8.0          # music reduction while narration is active
MASTER_LUFS = -14.0
CEILING_DBTP = -1.3     # limiter ceiling (4x oversampled) -> leaves margin for AAC


# ----------------------------------------------------------------------------- loudness (BS.1770-4)
def k_weight(x):
    b1 = [1.53512485958697, -2.69169618940638, 1.19839281085285]
    a1 = [1.0, -1.69065929318241, 0.73248077421585]
    b2 = [1.0, -2.0, 1.0]
    a2 = [1.0, -1.99004745483398, 0.99007225036621]
    return signal.lfilter(b2, a2, signal.lfilter(b1, a1, x, axis=-1), axis=-1)


def lufs(x, gate=True):
    """integrated loudness of (2, n) or (n,) at 48 kHz."""
    x = np.atleast_2d(x)
    y = k_weight(x)
    blk, hop = int(0.4 * SR), int(0.1 * SR)
    nb = max(1, (y.shape[1] - blk) // hop + 1)
    z = np.array([np.sum(np.mean(y[:, i * hop:i * hop + blk] ** 2, axis=1)) for i in range(nb)])
    l = -0.691 + 10 * np.log10(z + 1e-15)
    if not gate:
        return -0.691 + 10 * np.log10(np.mean(z) + 1e-15)
    z1 = z[l > -70]
    if len(z1) == 0:
        return -70.0
    rel = -0.691 + 10 * np.log10(np.mean(z1)) - 10
    z2 = z[(l > -70) & (l > rel)]
    return -0.691 + 10 * np.log10(np.mean(z2))


# ----------------------------------------------------------------------------- narration
def process_voice(x):
    """x: mono 48k. Warmth/presence/de-harsh/compress chain."""
    x = hp(x, 80, order=4)
    x = peq(x, 180, 1.5, 0.7)        # low-mid warmth
    x = peq(x, 420, -1.5, 1.0)       # remove boxiness
    x = peq(x, 3000, 2.5, 0.9)       # presence
    x = shelf(x, 7500, -2.0, high=True)  # de-harsh
    # split-band de-esser (5-10 kHz)
    hb = bp(x, 5000, 10000, order=2)
    w = int(0.004 * SR)
    env = np.sqrt(np.convolve(hb ** 2, np.ones(w) / w, mode="same") + 1e-12)
    full = np.sqrt(np.convolve(x ** 2, np.ones(w * 4) / (w * 4), mode="same") + 1e-12)
    ratio = env / (full + 1e-9)
    g = np.clip((0.25 / (ratio + 1e-9)) ** 0.7, 0.5, 1.0)
    g = np.convolve(g, np.ones(w) / w, mode="same")
    x = x - hb + hb * g
    x = compress(x, thr_db=-22, ratio=2.6, att=0.006, rel=0.12, makeup_db=0, knee_db=8)
    x = compress(x, thr_db=-10, ratio=6, att=0.001, rel=0.05)  # peak catcher
    return x


def speech_bounds(x, thr_db=-38):
    w = int(0.01 * SR)
    e = np.sqrt(np.convolve(x ** 2, np.ones(w) / w, mode="same"))
    thr = np.max(e) * 10 ** (thr_db / 20)
    idx = np.where(e > thr)[0]
    return idx[0], idx[-1]


def build_vo():
    vo = np.zeros(N)
    cues = []
    room = make_ir(t60=0.35, predelay=0.006, seed=99, size=0.3, early=True)
    for L in LINES:
        x, sr = sf.read(os.path.join(HERE, "vo_raw", f"L{L['id']}.wav"))
        if x.ndim > 1:
            x = x.mean(axis=1)
        x = signal.resample_poly(x, 320, 147) if sr == 22050 else signal.resample_poly(x, SR, sr)
        x = process_voice(x)
        # loudness-match lines (speech-gated)
        x *= 10 ** ((-20.0 - lufs(x)) / 20)
        x = true_peak_limiter(np.stack([x, x]), ceiling_db=-6.0, look=0.002, rel=0.06)[0][0]
        a, b = speech_bounds(x)
        off = int(round(L["start"] * SR)) - a          # speech onset lands exactly on the cue time
        seg = x[:min(len(x), N - off)]
        vo[off:off + len(seg)] += seg
        cues.append(dict(id=L["id"], text=L["text"], start=round((off + a) / SR, 3), end=round((off + b) / SR, 3),
                         window_end=L["end_by"]))
    # very subtle short room, centred
    wet = convolve_st(np.stack([vo, vo]), room)
    wet = hp(lp(wet, 6000), 250)
    st = np.stack([vo, vo]) + 0.07 * wet
    return st, cues


def duck_gain(vo_st, depth_db=DUCK_DB, att=0.2, rel=0.3, hold=0.25):
    m = vo_st.mean(axis=0)
    w = int(0.02 * SR)
    e = np.sqrt(np.convolve(m ** 2, np.ones(w) / w, mode="same"))
    active = (20 * np.log10(e + 1e-9) > -48).astype(float)
    # hold: bridge gaps between words/phrases shorter than `hold`
    h = int(hold * SR)
    from scipy.ndimage import maximum_filter1d, uniform_filter1d
    active = maximum_filter1d(active, size=2 * h + 1)
    # smooth (attack ~200 ms down, ~300 ms release), slightly anticipatory
    sm = dsp.smooth_ar(active, att / 3, rel / 3)
    lead = int(0.06 * SR)
    sm = np.concatenate([sm[lead:], np.zeros(lead)])
    sm = np.clip(sm, 0, 1)
    return 10 ** (depth_db * sm / 20), sm


# ----------------------------------------------------------------------------- utils
def write(path, x, subtype="PCM_24"):
    x = np.asarray(x)
    assert x.shape == (2, N), x.shape
    sf.write(path, x.T, SR, subtype=subtype)


def ffmpeg_loudness(path):
    r = subprocess.run(["ffmpeg", "-hide_banner", "-nostats", "-i", path, "-af", "ebur128=peak=true", "-f", "null",
                        "-"], capture_output=True, text=True)
    txt = r.stderr[r.stderr.rfind("Summary:"):]
    I_ = float(txt.split("I:")[1].split("LUFS")[0])
    tp = float(txt.split("Peak:")[1].split("dBFS")[0])
    lra = float(txt.split("LRA:")[1].split("LU")[0])
    return I_, tp, lra


def main():
    t0 = time.time()
    os.makedirs(OUT, exist_ok=True)
    if "--revoice" in sys.argv:
        subprocess.run([sys.executable, os.path.join(HERE, "vo_synth.py")], check=True)

    import music, sfx
    print("rendering music ...", flush=True)
    mus = music.finish(music.render())
    print("rendering sfx ...", flush=True)
    fx = sfx.finish(sfx.render())
    print("processing narration ...", flush=True)
    vo, vo_cues = build_vo()

    # ---- gain staging (stems are written at the exact gain they have in the mix)
    mus *= 10 ** ((-18.5 - lufs(mus)) / 20)
    fx *= 10 ** ((-23.5 - lufs(fx)) / 20)
    vo *= 10 ** ((-15.5 - lufs(vo)) / 20)
    g, act = duck_gain(vo)
    mus_ducked = mus * g[None, :]
    mix = mus_ducked + fx + vo

    # ---- master: gentle glue, loudness, true-peak limiting (iterate to hit target)
    mix = compress(mix, thr_db=-14, ratio=1.6, att=0.015, rel=0.2)
    gain_db = 0.0
    for it in range(4):
        y = mix * 10 ** (gain_db / 20)
        y, gr = true_peak_limiter(y, ceiling_db=CEILING_DBTP)
        L = lufs(y)
        print(f"  master pass {it}: {L:.2f} LUFS, max GR {20 * np.log10(gr.min()):.2f} dB", flush=True)
        if abs(L - MASTER_LUFS) < 0.05:
            break
        gain_db += MASTER_LUFS - L
    # final safety: exact silence at the very end (last ~0.25 s) and click-free start
    tt = np.arange(N) / SR
    y *= np.clip((54.78 - tt) / 0.04, 0, 1)[None, :]
    stem_gain = 10 ** (gain_db / 20)
    for name, st in [("music", mus * stem_gain), ("sfx", fx * stem_gain), ("vo", vo * stem_gain)]:
        st = st * np.clip((54.78 - tt) / 0.04, 0, 1)[None, :]
        pk = np.max(np.abs(st))
        if pk > 0.89:  # stems are not limited; keep them below clipping (note: then not sample-aligned in gain)
            st *= 0.89 / pk
            print(f"  {name}: scaled by {20 * np.log10(0.89 / pk):.2f} dB to avoid clipping")
        write(os.path.join(OUT, f"{name}.wav"), st)
    write(os.path.join(OUT, "mix.wav"), y)
    subprocess.run(["ffmpeg", "-v", "error", "-y", "-i", os.path.join(OUT, "mix.wav"), "-c:a", "aac", "-b:a", "256k",
                    "-movflags", "+faststart", os.path.join(OUT, "mix.m4a")], check=True)

    # ---- cues
    I_, tp, lra = ffmpeg_loudness(os.path.join(OUT, "mix.wav"))
    cues = dict(
        title="STARMAH / ستاره ماه - 55 s promo audio timeline",
        sample_rate=SR, duration=N / SR,
        loudness=dict(integrated_lufs=I_, true_peak_dbtp=tp, lra_lu=lra),
        music=dict(key="D major (scene 1 B-minor colour)", tempo_bpm=120, beat_s=0.5,
                   note="free tempo 0-6 s; 120 BPM grid from 6.0 s with 2/4 bars at 12-13 s and 43-44 s",
                   hits=[dict(time=4.8, name="constellation burst (A sus4)"), dict(time=6.0, name="main theme starts (D)"),
                         dict(time=35.0, name="countdown burst / tutti D"), dict(time=48.0, name="LOGO REVEAL hit (tutti D + bell)"),
                         dict(time=51.0, name="final chord D(add9), rings out to ~54.7")],
                   scenes=[dict(start=0, end=6, name="Awakening"), dict(start=6, end=13, name="Birth of Starmah"),
                           dict(start=13, end=21, name="Galaxy of learning"),
                           dict(start=21, end=29, name="Learning through play"),
                           dict(start=29, end=37, name="Live competition"), dict(start=37, end=44, name="Power of progress"),
                           dict(start=44, end=51, name="Big reveal"), dict(start=51, end=55, name="Final brand moment")]),
        vo=vo_cues,
        sfx=sorted(sfx.EVENTS, key=lambda e: e["time"]),
        ducking=dict(depth_db=DUCK_DB, attack_ms=200, release_ms=300),
    )
    json.dump(cues, open(os.path.join(OUT, "cues.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    print(f"done in {time.time() - t0:.1f}s  ->  {I_} LUFS, TP {tp} dBTP, LRA {lra}")
    for c in vo_cues:
        print(f"  VO{c['id']}: {c['start']:.3f} - {c['end']:.3f}  (deadline {c['window_end']})")


if __name__ == "__main__":
    main()
