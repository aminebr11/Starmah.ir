import { useRef, useState, useCallback, useEffect } from 'react';

/**
 * صدای بازی — سبک، آفلاین و بدون فایل (Web Audio API).
 * صداها با اسیلاتور ساخته می‌شوند تا روی cPanel بدونِ هیچ asset اضافه کار کنند.
 * دکمه‌ی بی‌صدا + احترام به prefers-reduced-motion (پیش‌فرض خاموش برای کاربرانِ حساس).
 *
 * خروجی: { play, muted, toggleMute, reduced }
 * play(name): 'correct' | 'wrong' | 'move' | 'win' | 'lose' | 'click' | 'tick'
 */
const STORE_KEY = 'starmah_game_muted';

function prefersReduced() {
    if (typeof window === 'undefined' || !window.matchMedia) return false;
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

export default function useGameSound() {
    const reduced = prefersReduced();
    const [muted, setMuted] = useState(() => {
        if (typeof window === 'undefined') return false;
        const saved = window.localStorage?.getItem(STORE_KEY);
        if (saved !== null && saved !== undefined) return saved === '1';
        return false; // پیش‌فرض: صدا روشن (بچه‌ها صدا را دوست دارند)؛ اما با دکمه قابل خاموش‌کردن
    });
    const ctxRef = useRef(null);

    useEffect(() => {
        try { window.localStorage?.setItem(STORE_KEY, muted ? '1' : '0'); } catch { /* ignore */ }
    }, [muted]);

    const ctx = useCallback(() => {
        if (typeof window === 'undefined') return null;
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return null;
        if (!ctxRef.current) ctxRef.current = new AC();
        if (ctxRef.current.state === 'suspended') ctxRef.current.resume().catch(() => {});
        return ctxRef.current;
    }, []);

    // یک نتِ ساده با پاکتِ صداییِ نرم
    const tone = useCallback((ac, { freq = 440, dur = 0.14, type = 'sine', gain = 0.14, delay = 0, sweep = 0 }) => {
        const t0 = ac.currentTime + delay;
        const osc = ac.createOscillator();
        const g = ac.createGain();
        osc.type = type;
        osc.frequency.setValueAtTime(freq, t0);
        if (sweep) osc.frequency.exponentialRampToValueAtTime(Math.max(40, freq + sweep), t0 + dur);
        g.gain.setValueAtTime(0.0001, t0);
        g.gain.exponentialRampToValueAtTime(gain, t0 + 0.012);
        g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
        osc.connect(g); g.connect(ac.destination);
        osc.start(t0); osc.stop(t0 + dur + 0.02);
    }, []);

    const play = useCallback((name) => {
        if (muted) return;
        const ac = ctx();
        if (!ac) return;
        try {
            switch (name) {
                case 'correct': // آرپژِ شادِ بالارونده
                    tone(ac, { freq: 523, dur: 0.12, type: 'triangle', gain: 0.16 });
                    tone(ac, { freq: 659, dur: 0.12, type: 'triangle', gain: 0.16, delay: 0.09 });
                    tone(ac, { freq: 784, dur: 0.18, type: 'triangle', gain: 0.17, delay: 0.18 });
                    break;
                case 'wrong': // بازِرِ کوتاهِ پایین‌رونده
                    tone(ac, { freq: 200, dur: 0.22, type: 'sawtooth', gain: 0.12, sweep: -80 });
                    break;
                case 'move': // قدمِ مهره
                    tone(ac, { freq: 340, dur: 0.07, type: 'square', gain: 0.08, sweep: 120 });
                    break;
                case 'tick': // شمارش/تاس
                    tone(ac, { freq: 660, dur: 0.04, type: 'square', gain: 0.06 });
                    break;
                case 'click':
                    tone(ac, { freq: 420, dur: 0.05, type: 'sine', gain: 0.07 });
                    break;
                case 'win': { // فانفارِ برد
                    const notes = [523, 659, 784, 1046];
                    notes.forEach((f, i) => tone(ac, { freq: f, dur: 0.22, type: 'triangle', gain: 0.18, delay: i * 0.13 }));
                    tone(ac, { freq: 784, dur: 0.4, type: 'sine', gain: 0.14, delay: 0.55 });
                    break;
                }
                case 'lose':
                    tone(ac, { freq: 330, dur: 0.2, type: 'sine', gain: 0.12 });
                    tone(ac, { freq: 262, dur: 0.3, type: 'sine', gain: 0.12, delay: 0.16 });
                    break;
                default:
                    break;
            }
        } catch { /* ignore audio errors */ }
    }, [muted, ctx, tone]);

    const toggleMute = useCallback(() => setMuted((m) => !m), []);

    return { play, muted, toggleMute, reduced };
}
