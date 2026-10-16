"""Small numpy DSP / synthesis toolkit for the STARMAH promo score + sound design.

Everything is deterministic (seeded RNG) and band-limited (additive / wavetable synthesis),
rendered at 48 kHz float64.
"""
import numpy as np
from scipy import signal

SR = 48000
DUR = 55.0
N = int(round(SR * DUR))
RNG = np.random.default_rng(20261016)


def rng(seed):
    return np.random.default_rng(seed)


def t_axis(dur):
    return np.arange(int(round(dur * SR))) / SR


def midi_hz(m):
    return 440.0 * 2 ** ((np.asarray(m, dtype=float) - 69) / 12)


NOTE = {"C": 0, "C#": 1, "Db": 1, "D": 2, "D#": 3, "Eb": 3, "E": 4, "F": 5, "F#": 6, "Gb": 6,
        "G": 7, "G#": 8, "Ab": 8, "A": 9, "A#": 10, "Bb": 10, "B": 11}


def n2m(name):
    """'F#5' -> midi number"""
    if isinstance(name, (int, float)):
        return name
    i = 2 if len(name) > 2 and name[1] in "#b" else 1
    return 12 * (int(name[i:]) + 1) + NOTE[name[:i]]


def hz(name):
    return float(midi_hz(n2m(name)))


# ----------------------------------------------------------------------------- envelopes
def adsr(n, a, d, s, r, sustain_len=None):
    """Linear-ish ADSR in seconds; total length n samples; release at the end."""
    a_n, d_n, r_n = int(a * SR), int(d * SR), int(r * SR)
    env = np.full(n, s, dtype=float)
    a_n = min(a_n, n)
    if a_n > 0:
        env[:a_n] = np.sin(np.linspace(0, np.pi / 2, a_n)) ** 2
    e2 = min(a_n + d_n, n)
    if e2 > a_n:
        env[a_n:e2] = 1 + (s - 1) * (1 - np.exp(-5 * np.linspace(0, 1, e2 - a_n))) / (1 - np.exp(-5))
    if r_n > 0 and r_n < n:
        env[n - r_n:] *= np.cos(np.linspace(0, np.pi / 2, r_n)) ** 2
    return env


def fade(x, fi=0.005, fo=0.02):
    x = x.copy()
    a, b = int(fi * SR), int(fo * SR)
    if a: x[..., :a] *= np.linspace(0, 1, a) ** 2
    if b: x[..., -b:] *= np.linspace(1, 0, b) ** 2
    return x


def expdec(n, t60):
    return np.exp(-6.9078 * np.arange(n) / (SR * t60))


# ----------------------------------------------------------------------------- filters
def lp(x, fc, order=2):
    fc = min(fc, SR * 0.45)
    sos = signal.butter(order, fc, "low", fs=SR, output="sos")
    return signal.sosfilt(sos, x, axis=-1)


def hp(x, fc, order=2):
    sos = signal.butter(order, fc, "high", fs=SR, output="sos")
    return signal.sosfilt(sos, x, axis=-1)


def bp(x, f1, f2, order=2):
    f2 = min(f2, SR * 0.45)
    sos = signal.butter(order, [f1, f2], "band", fs=SR, output="sos")
    return signal.sosfilt(sos, x, axis=-1)


def peq(x, f0, gain_db, q=0.9):
    """RBJ peaking EQ"""
    A = 10 ** (gain_db / 40); w = 2 * np.pi * f0 / SR; al = np.sin(w) / (2 * q)
    b = [1 + al * A, -2 * np.cos(w), 1 - al * A]; a = [1 + al / A, -2 * np.cos(w), 1 - al / A]
    return signal.lfilter(np.array(b) / a[0], np.array(a) / a[0], x, axis=-1)


def shelf(x, f0, gain_db, high=True, s=0.8):
    A = 10 ** (gain_db / 40); w = 2 * np.pi * f0 / SR
    al = np.sin(w) / 2 * np.sqrt((A + 1 / A) * (1 / s - 1) + 2); c = np.cos(w); sq = 2 * np.sqrt(A) * al
    if high:
        b = [A * ((A + 1) + (A - 1) * c + sq), -2 * A * ((A - 1) + (A + 1) * c), A * ((A + 1) + (A - 1) * c - sq)]
        a = [(A + 1) - (A - 1) * c + sq, 2 * ((A - 1) - (A + 1) * c), (A + 1) - (A - 1) * c - sq]
    else:
        b = [A * ((A + 1) - (A - 1) * c + sq), 2 * A * ((A - 1) - (A + 1) * c), A * ((A + 1) - (A - 1) * c - sq)]
        a = [(A + 1) + (A - 1) * c + sq, -2 * ((A - 1) + (A + 1) * c), (A + 1) + (A - 1) * c - sq]
    return signal.lfilter(np.array(b) / a[0], np.array(a) / a[0], x, axis=-1)


def svf_sweep(x, fc, q=0.7, mode="bp"):
    """Time-varying state-variable filter (Chamberlin/TPT), fc array per sample. Python loop in
    blocks of 32 samples with constant coefficients (fast enough for short SFX)."""
    y = np.zeros_like(x)
    ic1 = ic2 = 0.0
    k = 1.0 / q
    B = 32
    for s0 in range(0, len(x), B):
        f = min(float(fc[min(s0, len(fc) - 1)]), SR * 0.45)
        g = np.tan(np.pi * f / SR)
        a1 = 1 / (1 + g * (g + k)); a2 = g * a1; a3 = g * a2
        for i in range(s0, min(s0 + B, len(x))):
            v3 = x[i] - ic2
            v1 = a1 * ic1 + a2 * v3
            v2 = ic2 + a2 * ic1 + a3 * v3
            ic1 = 2 * v1 - ic1; ic2 = 2 * v2 - ic2
            y[i] = v1 if mode == "bp" else (v2 if mode == "lp" else x[i] - k * v1 - v2)
    return y


# ----------------------------------------------------------------------------- oscillators
_TABLES = {}


def bl_table(shape, f0, cutoff=9000.0, size=4096, tilt=1.0):
    """Band-limited single-cycle table containing only partials below `cutoff` Hz."""
    nmax = max(1, int(cutoff / max(f0, 1)))
    key = (shape, nmax, size, tilt)
    if key in _TABLES:
        return _TABLES[key]
    ph = np.arange(size) / size * 2 * np.pi
    tab = np.zeros(size)
    for k in range(1, nmax + 1):
        if shape == "saw":
            a = 1.0 / k
        elif shape == "square":
            a = (1.0 / k) if k % 2 else 0.0
        elif shape == "tri":
            a = (1.0 / k ** 2) * (1 if (k // 2) % 2 == 0 else -1) if k % 2 else 0.0
        else:
            a = 1.0 if k == 1 else 0.0
        # gentle roll-off of the top partials to avoid a brittle edge
        a *= (1.0 / k ** (tilt - 1)) * (0.5 + 0.5 * np.cos(np.pi * k / (nmax + 1)))
        tab += a * np.sin(k * ph)
    tab /= np.max(np.abs(tab)) + 1e-12
    _TABLES[key] = tab
    return tab


def table_osc(tab, freq, phase0=0.0):
    """freq: array (Hz per sample)."""
    ph = (phase0 + np.cumsum(freq) / SR) % 1.0
    idx = ph * len(tab)
    i0 = idx.astype(int)
    fr = idx - i0
    return tab[i0] * (1 - fr) + tab[(i0 + 1) % len(tab)] * fr


def vibrato(n, f, rate=5.2, depth_cents=8, delay=0.25, seed=0):
    r = rng(seed)
    tt = np.arange(n) / SR
    ramp = np.clip((tt - delay) / 0.6, 0, 1)
    jitter = 1 + 0.08 * np.sin(2 * np.pi * 0.37 * tt + r.uniform(0, 6))
    cents = depth_cents * ramp * np.sin(2 * np.pi * rate * jitter * tt + r.uniform(0, 6))
    return f * 2 ** (cents / 1200)


def pan_st(x, pan):
    """equal power pan, pan in [-1, 1] (scalar or array). returns (2, n)"""
    p = (np.asarray(pan) + 1) * np.pi / 4
    return np.stack([x * np.cos(p), x * np.sin(p)])


# ----------------------------------------------------------------------------- noise
def noise(n, seed=0, color="white"):
    r = rng(seed)
    w = r.standard_normal(n)
    if color == "pink":
        # Paul Kellet-ish filter
        b = [0.049922035, -0.095993537, 0.050612699, -0.004408786]
        a = [1, -2.494956002, 2.017265875, -0.522189400]
        w = signal.lfilter(b, a, w)
        w /= np.std(w) + 1e-12
    elif color == "brown":
        w = signal.lfilter([1], [1, -0.995], w)
        w /= np.std(w) + 1e-12
    return w


# ----------------------------------------------------------------------------- reverb
def make_ir(t60=2.2, predelay=0.018, seed=7, size=1.0, damp=1.0, early=True):
    """Stereo, decorrelated, frequency-dependent decaying-noise impulse response."""
    n = int(SR * (t60 * 1.15 + predelay))
    r = rng(seed)
    ir = np.zeros((2, n))
    bands = [(20, 250, 1.15), (250, 1200, 1.0), (1200, 4000, 0.78), (4000, 9000, 0.52), (9000, 20000, 0.3)]
    tt = np.arange(n) / SR
    for ch in range(2):
        acc = np.zeros(n)
        for (f1, f2, k) in bands:
            w = r.standard_normal(n)
            w = bp(w, f1, min(f2, 21000), order=2) if f1 > 20 else lp(w, f2, order=2)
            tk = t60 * (k ** damp)
            acc += w * np.exp(-6.9078 * np.maximum(tt - predelay, 0) / tk)
        # soft onset (diffusion build-up)
        onset = np.clip((tt - predelay) / (0.03 * size), 0, 1) ** 1.5
        acc *= onset
        acc[tt < predelay] = 0
        if early:
            for i in range(9):
                d = predelay * 0.4 + r.uniform(0.004, 0.06) * size
                acc[int(d * SR)] += r.uniform(-1, 1) * 0.6 * (1 - i / 12)
        ir[ch] = acc
    ir /= np.sqrt(np.sum(ir ** 2) / 2)
    return ir


def convolve_st(x_st, ir):
    """x_st (2, n) stereo send -> wet (2, n). Each input channel feeds both IR channels
    (L->L uses ir[0], R->R uses ir[1], small cross-feed)."""
    n = x_st.shape[1]
    mono = x_st.mean(axis=0)
    out = np.zeros((2, n))
    for ch in range(2):
        main = signal.fftconvolve(x_st[ch], ir[ch])[:n]
        cross = signal.fftconvolve(mono, ir[1 - ch])[:n]
        out[ch] = 0.8 * main + 0.35 * cross
    return out


# ----------------------------------------------------------------------------- dynamics
def env_follow(x, att=0.005, rel=0.08):
    """peak envelope follower (vectorised approximation via two one-pole filters)"""
    a = np.abs(x)
    ar = np.exp(-1 / (rel * SR))
    e = signal.lfilter([1 - ar], [1, -ar], a)
    return np.maximum(e, signal.lfilter([1 - np.exp(-1 / (att * SR))], [1, -np.exp(-1 / (att * SR))], a))


def compress(x, thr_db=-18, ratio=2.5, att=0.01, rel=0.15, makeup_db=0.0, knee_db=6.0, rms_win=0.01):
    """Feed-forward RMS compressor with soft knee; x mono or (2, n) (linked)."""
    det = x if x.ndim == 1 else np.max(np.abs(x), axis=0)
    w = max(1, int(rms_win * SR))
    rms = np.sqrt(np.convolve(det ** 2, np.ones(w) / w, mode="same") + 1e-12)
    lvl = 20 * np.log10(rms + 1e-12)
    over = lvl - thr_db
    gr = np.where(over <= -knee_db / 2, 0.0,
                  np.where(over >= knee_db / 2, over * (1 - 1 / ratio),
                           (1 - 1 / ratio) * (over + knee_db / 2) ** 2 / (2 * knee_db)))
    # smooth gain reduction (attack faster than release)
    gr = smooth_ar(gr, att, rel)
    g = 10 ** ((makeup_db - gr) / 20)
    return x * g


def smooth_ar(v, att, rel):
    """attack/release smoothing of a control signal where *rising* v = attack."""
    out = np.empty_like(v)
    aa = np.exp(-1 / (att * SR)); ar = np.exp(-1 / (rel * SR))
    # vectorised by processing in blocks with python loop on block level
    y = 0.0
    B = 64
    for s0 in range(0, len(v), B):
        seg = v[s0:s0 + B]
        tgt = seg.mean()
        c = aa if tgt > y else ar
        c = c ** B
        y_new = tgt + (y - tgt) * c
        out[s0:s0 + B] = np.linspace(y, y_new, len(seg), endpoint=False) if len(seg) > 1 else y_new
        y = y_new
    return out


def softclip(x, drive=1.0):
    return np.tanh(x * drive) / np.tanh(drive)


def true_peak_limiter(x, ceiling_db=-1.2, look=0.0015, rel=0.08, os=4):
    """Look-ahead brick-wall limiter on 4x-oversampled peak estimate. x: (2, n)."""
    ceil = 10 ** (ceiling_db / 20)
    up = signal.resample_poly(x, os, 1, axis=1)
    pk = np.max(np.abs(up), axis=0).reshape(-1, os).max(axis=1)[:x.shape[1]]
    graw = np.minimum(1.0, ceil / (pk + 1e-12))
    W = max(1, int(look * SR))
    from scipy.ndimage import minimum_filter1d, uniform_filter1d
    m = minimum_filter1d(graw, size=2 * W + 1, mode="nearest")
    g = uniform_filter1d(m, size=W + 1, mode="nearest")
    g = np.minimum(g, m)
    # release: one-pole upward only (python loop over block minima)
    ar = np.exp(-1 / (rel * SR))
    out = np.empty_like(g)
    y = 1.0
    for i in range(len(g)):
        gi = g[i]
        if gi < y:
            y = gi
        else:
            y = gi + (y - gi) * ar
        out[i] = y
    return x * out, out
