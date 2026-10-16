"""Sound-design stem for the STARMAH promo. Every event is synthesized and logged to EVENTS
(time = sync point: onset for hits/taps, peak for whooshes; start/end for long sounds)."""
import numpy as np
from dsp import SR, N, hz, n2m, lp, hp, bp, peq, shelf, noise, pan_st, make_ir, convolve_st, compress, rng, \
    expdec, fade, svf_sweep, midi_hz
import instruments as I
from music import Mixer

R = rng(777)
EVENTS = []
PENTA = ["D", "E", "F#", "A", "B"]


def ev(name, t, start=None, end=None):
    e = dict(name=name, time=round(t, 3))
    if start is not None:
        e["start"] = round(start, 3)
        e["end"] = round(end, 3)
    EVENTS.append(e)


def _t(d):
    return np.arange(int(d * SR)) / SR


# ----------------------------------------------------------------------------- building blocks
def ping(note, vel=0.5, seed=0):
    return I.glock(note, vel, seed=seed, t60=float(np.clip(1.1 * (1500 / hz(note)) ** 0.4, 0.4, 1.4)))


def penta_note(lo="D6", hi="D8"):
    ms = [m for m in range(n2m(lo), n2m(hi) + 1) if m % 12 in (2, 4, 6, 9, 11)]
    m = int(R.choice(ms))
    names = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "A#", "B"]
    return f"{names[m % 12]}{m // 12 - 1}"


def sparkle_cloud(mx, t0, dur, count, lo="D6", hi="A7", vel=(0.08, 0.25), decay_density=True, pan=(-0.9, 0.9),
                  gain=1.0, send=0.55):
    for k in range(count):
        u = R.exponential(0.35) if decay_density else R.uniform()
        dt = min(u, 1.0) * dur
        v = R.uniform(*vel) * (1 - 0.6 * dt / dur if decay_density else 1)
        mx.add(ping(penta_note(lo, hi), v, seed=int(R.integers(1e9))), t0 + dt, R.uniform(*pan), gain, send)


def whoosh(dur=1.0, peak=0.55, f_lo=300, f_hi=3500, q=1.2, pan=(-0.7, 0.7), seed=0, tone=False, color="pink"):
    """Air pass-by: band-passed noise with centre frequency + level rising to `peak` (fraction)
    then falling; equal-power pan travels across. Returns stereo."""
    n = int(dur * SR)
    tt = np.arange(n) / n
    shape = np.where(tt < peak, (tt / peak) ** 2.2, np.exp(-((tt - peak) / (1 - peak)) * 3.5))
    fc = f_lo + (f_hi - f_lo) * np.where(tt < peak, (tt / peak) ** 1.5, 1 - 0.6 * (tt - peak) / (1 - peak))
    x = noise(n, seed, color)
    y = svf_sweep(x, fc, q=q, mode="bp")
    y = y / (np.std(y) + 1e-9)
    y *= shape
    if tone:
        f = fc * 0.5
        y += 0.15 * np.sin(2 * np.pi * np.cumsum(f) / SR) * shape
    p = pan[0] + (pan[1] - pan[0]) * np.clip(tt / max(peak * 1.6, 1e-3), 0, 1)
    y = lp(y, 9000)
    return fade(pan_st(y, p), 0.003, 0.03) * 0.25


def sub_boom(f0=55, f1=36, dur=2.0, vel=1.0):
    t = _t(dur)
    f = f1 + (f0 - f1) * np.exp(-t / 0.25)
    y = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / (dur / 4)) * (1 - np.exp(-t / 0.004))
    y += 0.4 * lp(noise(len(t), 3), 180) * np.exp(-t / 0.3)
    return fade(y, 0, 0.1) * vel


def impact(vel=1.0, seed=0, dur=0.6):
    t = _t(dur)
    y = lp(noise(len(t), seed), 3500) * np.exp(-t / 0.06)
    y += 0.6 * bp(noise(len(t), seed + 1), 120, 600) * np.exp(-t / 0.15)
    return fade(y, 0, 0.05) * vel


def ui_tap(vel=0.5, seed=0):
    t = _t(0.06)
    y = np.sin(2 * np.pi * 1450 * t) * np.exp(-t / 0.008) * 0.6
    y += bp(noise(len(t), seed), 2000, 6000) * np.exp(-t / 0.003) * 0.35
    y += np.sin(2 * np.pi * 520 * t) * np.exp(-t / 0.012) * 0.4
    return fade(y, 0.0005, 0.01) * vel


def soft_tone(f, dur=0.2, vel=0.5, vib=0.0, bend=0.0, harm=(1.0, 0.25, 0.08)):
    t = _t(dur)
    fr = f * (1 + bend * (t / dur)) * (1 + vib * np.sin(2 * np.pi * 6 * t))
    ph = 2 * np.pi * np.cumsum(fr) / SR
    y = sum(a * np.sin((k + 1) * ph) for k, a in enumerate(harm))
    env = (1 - np.exp(-t / 0.008)) * np.exp(-t / (dur / 2.2))
    return fade(y * env, 0.002, 0.03) * vel


def bubble(f0, dur=0.07, vel=0.4):
    t = _t(dur)
    f = f0 * (1 + 1.8 * (t / dur) ** 1.4)
    y = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / (dur / 3)) * (1 - np.exp(-t / 0.002))
    return fade(y, 0, 0.01) * vel


def page_turn(seed=0, dur=0.5):
    r = rng(seed)
    n = int(dur * SR)
    t = np.arange(n) / SR
    crinkle = np.zeros(n)
    sw = np.sin(np.pi * np.clip(t / dur, 0, 1)) ** 1.5 * np.exp(-t / (dur * 0.7))
    for k in range(70):
        i = int(min(r.beta(2, 3), 0.999) * n)
        L = int(r.uniform(0.002, 0.012) * SR)
        g = r.uniform(0.2, 1.0)
        e = np.exp(-np.arange(L) / (L / 4))
        crinkle[i:i + L] += g * e[:max(0, min(L, n - i))]
    x = bp(noise(n, seed + 1), 900, 7000) * (0.35 * sw + 0.65 * crinkle * sw)
    flap = bp(noise(n, seed + 2), 150, 900) * np.exp(-((t - dur * 0.35) / (dur * 0.18)) ** 2)
    y = x + 0.6 * flap
    y = lp(y, 7500)
    return fade(y / (np.max(np.abs(y)) + 1e-9), 0.005, 0.05) * 0.5


def applause(dur, seed=0, density=110, env=None):
    r = rng(seed)
    n = int(dur * SR)
    y = np.zeros(n)
    tt = np.arange(n) / SR
    env = env if env is not None else np.ones(n)
    k = 0
    t = 0.0
    while t < dur:
        i = int(t * SR)
        L = int(r.uniform(0.006, 0.014) * SR)
        f1 = r.uniform(700, 1600)
        c = bp(r.standard_normal(L), f1, f1 * r.uniform(2.2, 3.5)) * np.exp(-np.arange(L) / (L / 4))
        e = env[min(i, n - 1)]
        if i + L < n:
            y[i:i + L] += c * r.uniform(0.3, 1.0) * e
        t += r.exponential(1 / (density * max(e, 0.05)))
        k += 1
    return lp(y, 6500)


def cheer(dur, seed=0):
    """Soft 'aaah' crowd bed: formant-filtered noise swarm (no human voice samples)."""
    n = int(dur * SR)
    tt = np.arange(n) / SR
    x = noise(n, seed, "pink")
    y = 0.0
    for f1, g in [(650, 1.0), (1100, 0.7), (2500, 0.3)]:
        y = y + g * bp(x, f1 * 0.85, f1 * 1.18)
    am = 1 + 0.25 * np.sin(2 * np.pi * 3.1 * tt + seed) + 0.15 * np.sin(2 * np.pi * 5.3 * tt)
    return lp(y * am, 5000)


# ----------------------------------------------------------------------------- the event list
def render():
    mx = Mixer()

    # ---------------------------------------------------- scene 1
    t = 0.4
    mx.add(I.bell("F#5", 0.5, length=4.0, seed=1, bright=0.5), t, -0.25, 0.42, 0.7)
    mx.add(ping("A6", 0.2), t + 0.02, 0.3, 0.6, 0.7)
    ev("distant_magic_bell", t)
    for t, sd, p in [(2.6, 11, (-0.4, 0.2)), (3.4, 12, (0.3, -0.2))]:
        y = page_turn(sd)
        n = len(y); pp = np.linspace(p[0], p[1], n)
        mx.add(pan_st(y, pp), t, gain=0.55, send=0.25)
        ev("page_turn_rustle", t)
    # rising shimmer 4.0-4.8
    t0, d = 4.0, 0.8
    k = 0
    while True:
        u = (k / 38) ** 0.6
        if u >= 1: break
        tt = t0 + d * u
        f = 1800 + 4200 * u + R.uniform(-200, 200)
        y = soft_tone(f, 0.12, 0.10 + 0.12 * u, harm=(1.0,))
        mx.add(y, tt, R.uniform(-0.8, 0.8), 0.6, 0.6)
        k += 1
    w = whoosh(0.85, peak=0.97, f_lo=1200, f_hi=7000, q=2.0, pan=(-0.2, 0.2), seed=41)
    mx.add(w, t0, gain=0.35, send=0.5)
    ev("rising_shimmer", 4.0, 4.0, 4.8)
    # sparkle burst 4.8
    t = 4.8
    sparkle_cloud(mx, t, 1.2, 46, "D6", "D8", vel=(0.1, 0.32), gain=0.7)
    pf = lp(noise(int(0.5 * SR), 51), 2500) * np.exp(-_t(0.5) / 0.09)
    mx.add(fade(pf, 0.002, 0.05) * 0.25, t, 0, 0.6, 0.5)
    mx.add(soft_tone(82, 0.35, 0.5, harm=(1.0, 0.2)), t, 0, 0.5, 0.2)
    ev("sparkle_burst_constellation", t)
    # warm swell / whoosh 5.2-6.0
    w = whoosh(1.0, peak=0.78, f_lo=250, f_hi=2600, q=0.9, pan=(0.4, -0.1), seed=52)
    mx.add(w, 5.2, gain=0.55, send=0.5)
    sw_n = int(0.95 * SR); st = np.arange(sw_n) / sw_n
    sub = np.sin(2 * np.pi * 73.4 * np.arange(sw_n) / SR) * st ** 2 * np.exp(-np.maximum(st - 0.85, 0) * 30)
    mx.add(fade(sub, 0.01, 0.05) * 0.25, 5.2, 0, 1.0, 0.1)
    ev("warm_light_swell", 5.95, 5.2, 6.0)

    # ---------------------------------------------------- scene 2
    t = 6.6
    d = 0.42
    tt = _t(d)
    f = 300 * 2 ** (1.6 * np.clip(tt / 0.26, 0, 1)) * (1 + 0.06 * np.exp(-tt / 0.12) * np.sin(2 * np.pi * 16 * tt))
    ph = 2 * np.pi * np.cumsum(f) / SR
    y = (np.sin(ph) + 0.2 * np.sin(2 * ph) + 0.06 * np.sin(3 * ph)) * (1 - np.exp(-tt / 0.006)) * np.exp(-tt / 0.16)
    mx.add(fade(y, 0.002, 0.03) * 0.28, t, 0.1, 1.0, 0.35)
    mx.add(I.celesta("A6", 0.4), t + 0.24, 0.25, 0.4, 0.5)
    mx.add(ping("D7", 0.18), t + 0.3, -0.2, 0.6, 0.6)
    ev("star_leap_boing_chime", t)
    # magical rise 8.0-9.5
    scale = [m for m in range(n2m("D5"), n2m("E7") + 1) if m % 12 in (2, 4, 6, 7, 9, 11, 1)]
    for k, m in enumerate(scale):
        u = k / (len(scale) - 1)
        names = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "A#", "B"]
        nm = f"{names[m % 12]}{m // 12 - 1}"
        mx.add(ping(nm, 0.12 + 0.2 * u, seed=k), 8.0 + 1.45 * u ** 0.9, -0.6 + 1.2 * u, 0.6, 0.6)
    w = whoosh(1.6, peak=0.85, f_lo=700, f_hi=6000, q=1.6, pan=(-0.3, 0.3), seed=61)
    mx.add(w, 8.0, gain=0.45, send=0.6)
    ev("moon_magic_rise", 8.0, 8.0, 9.5)
    t = 9.6
    mx.add(ping("A6", 0.3), t, 0.25, 0.7, 0.6)
    mx.add(ping("E7", 0.22), t + 0.07, 0.35, 0.7, 0.6)
    ev("moon_blink_twinkle", t)
    t = 10.5
    for k, nm in enumerate(["D5", "F#5", "A5"]):
        mx.add(I.bell(nm, 0.3, length=2.2, seed=70 + k, bright=0.3), t + k * 0.02, -0.2 + 0.2 * k, 0.32, 0.6)
    pil = lp(noise(int(0.3 * SR), 71), 400) * np.exp(-_t(0.3) / 0.06)
    mx.add(fade(pil, 0.004, 0.05) * 0.4 + soft_tone(110, 0.3, 0.3, harm=(1.0,)), t, 0, 0.6, 0.2)
    ev("soft_hug_chime", t)
    # ring shimmer swoosh 11.0-12.5
    d = 1.5; n = int(d * SR); tt = np.arange(n) / SR; u = tt / d
    fc = 900 + 4200 * np.sin(np.pi * u) ** 0.8
    y = svf_sweep(noise(n, 81, "pink"), fc, q=2.2)
    y = y / (np.std(y) + 1e-9) * np.sin(np.pi * u) ** 1.6
    pan = 0.75 * np.sin(2 * np.pi * 0.9 * tt)
    mx.add(fade(pan_st(lp(y, 8500), pan), 0.01, 0.05) * 0.12, 11.0, send=0.5)
    sh = np.zeros(n)
    for m in [n2m("D6"), n2m("F#6"), n2m("A6"), n2m("D7")]:
        sh += np.sin(2 * np.pi * float(midi_hz(m)) * tt + R.uniform(0, 6)) * (0.6 + 0.4 * np.sin(2 * np.pi * 9 * tt + m))
    sh *= np.sin(np.pi * u) ** 2 * 0.04
    mx.add(pan_st(sh, -pan * 0.6), 11.0, send=0.6)
    ev("orbital_ring_shimmer_swoosh", 11.75, 11.0, 12.5)

    # ---------------------------------------------------- scene 3
    for k, t in enumerate([13.2, 15.0, 17.0, 19.0]):
        p = (-0.8, 0.8) if k % 2 == 0 else (0.8, -0.8)
        w = whoosh(1.1, peak=0.5, f_lo=250, f_hi=2200 + 300 * k, q=1.0, pan=p, seed=90 + k, tone=True)
        mx.add(w, t - 0.55, gain=0.62, send=0.25)
        ev("island_whoosh_passby", t)
    for k in range(10):
        t = 15.5 + k * 0.1 + R.uniform(-0.03, 0.03)
        mx.add(bubble(R.uniform(500, 1300), R.uniform(0.05, 0.1), 0.28), t, R.uniform(-0.6, 0.6), 0.6, 0.3)
    ev("science_bubbly_blips", 15.5, 15.5, 16.5)
    for k, t in enumerate([19.6, 20.2]):
        p0, p1 = (-0.4, 0.5) if k == 0 else (0.4, -0.5)
        ig = lp(noise(int(0.5 * SR), 100 + k), 2500) * np.exp(-_t(0.5) / 0.12)
        mx.add(fade(ig, 0.003, 0.05) * 0.18, t, p0, 1.0, 0.25)
        d = 0.7; tt = _t(d)
        f = 650 * 2 ** (1.7 * (tt / d) ** 0.8) * (1 + 0.01 * np.sin(2 * np.pi * 9 * tt))
        wh = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.sin(np.pi * tt / d) ** 1.2
        mx.add(pan_st(wh * 0.07, np.linspace(p0, p1, len(wh))), t + 0.05, send=0.4)
        w = whoosh(0.8, peak=0.35, f_lo=900, f_hi=5000, q=1.3, pan=(p0, p1), seed=110 + k)
        mx.add(w, t, gain=0.35, send=0.4)
        sparkle_cloud(mx, t + 0.15, 0.7, 10, "A6", "D8", vel=(0.06, 0.15), pan=(min(p0, p1), max(p0, p1)), gain=0.6)
        ev("pencil_rocket_launch", t)

    # ---------------------------------------------------- scene 4
    t = 21.2
    d = 1.3; n = int(d * SR); tt = np.arange(n) / SR; u = tt / d
    fc = 350 * 2 ** (3.0 * u ** 0.8)
    y = svf_sweep(noise(n, 120, "pink"), fc, q=3.0)
    y = y / (np.std(y) + 1e-9) * (u ** 0.8) * np.exp(-np.maximum(u - 0.6, 0) * 6)
    mx.add(fade(pan_st(lp(y, 8000), 0.7 * np.sin(2 * np.pi * 1.4 * tt)), 0.01, 0.1) * 0.09, t - 0.3, send=0.5)
    mx.add(sub_boom(90, 45, 1.2, 0.25), t, 0, 1.0, 0.1)
    for k, nm in enumerate(["D6", "A6", "E7", "F#7"]):
        mx.add(ping(nm, 0.18), t + 0.05 + k * 0.07, -0.5 + 0.33 * k, 0.7, 0.6)
    ev("hologram_portal_open", t)
    # taps
    mx.add(ui_tap(0.5, 1), 22.6, 0.15, 0.8, 0.15); ev("ui_tap", 22.6)
    mx.add(I.marimba("A4", 0.5), 22.7, 0.1, 0.55, 0.25)
    mx.add(I.marimba("F#4", 0.45), 22.88, 0.1, 0.55, 0.25)
    ev("wrong_answer_think_again", 22.7)
    mx.add(ui_tap(0.5, 2), 23.6, 0.2, 0.8, 0.15); ev("ui_tap", 23.6)
    for k, nm in enumerate(["D6", "F#6", "A6", "D7"]):
        mx.add(I.celesta(nm, 0.5), 23.65 + k * 0.06, -0.2 + 0.15 * k, 0.4, 0.45)
    sparkle_cloud(mx, 23.75, 0.6, 12, "A6", "D8", vel=(0.06, 0.16), gain=0.6)
    ev("correct_reward_chime_sparkle", 23.65)
    mx.add(ui_tap(0.5, 3), 24.8, -0.1, 0.8, 0.15); ev("ui_tap", 24.8)
    mx.add(I.celesta("A5", 0.5), 24.85, -0.1, 0.4, 0.45)
    mx.add(I.celesta("D6", 0.5), 24.93, 0.1, 0.4, 0.45)
    ev("correct_chime", 24.85)
    t = 26.0
    for k, (fa, fb) in enumerate([(260, 520), (390, 700)]):
        d = 0.22; tt = _t(d)
        f = fa + (fb - fa) * (1 - np.exp(-tt / 0.05))
        y = np.sin(2 * np.pi * np.cumsum(f) / SR) * (1 - np.exp(-tt / 0.004)) * np.exp(-tt / 0.07)
        mx.add(fade(y, 0, 0.02) * 0.3, t + k * 0.11, -0.2 + 0.4 * k, 1.0, 0.3)
    ev("colour_mix_bloop", t)
    t = 27.0
    sn = bp(noise(int(0.03 * SR), 140), 1500, 6000) * np.exp(-_t(0.03) / 0.004)
    mx.add(sn * 0.5, t, 0.1, 1.0, 0.15)
    mx.add(I.marimba("D4", 0.35), t, 0.1, 0.4, 0.15)
    ev("puzzle_piece_snap", t)
    mx.add(I.celesta("E6", 0.45), t + 0.06, 0.2, 0.4, 0.5)
    mx.add(I.celesta("A6", 0.45), t + 0.14, 0.3, 0.4, 0.5)
    ev("puzzle_chime", t + 0.06)
    for k, (t, nm) in enumerate([(23.8, "A6"), (25.0, "B6"), (26.2, "C#7"), (27.2, "D7")]):
        mx.add(soft_tone(hz(nm), 0.09, 0.22, harm=(1.0, 0.1)), t, 0.5, 1.0, 0.3)
        ev("progress_light_tick", t)

    # ---------------------------------------------------- scene 5
    for t in (31.0, 32.0, 33.0, 34.0):
        mx.add(soft_tone(hz("A5"), 0.16, 0.24, harm=(1.0, 0.15, 0.04)), t, 0.0, 1.0, 0.2)
        ev("countdown_beep", t)
    mx.add(ui_tap(0.45, 5), 34.4, 0.2, 0.8, 0.15)
    mx.add(soft_tone(hz("E6"), 0.1, 0.12, bend=0.12, harm=(1.0,)), 34.42, 0.2, 1.0, 0.3)
    ev("star_answer_select", 34.4)
    t = 35.0
    mx.add(soft_tone(hz("D6"), 0.42, 0.26, harm=(1.0, 0.12, 0.03)), t, -0.05, 1.0, 0.25)
    mx.add(soft_tone(hz("A6"), 0.42, 0.14, harm=(1.0, 0.1)), t, 0.05, 1.0, 0.25)
    ev("countdown_final_beep", t)
    mx.add(sub_boom(60, 40, 1.2, 0.6), t, 0, 1.0, 0.1)
    mx.add(impact(0.35, 150), t, 0, 1.0, 0.2)
    for k, nm in enumerate(["D6", "F#6", "A6", "D7", "F#7"]):
        mx.add(ping(nm, 0.2), t + 0.04 + k * 0.05, -0.6 + 0.3 * k, 0.6, 0.5)
    ev("scoreboard_celebration_hit", t)
    # confetti flutter 35.0-36.6
    d = 1.6; n = int(d * SR); tt = np.arange(n) / SR
    conf = np.zeros((2, n))
    r = rng(162)
    for k in range(260):
        i = int(min(r.exponential(0.35), 0.98) * n)
        L = int(r.uniform(0.003, 0.009) * SR)
        if i + L >= n: continue
        g = r.uniform(0.2, 1.0) * (1 - i / n)
        c = bp(r.standard_normal(L), 2500, 8000) * np.exp(-np.arange(L) / (L / 3))
        p = r.uniform(-0.9, 0.9)
        conf[:, i:i + L] += pan_st(c * g, p)
    mx.add(lp(conf, 8500) * 0.22, t, send=0.3)
    sparkle_cloud(mx, t + 0.1, 1.4, 22, "D6", "D8", vel=(0.05, 0.16), gain=0.6)
    ev("golden_confetti", t, 35.0, 36.6)
    # soft crowd cheer 35.0-36.8
    d = 1.8; n = int(d * SR); tt = np.arange(n) / SR
    env = np.clip(tt / 0.3, 0, 1) ** 1.5 * np.clip((d - tt) / 1.2, 0, 1) ** 1.3
    apL = applause(d, 170, 140, env); apR = applause(d, 171, 140, env)
    chL = cheer(d, 172) * env; chR = cheer(d, 173) * env
    st = np.stack([apL / (np.std(apL) + 1e-9) * 0.5 + chL / (np.std(chL) + 1e-9) * 0.35,
                   apR / (np.std(apR) + 1e-9) * 0.5 + chR / (np.std(chR) + 1e-9) * 0.35])
    st = hp(st, 300)
    mx.add(np.stack([fade(st[0], .01, .2), fade(st[1], .01, .2)]) * 0.05, t, send=0.35)
    ev("soft_crowd_cheer", t, 35.0, 36.8)

    # ---------------------------------------------------- scene 6
    w = whoosh(1.3, peak=0.42, f_lo=200, f_hi=3000, q=0.9, pan=(-0.6, 0.6), seed=180, tone=True)
    mx.add(w, 37.0 - 0.55, gain=0.75, send=0.4)
    mx.add(sub_boom(70, 40, 1.0, 0.3), 37.0, 0, 1.0, 0.1)
    ev("portal_transition_whoosh", 37.0)
    t = 38.5; k = 0
    tones = [m for m in range(n2m("D6"), n2m("D7") + 1) if m % 12 in (2, 4, 6, 7, 9, 11, 1)]
    while t < 39.95:
        u = (t - 38.5) / 1.5
        m = tones[min(int(u * len(tones)), len(tones) - 1)]
        mx.add(soft_tone(float(midi_hz(m)), 0.06, 0.18 + 0.12 * u, harm=(1.0, 0.1)), t, -0.3 + 0.6 * u, 1.0, 0.3)
        t += 0.13 - 0.07 * u; k += 1
    w = whoosh(1.5, peak=0.95, f_lo=1500, f_hi=7000, q=2.2, pan=(-0.3, 0.3), seed=190)
    mx.add(w, 38.5, gain=0.4, send=0.5)
    mx.add(ping("D7", 0.25), 40.0, 0.2, 0.7, 0.6)
    ev("progress_bars_fill", 38.5, 38.5, 40.0)
    t = 41.5
    mx.add(I.bell("D5", 0.35, length=3.0, seed=200, bright=0.4), t, 0, 0.4, 0.6)
    for k, nm in enumerate(["F#6", "A6", "D7"]):
        mx.add(I.celesta(nm, 0.45), t + 0.05 + k * 0.08, -0.3 + 0.3 * k, 0.4, 0.5)
    sparkle_cloud(mx, t + 0.2, 0.9, 10, "A6", "D8", vel=(0.05, 0.13), gain=0.6)
    mx.add(soft_tone(147, 0.4, 0.25, harm=(1.0, 0.2)), t, 0, 0.7, 0.2)
    ev("badge_achievement_chime", t)

    # ---------------------------------------------------- scene 7
    d = 3.95; n = int(d * SR); tt = np.arange(n) / SR; u = tt / d
    fc = 300 * 2 ** (4.3 * u ** 1.3)
    y = svf_sweep(noise(n, 210, "pink"), fc, q=1.4)
    y = y / (np.std(y) + 1e-9) * (0.05 + 0.95 * u ** 2.4)
    y2 = svf_sweep(noise(n, 211, "pink"), fc * 1.1, q=1.4)
    y2 = y2 / (np.std(y2) + 1e-9) * (0.05 + 0.95 * u ** 2.4)
    st = lp(np.stack([y, y2]), 9000) * 0.06
    st[:, -int(0.03 * SR):] *= np.linspace(1, 0, int(0.03 * SR))
    mx.add(st, 44.0, send=0.4)
    ev("orbit_accelerating_riser", 47.95, 44.0, 47.95)
    t = 48.0
    mx.add(sub_boom(52, 31, 3.0, 0.9), t, 0, 1.0, 0.1)
    mx.add(impact(0.5, 220, 0.8), t, 0, 1.0, 0.3)
    mx.add(I.bell("D4", 0.8, length=7.0, seed=221, bright=0.7), t, -0.1, 0.42, 0.6)
    mx.add(I.bell("D3", 0.8, length=7.0, seed=222, bright=0.4), t, 0.1, 0.35, 0.5)
    sparkle_cloud(mx, t + 0.05, 1.6, 30, "D6", "D8", vel=(0.06, 0.2), gain=0.55)
    ev("logo_reveal_hit_bell_boom", t)

    # ---------------------------------------------------- scene 8
    t = 51.6
    mx.add(ping("D7", 0.22), t, 0.35, 0.7, 0.6)
    mx.add(ping("A7", 0.16), t + 0.06, 0.4, 0.7, 0.6)
    ev("logo_wink_twinkle", t)
    w = whoosh(1.2, peak=0.5, f_lo=1200, f_hi=6500, q=1.6, pan=(-0.85, 0.85), seed=240)
    mx.add(w, 52.2, gain=0.4, send=0.5)
    for k in range(18):
        u = k / 17
        nm = penta_note("A6", "D8")
        mx.add(ping(nm, 0.06 + 0.1 * np.sin(np.pi * u)), 52.25 + 1.1 * u, -0.85 + 1.7 * u, 0.6, 0.6)
    ev("shooting_star_sparkly_whoosh", 52.8, 52.2, 53.4)
    sparkle_cloud(mx, 53.7, 0.6, 6, "D7", "D8", vel=(0.04, 0.09), gain=0.6)
    ev("final_soft_sparkle", 53.7)
    return mx


def finish(mx):
    ir = make_ir(t60=1.8, predelay=0.015, seed=17)
    wet = convolve_st(mx.send, ir)
    wet = hp(wet, 200)
    y = mx.dry + 0.5 * wet
    y = hp(y, 30)
    y = shelf(y, 9500, -2.0, high=True)
    tt = np.arange(N) / SR
    f = np.where(tt < 54.0, 1.0, np.cos(np.clip((tt - 54.0) / 0.7, 0, 1) * np.pi / 2) ** 2)
    return y * f[None, :]


if __name__ == "__main__":
    import soundfile as sf, os, time, json
    a = time.time()
    y = finish(render())
    os.makedirs("out", exist_ok=True)
    print("peak", np.max(np.abs(y)))
    sf.write("out/sfx_test.wav", (y / np.max(np.abs(y)) * 0.7).T, SR, subtype="PCM_24")
    print(json.dumps(EVENTS, indent=0)[:400], len(EVENTS), time.time() - a)
