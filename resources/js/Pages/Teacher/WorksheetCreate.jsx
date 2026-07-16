import { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import axios from 'axios';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** کاربرگ‌سازِ هوشمند: مشخصات + تم → پیشنهاد سؤال با AI → پیش‌نمایش تصویری → ذخیره در بانک. */
export default function WorksheetCreate() {
    const { themes = [] } = usePage().props;
    const [spec, setSpec] = useState({
        title: '', subject: '', grade: 'چهارم', topic: '', goal: '',
        theme: themes[0]?.key || 'stars', count: 6, difficulty: 'medium', type: 'mc',
    });
    const set = (k, v) => setSpec((s) => ({ ...s, [k]: v }));

    const [questions, setQuestions] = useState([]);
    const [busy, setBusy] = useState(false);
    const [msg, setMsg] = useState(null);
    const [saving, setSaving] = useState(false);

    const runAi = async (sample = false) => {
        setBusy(true); setMsg(null);
        try {
            const { data } = await axios.post(route('teacher.worksheets.ai'), {
                subject: spec.subject, grade: spec.grade, topic: spec.topic || spec.subject,
                goal: spec.goal, theme: spec.theme, count: spec.count,
                difficulty: spec.difficulty, type: spec.type, sample,
            });
            if (data.ok) {
                setQuestions(data.questions.map((q) => ({ ...q })));
                setMsg(data.message ? { t: 'info', m: data.message } : { t: 'ok', m: `${fa(data.questions.length)} سؤال پیشنهاد شد ✅` });
            } else {
                setMsg({ t: 'err', m: data.message || 'تولید نشد.' });
            }
        } catch (e) {
            setMsg({ t: 'err', m: 'خطا در ارتباط با سرور.' });
        } finally { setBusy(false); }
    };

    const editQ = (i, k, v) => setQuestions((qs) => qs.map((q, j) => (j === i ? { ...q, [k]: v } : q)));
    const editChoice = (qi, ci, v) => setQuestions((qs) => qs.map((q, j) => {
        if (j !== qi) return q;
        const choices = (q.choices || []).map((c, k) => (k === ci ? { ...c, value: v } : c));
        return { ...q, choices };
    }));
    const setCorrect = (qi, ci) => setQuestions((qs) => qs.map((q, j) => {
        if (j !== qi) return q;
        return { ...q, choices: (q.choices || []).map((c, k) => ({ ...c, correct: k === ci })) };
    }));
    const removeQ = (i) => setQuestions((qs) => qs.filter((_, j) => j !== i));

    const save = () => {
        if (!spec.title.trim()) { setMsg({ t: 'err', m: 'عنوان کاربرگ را وارد کن.' }); return; }
        if (questions.length === 0) { setMsg({ t: 'err', m: 'ابتدا سؤال‌ها را تولید کن.' }); return; }
        setSaving(true);
        router.post(route('teacher.worksheets.store'), {
            title: spec.title, subject: spec.subject, grade: spec.grade,
            theme: spec.theme, spec: spec.topic + (spec.goal ? ' | ' + spec.goal : ''),
            questions,
        }, { onFinish: () => setSaving(false) });
    };

    const themeColors = { stars: '#3d7bf0', pitch: '#2bb673', blocks: '#4caf50', speed: '#e8862e', classic: '#3d7bf0' };

    return (
        <DashLayout title="ساخت کاربرگ هوشمند" roleLabel="معلم" menu={teacherMenu} active="assignments">
            <div style={{ display: 'grid', gap: 16, gridTemplateColumns: 'repeat(auto-fit,minmax(340px,1fr))' }}>
                {/* گام ۱: مشخصات */}
                <div className="panel">
                    <h3>① مشخصات کاربرگ</h3>
                    <Field label="عنوان کاربرگ">
                        <input className="input" value={spec.title} onChange={(e) => set('title', e.target.value)} placeholder="مثلاً: کاربرگ ضرب و ماجراجویی فضایی" />
                    </Field>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                        <Field label="درس"><input className="input" value={spec.subject} onChange={(e) => set('subject', e.target.value)} placeholder="ریاضی" /></Field>
                        <Field label="پایه"><input className="input" value={spec.grade} onChange={(e) => set('grade', e.target.value)} placeholder="چهارم" /></Field>
                    </div>
                    <Field label="موضوع"><input className="input" value={spec.topic} onChange={(e) => set('topic', e.target.value)} placeholder="ضرب اعداد دو رقمی" /></Field>
                    <Field label="هدف آموزشی (اختیاری)"><input className="input" value={spec.goal} onChange={(e) => set('goal', e.target.value)} placeholder="تسلط بر جدول ضرب" /></Field>

                    <Field label="تمِ تصویری کاربرگ">
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            {themes.map((t) => (
                                <button type="button" key={t.key} onClick={() => set('theme', t.key)}
                                    className={`tag ${spec.theme === t.key ? 'tag-warn' : 'tag-info'}`}
                                    style={{ cursor: 'pointer', border: spec.theme === t.key ? `2px solid ${themeColors[t.key]}` : 0, fontFamily: 'inherit', padding: '8px 14px' }}>{t.label}</button>
                            ))}
                        </div>
                    </Field>

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                        <Field label={`تعداد سؤال: ${fa(spec.count)}`}>
                            <input type="range" min="1" max="20" value={spec.count} onChange={(e) => set('count', +e.target.value)} style={{ width: '100%' }} />
                        </Field>
                        <Field label="نوع سؤال">
                            <select className="input" value={spec.type} onChange={(e) => set('type', e.target.value)}>
                                <option value="mc">چهارگزینه‌ای</option>
                                <option value="tf">درست/نادرست</option>
                                <option value="desc">تشریحی</option>
                                <option value="blank">جای خالی</option>
                            </select>
                        </Field>
                    </div>
                    <Field label="سطح دشواری">
                        <div style={{ display: 'flex', gap: 8 }}>
                            {[['easy', 'آسان'], ['medium', 'متوسط'], ['hard', 'دشوار']].map(([v, l]) => (
                                <button type="button" key={v} onClick={() => set('difficulty', v)} className={`tag ${spec.difficulty === v ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '7px 14px' }}>{l}</button>
                            ))}
                        </div>
                    </Field>

                    <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                        <button type="button" disabled={busy} onClick={() => runAi(false)} className="btn" style={{ flex: 1 }}>{busy ? 'در حال تولید…' : '✨ پیشنهاد سؤال با هوش مصنوعی'}</button>
                        <button type="button" disabled={busy} onClick={() => runAi(true)} className="btn btn-ghost" title="بدون کلید API — سؤال نمونه">نمونه</button>
                    </div>
                    {msg && <div style={{ marginTop: 10, fontSize: 13, borderRadius: 10, padding: '9px 12px', background: msg.t === 'err' ? '#fdecee' : msg.t === 'ok' ? '#e6f7ee' : '#eef3ff', color: msg.t === 'err' ? '#b0333f' : msg.t === 'ok' ? '#1a8a52' : '#2555c0' }}>{msg.m}</div>}
                </div>

                {/* گام ۲: سؤال‌ها + پیش‌نمایش */}
                <div className="panel">
                    <h3>② سؤال‌ها ({fa(questions.length)}) — قابل ویرایش</h3>
                    {questions.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز سؤالی تولید نشده. از سمت راست «پیشنهاد سؤال» را بزن.</p>}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 12, maxHeight: 460, overflowY: 'auto' }}>
                        {questions.map((q, i) => (
                            <div key={i} style={{ border: '1px solid var(--line)', borderRadius: 12, padding: 12 }}>
                                <div style={{ display: 'flex', gap: 8, alignItems: 'flex-start' }}>
                                    <span style={{ fontWeight: 900, color: 'var(--muted)' }}>{fa(i + 1)}.</span>
                                    <textarea className="input" rows={2} value={q.prompt || ''} onChange={(e) => editQ(i, 'prompt', e.target.value)} style={{ flex: 1, resize: 'vertical' }} />
                                    <button type="button" onClick={() => removeQ(i)} className="tag" style={{ border: 0, background: '#fdecee', color: '#b0333f', cursor: 'pointer' }}>✕</button>
                                </div>
                                {(q.type === 'mc' || q.type === 'tf') && (q.choices || []).map((c, ci) => (
                                    <div key={ci} style={{ display: 'flex', gap: 6, alignItems: 'center', marginTop: 6, marginInlineStart: 22 }}>
                                        <input type="radio" checked={!!c.correct} onChange={() => setCorrect(i, ci)} title="پاسخ درست" />
                                        <input className="input" value={c.value || ''} onChange={(e) => editChoice(i, ci, e.target.value)} style={{ flex: 1 }} />
                                    </div>
                                ))}
                                {(q.type === 'desc' || q.type === 'blank') && q.answer != null && (
                                    <input className="input" value={q.answer || ''} onChange={(e) => editQ(i, 'answer', e.target.value)} placeholder="پاسخ نمونه" style={{ marginTop: 6, marginInlineStart: 22, width: 'calc(100% - 22px)' }} />
                                )}
                            </div>
                        ))}
                    </div>
                    {questions.length > 0 && (
                        <button type="button" disabled={saving} onClick={save} className="btn" style={{ width: '100%', marginTop: 14 }}>{saving ? 'در حال ذخیره…' : '🖼️ ساخت تصویر کاربرگ و ذخیره در بانک'}</button>
                    )}
                </div>
            </div>
        </DashLayout>
    );
}

const Field = ({ label, children }) => (
    <div className="field"><label>{label}</label>{children}</div>
);
