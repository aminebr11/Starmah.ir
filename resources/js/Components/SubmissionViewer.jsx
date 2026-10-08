import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { pushOverlay, closeOverlay } from '@/lib/overlayBack';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const QUICK = ['آفرین! 🌟', 'عالی بود 👏', 'دقتت را بیشتر کن', 'جواب‌های قرمز را دوباره ببین', 'خط خواناتر بنویس'];
const TOOLS = [
    { key: 'pen', icon: '✏️', label: 'قلم' },
    { key: 'ok', icon: '✔', label: 'درست', color: '#16a34a' },
    { key: 'no', icon: '✘', label: 'غلط', color: '#dc2626' },
    { key: 'star', icon: '⭐', label: 'ستاره' },
];

/**
 * نمایشگرِ تمام‌صفحه‌ی کاربرگِ پرشده (موبایل و کامپیوتر).
 *
 * - دکمه‌ی ✕ همیشه بالا؛ «بازگشتِ» گوشی و Esc هم می‌بندد (در اپ، صفحه‌ی خامِ عکس راهِ برگشت نداشت).
 * - معلم: قلمِ قرمز، تیک، ضربدر و ستاره مستقیم روی برگه + نمره‌ی توصیفی، امتیاز و توضیح.
 *   علامت‌ها روی خودِ عکس ذخیره می‌شوند و دانش‌آموز همان برگه‌ی تصحیح‌شده را می‌بیند.
 * - جابه‌جایی بینِ کاربرگ‌های بچه‌ها با «قبلی/بعدی».
 */
export default function SubmissionViewer({ items, index = 0, onClose, canGrade = false, grades = {}, maxXp = 50, onSaved }) {
    const [i, setI] = useState(index);
    const item = items[i];
    const me = useRef({ close: () => {} });
    me.current.close = onClose;
    useEffect(() => pushOverlay(me.current), []); // eslint-disable-line react-hooks/exhaustive-deps
    const close = () => closeOverlay(me.current);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') close();
            if (e.target?.tagName === 'TEXTAREA' || e.target?.tagName === 'INPUT') return;
            if (e.key === 'ArrowRight' && i > 0) setI(i - 1);
            if (e.key === 'ArrowLeft' && i < items.length - 1) setI(i + 1);
        };
        window.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => { window.removeEventListener('keydown', onKey); document.body.style.overflow = ''; };
    }, [i, items.length]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!item) return null;

    return (
        <div className="sv" dir="rtl" role="dialog" aria-modal="true">
            <header className="sv-bar">
                <button type="button" className="sv-x" onClick={close} aria-label="بستن">✕</button>
                <div className="sv-title">
                    <b>{item.student ? `👤 ${item.student}` : 'کاربرگِ من'}</b>
                    <span>{item.date}{item.graded ? ` · ✅ تصحیح‌شده${item.grade ? ` (${item.grade})` : ''}` : ''}</span>
                </div>
                {items.length > 1 && (
                    <div className="sv-nav">
                        <button type="button" onClick={() => setI(i - 1)} disabled={i === 0} aria-label="قبلی">→</button>
                        <span>{fa(i + 1)} از {fa(items.length)}</span>
                        <button type="button" onClick={() => setI(i + 1)} disabled={i === items.length - 1} aria-label="بعدی">←</button>
                    </div>
                )}
            </header>
            <Sheet key={item.id} item={item} canGrade={canGrade} grades={grades} maxXp={maxXp}
                onSaved={(s) => { onSaved?.(s); if (i < items.length - 1 && !items[i + 1].graded) setTimeout(() => setI(i + 1), 700); }} />
        </div>
    );
}

function Sheet({ item, canGrade, grades, maxXp, onSaved }) {
    const [tool, setTool] = useState(null);          // null = فقط دیدن/بزرگ‌نمایی
    const [marks, setMarks] = useState([]);         // در مختصاتِ طبیعیِ عکس
    const [showOriginal, setShowOriginal] = useState(false);
    const [zoom, setZoom] = useState(false);
    const [size, setSize] = useState(null);         // {w,h} طبیعی
    const [grade, setGrade] = useState(item.grade || '');
    const [xp, setXp] = useState(item.graded ? item.xp : 0);
    const [feedback, setFeedback] = useState(item.feedback || '');
    const [busy, setBusy] = useState(false);
    const [msg, setMsg] = useState(null);
    const [failed, setFailed] = useState(false);
    const imgRef = useRef(null);
    const canvasRef = useRef(null);
    const drawing = useRef(null);

    // پایه‌ی علامت‌گذاری: نسخه‌ی تصحیح‌شده‌ی قبلی (اگر هست) تا علامت‌ها روی هم جمع شوند
    const base = !showOriginal && item.marked_url ? item.marked_url : item.url;

    // چرخشِ گوشی/تغییرِ اندازه‌ی پنجره → بوم دوباره هم‌اندازه‌ی عکس شود
    const [, bump] = useState(0);
    useEffect(() => {
        const r = () => bump((n) => n + 1);
        window.addEventListener('resize', r);
        return () => window.removeEventListener('resize', r);
    }, []);

    // بازکشیدنِ علامت‌ها روی بوم (اندازه‌ی نمایشی)
    useEffect(() => {
        const c = canvasRef.current, img = imgRef.current;
        if (!c || !img || !size) return;
        const w = img.clientWidth, h = img.clientHeight;
        c.width = w; c.height = h;
        const k = w / size.w;
        const g = c.getContext('2d');
        g.clearRect(0, 0, w, h);
        marks.forEach((m) => paint(g, m, k));
    });

    const point = (e) => {
        const r = canvasRef.current.getBoundingClientRect();
        const k = size.w / r.width;
        return [(e.clientX - r.left) * k, (e.clientY - r.top) * k];
    };
    const down = (e) => {
        if (!tool || !size) return;
        e.preventDefault();
        canvasRef.current.setPointerCapture?.(e.pointerId);
        const [x, y] = point(e);
        const unit = Math.max(size.w, size.h) / 40;
        if (tool === 'pen') {
            drawing.current = { type: 'pen', pts: [[x, y]], w: Math.max(3, unit / 5) };
            setMarks((m) => [...m, drawing.current]);
        } else {
            setMarks((m) => [...m, { type: tool, x, y, s: unit * 1.6 }]);
        }
    };
    const move = (e) => {
        if (!drawing.current) return;
        e.preventDefault();
        drawing.current.pts.push(point(e));
        setMarks((m) => [...m]);
    };
    const up = () => { drawing.current = null; };

    const save = async () => {
        setBusy(true); setMsg(null);
        try {
            const fd = new FormData();
            fd.append('xp', String(Math.max(0, Math.min(maxXp, Number(xp) || 0))));
            if (grade) fd.append('grade', grade);
            if (feedback.trim()) fd.append('feedback', feedback.trim());
            if (marks.length && size && !item.pdf) {
                const blob = await composite(imgRef.current, size, marks);
                if (blob) fd.append('marked', blob, 'marked.jpg');
            }
            const { data } = await axios.post(route('worksheet.grade', item.id), fd);
            setMsg({ ok: true, text: '✅ تصحیح ثبت شد' + (Number(xp) > 0 ? ` و ${fa(xp)} امتیاز به دانش‌آموز رسید` : '') });
            setMarks([]);
            setShowOriginal(false);
            onSaved?.(data.submission);
        } catch (e) {
            setMsg({ ok: false, text: e.response?.data?.message || 'ثبتِ تصحیح انجام نشد؛ دوباره امتحان کنید.' });
        } finally { setBusy(false); }
    };

    const pickGrade = (g) => { setGrade(g); setXp(grades[g] ?? xp); };

    return (
        <div className={`sv-body ${canGrade ? 'with-panel' : ''}`}>
            <div className={`sv-stage ${zoom ? 'zoom' : ''} ${tool ? 'drawing' : ''}`}>
                {item.pdf ? (
                    <div className="sv-pdf">
                        <iframe title="کاربرگ" src={item.url} />
                        <a href={item.url} target="_blank" rel="noreferrer" className="btn btn-sm">📄 بازکردنِ PDF در برنامه‌ی دیگر</a>
                    </div>
                ) : failed ? (
                    <div className="sv-fail">فایل باز نشد. <a href={base} target="_blank" rel="noreferrer">دوباره امتحان کن</a></div>
                ) : (
                    <div className="sv-paper">
                        <img ref={imgRef} src={base} alt="کاربرگِ پرشده" draggable={false}
                            onLoad={(e) => setSize({ w: e.currentTarget.naturalWidth, h: e.currentTarget.naturalHeight })}
                            onError={() => setFailed(true)}
                            onClick={() => !tool && setZoom((z) => !z)} />
                        <canvas ref={canvasRef} className="sv-canvas"
                            onPointerDown={down} onPointerMove={move} onPointerUp={up} onPointerCancel={up} onPointerLeave={up} />
                    </div>
                )}
            </div>

            {canGrade ? (
                <aside className="sv-panel">
                    {!item.pdf && !failed && (
                        <div className="sv-tools">
                            <button type="button" className={!tool ? 'on' : ''} onClick={() => setTool(null)} title="دیدن و بزرگ‌نمایی">🔍</button>
                            {TOOLS.map((t) => (
                                <button key={t.key} type="button" className={tool === t.key ? 'on' : ''} onClick={() => setTool(t.key)} title={t.label}
                                    style={t.color ? { color: t.color } : undefined}>{t.icon}<small>{t.label}</small></button>
                            ))}
                            <button type="button" onClick={() => setMarks((m) => m.slice(0, -1))} disabled={!marks.length} title="برگرداندن">↶<small>برگردان</small></button>
                            {item.marked_url && (
                                <button type="button" className={showOriginal ? 'on' : ''} onClick={() => { setShowOriginal(!showOriginal); setMarks([]); }}
                                    title="نسخه‌ی اصلی / تصحیح‌شده">{showOriginal ? '🖍️' : '📄'}<small>{showOriginal ? 'تصحیح‌شده' : 'اصلی'}</small></button>
                            )}
                        </div>
                    )}
                    {tool && <div className="sv-hint">{tool === 'pen' ? 'روی برگه بکشید' : 'روی جای جواب ضربه بزنید'}</div>}

                    <div className="sv-grades">
                        {Object.keys(grades).map((g) => (
                            <button key={g} type="button" className={grade === g ? 'on' : ''} onClick={() => pickGrade(g)}>{g}</button>
                        ))}
                    </div>
                    <label className="sv-xp">
                        <span>امتیاز</span>
                        <button type="button" onClick={() => setXp(Math.max(0, (Number(xp) || 0) - 1))}>−</button>
                        <input type="number" inputMode="numeric" min="0" max={maxXp} value={xp} onChange={(e) => setXp(e.target.value)} />
                        <button type="button" onClick={() => setXp(Math.min(maxXp, (Number(xp) || 0) + 1))}>+</button>
                    </label>
                    <textarea rows={2} maxLength={500} placeholder="توضیحِ شما برای دانش‌آموز (اختیاری)" value={feedback} onChange={(e) => setFeedback(e.target.value)} />
                    <div className="sv-quick">
                        {QUICK.map((q) => <button key={q} type="button" onClick={() => setFeedback((f) => (f ? f + ' ' : '') + q)}>{q}</button>)}
                    </div>
                    {msg && <div className={`sv-msg ${msg.ok ? 'ok' : 'bad'}`}>{msg.text}</div>}
                    <button type="button" className="btn sv-save" onClick={save} disabled={busy}>
                        {busy ? 'در حالِ ثبت…' : item.graded ? '💾 به‌روزرسانیِ تصحیح' : '💾 ثبتِ تصحیح و امتیاز'}
                    </button>
                    {item.note && <div className="sv-note">📝 یادداشتِ دانش‌آموز: {item.note}</div>}
                </aside>
            ) : (item.graded || item.feedback) && (
                <aside className="sv-panel sv-result">
                    {item.grade && <div className="sv-grade-big">{item.grade}</div>}
                    {item.xp > 0 && <div>⚡ {fa(item.xp)} امتیاز</div>}
                    {item.feedback && <div className="sv-feedback">💬 {item.feedback}</div>}
                    {item.marked_url && (
                        <button type="button" className="btn btn-sm" onClick={() => setShowOriginal(!showOriginal)}>
                            {showOriginal ? '🖍️ نسخه‌ی تصحیح‌شده' : '📄 نسخه‌ی خودم'}
                        </button>
                    )}
                </aside>
            )}
        </div>
    );
}

/** کشیدنِ یک علامت (k = نسبتِ اندازه‌ی نمایشی به طبیعی). */
function paint(g, m, k) {
    g.save();
    g.lineCap = 'round'; g.lineJoin = 'round';
    if (m.type === 'pen') {
        g.strokeStyle = '#e11d48'; g.lineWidth = m.w * k;
        g.beginPath();
        m.pts.forEach(([x, y], j) => (j ? g.lineTo(x * k, y * k) : g.moveTo(x * k, y * k)));
        if (m.pts.length === 1) g.lineTo(m.pts[0][0] * k + 0.1, m.pts[0][1] * k);
        g.stroke();
    } else {
        const s = m.s * k;
        g.font = `bold ${s}px sans-serif`; g.textAlign = 'center'; g.textBaseline = 'middle';
        g.fillStyle = m.type === 'ok' ? '#16a34a' : m.type === 'no' ? '#dc2626' : '#f59e0b';
        g.strokeStyle = 'rgba(255,255,255,.9)'; g.lineWidth = Math.max(2, s / 10);
        const glyph = m.type === 'ok' ? '✔' : m.type === 'no' ? '✘' : '★';
        g.strokeText(glyph, m.x * k, m.y * k);
        g.fillText(glyph, m.x * k, m.y * k);
    }
    g.restore();
}

/** عکس + علامت‌ها در اندازه‌ی طبیعی (حداکثر ۲۰۰۰ پیکسل) → JPEG برای ذخیره. */
function composite(img, size, marks) {
    const scale = Math.min(1, 2000 / Math.max(size.w, size.h));
    const c = document.createElement('canvas');
    c.width = Math.round(size.w * scale); c.height = Math.round(size.h * scale);
    const g = c.getContext('2d');
    g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height);
    g.drawImage(img, 0, 0, c.width, c.height);
    marks.forEach((m) => paint(g, m, scale));
    return new Promise((res) => c.toBlob((b) => res(b), 'image/jpeg', 0.85));
}
