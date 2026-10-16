"""QA helper: per-second short-term levels of every stem + mix, peaks, DC, silence gaps."""
import os, sys, numpy as np, soundfile as sf
HERE = os.path.dirname(os.path.abspath(__file__)); sys.path.insert(0, HERE)
from make_audio import lufs, OUT
SR = 48000
st = {k: sf.read(os.path.join(OUT, f"{k}.wav"))[0].T for k in ["music", "sfx", "vo", "mix"]}
print("sec   " + "  ".join(f"{k:>6}" for k in st))
for s in range(55):
    row = []
    for k, x in st.items():
        seg = x[:, s * SR:(s + 1) * SR]
        row.append(f"{lufs(seg, gate=False):6.1f}")
    print(f"{s:3d}   " + "  ".join(row))
for k, x in st.items():
    print(k, "peak dBFS %.2f" % (20 * np.log10(np.abs(x).max())), "DC", np.round(x.mean(axis=1), 6),
          "len", x.shape[1] / SR)
m = st["music"].mean(axis=0)
w = int(0.05 * SR)
e = np.sqrt(np.convolve(m ** 2, np.ones(w) / w, mode="same"))
quiet = np.where(20 * np.log10(e + 1e-12) < -60)[0] / SR
print("music <-60 dB (50 ms RMS) spans:", (quiet.min(), quiet.max()) if len(quiet) else None, "count", len(quiet))

# click detector: isolated broadband spikes (energy > 10 kHz far above its local average)
from dsp import hp
for k in ["music", "sfx", "mix"]:
    x = st[k].mean(axis=0)
    h = hp(x, 10000, order=4)
    w = int(0.02 * SR)
    loc = np.sqrt(np.convolve(h ** 2, np.ones(w) / w, mode="same")) + 1e-7
    big = np.where((np.abs(h) > 8 * loc) & (np.abs(h) > 10 ** (-55 / 20)))[0]
    ts = sorted(set(np.round(big / SR, 2)))
    print(k, "possible clicks at:", ts[:40])
