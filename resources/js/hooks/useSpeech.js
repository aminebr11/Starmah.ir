import { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';

/**
 * «بخوان برایم» به فارسی.
 *
 * ۱) اگر دستگاه صدای فارسی دارد (Web Speech API) همان استفاده می‌شود — بی‌هزینه و بی‌اینترنت.
 * ۲) وگرنه متن یک‌بار در سرور با سرویسِ صدای فارسی خوانده و فایلش پخش می‌شود (بیشترِ گوشی‌ها
 *    و همه‌ی آیفون‌ها صدای فارسی ندارند). فایلِ هر متن در سرور و در این صفحه نگه داشته می‌شود.
 * ۳) اگر هیچ‌کدام نبود supported=false — دکمه اصلاً نمایش داده نمی‌شود.
 */
const pickVoice = (voices) =>
    voices.find((v) => /^fa(-|_|$)/i.test(v.lang)) || voices.find((v) => /persian|farsi|فارسی/i.test(v.name)) || null;

// ایموجی و علائمِ تزئینی خوانده نشوند
const clean = (t) => String(t ?? '').replace(/[\p{Extended_Pictographic}\u{FE0F}\u{200D}]/gu, '').replace(/\s+/g, ' ').trim();

const urls = new Map(); // متن ← نشانیِ فایلِ صوتی (کشِ همین برگه)

export default function useSpeech() {
    const { tts = false } = usePage().props;
    const synth = typeof window !== 'undefined' ? window.speechSynthesis : null;
    const [voice, setVoice] = useState(null);
    const [ready, setReady] = useState(false);
    const [speaking, setSpeaking] = useState(false);
    const [loading, setLoading] = useState(false);
    const [serverOk, setServerOk] = useState(!!tts);
    const audio = useRef(null);

    useEffect(() => {
        if (!synth) { setReady(true); return undefined; }
        const load = () => { setVoice(pickVoice(synth.getVoices() || [])); setReady(true); };
        load();
        synth.addEventListener?.('voiceschanged', load);
        // بعضی مرورگرها voiceschanged نمی‌فرستند
        const t = setTimeout(load, 1200);
        return () => { synth.removeEventListener?.('voiceschanged', load); clearTimeout(t); synth.cancel(); };
    }, [synth]);

    useEffect(() => () => { audio.current?.pause(); }, []);

    const stop = useCallback(() => {
        synth?.cancel();
        if (audio.current) { audio.current.pause(); audio.current.currentTime = 0; }
        setSpeaking(false);
    }, [synth]);

    const speak = useCallback(async (text) => {
        const t = clean(text);
        if (!t) return;
        stop();
        if (synth && voice) {
            const u = new SpeechSynthesisUtterance(t);
            try { u.voice = voice; } catch { /* بعضی مرورگرها شیءِ صدا را نمی‌پذیرند؛ زبان کافی است */ }
            u.lang = voice.lang || 'fa-IR';
            u.rate = 0.9;
            u.onend = u.onerror = () => setSpeaking(false);
            setSpeaking(true);
            synth.speak(u);
            return;
        }
        if (!serverOk) return;
        try {
            setLoading(true);
            let url = urls.get(t);
            if (!url) {
                const { data } = await axios.post(route('speech'), { text: t });
                url = data?.url;
                if (url) urls.set(t, url);
            }
            if (!url) { setServerOk(false); return; }
            const a = audio.current || new Audio();
            audio.current = a;
            a.src = url;
            a.onended = a.onerror = () => setSpeaking(false);
            setSpeaking(true);
            await a.play();
        } catch {
            setSpeaking(false);
            setServerOk(false); // سرویس در دسترس نیست → دکمه پنهان می‌شود
        } finally {
            setLoading(false);
        }
    }, [synth, voice, serverOk, stop]);

    return { supported: !!(synth && voice) || serverOk, ready, speaking, loading, speak, stop };
}
