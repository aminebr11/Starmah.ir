import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * خواندنِ متن با صدای فارسیِ خودِ دستگاه (Web Speech API) — بدون هزینه و بدون اینترنت.
 * اگر دستگاه صدای فارسی نداشته باشد supported=false برمی‌گرداند تا دکمه با توضیح غیرفعال شود.
 */
const pickVoice = (voices) =>
    voices.find((v) => /^fa(-|_|$)/i.test(v.lang)) || voices.find((v) => /persian|farsi/i.test(v.name)) || null;

// ایموجی و علائمِ تزئینی خوانده نشوند
const clean = (t) => String(t ?? '').replace(/[\p{Extended_Pictographic}\u{FE0F}\u{200D}]/gu, '').replace(/\s+/g, ' ').trim();

export default function useSpeech() {
    const synth = typeof window !== 'undefined' ? window.speechSynthesis : null;
    const [voice, setVoice] = useState(null);
    const [ready, setReady] = useState(false);
    const [speaking, setSpeaking] = useState(false);
    const utter = useRef(null);

    useEffect(() => {
        if (!synth) { setReady(true); return undefined; }
        const load = () => { setVoice(pickVoice(synth.getVoices() || [])); setReady(true); };
        load();
        synth.addEventListener?.('voiceschanged', load);
        // بعضی مرورگرها voiceschanged نمی‌فرستند
        const t = setTimeout(load, 1200);
        return () => { synth.removeEventListener?.('voiceschanged', load); clearTimeout(t); synth.cancel(); };
    }, [synth]);

    const stop = useCallback(() => { synth?.cancel(); setSpeaking(false); }, [synth]);

    const speak = useCallback((text) => {
        if (!synth || !voice) return;
        synth.cancel();
        const u = new SpeechSynthesisUtterance(clean(text));
        try { u.voice = voice; } catch { /* بعضی مرورگرها شیءِ صدا را نمی‌پذیرند؛ زبان کافی است */ }
        u.lang = voice.lang || 'fa-IR';
        u.rate = 0.9;
        u.onend = u.onerror = () => setSpeaking(false);
        utter.current = u;
        setSpeaking(true);
        synth.speak(u);
    }, [synth, voice]);

    return { supported: !!(synth && voice), ready, speaking, speak, stop };
}
