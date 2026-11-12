import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { compressImage } from '@/lib/imageCompress';

/**
 * 🖼️ شکلِ سؤال — در آزمون‌ساز و استودیوی بازی.
 *
 * سه راه: 📐 رسم (کسرِ پیتزایی، نوار، محورِ اعداد، نمودار، ساعت، شکلِ هندسی…) با پیش‌نمایشِ زنده،
 * 🤖 «هوش مصنوعی شکل را پیشنهاد بدهد» (همان فرمِ رسم پر می‌شود و قابلِ اصلاح است)، و 📷 عکس.
 *
 * props: value (نشانیِ شکل)، onChange(url|null)، question ({prompt, choices, answer})، context ({grade, subject})
 */
const TYPES = [
    ['pizza', '🍕 کسر با پیتزا'], ['fraction_bar', '🍫 کسر با نوار'], ['grid', '🔲 جدولِ خانه‌ها'],
    ['number_line', '📏 محورِ اعداد'], ['bar_chart', '📊 نمودارِ ستونی'], ['pie_chart', '🥧 نمودارِ دایره‌ای'],
    ['pictograph', '🍎 گروه‌ها / آرایه'], ['base10', '🧱 صدتایی، ده‌تایی، یکی'], ['clock', '🕒 ساعت'],
    ['shape', '🔺 شکلِ هندسی'], ['angle', '📐 زاویه'], ['ruler', '📏 خط‌کش'], ['table', '🧾 جدول'],
];
const SHAPES = [['triangle', 'مثلث'], ['right_triangle', 'مثلثِ قائم‌الزاویه'], ['square', 'مربع'], ['rectangle', 'مستطیل'], ['parallelogram', 'متوازی‌الاضلاع'],
    ['trapezoid', 'ذوزنقه'], ['rhombus', 'لوزی'], ['circle', 'دایره'], ['pentagon', 'پنج‌ضلعی'], ['hexagon', 'شش‌ضلعی']];

// فیلدهای هر نوع: [کلید، برچسب، نوع(num|text|list|select|lines)، پیش‌فرض، راهنما]
const FIELDS = {
    pizza: [['parts', 'چند قسمت؟', 'num', 8], ['shaded', 'چند قسمت رنگی؟', 'num', 3], ['wholes', 'چند پیتزا؟', 'num', 1]],
    fraction_bar: [['parts', 'چند قسمت؟', 'num', 5], ['shaded', 'چند قسمت رنگی؟', 'num', 2], ['wholes', 'چند نوار؟', 'num', 1]],
    grid: [['rows', 'سطر', 'num', 10], ['cols', 'ستون', 'num', 10], ['shaded', 'خانه‌های رنگی', 'num', 30]],
    number_line: [['from', 'از', 'num', 0], ['to', 'تا', 'num', 10], ['step', 'فاصله‌ی درجه‌ها', 'num', 1],
        ['points', 'نقطه‌ها', 'text', '', 'مثلاً: ۷=؟ ، ۳=الف'], ['jumps', 'پرش‌ها', 'text', '', 'مثلاً: ۲-۵=+۳'], ['labels', 'برچسبِ درجه‌ها (اختیاری)', 'list', '', 'مثلاً: ۰، ۱/۴، ۲/۴، ۳/۴، ۱']],
    bar_chart: [['title', 'عنوان', 'text', ''], ['labels', 'برچسب‌ها', 'list', 'شنبه، یکشنبه، دوشنبه'], ['values', 'مقدارها', 'list', '۳، ۵، ۲'], ['unit', 'واحد', 'text', '']],
    pie_chart: [['title', 'عنوان', 'text', ''], ['labels', 'برچسب‌ها', 'list', 'سیب، موز، پرتقال'], ['values', 'مقدارها', 'list', '۵۰، ۲۵، ۲۵']],
    pictograph: [['emoji', 'شکلک', 'text', '🍎'], ['groups', 'تعدادِ هر گروه (با «،» جدا)', 'list', '۴، ۴، ۴', 'خالی بگذارید تا آرایه (سطر × ستون) کشیده شود'], ['rows', 'سطر (آرایه)', 'num', 3], ['cols', 'ستون (آرایه)', 'num', 4]],
    base10: [['hundreds', 'صدتایی', 'num', 1], ['tens', 'ده‌تایی', 'num', 2], ['ones', 'یکی', 'num', 3]],
    clock: [['hour', 'ساعت', 'num', 3], ['minute', 'دقیقه', 'num', 30]],
    shape: [['kind', 'شکل', 'select', 'rectangle'], ['side_labels', 'برچسبِ ضلع‌ها (به ترتیب)', 'list', '۸ سانتی‌متر، ۵ سانتی‌متر', 'مستطیل: بالا، راست، پایین، چپ · قائم‌الزاویه: عمودی، افقی، وتر · دایره: شعاع'],
        ['angle_labels', 'برچسبِ زاویه‌ها', 'list', ''], ['center_label', 'وسطِ شکل', 'text', '']],
    angle: [['degrees', 'درجه', 'num', 60], ['label', 'برچسب (مثلاً ؟)', 'text', '']],
    ruler: [['length', 'طولِ جسم (سانتی‌متر)', 'num', 7], ['max', 'طولِ خط‌کش', 'num', 10], ['object', 'شکلکِ جسم', 'text', '✏️']],
    table: [['headers', 'سرستون‌ها', 'list', 'نام، تعداد'], ['rows', 'سطرها (هر سطر یک خط، خانه‌ها با «،»)', 'lines', 'سیب، ۵\nموز، ۳']],
};

const split = (s) => String(s ?? '').split(/[،,]/).map((x) => x.trim()).filter((x) => x !== '');
const toEn = (s) => String(s ?? '').replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace('٫', '.');

/** فرم → توصیف */
function toSpec(type, f) {
    const s = { type };
    for (const [k, , kind] of FIELDS[type]) {
        const v = f[k];
        if (kind === 'num') s[k] = toEn(v);
        else if (kind === 'list') s[k] = split(v);
        else if (kind === 'lines') s[k] = String(v ?? '').split('\n').map(split).filter((r) => r.length);
        else s[k] = v ?? '';
    }
    if (type === 'number_line') {
        s.points = split(f.points).map((p) => { const [at, label = ''] = p.split('='); return { at: toEn(at), label: label.trim() }; });
        s.jumps = split(f.jumps).map((p) => { const [r, label = ''] = p.split('='); const [a, b] = r.split('-'); return { from: toEn(a), to: toEn(b), label: label.trim() }; });
        s.labels = s.labels.length ? s.labels : null;
    }
    if (type === 'pictograph') {
        const g = split(f.groups);
        if (g.length) s.groups = g.map((n) => ({ emoji: f.emoji || '🍎', count: toEn(n) }));
        else delete s.groups;
    }
    if (f.caption) s.caption = f.caption;
    return s;
}

/** توصیف (مثلاً از هوش مصنوعی) → فرم */
function toForm(spec) {
    const f = { caption: spec.caption || '' };
    for (const [k, , kind, def] of FIELDS[spec.type] || []) {
        const v = spec[k];
        if (kind === 'list') f[k] = Array.isArray(v) ? v.join('، ') : (v ?? '');
        else if (kind === 'lines') f[k] = Array.isArray(v) ? v.map((r) => r.join('، ')).join('\n') : '';
        else f[k] = v ?? def;
    }
    if (spec.type === 'number_line') {
        f.points = (spec.points || []).map((p) => `${p.at}=${p.label || ''}`).join('، ');
        f.jumps = (spec.jumps || []).map((j) => `${j.from}-${j.to}=${j.label || ''}`).join('، ');
    }
    if (spec.type === 'pictograph' && spec.groups) {
        f.groups = spec.groups.map((g) => g.count).join('، ');
        f.emoji = spec.groups[0]?.emoji || '🍎';
    }
    return f;
}

const defaults = (type) => Object.fromEntries(FIELDS[type].map(([k, , , def]) => [k, def]));

export default function QuestionFigure({ value, onChange, question = {}, context = {} }) {
    const [open, setOpen] = useState(false);
    const [tab, setTab] = useState('draw');
    const [type, setType] = useState('pizza');
    const [form, setForm] = useState(() => ({ ...defaults('pizza'), caption: '' }));
    const [svg, setSvg] = useState(null);
    const [busy, setBusy] = useState(null);
    const [msg, setMsg] = useState(null);
    const [hint, setHint] = useState('');
    const fileRef = useRef(null);
    const t = useRef(null);

    // پیش‌نمایشِ زنده با کمی تأخیر
    useEffect(() => {
        if (!open || tab !== 'draw') return undefined;
        clearTimeout(t.current);
        t.current = setTimeout(async () => {
            try {
                const { data } = await axios.post(route('teacher.figures.preview'), { spec: toSpec(type, form) });
                setSvg(data.ok ? data.svg : null);
            } catch { setSvg(null); }
        }, 350);
        return () => clearTimeout(t.current);
    }, [open, tab, type, form]);

    const pickType = (k) => { setType(k); setForm({ ...defaults(k), caption: form.caption || '' }); };
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const save = async () => {
        setBusy('save'); setMsg(null);
        try {
            const { data } = await axios.post(route('teacher.figures.store'), { spec: toSpec(type, form) });
            onChange(data.url); setOpen(false);
        } catch (e) { setMsg(e.response?.data?.message || 'ذخیره‌ی شکل نشد.'); }
        setBusy(null);
    };
    const ai = async () => {
        if (!(question.prompt || '').trim()) { setMsg('اول متنِ سؤال را بنویسید.'); return; }
        setBusy('ai'); setMsg(null);
        try {
            const { data } = await axios.post(route('teacher.figures.ai'), {
                prompt: question.prompt, choices: (question.choices || []).map((c) => c.value).filter(Boolean), answer: question.answer || '',
                grade: context.grade || '', subject: context.subject || '', hint,
            });
            if (data.ok && FIELDS[data.spec?.type]) {
                setType(data.spec.type); setForm(toForm(data.spec)); setTab('draw');
                setMsg('✨ هوش مصنوعی این شکل را پیشنهاد داد؛ اگر لازم است عددها را اصلاح کنید و «افزودن» را بزنید.');
            } else setMsg(data.message || 'شکلی پیشنهاد نشد.');
        } catch (e) { setMsg(e.response?.data?.message || 'هوش مصنوعی پاسخ نداد.'); }
        setBusy(null);
    };
    const upload = async (file) => {
        if (!file) return;
        setBusy('up'); setMsg(null);
        try {
            const fd = new FormData();
            fd.append('image', await compressImage(file));
            const { data } = await axios.post(route('teacher.figures.upload'), fd);
            onChange(data.url); setOpen(false);
        } catch (e) { setMsg(e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'آپلودِ عکس نشد.'); }
        setBusy(null);
        if (fileRef.current) fileRef.current.value = '';
    };

    return (
        <div className="qf">
            {value && !open && (
                <div className="qf-has">
                    <img src={value} alt="شکلِ سؤال" />
                    <div className="qf-acts">
                        <button type="button" className="qf-btn" onClick={() => setOpen(true)}>✏️ شکلِ دیگر</button>
                        <button type="button" className="qf-btn danger" onClick={() => onChange(null)}>🗑 حذفِ شکل</button>
                    </div>
                </div>
            )}
            {!value && !open && <button type="button" className="qf-add" onClick={() => setOpen(true)}>🖼️ افزودنِ شکل (پیتزا، نمودار، محورِ اعداد، شکلِ هندسی یا عکس)</button>}
            {open && (
                <div className="qf-panel">
                    <div className="qf-tabs">
                        {[['draw', '📐 رسمِ شکل'], ['ai', '🤖 با هوش مصنوعی'], ['photo', '📷 عکس']].map(([k, l]) => (
                            <button type="button" key={k} className={tab === k ? 'on' : ''} onClick={() => { setTab(k); setMsg(null); }}>{l}</button>
                        ))}
                        <button type="button" className="qf-x" onClick={() => setOpen(false)} aria-label="بستن">✕</button>
                    </div>

                    {tab === 'ai' && (
                        <div className="qf-body">
                            <p className="qf-muted">هوش مصنوعی متنِ سؤال را می‌خواند و شکلِ مناسب (کسرِ پیتزایی، نمودار، محور، شکلِ هندسی…) با همان عددها پیشنهاد می‌دهد.</p>
                            <input className="qf-in" value={hint} onChange={(e) => setHint(e.target.value)} placeholder="توضیحِ اختیاری؛ مثلاً «پیتزای ۸ تکه که ۳ تکه‌اش خورده شده»" />
                            <button type="button" className="qf-btn primary" onClick={ai} disabled={!!busy}>{busy === 'ai' ? '⏳ در حالِ طراحی…' : '🤖 پیشنهادِ شکل'}</button>
                        </div>
                    )}

                    {tab === 'photo' && (
                        <div className="qf-body">
                            <p className="qf-muted">عکسِ شکل را از کتاب یا برگه بگیرید یا انتخاب کنید (فقط همان بخشِ شکل).</p>
                            <input ref={fileRef} type="file" accept="image/*" onChange={(e) => upload(e.target.files?.[0])} disabled={!!busy} />
                            {busy === 'up' && <span className="qf-muted">⏳ در حالِ آپلود…</span>}
                        </div>
                    )}

                    {tab === 'draw' && (
                        <div className="qf-body qf-draw">
                            <div className="qf-types">
                                {TYPES.map(([k, l]) => <button type="button" key={k} className={type === k ? 'on' : ''} onClick={() => pickType(k)}>{l}</button>)}
                            </div>
                            <div className="qf-grid">
                                <div className="qf-fields">
                                    {FIELDS[type].map(([k, label, kind, , help]) => (
                                        <label key={k}>
                                            <span>{label}</span>
                                            {kind === 'select'
                                                ? <select className="qf-in" value={form[k]} onChange={(e) => set(k, e.target.value)}>{SHAPES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
                                                : kind === 'lines'
                                                    ? <textarea className="qf-in" rows={3} value={form[k] ?? ''} onChange={(e) => set(k, e.target.value)} />
                                                    : <input className="qf-in" value={form[k] ?? ''} onChange={(e) => set(k, e.target.value)} inputMode={kind === 'num' ? 'decimal' : undefined} dir={kind === 'num' ? 'ltr' : undefined} />}
                                            {help && <small>{help}</small>}
                                        </label>
                                    ))}
                                    <label><span>زیرنویس (اختیاری)</span><input className="qf-in" value={form.caption ?? ''} onChange={(e) => set('caption', e.target.value)} /></label>
                                </div>
                                <div className="qf-prev">
                                    {svg ? <img src={`data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`} alt="پیش‌نمایش" /> : <span className="qf-muted">پیش‌نمایش…</span>}
                                </div>
                            </div>
                            <button type="button" className="qf-btn primary" onClick={save} disabled={!!busy || !svg}>{busy === 'save' ? '⏳…' : '✅ افزودنِ این شکل به سؤال'}</button>
                        </div>
                    )}
                    {msg && <div className="qf-msg">{msg}</div>}
                </div>
            )}
        </div>
    );
}
