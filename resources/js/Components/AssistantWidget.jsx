import { useState, useRef, useEffect } from 'react';
import axios from 'axios';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const HINTS = ['وضعیت من چطوره؟', 'چطور آزمون بدم؟', 'مأموریت‌هام کجاست؟', 'چطور امتیاز بگیرم؟'];

/** حبابِ شناورِ «دستیارِ هوشمند» — در همه‌ی صفحه‌ها. تم‌پذیر. */
export default function AssistantWidget() {
    const [open, setOpen] = useState(false);
    const [msgs, setMsgs] = useState([{ role: 'assistant', content: 'سلام! 👋 من دستیارِ هوشمندِ تو هستم. هر سؤالی درباره‌ی سایت یا وضعیتِ خودت داری بپرس.' }]);
    const [input, setInput] = useState('');
    const [busy, setBusy] = useState(false);
    const boxRef = useRef(null);

    useEffect(() => { if (boxRef.current) boxRef.current.scrollTop = boxRef.current.scrollHeight; }, [msgs, busy, open]);

    const send = async (text) => {
        const message = (text ?? input).trim();
        if (!message || busy) return;
        const history = msgs.slice(-8);
        setMsgs((m) => [...m, { role: 'user', content: message }]);
        setInput('');
        setBusy(true);
        try {
            const { data } = await axios.post(route('assistant.chat'), { message, history });
            setMsgs((m) => [...m, { role: 'assistant', content: data.reply }]);
        } catch (e) {
            setMsgs((m) => [...m, { role: 'assistant', content: 'الان نتوانستم پاسخ بدهم. کمی بعد دوباره امتحان کن.' }]);
        } finally { setBusy(false); }
    };

    return (
        <>
            {/* دکمه‌ی شناور */}
            <button onClick={() => setOpen((o) => !o)} title="دستیارِ هوشمند" className="assistant-fab" aria-label="دستیار هوشمند"
                style={{ position: 'fixed', insetInlineEnd: 18, bottom: 18, zIndex: 120, width: 56, height: 56, borderRadius: '50%', border: 0, cursor: 'pointer',
                    background: 'linear-gradient(135deg,#7c5cf0,#4c2fb0)', color: '#fff', fontSize: 26, boxShadow: '0 12px 30px -8px rgba(76,47,176,.6)' }}>
                {open ? '✕' : '🤖'}
            </button>

            {open && (
                <div className="assistant-panel" dir="rtl"
                    style={{ position: 'fixed', insetInlineEnd: 18, bottom: 84, zIndex: 120, width: 340, maxWidth: 'calc(100vw - 36px)', height: 460, maxHeight: 'calc(100vh - 120px)',
                        background: '#fff', color: '#1b2742', borderRadius: 18, boxShadow: '0 24px 60px -20px rgba(0,0,0,.5)', display: 'flex', flexDirection: 'column', overflow: 'hidden', fontFamily: 'inherit' }}>
                    <div style={{ background: 'linear-gradient(135deg,#7c5cf0,#4c2fb0)', color: '#fff', padding: '12px 16px', fontWeight: 800, display: 'flex', alignItems: 'center', gap: 8 }}>
                        <span style={{ fontSize: 20 }}>🤖</span> دستیارِ هوشمندِ ستاره‌ماه
                    </div>

                    <div ref={boxRef} style={{ flex: 1, overflowY: 'auto', padding: 12, display: 'flex', flexDirection: 'column', gap: 8, background: '#f6f8fc' }}>
                        {msgs.map((m, i) => (
                            <div key={i} style={{ maxWidth: '85%', alignSelf: m.role === 'user' ? 'flex-start' : 'flex-end',
                                background: m.role === 'user' ? 'linear-gradient(135deg,#3d7bf0,#2555c0)' : '#fff', color: m.role === 'user' ? '#fff' : '#1b2742',
                                border: m.role === 'user' ? 0 : '1px solid #e6ebf3', borderRadius: 14, padding: '9px 12px', fontSize: 13, lineHeight: 1.9, whiteSpace: 'pre-wrap' }}>
                                {m.content}
                            </div>
                        ))}
                        {busy && <div style={{ alignSelf: 'flex-end', color: '#8896ad', fontSize: 12, padding: '4px 8px' }}>در حال نوشتن…</div>}
                        {msgs.length <= 1 && (
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 6 }}>
                                {HINTS.map((h) => (
                                    <button key={h} onClick={() => send(h)} style={{ cursor: 'pointer', fontFamily: 'inherit', fontSize: 12, border: '1px solid #d7deea', background: '#fff', color: '#4c2fb0', borderRadius: 20, padding: '6px 11px' }}>{h}</button>
                                ))}
                            </div>
                        )}
                    </div>

                    <form onSubmit={(e) => { e.preventDefault(); send(); }} style={{ display: 'flex', gap: 8, padding: 10, borderTop: '1px solid #e6ebf3', background: '#fff' }}>
                        <input value={input} onChange={(e) => setInput(e.target.value)} placeholder="سؤالت را بنویس…"
                            style={{ flex: 1, border: '1px solid #d7deea', borderRadius: 12, padding: '10px 12px', fontFamily: 'inherit', fontSize: 13, color: '#1b2742' }} />
                        <button type="submit" disabled={busy || !input.trim()} style={{ border: 0, borderRadius: 12, padding: '0 16px', background: 'linear-gradient(135deg,#7c5cf0,#4c2fb0)', color: '#fff', fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit' }}>➤</button>
                    </form>
                </div>
            )}
        </>
    );
}
