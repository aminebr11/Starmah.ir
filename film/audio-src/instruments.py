"""Synthesized instruments. Every function returns a mono numpy array at dsp.SR (unless noted)."""
import numpy as np
from scipy import signal
from dsp import SR, hz, n2m, midi_hz, lp, hp, bp, peq, noise, expdec, adsr, fade, bl_table, table_osc, \
    vibrato, rng, pan_st

TWO_PI = 2 * np.pi


def _t(dur):
    return np.arange(int(dur * SR)) / SR


def _sum_partials(f, parts, dur, nyq_guard=17000.0, seed=0):
    """parts: list of (ratio, amp, t60, detune_hz). Additive sum with exp decays."""
    t = _t(dur)
    y = np.zeros(len(t))
    r = rng(seed)
    for ratio, amp, t60, det in parts:
        fk = f * ratio + det
        if fk >= nyq_guard or amp == 0:
            continue
        y += amp * np.sin(TWO_PI * fk * t + r.uniform(0, TWO_PI)) * np.exp(-6.9078 * t / t60)
    return y


# --------------------------------------------------------------------------- tuned percussion
def celesta(note, vel=0.8, dur=None, seed=0):
    """FM celesta: sine carrier, 1:1 modulator with fast-decaying index + bright 4th partial."""
    f = hz(note) if isinstance(note, str) else float(note)
    t60 = float(np.clip(2.6 * (523.0 / f) ** 0.6, 0.6, 3.5))
    dur = dur or t60 * 1.05
    t = _t(dur)
    idx = (0.25 + 1.6 * vel) * np.exp(-t / 0.035) + 0.12
    mod = np.sin(TWO_PI * f * t)
    y = np.sin(TWO_PI * f * t + idx * mod) * expdec(len(t), t60)
    # metal-bar overtone (~4x) and soft resonator (octave) body
    y += 0.10 * vel * np.sin(TWO_PI * f * 4.02 * t) * expdec(len(t), 0.09) * (f * 4 < 16000)
    y += 0.12 * np.sin(TWO_PI * f * 2.0 * t + 0.3) * expdec(len(t), t60 * 0.35)
    # hammer felt "tock"
    k = noise(int(0.006 * SR), seed)
    k = lp(k, 5000) * np.hanning(len(k))
    y[:len(k)] += 0.05 * vel * k
    y = fade(y, 0.0015, 0.05) * vel
    return y


def musicbox(note, vel=0.8, seed=0):
    """Comb-tine model: fundamental (two slightly beating modes) + cantilever modes 6.27, 17.5."""
    f = hz(note) if isinstance(note, str) else float(note)
    t60 = float(np.clip(2.2 * (1046.0 / f) ** 0.5, 0.8, 3.0))
    parts = [(1.0, 1.0, t60, 0.0), (1.0, 0.25, t60 * 0.8, 0.7), (2.0, 0.06, t60 * 0.3, 0.0),
             (6.27, 0.18 * vel, 0.12, 0.0), (17.55, 0.05 * vel, 0.03, 0.0)]
    y = _sum_partials(f, parts, t60 * 1.05, seed=seed)
    k = noise(int(0.004 * SR), seed + 3)
    k = bp(k, 2500, 8000) * np.hanning(len(k))
    y[:len(k)] += 0.06 * k * vel
    return fade(y, 0.0008, 0.05) * vel * 0.8


def glock(note, vel=0.8, seed=0, t60=None):
    f = hz(note) if isinstance(note, str) else float(note)
    t60 = t60 or float(np.clip(2.4 * (1318.0 / f) ** 0.4, 0.8, 3.0))
    parts = [(1.0, 1.0, t60, 0.0), (2.76, 0.30 * vel, t60 * 0.18, 0.0), (5.40, 0.12 * vel, t60 * 0.07, 0.0),
             (8.93, 0.05 * vel, 0.04, 0.0)]
    y = _sum_partials(f, parts, t60 * 1.05, seed=seed)
    return fade(y, 0.0008, 0.05) * vel * 0.7


def bell(note, vel=0.8, length=6.0, seed=0, major=True, bright=1.0):
    """Risset-style additive bell. major=True uses a major-third partial set that sits in D major."""
    f = hz(note) if isinstance(note, str) else float(note)
    if major:
        tab = [(0.5, 1.0, 1.0, 0.0), (0.5, 0.6, 0.9, 0.9), (1.0, 0.9, 0.7, 0.0), (1.0, 0.5, 0.65, 1.3),
               (1.26, 0.55, 0.42, 0.0), (1.5, 0.6, 0.38, 0.0), (2.0, 0.65, 0.3, 0.0), (2.51, 0.35, 0.2, 0.0),
               (3.0, 0.3, 0.16, 0.0), (4.17, 0.22 * bright, 0.1, 0.0), (5.43, 0.15 * bright, 0.07, 0.0)]
    else:
        tab = [(0.56, 1, 1, 0), (0.56, 0.67, 0.9, 1.0), (0.92, 1, 0.65, 0), (0.92, 1.8, 0.55, 1.7),
               (1.19, 2.67, 0.325, 0), (1.7, 1.67, 0.35, 0), (2, 1.46, 0.25, 0), (2.74, 1.33, 0.2, 0),
               (3, 1.33, 0.15, 0), (3.76, 1, 0.1, 0), (4.07, 1.33, 0.075, 0)]
    parts = [(r_, a, length * d, det) for (r_, a, d, det) in tab]
    y = _sum_partials(f, parts, length, seed=seed)
    y /= np.max(np.abs(y)) + 1e-9
    k = noise(int(0.01 * SR), seed + 5)
    k = bp(k, 800, 6000) * np.hanning(len(k))
    y[:len(k)] += 0.15 * k
    return fade(y, 0.001, 0.3) * vel


def marimba(note, vel=0.8, seed=0):
    f = hz(note) if isinstance(note, str) else float(note)
    t60 = float(np.clip(1.2 * (440.0 / f) ** 0.7, 0.25, 1.6))
    parts = [(1.0, 1.0, t60, 0.0), (3.93, 0.25 * vel, t60 * 0.2, 0.0), (9.2, 0.06 * vel, 0.03, 0.0)]
    y = _sum_partials(f, parts, t60 * 1.1, seed=seed)
    t = _t(t60 * 1.1)
    y *= 1 - np.exp(-t / 0.002)
    return fade(y, 0.0, 0.04) * vel * 0.8


# --------------------------------------------------------------------------- piano
def piano(note, vel=0.6, dur=1.5, seed=0):
    """Additive piano: inharmonic partials (B), 2 detuned strings, two-stage decay, felt hammer."""
    m = n2m(note); f = float(midi_hz(m))
    B = 0.00012 * (f / 130.0) ** 0.9 + 0.00006
    tail = min(1.2, 0.3 + 0.4 * (440 / f) ** 0.3)
    total = dur + tail
    t = _t(total)
    n = len(t)
    r = rng(seed)
    bright = 1200 + 3500 * vel
    t_slow = float(np.clip(9.0 * (130.0 / f) ** 0.55, 1.5, 14))
    y = np.zeros(n)
    K = int(min(40, 15000 / f))
    for k in range(1, K + 1):
        fk = k * f * np.sqrt(1 + B * k * k)
        if fk > 15000: break
        a = (1.0 / k ** 0.9) * np.exp(-(fk - f) / bright)
        if k == 1: a *= 0.9
        ts = t_slow / (1 + 0.22 * (k - 1))
        tf = 0.25 * ts
        env = 0.55 * np.exp(-t / (tf / 6.9)) + 0.45 * np.exp(-t / (ts / 6.9))
        for s, det in enumerate((-0.6, 0.7)):
            fd = fk * 2 ** (det / 1200)
            y += 0.5 * a * env * np.sin(TWO_PI * fd * t + r.uniform(0, TWO_PI))
    # hammer
    hn = int(0.012 * SR)
    hmr = lp(noise(hn, seed + 11), 1500 + 2500 * vel) * np.hanning(hn) ** 2
    y[:hn] += 0.12 * vel * hmr
    y *= 1 - np.exp(-t / 0.0012)
    # damper release
    rel_n = int(tail * SR)
    off = int(dur * SR)
    if off < n:
        relenv = np.exp(-np.arange(n - off) / (0.12 * SR))
        y[off:] *= relenv
    y = fade(y, 0, 0.03)
    return y * vel * 0.5


# --------------------------------------------------------------------------- plucked strings
def ks_pluck(f, dur, t60=1.0, bright=0.5, seed=0, pick=0.12):
    """Karplus-Strong with fractional (allpass) tuning, implemented as one IIR via lfilter."""
    P = SR / f
    N = int(np.floor(P - 0.5 - 0.1))
    d = P - N - 0.5
    c = (1 - d) / (1 + d)
    g = 10 ** (-3.0 / (f * t60))
    a = np.zeros(N + 3); a[0] = 1.0; a[1] += c
    a[N] -= g * 0.5 * c; a[N + 1] -= g * 0.5 * (1 + c); a[N + 2] -= g * 0.5
    b = np.array([1.0, c])
    n = int(dur * SR)
    exc = np.zeros(n)
    L = max(4, int(round(P)))
    # displacement profile of a plucked string (triangle at pick position) + a little noise
    pk = max(1, int(L * min(max(pick, 0.05), 0.5)))
    tri = np.concatenate([np.linspace(0, 1, pk, endpoint=False), np.linspace(1, 0, L - pk)])
    burst = tri + 0.25 * bright * noise(L, seed)
    burst = lp(burst, 500 + 6000 * bright, order=1)
    burst -= burst.mean()
    exc[:L] = burst
    y = signal.lfilter(b, a, exc)
    return y / (np.max(np.abs(y)) + 1e-9)


def pizz(note, vel=0.7, dur=None, seed=0):
    f = hz(note) if isinstance(note, str) else float(note)
    decay = float(np.clip(0.9 * (220.0 / f) ** 0.5, 0.25, 1.4))
    dur = dur or decay * 1.4
    y = ks_pluck(f, dur, t60=decay * 1.6, bright=0.25 + 0.4 * vel, seed=seed)
    # body: violin/cello resonances, soft top
    y = peq(y, 280, 4, 1.2); y = peq(y, 2800, -3, 0.7)
    y = lp(y, 4200)
    t = _t(dur)
    y *= np.exp(-t / (decay / 3)) * (1 - np.exp(-t / 0.003))
    return fade(y, 0, 0.03) * vel


def harp(note, vel=0.7, seed=0):
    f = hz(note) if isinstance(note, str) else float(note)
    decay = float(np.clip(3.0 * (220 / f) ** 0.5, 0.8, 4))
    y = ks_pluck(f, decay * 1.1, t60=decay, bright=0.35 + 0.3 * vel, seed=seed, pick=0.5)
    y = lp(y, 6000)
    return fade(y, 0, 0.05) * vel * 0.8


# --------------------------------------------------------------------------- sustained ensembles
def ensemble(notes, dur, att=0.6, rel=0.8, cutoff=3000.0, shape="saw", voices=4, spread_cents=9,
             vib_cents=9, vib_rate=5.3, width=0.8, seed=0, gain=1.0, env=None, cutoff_env=None,
             vib_delay=0.25):
    """Detuned, vibrato'd, band-limited ensemble (strings / pad / brass). Returns (2, n) stereo.
    env: optional per-sample amplitude envelope (len n), overrides att/rel shape."""
    n = int(dur * SR)
    r = rng(seed)
    out = np.zeros((2, n))
    for ni, note in enumerate(notes):
        f = hz(note) if isinstance(note, str) else float(midi_hz(note))
        tab = bl_table(shape, f * 1.02, cutoff=min(14000.0, max(cutoff * 2.2, f * 3)))
        for v in range(voices):
            det = (v - (voices - 1) / 2) / max(1, (voices - 1) / 2) * spread_cents / 2 + r.normal(0, 1.5)
            fr = vibrato(n, f * 2 ** (det / 1200), rate=vib_rate * r.uniform(0.9, 1.1), depth_cents=vib_cents,
                         delay=vib_delay, seed=int(r.integers(1 << 30)))
            # slow random pitch drift (ensemble looseness)
            fr *= 2 ** (2.0 * np.sin(TWO_PI * r.uniform(0.1, 0.3) * np.arange(n) / SR + r.uniform(0, 6)) / 1200)
            x = table_osc(tab, fr, r.uniform())
            pan = (v - (voices - 1) / 2) / max(1, (voices - 1) / 2) * width
            pan += 0.15 * np.tanh((n2m(note) if isinstance(note, str) else note) - 64) / 1.0 * 0
            out += pan_st(x, float(np.clip(pan, -1, 1)))
    out /= np.sqrt(len(notes) * voices)
    if cutoff_env is None:
        out = lp(out, cutoff, order=2)
        out = lp(out, cutoff * 1.6, order=2)
    else:
        # piecewise filtering in overlapping chunks for a moving cutoff (strings crescendo)
        out = _moving_lp(out, cutoff_env)
    if env is None:
        env = adsr(n, att, 0.2, 1.0, rel)
    out *= env[None, :n]
    return out * gain


def _moving_lp(x, fc, block=2048):
    n = x.shape[1]
    hop = block // 2
    win = np.hanning(block + 1)[:-1]
    pad = np.concatenate([np.zeros((2, block)), x, np.zeros((2, block))], axis=1)
    res = np.zeros_like(pad)
    for s0 in range(0, pad.shape[1] - block, hop):
        c = float(fc[int(np.clip(s0 - block + hop, 0, n - 1))])
        st = max(s0 - 3 * block, 0)
        sos = signal.butter(4, min(c, SR * 0.45), "low", fs=SR, output="sos")
        yseg = signal.sosfilt(sos, pad[:, st:s0 + block], axis=1)[:, -block:]
        res[:, s0:s0 + block] += yseg * win
    return res[:, block:block + n]


# --------------------------------------------------------------------------- drums
def kick(vel=0.8, seed=0, soft=True):
    d = 0.45
    t = _t(d)
    f = 46 + 75 * np.exp(-t / 0.035)
    ph = TWO_PI * np.cumsum(f) / SR
    y = np.sin(ph) * np.exp(-t / 0.16)
    cl = lp(noise(len(t), seed), 2500 if soft else 5000) * np.exp(-t / 0.004)
    y += 0.25 * cl
    return fade(y, 0, 0.05) * vel


def tom(note="D3", vel=0.7, seed=0, decay=0.35):
    f0 = hz(note) if isinstance(note, str) else note
    t = _t(decay * 2.2)
    f = f0 * (1 + 0.45 * np.exp(-t / 0.04))
    y = np.sin(TWO_PI * np.cumsum(f) / SR) * np.exp(-t / (decay / 2.5))
    y += 0.3 * np.sin(TWO_PI * np.cumsum(f * 1.5) / SR) * np.exp(-t / (decay / 5))
    sk = bp(noise(len(t), seed), f0 * 2, min(f0 * 12, 6000)) * np.exp(-t / 0.025)
    y += 0.35 * sk
    return fade(y, 0, 0.05) * vel


def taiko(vel=0.9, f0=62.0, seed=0, decay=0.9):
    t = _t(decay * 2)
    f = f0 * (1 + 0.7 * np.exp(-t / 0.025))
    y = np.sin(TWO_PI * np.cumsum(f) / SR) * np.exp(-t / (decay / 3))
    y += 0.35 * np.sin(TWO_PI * np.cumsum(f * 1.59) / SR) * np.exp(-t / (decay / 6))
    y += 0.18 * np.sin(TWO_PI * np.cumsum(f * 2.14) / SR) * np.exp(-t / (decay / 8))
    skin = bp(noise(len(t), seed), 90, 900) * np.exp(-t / 0.06)
    slap = bp(noise(len(t), seed + 1), 900, 4000) * np.exp(-t / 0.008)
    y += 0.45 * skin + 0.12 * slap
    return fade(y, 0, 0.1) * vel


def shaker(vel=0.5, seed=0, length=0.07):
    t = _t(length)
    x = bp(noise(len(t), seed), 4000, 9500, order=2)
    env = (1 - np.exp(-t / 0.008)) * np.exp(-t / (length / 3.5))
    return x * env * vel * 0.5


def rim(vel=0.5, seed=0):
    t = _t(0.08)
    y = bp(noise(len(t), seed), 1500, 4000) * np.exp(-t / 0.006) * 0.6
    y += np.sin(TWO_PI * 820 * t) * np.exp(-t / 0.012) * 0.5
    return y * vel


def snare_soft(vel=0.5, seed=0):
    t = _t(0.25)
    y = bp(noise(len(t), seed), 1200, 7500) * np.exp(-t / 0.05) * 0.5
    y += np.sin(TWO_PI * 190 * t) * np.exp(-t / 0.03) * 0.4
    return y * vel


def metal_noise(n, seed=0, lo=3000, hi=11000):
    """Cymbal-ish: bandlimited noise + inharmonic sine cluster (ring-mod character)."""
    r = rng(seed)
    t = np.arange(n) / SR
    x = bp(noise(n, seed), lo, hi, order=2)
    fr = r.uniform(lo * 0.9, min(hi, 9000), 24)
    cl = np.zeros(n)
    for f in fr:
        cl += np.sin(TWO_PI * f * t + r.uniform(0, 6))
    cl = bp(cl, lo, hi)
    return x / (np.std(x) + 1e-9) * 0.8 + cl / (np.std(cl) + 1e-9) * 0.25


def crash(vel=0.7, length=3.0, seed=0, dark=True):
    n = int(length * SR)
    t = np.arange(n) / SR
    x = metal_noise(n, seed, 2500, 12000 if not dark else 9500)
    x = lp(x, 8000 if dark else 11000)
    env = np.exp(-t / (length / 4.5)) * (1 - np.exp(-t / 0.002))
    # high frequencies die first
    hi = hp(x, 5000) * np.exp(-t / (length / 9))
    y = (x - hp(x, 5000)) * env + hi
    return fade(y, 0, 0.3) * vel * 0.35


def cymbal_swell(length=2.0, vel=0.6, seed=0):
    n = int(length * SR)
    t = np.arange(n) / SR
    x = metal_noise(n, seed, 2000, 9000)
    x = lp(x, 8000)
    env = (t / length) ** 2.6
    y = x * env
    y[-int(0.004 * SR):] *= np.linspace(1, 0, int(0.004 * SR))
    return y * vel * 0.35
