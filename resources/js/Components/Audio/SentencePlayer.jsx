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

/** سرعتِ پخشِ سه‌حالته: عادی → آهسته → خیلی آهسته (بدونِ بم‌شدنِ صدا). */
const SPEEDS = [
    { rate: 1, label: '🐇 سرعتِ عادی' },
    { rate: 0.8, label: '🐢 آهسته' },
    { rate: 0.6, label: '🐌 خیلی آهسته' },
];
function applyRate(a, rate) {
    if (!a) return;
    try {
        a.preservesPitch = true; a.webkitPreservesPitch = true; a.mozPreservesPitch = true;
        // مرورگر با بارگذاریِ فایلِ تازه سرعت را به defaultPlaybackRate برمی‌گرداند؛ پس هر دو تنظیم می‌شوند
        a.defaultPlaybackRate = rate;
        a.playbackRate = rate;
    } catch { /* */ }
}
function SpeedButton({ speed, onChange }) {
    const next = (speed + 1) % SPEEDS.length;
    return (
        <button type="button" className={`sp-speed s${speed}`} onClick={() => onChange(next)} aria-label="تغییرِ سرعت" title="بزن تا سرعت عوض شود">
            {SPEEDS[speed].label}
            <span className="sp-speed-dots">{SPEEDS.map((_, k) => <i key={k} className={k === speed ? 'on' : ''} />)}</span>
        </button>
    );
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
    const [speed, setSpeed] = useState(0);
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
    rateRef.current = SPEEDS[speed].rate;
    const changeSpeed = (k) => { setSpeed(k); rateRef.current = SPEEDS[k].rate; applyRate(audio.current, SPEEDS[k].rate); };

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
            a.onended = () => after(k, a.duration);
            if (!(await playSafe(a, s.audio, rateRef.current, setMsg))) setPlaying(false);
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
            <audio ref={audio} preload="auto" playsInline />
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
                <SpeedButton speed={speed} onChange={changeSpeed} />
                {task.kind === 'dictation' && (
                    <label>
                        <input type="checkbox" checked={auto} onChange={(e) => { setAuto(e.target.checked); if (!e.target.checked) { clearInterval(timer.current); setWait(0); } }} />
                        {' '}🏫 حالتِ کلاس (مکث برای نوشتن و رفتنِ خودکار به جمله‌ی بعد)
                    </label>
                )}
            </div>
            {msg === 'fail' ? <FailMsg src={sentences[i]?.audio} /> : msg && <div className="sp-msg">{msg}</div>}
        </div>
    );
}

/**
 * پخشِ مطمئن: اگر پخشِ مستقیم شکست خورد (نوعِ فایل، کشِ اپ، اینترنتِ ضعیف)، یک بار کلِ فایل
 * دانلود و از حافظه‌ی گوشی پخش می‌شود. اگر باز هم نشد، یعنی این گوشی این قالب را نمی‌شناسد.
 */
async function playSafe(a, src, rate, setMsg) {
    applyRate(a, rate);
    try {
        await a.play();
        return true;
    } catch (e) {
        if (e?.name === 'NotAllowedError') { setMsg('پخش نشد؛ یک بار دیگر دکمه‌ی پخش را بزنید.'); return false; }
    }
    try {
        const { data } = await axios.get(src, { responseType: 'blob' });
        a.src = URL.createObjectURL(data);
        applyRate(a, rate);
        await a.play();
        return true;
    } catch {
        setMsg('fail');
        return false;
    }
}

function FailMsg({ src }) {
    return (
        <div className="sp-msg">
            این گوشی نتوانست صدا را پخش کند. <a href={src} target="_blank" rel="noreferrer" download style={{ fontWeight: 800 }}>⬇️ دانلودِ صدا</a> و پخش با برنامه‌ی موسیقیِ گوشی را امتحان کنید،
            یا از معلم بخواهید صدا را دوباره ضبط کند.
        </div>
    );
}

export function WholeAudio({ src, onPlay, dark, title = '🎧 صدای معلم' }) {
    const a = useRef(null);
    const [speed, setSpeed] = useState(0);
    const [playing, setPlaying] = useState(false);
    const [pos, setPos] = useState({ t: 0, d: 0 });
    const [msg, setMsg] = useState(null);
    const tried = useRef(false);
    const skip = (d) => { if (a.current) a.current.currentTime = Math.max(0, a.current.currentTime + d); };
    const toggle = async () => {
        const el = a.current;
        if (!el) return;
        if (!el.paused) { el.pause(); return; }
        setMsg(null);
        onPlay?.();
        await playSafe(el, src, SPEEDS[speed].rate, setMsg);
    };
    // خطای بارگذاری (مثلاً نوعِ فایل): یک بار از مسیرِ دانلودِ کامل امتحان کن
    const onError = async () => {
        if (tried.current || !a.current) return;
        tried.current = true;
        try {
            const { data } = await axios.get(src, { responseType: 'blob' });
            a.current.src = URL.createObjectURL(data);
        } catch { setMsg('fail'); }
    };
    const mm = (x) => (Number.isFinite(x) ? fa(`${Math.floor(x / 60)}:${String(Math.floor(x % 60)).padStart(2, '0')}`) : '—');
    return (
        <div className={`sp ${dark ? 'sp-dark' : ''}`}>
            <audio ref={a} preload="metadata" playsInline src={src} onError={onError}
                onPlay={() => setPlaying(true)} onPause={() => setPlaying(false)} onEnded={() => setPlaying(false)}
                onTimeUpdate={(e) => setPos({ t: e.target.currentTime, d: e.target.duration })}
                onLoadedMetadata={(e) => { applyRate(e.target, SPEEDS[speed].rate); setPos({ t: 0, d: e.target.duration }); }} />
            <div className="sp-stage">
                <button type="button" className={`sp-big ${playing ? 'on' : ''}`} onClick={toggle} aria-label={playing ? 'توقف' : 'پخش'}>
                    {playing ? <span className="sp-waves"><i /><i /><i /><i /><i /></span> : '▶'}
                </button>
                <div className="sp-info">
                    <b>{title}</b>
                    <span>{playing ? 'گوش بده…' : 'برای شنیدن دکمه را بزن؛ هر جا لازم بود مکث کن.'}</span>
                    {pos.d > 0 && Number.isFinite(pos.d) && (
                        <input type="range" min={0} max={pos.d} step={0.1} value={pos.t} aria-label="جای پخش" style={{ width: '100%' }}
                            onChange={(e) => { if (a.current) a.current.currentTime = Number(e.target.value); }} />
                    )}
                    <small>{mm(pos.t)}{Number.isFinite(pos.d) && pos.d > 0 ? ` / ${mm(pos.d)}` : ''}</small>
                </div>
            </div>
            <div className="sp-controls">
                <button type="button" onClick={() => skip(-5)}>⏪ ۵ ثانیه عقب</button>
                <SpeedButton speed={speed} onChange={(k) => { setSpeed(k); applyRate(a.current, SPEEDS[k].rate); }} />
                <button type="button" onClick={() => skip(5)}>۵ ثانیه جلو ⏩</button>
            </div>
            {msg === 'fail' ? <FailMsg src={src} /> : msg && <div className="sp-msg">{msg}</div>}
        </div>
    );
}
