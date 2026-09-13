import { useState, useRef, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';

/**
 * حبابِ شناورِ «دستیارِ هوشمند» — در همه‌ی صفحه‌ها.
 *
 * برای دانش‌آموز مثلِ یک معلمِ راهنما کار می‌کند: رتبه، گزارشِ تحلیلی،
 * درس‌های ضعیف و پیشنهادِ گامِ بعدی را از دادهٔ واقعیِ خودش می‌گوید.
 * میان‌برها بر اساسِ نقش عوض می‌شوند و همیشه بالای کادرِ نوشتن هستند،
 * نه فقط در پیامِ اول.
 */

const CHIPS = {
    student: ['📊 گزارش تحلیلی بده', '🏅 رتبه‌ام چنده؟', '📉 کدوم درسم ضعیفه؟', '🎯 امروز چه کار کنم؟', '⚡ چقدر امتیاز دارم؟', '📔 نمره‌هام چیه؟'],
    teacher: ['📊 وضعیتِ کلاس‌هام چطوره؟', '📉 ضعیف‌ترین درس‌ها', '🎯 چطور مأموریت بسازم؟', '🎨 کاربرگ چطور بسازم؟'],
    school_admin: ['📊 گزارشِ مدرسه', '👩‍🏫 مدیریتِ معلم‌ها', '📢 اطلاعیه چطور بگذارم؟'],
    default: ['راهنمای سایت', 'چطور امتیاز بگیرم؟'],
};

const GREETING = {
    student: 'سلام 👋 من دستیارِ ستاره‌ماه‌ام — مثلِ یک معلمِ راهنما کنارِ تو.\nاز من بپرس رتبه‌ات چند است، کدام درست ضعیف است، یا امروز چه کار کنی.',
    default: 'سلام 👋 من دستیارِ هوشمندِ ستاره‌ماه‌ام. هر سؤالی درباره‌ی سایت یا وضعیتِ خودت داری بپرس.',
};

/** متنِ ساده با **پررنگ** و فهرستِ گلوله‌ای. */
function Rich({ text }) {
    const parts = String(text ?? '').split(/(\*\*[^*]+\*\*)/g);
    return (
        <>
            {parts.map((p, i) => (p.startsWith('**') && p.endsWith('**')
                ? <b key={i}>{p.slice(2, -2)}</b>
                : <span key={i}>{p}</span>))}
        </>
    );
}

export default function AssistantWidget() {
    const { auth } = usePage().props;
    const roles = auth?.roles ?? [];
    const role = ['student', 'teacher', 'school_admin'].find((r) => roles.includes(r)) ?? 'default';
    const chips = CHIPS[role] ?? CHIPS.default;
    const hello = GREETING[role] ?? GREETING.default;

    // کلید به کاربر گره خورده تا گفت‌وگوی نفرِ قبلی روی همین مرورگر نماند
    const storeKey = `sm-assistant:${auth?.user?.id ?? 'guest'}`;

    const [open, setOpen] = useState(false);
    const [msgs, setMsgs] = useState(() => {
        // گفت‌وگو بینِ صفحه‌ها گم نشود
        try {
            const saved = sessionStorage.getItem(storeKey);
            if (saved) return JSON.parse(saved);
        } catch { /* حافظه در دسترس نیست — بی‌خیال */ }
        return [{ role: 'assistant', content: hello }];
    });
    const [input, setInput] = useState('');
    const [busy, setBusy] = useState(false);
    const boxRef = useRef(null);

    useEffect(() => { if (boxRef.current) boxRef.current.scrollTop = boxRef.current.scrollHeight; }, [msgs, busy, open]);
    useEffect(() => {
        try { sessionStorage.setItem(storeKey, JSON.stringify(msgs.slice(-20))); } catch { /* بی‌خیال */ }
    }, [msgs]);

    const send = async (text) => {
        const message = (text ?? input).replace(/^[^\p{L}\p{N}]+/u, '').trim();
        if (!message || busy) return;
        const history = msgs.slice(-8);
        setMsgs((m) => [...m, { role: 'user', content: message }]);
        setInput('');
        setBusy(true);
        try {
            const { data } = await axios.post(route('assistant.chat'), { message, history });
            setMsgs((m) => [...m, { role: 'assistant', content: data.reply }]);
        } catch {
            setMsgs((m) => [...m, { role: 'assistant', content: 'الان نتوانستم پاسخ بدهم. کمی بعد دوباره امتحان کن.' }]);
        } finally { setBusy(false); }
    };

    const reset = () => setMsgs([{ role: 'assistant', content: hello }]);

    return (
        <>
            <button onClick={() => setOpen((o) => !o)} title="دستیارِ هوشمند" className="assistant-fab" aria-label="دستیار هوشمند"
                style={{ position: 'fixed', insetInlineEnd: 18, bottom: `calc(18px + var(--sab, 0px))`, zIndex: 120, width: 56, height: 56, borderRadius: '50%', border: 0, cursor: 'pointer',
                    background: 'linear-gradient(135deg,#7c5cf0,#4c2fb0)', color: '#fff', fontSize: 26, boxShadow: '0 12px 30px -8px rgba(76,47,176,.6)' }}>
                {open ? '✕' : '🤖'}
            </button>

            {open && (
                <div className="assistant-panel" dir="rtl"
                    style={{ position: 'fixed', insetInlineEnd: 18, bottom: `calc(84px + var(--sab, 0px))`, zIndex: 120, width: 370, maxWidth: 'calc(100vw - 36px)', height: 540, maxHeight: 'calc(100vh - 150px)',
                        background: '#fff', color: '#1b2742', borderRadius: 18, boxShadow: '0 24px 60px -20px rgba(0,0,0,.5)', display: 'flex', flexDirection: 'column', overflow: 'hidden', fontFamily: 'inherit' }}>
                    <div style={{ background: 'linear-gradient(135deg,#7c5cf0,#4c2fb0)', color: '#fff', padding: '12px 16px', fontWeight: 800, display: 'flex', alignItems: 'center', gap: 8 }}>
                        <span style={{ fontSize: 20 }}>🤖</span>
                        <span style={{ flex: 1 }}>دستیارِ ستاره‌ماه</span>
                        <button onClick={reset} title="گفت‌وگوی تازه"
                            style={{ border: 0, background: 'rgba(255,255,255,.18)', color: '#fff', borderRadius: 9, width: 28, height: 28, cursor: 'pointer', fontSize: 13 }}>↺</button>
                    </div>

                    <div ref={boxRef} style={{ flex: 1, overflowY: 'auto', padding: 12, display: 'flex', flexDirection: 'column', gap: 8, background: '#f6f8fc' }}>
                        {msgs.map((m, i) => (
                            <div key={i} style={{ maxWidth: '88%', alignSelf: m.role === 'user' ? 'flex-start' : 'flex-end',
                                background: m.role === 'user' ? 'linear-gradient(135deg,#3d7bf0,#2555c0)' : '#fff', color: m.role === 'user' ? '#fff' : '#1b2742',
                                border: m.role === 'user' ? 0 : '1px solid #e6ebf3', borderRadius: 14, padding: '9px 12px', fontSize: 13, lineHeight: 1.95, whiteSpace: 'pre-wrap' }}>
                                <Rich text={m.content} />
                            </div>
                        ))}
                        {busy && <div style={{ alignSelf: 'flex-end', color: '#8896ad', fontSize: 12, padding: '4px 8px' }}>در حال نوشتن…</div>}
                    </div>

                    {/* میان‌برها — همیشه در دسترس، نه فقط پیامِ اول */}
                    <div style={{ display: 'flex', gap: 6, padding: '8px 10px 0', overflowX: 'auto', background: '#fff' }}>
                        {chips.map((h) => (
                            <button key={h} onClick={() => send(h)} disabled={busy}
                                style={{ cursor: 'pointer', fontFamily: 'inherit', fontSize: 12, border: '1px solid #d7deea', background: '#fff', color: '#4c2fb0', borderRadius: 20, padding: '7px 12px', whiteSpace: 'nowrap', flex: 'none' }}>
                                {h}
                            </button>
                        ))}
                    </div>

                    <form onSubmit={(e) => { e.preventDefault(); send(); }} style={{ display: 'flex', gap: 8, padding: 10, background: '#fff' }}>
                        <input value={input} onChange={(e) => setInput(e.target.value)} placeholder="سؤالت را بنویس…"
                            style={{ flex: 1, border: '1px solid #d7deea', borderRadius: 12, padding: '11px 12px', fontFamily: 'inherit', fontSize: 13, color: '#1b2742' }} />
                        <button type="submit" disabled={busy || !input.trim()}
                            style={{ border: 0, borderRadius: 12, padding: '0 16px', background: 'linear-gradient(135deg,#7c5cf0,#4c2fb0)', color: '#fff', fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit' }}>➤</button>
                    </form>
                </div>
            )}
        </>
    );
}
