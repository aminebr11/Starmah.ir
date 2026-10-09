import { useEffect, useRef, useState } from 'react';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const mmss = (s) => fa(`${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`);

/** بهترین قالبِ ضبطِ مرورگر (کروم: webm، سافاری: mp4). */
function pickMime() {
    if (typeof MediaRecorder === 'undefined') return null;
    for (const m of ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/ogg']) {
        if (MediaRecorder.isTypeSupported?.(m)) return m;
    }
    return '';
}
const extOf = (mime) => (mime.includes('mp4') ? 'm4a' : mime.includes('ogg') ? 'ogg' : 'webm');

/**
 * ضبطِ صدا در همین صفحه (معلم: صدای خودش برای املا؛ دانش‌آموز: روخوانی).
 * نوارِ بلندیِ صدا زنده، زمان‌سنج، گوش‌دادن پیش از فرستادن و «دوباره ضبط».
 * onDone(File|null) — هر بار ضبط کامل شد یا پاک شد.
 */
export default function AudioRecorder({ onDone, max = 600, label = 'ضبط کن', dark = false }) {
    const [state, setState] = useState('idle'); // idle | rec | done | error
    const [secs, setSecs] = useState(0);
    const [url, setUrl] = useState(null);
    const [err, setErr] = useState(null);
    const [level, setLevel] = useState(0);
    const rec = useRef(null);
    const chunks = useRef([]);
    const stream = useRef(null);
    const timer = useRef(null);
    const raf = useRef(null);
    const ctx = useRef(null);
    const secsRef = useRef(0);

    const cleanup = () => {
        clearInterval(timer.current);
        cancelAnimationFrame(raf.current);
        stream.current?.getTracks().forEach((t) => t.stop());
        ctx.current?.close?.().catch(() => {});
        stream.current = null; ctx.current = null;
    };
    useEffect(() => () => cleanup(), []); // eslint-disable-line react-hooks/exhaustive-deps

    const stop = () => { if (rec.current?.state === 'recording') rec.current.stop(); };

    const start = async () => {
        setErr(null);
        const mime = pickMime();
        if (mime === null || !navigator.mediaDevices?.getUserMedia) {
            setState('error');
            setErr('این مرورگر ضبطِ صدا را پشتیبانی نمی‌کند؛ با کروم یا سافاریِ به‌روز امتحان کنید، یا فایلِ صوتی بفرستید.');
            return;
        }
        try {
            const s = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
            stream.current = s;
            // نوارِ بلندیِ صدا
            try {
                const AC = window.AudioContext || window.webkitAudioContext;
                ctx.current = new AC();
                const an = ctx.current.createAnalyser();
                an.fftSize = 256;
                ctx.current.createMediaStreamSource(s).connect(an);
                const buf = new Uint8Array(an.frequencyBinCount);
                const tick = () => {
                    an.getByteTimeDomainData(buf);
                    let m = 0;
                    for (const v of buf) m = Math.max(m, Math.abs(v - 128));
                    setLevel(Math.min(1, m / 60));
                    raf.current = requestAnimationFrame(tick);
                };
                tick();
            } catch { /* بدونِ نوار هم ضبط می‌شود */ }
            chunks.current = [];
            const r = new MediaRecorder(s, mime ? { mimeType: mime } : undefined);
            r.ondataavailable = (e) => { if (e.data?.size) chunks.current.push(e.data); };
            r.onstop = () => {
                const type = r.mimeType || mime || 'audio/webm';
                const blob = new Blob(chunks.current, { type });
                const u = URL.createObjectURL(blob);
                setUrl((old) => { if (old) URL.revokeObjectURL(old); return u; });
                setState('done');
                onDone?.(new File([blob], `voice-${Date.now()}.${extOf(type)}`, { type }));
                cleanup();
            };
            rec.current = r;
            r.start(1000);
            secsRef.current = 0;
            setSecs(0);
            setState('rec');
            timer.current = setInterval(() => {
                secsRef.current += 1;
                setSecs(secsRef.current);
                if (secsRef.current >= max) stop();
            }, 1000);
        } catch (e) {
            setState('error');
            setErr(e?.name === 'NotAllowedError' ? 'اجازه‌ی میکروفون داده نشد. از تنظیماتِ مرورگر/گوشی اجازه بدهید و دوباره بزنید.' : 'میکروفون در دسترس نیست.');
        }
    };
    const reset = () => { setState('idle'); setSecs(0); if (url) URL.revokeObjectURL(url); setUrl(null); onDone?.(null); };

    return (
        <div className={`rec ${dark ? 'rec-dark' : ''} rec-${state}`}>
            {state === 'rec' ? (
                <>
                    <button type="button" className="rec-btn stop" onClick={stop} aria-label="پایانِ ضبط"><span /></button>
                    <div className="rec-live">
                        <b>🔴 در حالِ ضبط… {mmss(secs)}</b>
                        <div className="rec-meter"><i style={{ width: `${Math.round(level * 100)}%` }} /></div>
                        <small>تمام شد؟ دکمه‌ی مربع را بزن.</small>
                    </div>
                </>
            ) : state === 'done' ? (
                <div className="rec-done">
                    <audio controls src={url} preload="metadata" />
                    <div className="rec-row">
                        <span>✅ {mmss(secs)} ضبط شد</span>
                        <button type="button" className="rec-again" onClick={reset}>🔁 دوباره ضبط کن</button>
                    </div>
                </div>
            ) : (
                <>
                    <button type="button" className="rec-btn" onClick={start} aria-label={label}><span>🎙️</span></button>
                    <div className="rec-live">
                        <b>{label}</b>
                        <small>{err || 'دکمه را بزن، اجازه‌ی میکروفون بده و شروع کن.'}</small>
                    </div>
                </>
            )}
        </div>
    );
}
