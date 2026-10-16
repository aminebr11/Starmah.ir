"""Original score for the STARMAH 55 s promo.  Key: D major (scene 1 on B minor colour).

Tempo map: free/rubato in scene 1 (0-6 s); 120 BPM (beat = 0.5 s, bar = 2 s) from 6.0 s.
With a 2/4 bar at 12-13 s and 43-44 s every scene boundary (13, 21, 29, 37, 44) and every
hit point (6.0, 35.0, 48.0, countdown 31-35) lands exactly on a beat.

Render:  python music.py  ->  out/music.wav  (also used by make_audio.py)
"""
import numpy as np
from dsp import SR, N, DUR, hz, n2m, lp, hp, peq, shelf, noise, pan_st, make_ir, convolve_st, compress, \
    adsr, rng, bp
import instruments as I

BEAT = 0.5
R = rng(4242)


LAST = ["?"]
ANALYSE = False


class Mixer:
    def __init__(self, n=N):
        self.dry = np.zeros((2, n))
        self.send = np.zeros((2, n))
        self.n = n
        self.stems = {}

    def add(self, sig, t, pan=0.0, gain=1.0, send=0.25):
        st = pan_st(sig, pan) if sig.ndim == 1 else sig
        i = int(round(t * SR))
        if i >= self.n:
            return
        j = 0
        if i < 0:
            j = -i; i = 0
        L = min(st.shape[1] - j, self.n - i)
        if L <= 0:
            return
        seg = st[:, j:j + L] * gain
        # declick: 1 ms fade-in / 3 ms fade-out on every placed sound
        fi, fo = min(48, L // 4), min(144, L // 4)
        if j == 0 and fi > 0:
            seg = seg.copy(); seg[:, :fi] *= np.linspace(0, 1, fi)
        if j + L == st.shape[1] and fo > 0:
            seg = seg.copy(); seg[:, -fo:] *= np.linspace(1, 0, fo)
        self.dry[:, i:i + L] += seg
        self.send[:, i:i + L] += seg * send
        if ANALYSE:
            tag = LAST[0]
            if tag not in self.stems:
                self.stems[tag] = np.zeros(self.n // 480 + 1)
            e = np.sum(seg ** 2, axis=0)
            np.add.at(self.stems[tag], (np.arange(i, i + L) // 480), e)


def xf(env, fi, fo):
    """apply raised-cosine fade-in/out (seconds) to an envelope slice (for chord crossfades)."""
    env = env.copy()
    a, b = int(fi * SR), int(fo * SR)
    if a: env[:a] *= 0.5 - 0.5 * np.cos(np.linspace(0, np.pi, a))
    if b: env[-b:] *= 0.5 + 0.5 * np.cos(np.linspace(0, np.pi, b))
    return env


def hum(t, amt=0.006):
    return t + R.uniform(-amt, amt)


def hv(v, amt=0.08):
    return v * (1 + R.uniform(-amt, amt))


# chord dictionary: bass, low strings, mid voicing, upper chord tones (for arps)
CH = {
    "D":    dict(b="D2", lo=["D3", "A3"], mid=["D4", "F#4", "A4"], arp=["D", "F#", "A"]),
    "Bm":   dict(b="B1", lo=["B2", "F#3"], mid=["D4", "F#4", "B4"], arp=["B", "D", "F#"]),
    "G":    dict(b="G1", lo=["G2", "D3"], mid=["D4", "G4", "B4"], arp=["G", "B", "D"]),
    "A":    dict(b="A1", lo=["A2", "E3"], mid=["C#4", "E4", "A4"], arp=["A", "C#", "E"]),
    "Asus": dict(b="A1", lo=["A2", "E3"], mid=["D4", "E4", "A4"], arp=["A", "D", "E"]),
    "F#m":  dict(b="F#1", lo=["F#2", "C#3"], mid=["C#4", "F#4", "A4"], arp=["F#", "A", "C#"]),
    "Em":   dict(b="E2", lo=["E2", "B2"], mid=["E4", "G4", "B4"], arp=["E", "G", "B"]),
    "Em7":  dict(b="E2", lo=["E2", "B2"], mid=["D4", "G4", "B4"], arp=["E", "G", "B", "D"]),
    "Gmaj7": dict(b="G1", lo=["G2", "D3"], mid=["D4", "F#4", "B4"], arp=["G", "B", "D", "F#"]),
}
PC = {"C": 0, "C#": 1, "D": 2, "D#": 3, "E": 4, "F": 5, "F#": 6, "G": 7, "G#": 8, "A": 9, "A#": 10, "B": 11}


def chord_tones(ch, lo_midi, hi_midi):
    pcs = [PC[p] for p in CH[ch]["arp"]]
    return [m for m in range(lo_midi, hi_midi + 1) if m % 12 in pcs]


def mname(m):
    names = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "A#", "B"]
    return f"{names[m % 12]}{m // 12 - 1}"


def play_line(mx, inst, notes, t0, pan=0.0, gain=1.0, send=0.3, vel=0.7, human=0.004, **kw):
    """notes: list of (name, start_beat, len_beats[, vel])"""
    for nt in notes:
        name, sb, lb = nt[:3]
        v = nt[3] if len(nt) > 3 else vel
        if name is None:
            continue
        t = t0 + sb * BEAT
        if inst in (I.piano,):
            y = inst(name, hv(v, 0.05), dur=lb * BEAT * 1.05, seed=int(R.integers(1 << 30)))
        else:
            y = inst(name, hv(v, 0.05), seed=int(R.integers(1 << 30)), **kw)
        mx.add(y, hum(t, human) if sb > 0 else t, pan, gain, send)


def strings_line(mx, notes, t0, gain=0.3, cutoff=3800, pan=0.15, send=0.35, voices=3, vib=10, att=0.07):
    """Legato violin line from an ensemble, one note at a time with small overlaps."""
    for nt in notes:
        name, sb, lb = nt[:3]
        v = nt[3] if len(nt) > 3 else 1.0
        d = lb * BEAT + 0.12
        y = I.ensemble([name], d + 0.25, att=att, rel=0.25, cutoff=cutoff, voices=voices, spread_cents=8,
                       vib_cents=vib, width=0.35, seed=int(R.integers(1 << 30)), vib_delay=0.12)
        LAST[0] = "strings"
        mx.add(y, t0 + sb * BEAT, gain=gain * v, send=send)


def pad(mx, notes, t, dur, gain=0.2, cutoff=2000, att=0.6, rel=0.8, send=0.35, shape="saw", voices=4,
        vib=7, width=0.8, env=None, cutoff_env=None, spread=10):
    y = I.ensemble(notes, dur, att=att, rel=rel, cutoff=cutoff, shape=shape, voices=voices, vib_cents=vib,
                   width=width, seed=int(R.integers(1 << 30)), env=env, cutoff_env=cutoff_env,
                   spread_cents=spread)
    mx.add(y, t, gain=gain, send=send)


# ----------------------------------------------------------------------------- melodies (beats)
THEME_A = [  # D | Bm | G | A(2/4)   -- the "star theme"
    [("F#5", 0, 1), ("A5", 1, .5), ("D6", 1.5, 1.5), ("C#6", 3, .5), ("B5", 3.5, .5)],
    [("A5", 0, 1), ("F#5", 1, .5), ("D5", 1.5, .5), ("B5", 2, 1.5), ("A5", 3.5, .5)],
    [("G5", 0, .5), ("A5", .5, .5), ("B5", 1, 1), ("D6", 2, 1), ("B5", 3, .5), ("G5", 3.5, .5)],
    [("A5", 0, .5), ("G5", .5, .25), ("F#5", .75, .25), ("E5", 1, 1)],
]
THEME_B = [  # G | A | F#m | Bm   -- answer phrase
    [("B5", 0, 1), ("A5", 1, .5), ("G5", 1.5, .5), ("D6", 2, 2)],
    [("C#6", 0, 1), ("B5", 1, .5), ("A5", 1.5, .5), ("E6", 2, 2)],
    [("C#6", 0, 1), ("A5", 1, .5), ("F#5", 1.5, .5), ("A5", 2, 1), ("C#6", 3, 1)],
    [("B5", 0, 2.5), ("A5", 2.5, .5), ("F#5", 3, 1)],
]


def render():
    mx = Mixer()

    # ======================================================= SCENE 1  0-6  "Awakening" (B minor colour)
    # low drone
    drone = I.ensemble(["B1", "F#2"], 5.2, att=1.8, rel=0.5, cutoff=420, shape="tri", voices=3, vib_cents=3,
                       width=0.5, seed=1)
    mx.add(drone, 0.0, gain=0.12, send=0.2)
    drone2 = I.ensemble(["A1", "E2"], 1.5, att=0.25, rel=0.4, cutoff=420, shape="tri", voices=3, vib_cents=3,
                        width=0.5, seed=2)
    mx.add(drone2, 4.75, gain=0.11, send=0.2)
    # pads: Bm(add9) -> Gmaj7 -> (swell) Em9 -> Asus4 -> A -> D at 6.0
    pad(mx, ["B2", "F#3", "C#4", "D4"], 0.0, 3.1, gain=0.16, cutoff=900, att=1.6, rel=0.9, shape="saw", vib=4)
    pad(mx, ["G2", "D3", "F#3", "B3"], 2.4, 2.5, gain=0.16, cutoff=1000, att=0.9, rel=0.6, shape="saw", vib=4)
    n = int(0.85 * SR)
    sw = (np.linspace(0, 1, n) ** 2.2); sw[-int(0.06 * SR):] *= np.linspace(1, 0, int(0.06 * SR))
    pad(mx, ["E3", "B3", "F#4", "G4", "D5"], 3.97, 0.85, gain=0.30, cutoff=2200, env=sw, vib=8, voices=4)
    # 4.8 burst chord (A sus4) then A (5.4) -> D (6.0)
    pad(mx, ["A2", "E3", "A3", "D4", "E4", "A4"], 4.8, 0.85, gain=0.26, cutoff=2800, att=0.03, rel=0.35, vib=6)
    pad(mx, ["A2", "E3", "A3", "C#4", "E4", "A4"], 5.35, 0.75, gain=0.24, cutoff=3000, att=0.25, rel=0.15, vib=7)
    # music box: the theme head, slowly and mysteriously
    for t, nm, v, p in [(0.9, "F#5", .45, -.3), (1.45, "A5", .42, .2), (2.05, "D6", .5, -.1), (2.95, "C#6", .4, .3),
                        (3.5, "B5", .38, -.25), (4.12, "F#5", .35, .1), (4.42, "A5", .36, -.2)]:
        mx.add(I.musicbox(nm, v, seed=int(t * 100)), t, p, 0.55, 0.5)
    # golden dust particles: faint high glock pings
    for k in range(10):
        t = 0.3 + k * 0.43 + R.uniform(-0.1, 0.1)
        nm = R.choice(["B6", "D7", "F#6", "A6", "C#7", "E7"])
        mx.add(I.glock(nm, R.uniform(0.07, 0.13), seed=k, t60=1.2), t, R.uniform(-.8, .8), 0.5, 0.7)
    # air shimmer bed
    n = int(6.2 * SR); tt = np.arange(n) / SR
    air = bp(noise(n, 77), 5000, 9000) * (0.5 + 0.5 * np.sin(2 * np.pi * 0.7 * tt)) * np.clip(tt / 2, 0, 1)
    air *= np.clip((6.2 - tt) / 0.4, 0, 1)
    air2 = bp(noise(n, 78), 5000, 9000) * (0.5 + 0.5 * np.sin(2 * np.pi * 0.53 * tt + 2)) * np.clip(tt / 2, 0, 1)
    air2 *= np.clip((6.2 - tt) / 0.4, 0, 1)
    mx.add(np.stack([air, air2]) * 0.006, 0.0, send=0.6)
    # burst at 4.8: celesta roll
    for k, nm in enumerate(["A5", "D6", "E6", "A6", "D7"]):
        mx.add(I.celesta(nm, 0.5), 4.8 + k * 0.03, -0.4 + k * 0.2, 0.4, 0.5)
    # 5.3 -> 6.0 rising harp + celesta glissando (D major) into the theme
    scale = [m for m in range(n2m("A4"), n2m("B6") + 1) if m % 12 in (2, 4, 6, 7, 9, 11, 1)]
    ts = 5.3 + 0.68 * (np.linspace(0, 1, len(scale)) ** 0.8)
    for k, (m, t) in enumerate(zip(scale, ts)):
        mx.add(I.harp(mname(m), 0.32 + 0.25 * k / len(scale), seed=k), t, -0.5 + k / len(scale), 0.5, 0.45)
        if k % 2 == 0:
            mx.add(I.celesta(mname(m), 0.25), t, 0.5 - k / len(scale), 0.25, 0.5)

    # ======================================================= SCENE 2  6-13  "Birth of Starmah" (main theme)
    bars2 = [(6.0, "D"), (8.0, "Bm"), (10.0, "G"), (12.0, "A")]
    for bi, (t0, ch) in enumerate(bars2):
        L = 4 if bi < 3 else 2
        play_line(mx, I.celesta, THEME_A[bi], t0, pan=0.2, gain=0.42, send=0.35, vel=0.75)
        if bi >= 1:
            up = [(mname(n2m(a) + 12), b, c) for (a, b, c) in THEME_A[bi]]
            play_line(mx, I.musicbox, up, t0, pan=-0.35, gain=0.18 + 0.06 * bi, send=0.45, vel=0.5)
        pad(mx, CH[ch]["mid"] + CH[ch]["lo"][1:], t0, L * BEAT + 0.35, gain=0.13 + 0.03 * bi, cutoff=1900 + 300 * bi,
            att=0.35, rel=0.4)
        # pizzicato bass + offbeat chord plucks
        bn = CH[ch]["b"]; root3 = mname(n2m(bn) + 12); fifth = mname(n2m(bn) + 19)
        bass = [(root3, 0, 1, .7), (fifth, 1, 1, .5), (root3, 2, 1, .6), (fifth, 3, 1, .5)][:L]
        play_line(mx, I.pizz, bass, t0, pan=-0.15, gain=0.55, send=0.2)
        if bi >= 1:
            for b in np.arange(0.5, L, 1.0):
                for k, nm in enumerate(CH[ch]["mid"]):
                    mx.add(I.pizz(nm, hv(0.28)), hum(t0 + b * BEAT + k * 0.008), 0.45, 0.32, 0.25)
        if bi >= 1:
            mx.add(I.glock(R.choice(["A6", "D7", "F#6"]), 0.22), t0, 0.6, 0.3, 0.6)
    # leap boing at 6.6 is SFX; moon rise 8-9.5: rising string colour
    n = int(1.6 * SR); sw = np.sin(np.linspace(0, np.pi, n)) ** 1.5
    pad(mx, ["F#5", "B5", "D6"], 8.0, 1.6, gain=0.08, cutoff=5000, env=sw, vib=12, voices=3, width=0.9)
    # pickup fill 12.5-13.0
    for k, nm in enumerate(["E5", "A5", "C#6", "E6"]):
        mx.add(I.celesta(nm, 0.45), 12.5 + k * 0.125, 0.3 - 0.2 * k, 0.35, 0.4)

    # ======================================================= SCENE 3  13-21  "Galaxy of learning"
    bars3 = [(13.0, "G"), (15.0, "A"), (17.0, "F#m"), (19.0, "Bm")]
    for bi, (t0, ch) in enumerate(bars3):
        strings_line(mx, THEME_B[bi], t0, gain=0.19, cutoff=3600, pan=0.1)
        play_line(mx, I.celesta, THEME_B[bi], t0, pan=0.25, gain=0.18, send=0.4, vel=0.6)
        pad(mx, CH[ch]["lo"] + CH[ch]["mid"][:2], t0, 4 * BEAT + 0.3, gain=0.14, cutoff=1800, att=0.25, rel=0.4)
        # harp arpeggio in 8ths (up and down)
        tones = chord_tones(ch, n2m("D4"), n2m("E6"))[:7]
        seq = tones + tones[-2:0:-1]
        for k in range(8):
            m = seq[k % len(seq)]
            mx.add(I.harp(mname(m), hv(0.42 if k % 2 == 0 else 0.32), seed=k + bi * 10),
                   hum(t0 + k * 0.25), -0.45 + 0.9 * k / 7, 0.42, 0.35)
        # glock arpeggio sparkle on 16ths in the last beat
        for k, m in enumerate(chord_tones(ch, n2m("D6"), n2m("A7"))[:4]):
            mx.add(I.glock(mname(m), 0.15 + 0.03 * k), t0 + 3 * BEAT + k * 0.125, 0.5, 0.35, 0.6)
        # driving pizz bass in 8ths
        bn = CH[ch]["b"]; r2 = mname(n2m(bn) + 12); r3 = mname(n2m(bn) + 24); f2 = mname(n2m(bn) + 19)
        line = [(r2, 0, .5, .75), (r2, .5, .5, .45), (r3, 1, .5, .55), (r2, 1.5, .5, .45),
                (f2, 2, .5, .65), (r2, 2.5, .5, .45), (r3, 3, .5, .55), (f2, 3.5, .5, .5)]
        play_line(mx, I.pizz, line, t0, pan=-0.1, gain=0.5, send=0.15)
        # light percussion
        for b, v in [(0, .55), (2, .45), (2.75, .3)]:
            mx.add(I.kick(v), t0 + b * BEAT, 0.0, 0.5, 0.05)
        for k in range(8):
            mx.add(I.shaker(hv(0.45 if k % 2 else 0.28, .15), seed=k + 100 * bi), hum(t0 + k * 0.25, .003), 0.4,
                   0.35, 0.15)
        for b, nm, v in [(3.5, "A3", .32), (3.75, "E3", .38)]:
            mx.add(I.tom(nm, v, seed=int(b * 10) + bi), t0 + b * BEAT, -0.3 if nm == "A3" else 0.3, 0.45, 0.2)

    # ======================================================= SCENE 4  21-29  "Learning through play"
    bars4 = [(21.0, "D"), (23.0, "G"), (25.0, "Em"), (27.0, "A")]
    for bi, (t0, ch) in enumerate(bars4):
        tones = chord_tones(ch, n2m("D5"), n2m("F#6"))
        r_, th, fi = tones[0], tones[1], tones[2]
        octv = tones[3] if len(tones) > 3 else r_ + 12
        motif = [(mname(r_), 0, .5, .6), (mname(th), .5, .5, .45), (mname(fi), 1, .5, .55), (mname(th), 1.5, .5, .4),
                 (mname(octv), 2, 1, .6), (mname(fi), 3, .5, .45)]
        play_line(mx, I.pizz, motif, t0, pan=0.3, gain=0.62, send=0.3)
        # celesta / music-box answer on beats 3.5-4
        ans = [(mname(octv + 7 if (octv + 7) % 12 in [PC[p] for p in CH[ch]["arp"]] else octv + 12), 3.5, .5)]
        play_line(mx, I.celesta, ans, t0, pan=-0.3, gain=0.42, send=0.45, vel=0.55)
        pad(mx, CH[ch]["mid"], t0, 4 * BEAT + 0.3, gain=0.16, cutoff=1600, att=0.3, rel=0.4)
        bn = CH[ch]["b"]; r2 = mname(n2m(bn) + 12)
        play_line(mx, I.pizz, [(r2, 0, 1, .7), (mname(n2m(bn) + 19), 1.5, .5, .4), (r2, 2, 1, .6),
                               (mname(n2m(bn) + 24), 3, .5, .45)], t0, pan=-0.15, gain=0.68, send=0.15)
        for b in (0, 2):
            mx.add(I.kick(0.42), t0 + b * BEAT, 0, 0.58, 0.05)
        for b in (1, 3):
            mx.add(I.rim(0.4, seed=bi * 3 + b), hum(t0 + b * BEAT, .003), -0.25, 0.42, 0.2)
        for k in range(8):
            mx.add(I.shaker(hv(0.36 if k % 2 else 0.22, .2), seed=k + 300 * bi), hum(t0 + k * 0.25, .003), 0.45,
                   0.32, 0.15)
        mx.add(I.marimba(mname(tones[0] - 12), 0.35), t0 + 2.5 * BEAT, -0.4, 0.45, 0.3)
    # magical chimes: soft glock tail at the end of the section (portal open handled in SFX)
    for k, nm in enumerate(["E6", "A6", "C#7"]):
        mx.add(I.glock(nm, 0.18), 28.5 + k * 0.15, 0.4 - 0.4 * k, 0.4, 0.6)

    # ======================================================= SCENE 5  29-37  "Live competition"
    # 29-31 Bm drive
    t0 = 29.0
    pad(mx, ["B1", "B2", "F#3"], t0, 2.1, gain=0.22, cutoff=1400, att=0.03, rel=0.3, voices=4)
    pad(mx, ["B3", "D4", "F#4", "B4"], t0, 0.35, gain=0.22, cutoff=4000, att=0.01, rel=0.2, voices=4)  # stab
    ost = ["B3", "B3", "F#4", "B3", "D4", "B3", "F#4", "D4"]
    for k, nm in enumerate(ost):
        y = I.ensemble([nm, mname(n2m(nm) + 12)], 0.2, att=0.008, rel=0.12, cutoff=3500, voices=3, vib_cents=0,
                       seed=k, width=0.5)
        mx.add(y, t0 + k * 0.25, gain=0.30 if k % 2 == 0 else 0.22, send=0.2)
    for b, v in [(0, 1.0), (.75, .5), (1.5, .6), (2, .8), (2.5, .5), (3, .7), (3.5, .55)]:
        mx.add(I.taiko(hv(v) * 0.75, seed=int(b * 4)), t0 + b * BEAT, 0.0, 0.55, 0.15)
    for b in (1, 3):
        mx.add(I.snare_soft(0.35, seed=int(b)), t0 + b * BEAT, 0.2, 0.4, 0.2)
    # 31-35 countdown: A pedal pulse, rising violins, drums only on the beeps
    for k in range(16):
        t = 31.0 + k * 0.25
        nm = "A2" if k % 2 == 0 else "A3"
        y = I.ensemble([nm], 0.18, att=0.006, rel=0.1, cutoff=2200, voices=3, vib_cents=0, seed=50 + k, width=0.4)
        mx.add(y, t, gain=0.3 + 0.12 * (k / 15), send=0.15)
    for k, nm in enumerate(["D5", "E5", "F#5", "G5"]):
        n = int(1.05 * SR); env = np.minimum(np.linspace(0.4, 1, n), 1) * adsr(n, 0.08, 0.1, 1, 0.12)
        pad(mx, [nm, mname(n2m(nm) - 12)], 31.0 + k, 1.05, gain=0.12 + 0.03 * k, cutoff=3800, env=env, vib=14,
            voices=4, width=0.7)
    pad(mx, ["A1", "E2"], 31.0, 4.0, gain=0.18, cutoff=600, att=0.4, rel=0.15, voices=3)
    for k in range(4):
        mx.add(I.taiko(0.45 + 0.08 * k, seed=60 + k, decay=0.6), 31.0 + k, 0.0, 0.5, 0.15)
    # snare roll crescendo into 35
    tt = np.arange(34.0, 34.93, 0.0625)
    for k, t in enumerate(tt):
        mx.add(I.snare_soft(0.08 + 0.32 * (k / len(tt)) ** 1.5, seed=200 + k), t, 0.15, 0.4, 0.25)
    # 35 BURST: D major tutti
    t0 = 35.0
    pad(mx, ["D2", "A2", "D3", "F#3", "A3", "D4", "F#4", "A4"], t0, 2.1, gain=0.28, cutoff=4200, att=0.015, rel=0.6,
        voices=4)
    pad(mx, ["D3", "A3", "F#4", "D5"], t0, 1.0, gain=0.14, cutoff=3000, att=0.01, rel=0.5, voices=3, vib=4,
        width=0.5, spread=6)  # brass-like
    mx.add(I.crash(0.55, 2.5, seed=3), t0, 0.25, 0.6, 0.3)
    mx.add(I.taiko(1.0, f0=55, seed=9, decay=1.2), t0, 0, 0.5, 0.2)
    mx.add(I.kick(0.8), t0, 0, 0.5, 0.05)
    for k, nm in enumerate(["D6", "E6", "F#6", "A6", "B6", "D7"]):
        mx.add(I.celesta(nm, 0.5), t0 + k * 0.05, -0.5 + 0.2 * k, 0.3, 0.45)
    theme_head = [("F#5", 0, .5), ("A5", .5, .5), ("D6", 1, 1), ("C#6", 2, .5), ("B5", 2.5, .5), ("A5", 3, 1)]
    strings_line(mx, theme_head, t0, gain=0.3, cutoff=4500)
    play_line(mx, I.celesta, theme_head, t0, pan=0.2, gain=0.35, send=0.35, vel=0.8)
    pad(mx, ["C#2", "A2", "E3", "A3", "C#4", "E4"], 36.0, 1.1, gain=0.24, cutoff=3200, att=0.05, rel=0.5)
    for b, v in [(.5, .45), (1, .7), (1.5, .45), (2, .8), (2.25, .45), (2.5, .55), (3, .7), (3.25, .5), (3.5, .75)]:
        mx.add(I.taiko(hv(v) * 0.7, seed=int(b * 8) + 70), t0 + b * BEAT, 0, 0.5, 0.15)
    for k, nm in enumerate(["A3", "F#3", "D3"]):
        mx.add(I.tom(nm, 0.4 + 0.1 * k, seed=80 + k), 36.625 + k * 0.125, 0.3 - 0.3 * k, 0.45, 0.2)

    # ======================================================= SCENE 6  37-44  "Power of progress" (piano + strings)
    bars6 = [(37.0, "G", 4), (39.0, "A", 4), (41.0, "F#m", 2), (42.0, "Bm", 2), (43.0, "Em7", 2)]
    for t0, ch, L in bars6:
        bn = n2m(CH[ch]["b"]) + 12
        mx.add(I.piano(mname(bn), 0.42, dur=L * BEAT + 0.2, seed=int(t0)), t0, -0.25, 0.68, 0.3)
        tones = chord_tones(ch, n2m("D3") if bn < n2m("D3") else bn + 5, n2m("D5"))[:5]
        seq = tones + tones[-2:0:-1]
        for k in range(1, L * 2):
            m = seq[(k - 1) % len(seq)]
            mx.add(I.piano(mname(m), hv(0.24 if k % 2 else 0.3), dur=0.6, seed=int(t0 * 10) + k),
                   hum(t0 + k * 0.25, .006), -0.1 + 0.3 * (m - 50) / 24, 0.56, 0.3)
        pad(mx, CH[ch]["lo"] + CH[ch]["mid"], t0, L * BEAT + 0.4, gain=0.15, cutoff=1500, att=0.5, rel=0.55, vib=7)
    mel6 = [(37.0, [("B4", 0, 1), ("D5", 1, .5), ("G5", 1.5, 1.5), ("F#5", 3, .5), ("E5", 3.5, .5)]),
            (39.0, [("E5", 0, 1), ("C#5", 1, .5), ("A4", 1.5, .5), ("E5", 2, 2)]),
            (41.0, [("C#5", 0, 1), ("A4", 1, 1), ("D5", 2, 1), ("F#5", 3, 1)]),
            (43.0, [("E5", 0, 1.5), ("F#5", 1.5, .5)])]
    for t0, notes in mel6:
        play_line(mx, I.piano, notes, t0, pan=0.15, gain=0.68, send=0.35, vel=0.45)
    # cello counter-line
    strings_line(mx, [("G3", 0, 4), ("A3", 4, 4), ("C#4", 8, 2), ("D4", 10, 2), ("B3", 12, 2)], 37.0, gain=0.14,
                 cutoff=1600, vib=8, att=0.3)

    # ======================================================= SCENE 7  44-51  "Big reveal"
    # build 44-48: strings crescendo with opening filter
    n = int(4.05 * SR); tt = np.arange(n) / SR
    env = 0.25 + 0.75 * (tt / 4.0) ** 1.8; env[-int(0.05 * SR):] *= np.linspace(1, 0.3, int(0.05 * SR))
    cenv = 1200 + 4800 * (tt / 4.0) ** 2
    full = np.zeros(n)
    pad(mx, ["G1", "G2", "D3", "G3", "B3", "D4", "G4"], 44.0, 2.05, gain=0.30,
        env=xf(env[:int(2.05 * SR)], 0.12, 0.06), cutoff_env=cenv[:int(2.05 * SR)])
    pad(mx, ["A1", "A2", "E3", "A3", "D4", "E4", "A4"], 46.0, 1.03, gain=0.30,
        env=xf(env[int(2.0 * SR):int(3.03 * SR)], 0.05, 0.05), cutoff_env=cenv[int(2.0 * SR):int(3.03 * SR)])
    pad(mx, ["A1", "A2", "E3", "A3", "C#4", "E4", "A4"], 47.0, 1.0, gain=0.30,
        env=xf(env[int(3.0 * SR):int(4.0 * SR)], 0.04, 0.02), cutoff_env=cenv[int(3.0 * SR):int(4.0 * SR)])
    strings_line(mx, [("G5", 0, 4, .8), ("A5", 4, 2, .9)], 44.0, gain=0.22, cutoff=4500, vib=12)
    run = ["A5", "B5", "C#6", "D6", "E6", "F#6", "G6", "G#6"][:7]
    strings_line(mx, [(nm, 6 + k * 2 / 7, 2 / 7, 0.8 + 0.03 * k) for k, nm in enumerate(run)], 44.0, gain=0.24,
                 cutoff=5000, vib=6, att=0.02)
    # pizz ostinato in 8ths, crescendo
    for k in range(16):
        t = 44.0 + k * 0.25
        nm = ["G3", "D4", "G4", "D4"][k % 4] if t < 46 else ["A3", "E4", "A4", "E4"][k % 4]
        mx.add(I.pizz(nm, 0.3 + 0.3 * k / 15), hum(t), -0.3, 0.4, 0.2)
    # accelerating shimmer
    t = 44.0; k = 0
    shimmer = chord_tones("D", n2m("D6"), n2m("A7"))
    while t < 47.95:
        dt = 0.42 * (1 - (t - 44) / 4.2) ** 1.6 + 0.04
        m = shimmer[k % len(shimmer)] if t < 46 else (shimmer + [n2m("E7"), n2m("C#7")])[k % (len(shimmer) + 2)]
        mx.add(I.glock(mname(m), 0.10 + 0.14 * (t - 44) / 4, seed=900 + k, t60=1.0), t, R.uniform(-.7, .7), 0.45, 0.6)
        t += dt; k += 1
    # drums build
    for t, v in [(44.0, .55), (45.0, .5), (46.0, .65), (46.5, .55), (47.0, .7), (47.25, .6), (47.5, .75),
                 (47.625, .65), (47.75, .8), (47.875, .85)]:
        mx.add(I.taiko(v * 0.7, seed=int(t * 8)), t, 0, 0.55, 0.15)
    for k, t in enumerate(np.arange(47.0, 47.94, 0.0625)):
        mx.add(I.tom("D2" if k % 2 else "A2", 0.12 + 0.3 * k / 15, seed=400 + k, decay=0.25), t,
               -0.3 if k % 2 else 0.3, 0.45, 0.2)
    mx.add(I.cymbal_swell(2.0, 0.8, seed=11), 46.0, 0.0, 0.7, 0.4)

    # 48.0 LOGO HIT -- tutti D major + returning star theme
    t0 = 48.0
    hit_env_n = int(3.1 * SR)
    henv = adsr(hit_env_n, 0.012, 0.6, 0.72, 0.15)
    pad(mx, ["D1", "D2", "A2", "D3", "F#3", "A3", "D4", "F#4", "A4", "D5"], t0, 2.05, gain=0.36,
        env=henv[:int(2.05 * SR)], cutoff=4800, voices=4)
    # brass-ish (narrow detune, slower vibrato, filtered saw)
    pad(mx, ["D3", "A3", "D4", "F#4"], t0, 2.05, gain=0.16, att=0.02, rel=0.3, cutoff=2600, voices=3, vib=4,
        spread=5, width=0.5)
    mx.add(I.crash(0.75, 3.5, seed=21), t0, -0.2, 0.7, 0.35)
    mx.add(I.crash(0.5, 3.0, seed=22), t0 + 0.01, 0.3, 0.6, 0.35)
    mx.add(I.taiko(1.0, f0=48, seed=31, decay=1.6), t0, 0, 0.8, 0.25)
    mx.add(I.kick(0.9), t0, 0, 0.6, 0.05)
    mx.add(I.piano("D1", 0.8, dur=2.0, seed=5), t0, -0.2, 0.6, 0.3)
    mx.add(I.piano("D2", 0.8, dur=2.0, seed=6), t0, -0.1, 0.5, 0.3)
    # theme returns (seconds relative to 48)
    climax = [("F#5", 0, 1), ("A5", 1, .5), ("D6", 1.5, 1.5), ("C#6", 3, .5), ("B5", 3.5, .5),
              ("D6", 4, 1), ("E6", 5, .5), ("C#6", 5.5, .5), ("D6", 6, 5, 0.9)]
    strings_line(mx, climax[:-1] + [("D6", 6, 4.5, 0.9)], t0, gain=0.30, cutoff=5000, vib=12)
    strings_line(mx, [(mname(n2m(a) - 12),) + tuple(r[1:3]) for r in climax[:-1] for a in [r[0]]] +
                 [("D5", 6, 4.5)], t0, gain=0.20, cutoff=3500, vib=10)
    play_line(mx, I.celesta, climax, t0, pan=0.25, gain=0.38, send=0.4, vel=0.8)
    play_line(mx, I.glock, [(mname(n2m(r[0]) + 12),) + tuple(r[1:3]) for r in climax], t0, pan=-0.3, gain=0.2,
              send=0.5, vel=0.4)
    # 50 G -> 50.5 Asus4 -> 50.75 A -> 51 D (add9) with suspension
    pad(mx, ["G1", "G2", "D3", "G3", "B3", "D4", "G4", "B4"], 50.0, 0.55, gain=0.32, att=0.03, rel=0.12, cutoff=4500)
    pad(mx, ["A1", "A2", "E3", "A3", "D4", "E4", "A4"], 50.5, 0.3, gain=0.32, att=0.02, rel=0.08, cutoff=4500)
    pad(mx, ["A1", "A2", "E3", "A3", "C#4", "E4", "A4"], 50.75, 0.3, gain=0.32, att=0.02, rel=0.08, cutoff=4500)
    for t, v in [(48.5, .4), (49.0, .6), (49.5, .45), (50.0, .7), (50.5, .6), (50.75, .65)]:
        mx.add(I.taiko(v * 0.7, seed=int(t * 9)), t, 0, 0.55, 0.15)
    for k in range(12):  # sparkle arpeggio over the climax
        ct = chord_tones("D", n2m("A5"), n2m("D7")); m = ct[k % len(ct)]
        mx.add(I.celesta(mname(m), 0.3), 48.0 + 0.125 + k * 0.166, 0.6 - 0.1 * k, 0.18, 0.5)

    # ======================================================= SCENE 8  51-55  final chord rings out
    t0 = 51.0
    n = int(3.9 * SR); tt = np.arange(n) / SR
    fenv = np.minimum(tt / 0.03, 1) * np.where(tt < 1.2, 1.0, np.cos(np.clip((tt - 1.2) / 2.6, 0, 1) * np.pi / 2) ** 2)
    pad(mx, ["D2", "A2", "D3", "F#3", "A3", "E4", "F#4", "A4", "D5"], t0, 3.9, gain=0.30, env=fenv, cutoff=3200,
        vib=8)
    mx.add(I.taiko(0.7, f0=50, seed=41, decay=1.4), t0, 0, 0.6, 0.25)
    mx.add(I.crash(0.35, 3.0, seed=42), t0, 0.1, 0.5, 0.4)
    for k, nm in enumerate(["D2", "A2", "D3", "F#3", "A3", "E4", "F#4"]):
        mx.add(I.piano(nm, 0.45, dur=3.4, seed=500 + k), t0 + k * 0.018, -0.3 + 0.1 * k, 0.45, 0.35)
    for k, nm in enumerate(["A5", "D6", "F#6", "A6", "D7"]):
        mx.add(I.celesta(nm, 0.35 - 0.03 * k), 53.4 + k * 0.12, -0.4 + 0.2 * k, 0.32, 0.6)
    mx.add(I.musicbox("A6", 0.35), 54.05, 0.3, 0.35, 0.6)
    mx.add(I.musicbox("D7", 0.3), 54.1, -0.3, 0.3, 0.6)

    return mx


# per-scene balance (dB); each change ramps over the 0.3 s *before* the boundary so hits land at full level
SECTION_GAIN_DB = [(0.0, -3.0), (6.0, 0.0), (13.0, -1.5), (21.0, 1.0), (29.0, 0.0), (35.0, -1.5), (37.0, 0.0),
                   (48.0, 1.0), (51.0, 0.5)]


def section_gain():
    tt = np.arange(N) / SR
    g = np.full(N, SECTION_GAIN_DB[0][1])
    for (t, db), (_, prev) in zip(SECTION_GAIN_DB[1:], SECTION_GAIN_DB[:-1]):
        ramp = np.clip((tt - (t - 0.3)) / 0.3, 0, 1)
        ramp = 0.5 - 0.5 * np.cos(np.pi * ramp)
        g = np.where(tt >= t - 0.3, prev + (db - prev) * ramp, g)
    return 10 ** (g / 20)


def finish(mx):
    ir = make_ir(t60=2.2, predelay=0.02, seed=7)
    wet = convolve_st(mx.send, ir)
    wet = hp(wet, 160)
    wet = shelf(wet, 6000, -3, high=True)
    y = mx.dry + 0.45 * wet
    y *= section_gain()[None, :]
    y = hp(y, 28, order=2)
    y = shelf(y, 9000, -1.5, high=True)
    y = peq(y, 250, -1.0, 0.8)
    y = compress(y, thr_db=-16, ratio=1.8, att=0.02, rel=0.25)
    # final fade: let the last chord ring, silent by ~54.72 s
    tt = np.arange(N) / SR
    f = np.where(tt < 53.6, 1.0, np.cos(np.clip((tt - 53.6) / 1.12, 0, 1) * np.pi / 2) ** 1.5)
    y *= f[None, :]
    y[:, :int(0.002 * SR)] *= np.linspace(0, 1, int(0.002 * SR))
    return y


def _wrap():
    import functools
    for nm in ["celesta", "musicbox", "glock", "bell", "marimba", "piano", "pizz", "harp", "ensemble", "kick", "tom",
               "taiko", "shaker", "rim", "snare_soft", "crash", "cymbal_swell"]:
        f = getattr(I, nm)

        def w(*a, _f=f, _n=nm, **k):
            LAST[0] = _n
            return _f(*a, **k)
        setattr(I, nm, functools.wraps(f)(w))


def analyse():
    global ANALYSE
    ANALYSE = True
    _wrap()
    mx = render()
    secs = [(0, 6), (6, 13), (13, 21), (21, 29), (29, 31), (31, 35), (35, 37), (37, 44), (44, 48), (48, 51), (51, 55)]
    tags = sorted(mx.stems)
    print("sec      " + " ".join(f"{t[:7]:>7}" for t in tags) + "   total")
    for a, b in secs:
        row = []
        for t in tags:
            e = mx.stems[t][a * 100:b * 100].sum() / ((b - a) * SR)
            row.append(f"{10 * np.log10(e + 1e-12):7.1f}")
        tot = np.mean(mx.dry[:, a * SR:b * SR] ** 2)
        print(f"{a:2d}-{b:2d}    " + " ".join(row) + f"  {10 * np.log10(tot):6.1f}")


if __name__ == "__main__":
    import sys
    if "--analyse" in sys.argv:
        analyse(); sys.exit()
    import soundfile as sf, os, time
    t = time.time()
    mx = render()
    y = finish(mx)
    os.makedirs("out", exist_ok=True)
    y = y / np.max(np.abs(y)) * 0.7
    sf.write("out/music_test.wav", y.T, SR, subtype="PCM_24")
    print("done", time.time() - t)
