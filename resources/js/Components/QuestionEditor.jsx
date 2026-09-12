/**
 * ویرایشگرِ مشترکِ سؤال‌های کاربرگ.
 *
 * پیش‌تر این منطق فقط داخلِ صفحه‌ی «ساخت کاربرگ» بود، پس کاربرگِ ساخته‌شده
 * دیگر قابلِ اصلاح نبود. اکنون هم در ساخت و هم در پیش‌نمایش/بانک استفاده
 * می‌شود تا معلم بتواند سؤال‌ها را بعداً هم ویرایش کند.
 */

export const Q_TYPES = [
    { v: 'mc', t: 'چهارگزینه‌ای' },
    { v: 'tf', t: 'درست/نادرست' },
    { v: 'blank', t: 'جای خالی' },
    { v: 'desc', t: 'تشریحی' },
];

export const typeLabel = (v) => Q_TYPES.find((t) => t.v === v)?.t || 'چهارگزینه‌ای';

/** سؤالِ خالی «دقیقاً از نوعی که معلم خواسته». */
export const blankQ = (type = 'mc') => {
    const q = { prompt: '', type, choices: [], answer: null };
    if (type === 'mc') {
        q.choices = [0, 1, 2, 3].map((i) => ({ value: '', correct: i === 0 }));
    } else if (type === 'tf') {
        q.choices = [{ value: 'درست', correct: true }, { value: 'نادرست', correct: false }];
    } else {
        q.answer = '';
    }
    return q;
};

/** تبدیلِ نوعِ یک سؤالِ موجود، بدون از دست رفتنِ صورتِ سؤال. */
export const retype = (q, type) => {
    const base = blankQ(type);
    base.prompt = q.prompt || '';
    if ((type === 'mc' || type === 'tf') && (q.choices || []).length) {
        const keep = q.choices.slice(0, base.choices.length)
            .map((c, i) => ({ value: c.value || base.choices[i].value, correct: !!c.correct }));
        while (keep.length < base.choices.length) keep.push({ ...base.choices[keep.length], correct: false });
        if (!keep.some((c) => c.correct)) keep[0].correct = true;
        base.choices = keep;
    }
    if ((type === 'desc' || type === 'blank') && q.answer) base.answer = q.answer;
    return base;
};

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * فهرستِ سؤال‌ها با ویرایشِ کامل: متن، نوع، گزینه‌ها، پاسخِ درست و حذف.
 * `onChange` همیشه آرایه‌ی کاملِ تازه را می‌گیرد.
 */
export default function QuestionEditor({ questions, onChange, maxHeight = 460 }) {
    const patch = (i, fn) => onChange(questions.map((q, j) => (j === i ? fn(q) : q)));
    const editQ = (i, k, v) => patch(i, (q) => ({ ...q, [k]: v }));
    const editChoice = (qi, ci, v) => patch(qi, (q) => ({
        ...q, choices: (q.choices || []).map((c, k) => (k === ci ? { ...c, value: v } : c)),
    }));
    const setCorrect = (qi, ci) => patch(qi, (q) => ({
        ...q, choices: (q.choices || []).map((c, k) => ({ ...c, correct: k === ci })),
    }));
    const removeQ = (i) => onChange(questions.filter((_, j) => j !== i));
    const move = (i, d) => {
        const j = i + d;
        if (j < 0 || j >= questions.length) return;
        const next = questions.slice();
        [next[i], next[j]] = [next[j], next[i]];
        onChange(next);
    };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12, maxHeight, overflowY: 'auto' }}>
            {questions.map((q, i) => (
                <div key={i} style={{ border: '1px solid var(--line)', borderRadius: 12, padding: 12 }}>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'flex-start' }}>
                        <span style={{ fontWeight: 900, color: 'var(--muted)' }}>{fa(i + 1)}.</span>
                        <textarea className="input" rows={2} value={q.prompt || ''} onChange={(e) => editQ(i, 'prompt', e.target.value)} style={{ flex: 1, resize: 'vertical' }} />
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                            <button type="button" onClick={() => move(i, -1)} disabled={i === 0} className="tag" style={{ border: 0, cursor: 'pointer', padding: '2px 7px' }} title="بالاتر">▲</button>
                            <button type="button" onClick={() => move(i, 1)} disabled={i === questions.length - 1} className="tag" style={{ border: 0, cursor: 'pointer', padding: '2px 7px' }} title="پایین‌تر">▼</button>
                        </div>
                        <button type="button" onClick={() => removeQ(i)} className="tag" style={{ border: 0, background: '#fdecee', color: '#b0333f', cursor: 'pointer' }}>✕</button>
                    </div>

                    <div style={{ display: 'flex', gap: 6, alignItems: 'center', marginTop: 8, marginInlineStart: 22 }}>
                        <span style={{ fontSize: 12, color: 'var(--muted)' }}>نوع سؤال:</span>
                        <select className="input" value={q.type || 'mc'} onChange={(e) => patch(i, (x) => retype(x, e.target.value))}
                            style={{ padding: '5px 8px', fontSize: 12.5, width: 'auto' }}>
                            {Q_TYPES.map((t) => <option key={t.v} value={t.v}>{t.t}</option>)}
                        </select>
                    </div>

                    {(q.type === 'mc' || q.type === 'tf') && (q.choices || []).map((c, ci) => (
                        <div key={ci} style={{ display: 'flex', gap: 6, alignItems: 'center', marginTop: 6, marginInlineStart: 22 }}>
                            <input type="radio" checked={!!c.correct} onChange={() => setCorrect(i, ci)} title="پاسخ درست" />
                            <input className="input" value={c.value || ''} onChange={(e) => editChoice(i, ci, e.target.value)} style={{ flex: 1 }} />
                        </div>
                    ))}

                    {(q.type === 'desc' || q.type === 'blank') && (
                        <input className="input" value={q.answer || ''} onChange={(e) => editQ(i, 'answer', e.target.value)}
                            placeholder={q.type === 'blank' ? 'پاسخِ جای خالی' : 'پاسخ نمونه'}
                            style={{ marginTop: 6, marginInlineStart: 22, width: 'calc(100% - 22px)' }} />
                    )}
                    {q.type === 'blank' && (
                        <div style={{ fontSize: 11.5, color: 'var(--muted-2)', marginTop: 4, marginInlineStart: 22 }}>
                            برای جای خالی در متنِ سؤال از «…» یا «____» استفاده کن.
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}
