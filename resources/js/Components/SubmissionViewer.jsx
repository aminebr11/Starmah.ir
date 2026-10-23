import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { pushOverlay, closeOverlay } from '@/lib/overlayBack';
import { WholeAudio } from '@/Components/Audio/SentencePlayer';
import MarkStage, { paint } from '@/Components/MarkStage';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const QUICK = ['آفرین! 🌟', 'عالی بود 👏', 'دقتت را بیشتر کن', 'جواب‌های قرمز را دوباره ببین', 'خط خواناتر بنویس'];

/**
 * نمایشگرِ تمام‌صفحه‌ی کاربرگِ پرشده (موبایل و کامپیوتر).
 *
 * - دکمه‌ی ✕ همیشه بالا؛ «بازگشتِ» گوشی و Esc هم می‌بندد (در اپ، صفحه‌ی خامِ عکس راهِ برگشت نداشت).
 * - برگه: دو انگشت = زوم، یک انگشت = جابه‌جایی (✋) یا نوشتن (✏️) — MarkStage.
 * - معلم: قلم (رنگ و اندازه‌ی دلخواه)، تیک، ضربدر و ستاره مستقیم روی برگه + نمره‌ی توصیفی، امتیاز و توضیح.
 *   علامت‌ها روی خودِ عکس ذخیره می‌شوند و دانش‌آموز همان برگه‌ی تصحیح‌شده را می‌بیند.
 * - جابه‌جایی بینِ کاربرگ‌های بچه‌ها با «قبلی/بعدی».
 */
export default function SubmissionViewer({ items, index = 0, onClose, canGrade = false, grades = {}, maxXp = 50, onSaved, onRemoved, audio = null }) {
    const [i, setI] = useState(index);
    const [list, setList] = useState(false);
    const [toast, setToast] = useState(null);
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
        // منوی پایینِ صفحه، ماهو و دکمه‌های شناور زیرِ فرمِ تصحیح نمانند
        document.body.classList.add('sv-open');
        return () => { window.removeEventListener('keydown', onKey); document.body.style.overflow = ''; document.body.classList.remove('sv-open'); };
    }, [i, items.length]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!item) return null;

    // پنجره مستقیم روی <body> رندر می‌شود: داخلِ صفحه (با انیمیشن/transform) «fixed» به همان صفحه گیر می‌کرد
    // و منوی پایینِ گوشی (مثلاً سامسونگ) روی دکمه‌های آخرِ فرم می‌افتاد.
    return createPortal(
        <div className="sv" dir="rtl" role="dialog" aria-modal="true">
            <header className="sv-bar">
                <button type="button" className="sv-x" onClick={close} aria-label="بستن و بازگشت به فهرست" title="بستن و بازگشت به فهرست">✕</button>
                <div className="sv-title">
                    <b>{item.student ? `👤 ${item.student}` : 'کاربرگِ من'}</b>
                    <span>{item.worksheet ? `📄 ${item.worksheet} · ` : ''}{item.date}{item.graded ? ` · ✅ ${item.grade || 'تصحیح‌شده'}` : ''}</span>
                </div>
                {items.length > 1 && (
                    <div className="sv-nav">
                        <button type="button" onClick={() => setI(i - 1)} disabled={i === 0} aria-label="قبلی">→</button>
                        <button type="button" className="sv-count" onClick={() => setList(!list)} title="فهرستِ کاربرگ‌ها">📋 {fa(i + 1)}/{fa(items.length)}</button>
                        <button type="button" onClick={() => setI(i + 1)} disabled={i === items.length - 1} aria-label="بعدی">←</button>
                    </div>
                )}
            </header>
            {list && (
                <div className="sv-list" onClick={() => setList(false)}>
                    <div className="sv-list-box" onClick={(e) => e.stopPropagation()}>
                        <b>📋 کاربرگ‌های این فهرست</b>
                        {items.map((x, k) => (
                            <button key={x.id} type="button" className={k === i ? 'on' : ''} onClick={() => { setI(k); setList(false); }}>
                                <span>{x.graded ? '✅' : '⏳'} {x.student || 'کاربرگ'}</span>
                                <small>{x.worksheet ? `${x.worksheet} · ` : ''}{x.graded ? (x.grade || 'تصحیح‌شده') : 'منتظرِ تصحیح'}</small>
                            </button>
                        ))}
                        <button type="button" className="sv-list-back" onClick={close}>↩︎ بازگشت به فهرستِ کاربرگ‌ها</button>
                    </div>
                </div>
            )}
            <Sheet key={item.id} item={item} canGrade={canGrade} grades={grades} maxXp={maxXp} audio={audio}
                onRemoved={(s, mode) => {
                    if (mode === 'all') { onRemoved?.(s, mode); close(); return; }
                    onSaved?.(s);
                    setToast(`🗑️ نمره‌ی ${item.student || ''} حذف شد؛ امتیازش هم برداشته شد و دوباره منتظرِ تصحیح است.`);
                    setTimeout(() => setToast(null), 3200);
                }}
                onSaved={(s) => {
                    onSaved?.(s);
                    const next = items.findIndex((x, k) => k > i && !x.graded);
                    setToast(`✅ تصحیحِ ${s.student || item.student || ''} ثبت شد${s.xp > 0 ? ` (+${fa(s.xp)} امتیاز)` : ''}${next > 0 ? ' — کاربرگِ بعدی ←' : ' — همه تصحیح شدند 🎉'}`);
                    setTimeout(() => setToast(null), 3200);
                    if (next > 0) setTimeout(() => setI(next), 900);
                }} />
            {toast && <div className="sv-toast">{toast}</div>}
        </div>,
        document.body,
    );
}

/**
 * audio (اختیاری): «املا/روخوانی» — {kind, score_type, penalty, text, gradeUrl}
 * به‌جای امتیازِ دستی، نمره‌ی توصیفی/عددی و «تعدادِ غلط» ثبت می‌شود (امتیاز از قاعده‌ی دفترِ نمره).
 */
function Sheet({ item, canGrade, grades, maxXp, onSaved, onRemoved, audio = null }) {
    const [marks, setMarks] = useState([]);         // در مختصاتِ طبیعیِ عکس
    const [showOriginal, setShowOriginal] = useState(false);
    const [size, setSize] = useState(null);         // {w,h} طبیعی
    const [panelOpen, setPanelOpen] = useState(true);
    const [grade, setGrade] = useState(item.grade || '');
    const [xp, setXp] = useState(item.graded ? item.xp : 0);
    const [feedback, setFeedback] = useState(item.feedback || '');
    const [mistakes, setMistakes] = useState(item.mistakes ?? '');
    const [score, setScore] = useState(item.score ?? '');
    const [showText, setShowText] = useState(false);
    const gradeList = Array.isArray(grades) ? grades : Object.keys(grades);
    // تعدادِ غلط → نمره‌ی پیشنهادی (همان قاعده‌ی سرور)
    const suggest = (m) => {
        const n = Math.max(0, Number(m) || 0);
        setGrade(n <= 1 ? 'خیلی خوب' : n <= 3 ? 'خوب' : n <= 6 ? 'قابل قبول' : 'نیاز به تلاش');
        setScore(Math.max(0, Math.round((20 - n * (audio?.penalty || 0.5)) * 100) / 100));
    };
    const setMist = (v) => { setMistakes(v); if (v !== '') suggest(v); };
    const [busy, setBusy] = useState(false);
    const [msg, setMsg] = useState(null);
    const [failed, setFailed] = useState(false);
    const imgRef = useRef(null);

    // پایه‌ی علامت‌گذاری: نسخه‌ی تصحیح‌شده‌ی قبلی (اگر هست) تا علامت‌ها روی هم جمع شوند
    const base = !showOriginal && item.marked_url ? item.marked_url : item.url;

    const save = async () => {
        setBusy(true); setMsg(null);
        try {
            const fd = new FormData();
            if (audio) {
                if (mistakes !== '' && mistakes !== null) fd.append('mistakes', String(Math.max(0, Number(mistakes) || 0)));
                if (audio.score_type === 'numeric' && score !== '' && score !== null) fd.append('score', String(score));
            } else {
                fd.append('xp', String(Math.max(0, Math.min(maxXp, Number(xp) || 0))));
            }
            if (grade && (!audio || audio.score_type !== 'numeric')) fd.append('grade', grade);
            if (feedback.trim()) fd.append('feedback', feedback.trim());
            if (marks.length && size && !item.pdf) {
                const blob = await composite(imgRef.current, size, marks);
                if (blob) fd.append('marked', blob, 'marked.jpg');
            }
            const { data } = await axios.post(audio ? audio.gradeUrl(item) : route('worksheet.grade', item.id), fd);
            setMsg({ ok: true, text: audio ? '✅ نمره ثبت شد و در دفترِ نمره رفت' : '✅ تصحیح ثبت شد' + (Number(xp) > 0 ? ` و ${fa(xp)} امتیاز به دانش‌آموز رسید` : '') });
            setMarks([]);
            setShowOriginal(false);
            onSaved?.(data.submission);
        } catch (e) {
            setMsg({ ok: false, text: e.response?.data?.message || 'ثبتِ تصحیح انجام نشد؛ دوباره امتحان کنید.' });
        } finally { setBusy(false); }
    };

    const pickGrade = (g) => { setGrade(g); if (!audio) setXp(grades[g] ?? xp); };

    // 🗑️ حذفِ تصحیح: grade = فقط نمره (کار می‌ماند و دوباره منتظرِ تصحیح است) · all = همه‌چیز، دانش‌آموز دوباره می‌فرستد
    const ungrade = async (mode) => {
        const ok = window.confirm(mode === 'all'
            ? `کارِ «${item.student || 'دانش‌آموز'}» کامل حذف شود؟\n\nنمره از دفترِ نمره، امتیازهای این کار و فایلِ فرستاده‌شده پاک می‌شوند و دانش‌آموز می‌تواند دوباره بفرستد.`
            : `نمره‌ی «${item.student || 'دانش‌آموز'}» حذف شود؟\n\nنمره از دفترِ نمره و امتیازِ آن برداشته می‌شود؛ کارش می‌ماند و دوباره «منتظرِ تصحیح» می‌شود.`);
        if (!ok) return;
        setBusy(true); setMsg(null);
        try {
            const { data } = await axios.post(audio.ungradeUrl(item), { mode });
            if (mode === 'grade') { setGrade(''); setScore(''); setMistakes(''); setFeedback(''); }
            onRemoved?.(data.submission, mode);
        } catch (e) {
            setMsg({ ok: false, text: e.response?.data?.message || 'حذف انجام نشد؛ دوباره امتحان کنید.' });
        } finally { setBusy(false); }
    };

    return (
        <div className={`sv-body ${canGrade ? 'with-panel' : ''}`}>
            <div className={`sv-stage ${!item.audio && !item.pdf && !failed ? 'sv-stage-img' : ''}`}>
                {item.audio ? (
                    <div className="sv-audio">
                        <WholeAudio src={item.url} dark={false} title={`🎙️ صدای ${item.student || 'دانش‌آموز'}`} />
                        {audio?.text && <div className="sv-reading-text">{audio.text}</div>}
                    </div>
                ) : item.pdf ? (
                    <div className="sv-pdf">
                        <iframe title="کاربرگ" src={item.url} />
                        <a href={item.url} target="_blank" rel="noreferrer" className="btn btn-sm">📄 بازکردنِ PDF در برنامه‌ی دیگر</a>
                    </div>
                ) : failed ? (
                    <div className="sv-fail">فایل باز نشد. <a href={base} target="_blank" rel="noreferrer">دوباره امتحان کن</a></div>
                ) : (
                    <MarkStage key={base} src={base} marks={marks} setMarks={setMarks} editable={canGrade} imgRef={imgRef}
                        onSize={setSize} onError={() => setFailed(true)}
                        extra={item.marked_url ? (
                            <button type="button" className={`ms-tool ${showOriginal ? 'on' : ''}`}
                                onClick={() => { if (marks.length && !window.confirm('علامت‌های تازه پاک شوند؟')) return; setShowOriginal(!showOriginal); setMarks([]); }}
                                title="نسخه‌ی اصلی / تصحیح‌شده"><span>{showOriginal ? '🖍️' : '📄'}</span><small>{showOriginal ? 'تصحیح‌شده' : 'اصلی'}</small></button>
                        ) : null} />
                )}
            </div>

            {canGrade ? (
                <aside className={`sv-panel ${panelOpen ? '' : 'shut'}`}>
                    <button type="button" className="sv-handle" onClick={() => setPanelOpen((o) => !o)}>
                        {panelOpen ? '▼ کوچک‌کردنِ فرمِ نمره (برگه بزرگ‌تر)' : `▲ نمره و توضیح${marks.length ? ` · ${fa(marks.length)} علامت` : ''}`}
                    </button>
                    {audio && item.graded && <div className="sv-regrade">✏️ این کار تصحیح شده است. برای <b>ویرایش</b>، نمره یا توضیح را عوض کنید و «به‌روزرسانیِ تصحیح» را بزنید؛ امتیازِ دانش‌آموز هم خودکار درست می‌شود.</div>}
                    {audio?.kind === 'dictation' && audio.text && (
                        <div className="sv-ref">
                            <button type="button" onClick={() => setShowText(!showText)}>{showText ? '🙈 پنهان‌کردنِ متنِ املا' : '📄 دیدنِ متنِ املا برای مقایسه'}</button>
                            {showText && <div className="sv-reading-text small">{audio.text}</div>}
                        </div>
                    )}
                    {audio?.kind === 'dictation' && (
                        <label className="sv-xp">
                            <span>تعدادِ غلط</span>
                            <button type="button" onClick={() => setMist(Math.max(0, (Number(mistakes) || 0) - 1))}>−</button>
                            <input type="number" inputMode="numeric" min="0" value={mistakes} onChange={(e) => setMist(e.target.value)} />
                            <button type="button" onClick={() => setMist((Number(mistakes) || 0) + 1)}>+</button>
                        </label>
                    )}
                    {(!audio || audio.score_type !== 'numeric') && (
                        <div className="sv-grades">
                            {gradeList.map((g) => (
                                <button key={g} type="button" className={grade === g ? 'on' : ''} onClick={() => pickGrade(g)}>{g}</button>
                            ))}
                        </div>
                    )}
                    {audio?.score_type === 'numeric' && (
                        <label className="sv-xp">
                            <span>نمره از ۲۰</span>
                            <button type="button" onClick={() => setScore(Math.max(0, (Number(score) || 0) - 0.25))}>−</button>
                            <input type="number" inputMode="decimal" step="0.25" min="0" max="20" value={score} onChange={(e) => setScore(e.target.value)} />
                            <button type="button" onClick={() => setScore(Math.min(20, (Number(score) || 0) + 0.25))}>+</button>
                        </label>
                    )}
                    {audio?.kind === 'reading' && (
                        <div className="sv-quick">
                            {['روان و رسا 🌟', 'مکث‌های زیاد', 'چند کلمه را اشتباه خواند', 'نشانه‌ها را رعایت کن', 'بلندتر و شمرده‌تر بخوان'].map((q) => (
                                <button key={q} type="button" onClick={() => setFeedback((f) => (f ? f + ' ' : '') + q)}>{q}</button>
                            ))}
                        </div>
                    )}
                    {!audio && (
                        <label className="sv-xp">
                            <span>امتیاز</span>
                            <button type="button" onClick={() => setXp(Math.max(0, (Number(xp) || 0) - 1))}>−</button>
                            <input type="number" inputMode="numeric" min="0" max={maxXp} value={xp} onChange={(e) => setXp(e.target.value)} />
                            <button type="button" onClick={() => setXp(Math.min(maxXp, (Number(xp) || 0) + 1))}>+</button>
                        </label>
                    )}
                    <textarea rows={2} maxLength={500} placeholder="توضیحِ شما برای دانش‌آموز (اختیاری)" value={feedback} onChange={(e) => setFeedback(e.target.value)} />
                    <div className="sv-quick">
                        {QUICK.map((q) => <button key={q} type="button" onClick={() => setFeedback((f) => (f ? f + ' ' : '') + q)}>{q}</button>)}
                    </div>
                    {msg && <div className={`sv-msg ${msg.ok ? 'ok' : 'bad'}`}>{msg.text}</div>}
                    <button type="button" className="btn sv-save" onClick={save} disabled={busy}>
                        {busy ? 'در حالِ ثبت…' : item.graded ? '💾 به‌روزرسانیِ تصحیح' : audio ? '💾 ثبتِ نمره در دفتر' : '💾 ثبتِ تصحیح و امتیاز'}
                    </button>
                    {item.note && <div className="sv-note">📝 یادداشتِ دانش‌آموز: {item.note}</div>}
                    {audio?.ungradeUrl && (
                        <div className="sv-danger">
                            {item.graded && <button type="button" onClick={() => ungrade('grade')} disabled={busy}>🗑️ حذفِ نمره (کار بماند)</button>}
                            <button type="button" className="all" onClick={() => ungrade('all')} disabled={busy}>🔁 حذفِ کامل — دانش‌آموز دوباره بفرستد</button>
                        </div>
                    )}
                </aside>
            ) : (item.graded || item.feedback) && (
                <aside className="sv-panel sv-result">
                    {item.grade && <div className="sv-grade-big">{item.grade}</div>}
                    {item.score !== null && item.score !== undefined && item.score !== '' && <div className="sv-grade-big">{fa(item.score)} از ۲۰</div>}
                    {item.mistakes !== null && item.mistakes !== undefined && <div>✏️ تعدادِ غلط: {fa(item.mistakes)}</div>}
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

/**
 * عکس + علامت‌ها → JPEG برای ذخیره (زیرِ سقفِ آپلودِ هاست).
 * پهنا حداکثر ۱۶۰۰ و مساحت حداکثر ۱۲ میلیون پیکسل؛ برگه‌ی چندصفحه‌ایِ بلند (صفحه‌ها زیرِ هم)
 * پهنای خواناش را نگه می‌دارد و مثلِ قبل به ۱۶۰۰ پیکسلِ بلندا کوچک نمی‌شود.
 */
function composite(img, size, marks) {
    const scale = Math.min(1, 1600 / size.w, Math.sqrt(12_000_000 / (size.w * size.h)));
    const c = document.createElement('canvas');
    c.width = Math.round(size.w * scale); c.height = Math.round(size.h * scale);
    const g = c.getContext('2d');
    g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height);
    g.drawImage(img, 0, 0, c.width, c.height);
    marks.forEach((m) => paint(g, m, scale));
    return new Promise((res) => c.toBlob((b) => res(b), 'image/jpeg', 0.8));
}
