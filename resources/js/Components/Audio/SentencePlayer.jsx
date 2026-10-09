import { useEffect, useRef, useState } from 'react';
import axios from 'axios';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** صدای فارسیِ گوشی (وقتی صدای سرور برای یک جمله ساخته نشده). */
function speakDevice(text, rate) {
    return new Promise((res) => {
        const synth = window.speechSynthesis;
        if (!synth || !text) { res(false); return; }
        const v = synth.getVoices().find((x) => /^fa/i.test(x.lang));
        if (!v) { res(false); return; }
        const u = new SpeechSynthesisUtterance(text);
        u.voice = v; u.lang = v.lang; u.rate = rate * 0.85;
        u.onend = () => res(true);
        u.onerror = () => res(false);
        synth.cancel();
        synth.speak(u);
    });
}

/**
 * پخش‌کننده‌ی «املا/روخوانی».
 * - جمله‌به‌جمله: دکمه‌ی بزرگِ پخش، «دوباره»، قبلی/بعدی، سرعتِ آهسته؛
 *   «حالتِ کلاس»: بعد از هر جمله به اندازه‌ی طولش مکث می‌کند (وقتِ نوشتن) و خودش جمله‌ی بعد را می‌خواند.
 * - یا یک فایلِ کامل (صدای خودِ معلم) با ۵ ثانیه عقب/جلو و سرعت.
 */
export default function SentencePlayer({ task, onPlayed, dark = true }) {
    const sentences = task.sentences || [];
    const whole = task.audio;
    const [i, setI] = useState(0);
    const [playing, setPlaying] = useState(false);
    const [slow, setSlow] = useState(false);
    const [auto, setAuto] = useState(false);
    const [wait, setWait] = useState(0);
    const [msg, setMsg] = useState(null);
    const [done, setDone] = useState({});
    const audio = useRef(null);
    const timer = useRef(null);
    const counted = useRef(false);
    const autoRef = useRef(false);
    const rateRef = useRef(1);
    autoRef.current = auto;
    rateRef.current = slow ? 0.8 : 1;

    const count = () => { if (!counted.current) { counted.current = true; onPlayed?.(); } };
    useEffect(() => () => { clearInterval(timer.current); window.speechSynthesis?.cancel(); audio.current?.pause(); }, []);

    const pauseAll = () => {
        clearInterval(timer.current);
        setWait(0);
        audio.current?.pause();
        window.speechSynthesis?.cancel();
        setPlaying(false);
    };

    const after = (k, seconds) => {
        setPlaying(false);
        setDone((d) => ({ ...d, [k]: true }));
        if (autoRef.current && k < sentences.length - 1) {
            // وقتِ نوشتن: حدودِ دو برابرِ زمانِ خواندن (۴ تا ۲۵ ثانیه)
            let left = Math.max(4, Math.min(25, Math.round((seconds || 4) * 2)));
            setWait(left);
            timer.current = setInterval(() => {
                left -= 1;
                setWait(left);
                if (left <= 0) { clearInterval(timer.current); play(k + 1); }
            }, 1000);
        }
    };

    const play = async (k) => {
        pauseAll();
        setMsg(null);
        count();
        const s = sentences[k];
        if (!s) return;
        setI(k);
        setPlaying(true);
        if (s.audio) {
            const a = audio.current;
            a.src = s.audio;
            a.playbackRate = rateRef.current;
            a.onended = () => after(k, a.duration);
            a.play().catch(() => { setPlaying(false); setMsg('پخش نشد؛ یک بار روی صفحه بزنید و دوباره امتحان کنید.'); });
            return;
        }
        // صدای سرور نیست → صدای فارسیِ گوشی با متنِ همان یک جمله
        try {
            const text = s.text ?? (await axios.get(route('audio.sentence', [task.id, k]))).data.text;
            const t0 = Date.now();
            const ok = await speakDevice(text, rateRef.current);
            if (!ok) setMsg('این گوشی صدای فارسی ندارد و صدای سرور هم برای این جمله ساخته نشده. از معلم بخواهید صدای خودش را بگذارد.');
            after(k, (Date.now() - t0) / 1000);
        } catch {
            setPlaying(false);
            setMsg('جمله پخش نشد.');
        }
    };

    if (!sentences.length || (whole && task.source !== 'tts')) {
        return whole ? <WholeAudio src={whole} onPlay={count} dark={dark} /> : <div className="sp-empty">صدایی برای پخش نیست.</div>;
    }

    return (
        <div className={`sp ${dark ? 'sp-dark' : ''}`}>
            <audio ref={audio} preload="auto" />
            <div className="sp-stage">
                <button type="button" className={`sp-big ${playing ? 'on' : ''}`} onClick={() => (playing ? pauseAll() : play(i))} aria-label={playing ? 'توقف' : 'پخش'}>
                    {playing ? <span className="sp-waves"><i /><i /><i /><i /><i /></span> : '▶'}
                </button>
                <div className="sp-info">
                    <b>جمله‌ی {fa(i + 1)} از {fa(sentences.length)}</b>
                    {wait > 0 ? <span className="sp-wait">✍️ وقتِ نوشتن… {fa(wait)}</span> : <span>{playing ? 'گوش بده…' : 'برای شنیدن دکمه را بزن'}</span>}
                    {task.kind === 'reading' && sentences[i]?.text && <div className="sp-line">{sentences[i].text}</div>}
                </div>
            </div>
            <div className="sp-controls">
                <button type="button" onClick={() => play(Math.max(0, i - 1))} disabled={i === 0}>→ قبلی</button>
                <button type="button" onClick={() => play(i)}>🔁 دوباره</button>
                <button type="button" onClick={() => play(Math.min(sentences.length - 1, i + 1))} disabled={i >= sentences.length - 1}>بعدی ←</button>
            </div>
            <div className="sp-dots">
                {sentences.map((s, k) => (
                    <button key={k} type="button" className={`${k === i ? 'cur' : ''} ${done[k] ? 'done' : ''}`} onClick={() => play(k)} aria-label={`جمله‌ی ${k + 1}`}>{fa(k + 1)}</button>
                ))}
            </div>
            <div className="sp-opts">
                <label><input type="checkbox" checked={slow} onChange={(e) => setSlow(e.target.checked)} /> 🐢 آهسته‌تر</label>
                {task.kind === 'dictation' && (
                    <label>
                        <input type="checkbox" checked={auto} onChange={(e) => { setAuto(e.target.checked); if (!e.target.checked) { clearInterval(timer.current); setWait(0); } }} />
                        {' '}🏫 حالتِ کلاس (مکث برای نوشتن و رفتنِ خودکار به جمله‌ی بعد)
                    </label>
                )}
            </div>
            {msg && <div className="sp-msg">{msg}</div>}
        </div>
    );
}

function WholeAudio({ src, onPlay, dark }) {
    const a = useRef(null);
    const [slow, setSlow] = useState(false);
    const skip = (d) => { if (a.current) a.current.currentTime = Math.max(0, a.current.currentTime + d); };
    return (
        <div className={`sp ${dark ? 'sp-dark' : ''}`}>
            <div className="sp-stage">
                <div className="sp-big on" aria-hidden><span className="sp-waves"><i /><i /><i /><i /><i /></span></div>
                <div className="sp-info"><b>🎧 صدای معلم</b><span>هر جا لازم بود مکث کن و دوباره گوش بده.</span></div>
            </div>
            <audio ref={a} controls preload="metadata" src={src} onPlay={onPlay} style={{ width: '100%' }} />
            <div className="sp-controls">
                <button type="button" onClick={() => skip(-5)}>⏪ ۵ ثانیه عقب</button>
                <button type="button" onClick={() => { const s = !slow; setSlow(s); if (a.current) a.current.playbackRate = s ? 0.8 : 1; }}>{slow ? '🐇 سرعتِ عادی' : '🐢 آهسته‌تر'}</button>
                <button type="button" onClick={() => skip(5)}>۵ ثانیه جلو ⏩</button>
            </div>
        </div>
    );
}
