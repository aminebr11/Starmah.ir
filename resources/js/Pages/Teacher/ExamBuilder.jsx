import { usePage, useForm, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const DIFF = { easy: ['ساده', '#22c55e'], medium: ['متوسط', '#f59e0b'], hard: ['دشوار', '#ef4444'] };
const QTYPE = { mc: '🔘 چهارگزینه‌ای', tf: '✔️ درست/نادرست', desc: '✍️ تشریحی' };
const emptyQ = (type = 'mc') => {
    if (type === 'tf') return { type: 'tf', prompt: '', choices: [{ value: 'درست', correct: true }, { value: 'نادرست', correct: false }] };
    if (type === 'desc') return { type: 'desc', prompt: '', choices: [] };
    return { type: 'mc', prompt: '', choices: [{ value: '', correct: true }, { value: '', correct: false }, { value: '', correct: false }, { value: '', correct: false }] };
};

export default function ExamBuilder() {
    const { classroom, subjects = [], exams = [], bank = [], aiEnabled, flash } = usePage().props;
    const [mode, setMode] = useState('ai'); // ai | manual
    const [topic, setTopic] = useState('');
    const [aiCount, setAiCount] = useState(5);
    const [busy, setBusy] = useState(false);
    const [editId, setEditId] = useState(null);
    const [showBank, setShowBank] = useState(false);
    const [bankLesson, setBankLesson] = useState('');

    const form = useForm({ title: '', type: 'exam', subject: '', difficulty: 'medium', duration: '', scheduled_at: '', publish: true, questions: [] });

    // بانک سؤالات
    const insertFromBank = (q) => form.setData('questions', [...form.data.questions, { type: q.type, prompt: q.prompt, choices: (q.choices || []).map((c) => ({ ...c })) }]);
    const saveBank = () => {
        if (!form.data.questions.length) { alert('سؤالی برای ذخیره وجود ندارد'); return; }
        router.post(route('teacher.exams.bank.store'), { lesson: form.data.subject || topic, questions: form.data.questions }, { preserveScroll: true, preserveState: true });
    };
    const delBank = (id) => { if (confirm('این سؤال از بانک حذف شود؟')) router.delete(route('teacher.exams.bank.destroy', id), { preserveScroll: true, preserveState: true }); };
    const bankShown = bank.filter((q) => !bankLesson || q.lesson === bankLesson);
    const bankLessons = [...new Set(bank.map((q) => q.lesson).filter(Boolean))];

    const generate = async () => {
        if (!topic.trim()) { alert('موضوع آزمون را بنویس'); return; }
        setBusy(true);
        try {
            const { data } = await axios.post(route('teacher.exams.generate'), { topic, count: aiCount });
            form.setData('questions', data.questions);
            if (!form.data.title) form.setData('title', `آزمون ${topic}`);
            if (!form.data.subject && subjects.some((s) => s.name === topic)) form.setData('subject', topic);
        } finally { setBusy(false); }
    };

    const addQ = (type = 'mc') => form.setData('questions', [...form.data.questions, emptyQ(type)]);
    const setQ = (i, patch) => form.setData('questions', form.data.questions.map((q, k) => k === i ? { ...q, ...patch } : q));
    const changeType = (i, type) => form.setData('questions', form.data.questions.map((q, k) => k === i ? emptyQ(type) : q).map((q, k) => k === i ? { ...q, prompt: form.data.questions[i].prompt } : q));
    const setChoice = (i, ci, patch) => setQ(i, { choices: form.data.questions[i].choices.map((c, k) => k === ci ? { ...c, ...patch } : (patch.correct ? { ...c, correct: false } : c)) });
    const removeQ = (i) => form.setData('questions', form.data.questions.filter((_, k) => k !== i));

    const resetForm = () => { setEditId(null); form.reset(); form.setData('questions', []); setTopic(''); };

    const loadExam = (e, asCopy = false) => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setEditId(asCopy ? null : e.id);
        form.setData({
            title: asCopy ? `${e.title} (کپی)` : e.title, type: e.type, subject: e.subject || '',
            difficulty: e.difficulty || 'medium', duration: e.duration || '', scheduled_at: e.scheduled_at || '',
            publish: e.published, questions: (e.questions || []).map((q) => ({ ...q, choices: q.choices.map((c) => ({ ...c })) })),
        });
        setMode('manual');
    };

    const submit = (ev) => {
        ev.preventDefault();
        if (editId) form.put(route('teacher.exams.update', editId), { onSuccess: resetForm });
        else form.post(route('teacher.exams.store'), { onSuccess: resetForm });
    };
    const del = (id) => { if (confirm('این آزمون حذف شود؟')) router.delete(route('teacher.exams.destroy', id), { preserveScroll: true }); };

    if (!classroom) return <DashLayout title="آزمون‌ساز" roleLabel="معلم" menu={teacherMenu} active="exams"><div className="panel">ابتدا کلاس بساز.</div></DashLayout>;

    return (
        <DashLayout title="آزمون‌ساز" roleLabel="معلم" menu={teacherMenu} active="exams">
            {flash?.flash && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{typeof flash.flash === 'string' ? flash.flash : flash.flash.message}</b></div>}

            <form onSubmit={submit} className="panel">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <h3 style={{ margin: 0 }}>{editId ? '✏️ ویرایش آزمون' : '📝 ساخت آزمون جدید'}</h3>
                    {editId && <button type="button" onClick={resetForm} className="btn btn-ghost btn-sm">✕ انصراف از ویرایش</button>}
                </div>
                <div style={{ display: 'flex', gap: 8, margin: '14px 0' }}>
                    <button type="button" onClick={() => setMode('ai')} className={`tag ${mode === 'ai' ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 16px' }}>🤖 با هوش مصنوعی</button>
                    <button type="button" onClick={() => setMode('manual')} className={`tag ${mode === 'manual' ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 16px' }}>✍️ دستی</button>
                </div>

                {subjects.length > 0 && (
                    <div style={{ marginBottom: 12 }}>
                        <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>درس‌های پایه‌ی این کلاس (انتخاب سریع):</div>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                            {subjects.map((s) => (
                                <button type="button" key={s.name} className={`tag ${form.data.subject === s.name ? 'tag-ok' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit' }}
                                    onClick={() => { setTopic(s.name); form.setData('subject', s.name); if (!form.data.title) form.setData('title', `آزمون ${s.name}`); }}>
                                    {s.icon || '📘'} {s.name}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 12 }}>
                    <div className="field"><label>عنوان آزمون</label><input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً: آزمون ریاضی مهر" /></div>
                    <div className="field"><label>نوع</label>
                        <select className="input" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                            <option value="exam">آزمون</option><option value="quiz">کوییز</option>
                        </select>
                    </div>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 12 }}>
                    <div className="field"><label>سطح دشواری</label>
                        <select className="input" value={form.data.difficulty} onChange={(e) => form.setData('difficulty', e.target.value)}>
                            {Object.entries(DIFF).map(([k, v]) => <option key={k} value={k}>{v[0]}</option>)}
                        </select>
                    </div>
                    <div className="field"><label>مدت (دقیقه)</label><input type="number" min="1" max="300" className="input" value={form.data.duration} onChange={(e) => form.setData('duration', e.target.value)} placeholder="مثلاً ۲۰" /></div>
                    <div className="field"><label>زمان‌بندی برگزاری (اختیاری)</label><JalaliDatePicker withTime value={form.data.scheduled_at} onChange={(v) => form.setData('scheduled_at', v)} placeholder="تاریخ و ساعت" /></div>
                </div>

                {mode === 'ai' && (
                    <div style={{ background: 'var(--cream)', borderRadius: 14, padding: 14, margin: '6px 0 14px' }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr auto', gap: 10, alignItems: 'end' }} className="sched-form">
                            <div className="field" style={{ margin: 0 }}><label>موضوع آزمون</label><input className="input" value={topic} onChange={(e) => setTopic(e.target.value)} placeholder="مثلاً: ضرب و تقسیم، جمله‌سازی..." /></div>
                            <div className="field" style={{ margin: 0 }}><label>تعداد سؤال</label><input type="number" min="1" max="20" className="input" value={aiCount} onChange={(e) => setAiCount(+e.target.value)} /></div>
                            <button type="button" onClick={generate} disabled={busy} className="btn">{busy ? 'در حال ساخت...' : '✨ تولید سؤال'}</button>
                        </div>
                        {!aiEnabled && <div style={{ color: 'var(--muted)', fontSize: 12, marginTop: 8 }}>کلید هوش مصنوعی تنظیم نشده؛ فعلاً سؤال‌های پایه تولید می‌شود. با تنظیم ANTHROPIC_API_KEY در .env، تولید واقعی AI فعال می‌شود.</div>}
                    </div>
                )}

                {form.data.questions.map((q, i) => {
                    const qt = q.type || 'mc';
                    return (
                    <div key={i} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 14, marginBottom: 10 }}>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 8, flexWrap: 'wrap' }}>
                            <b style={{ color: 'var(--gold-2)' }}>{fa(i + 1)}.</b>
                            <input className="input" value={q.prompt} onChange={(e) => setQ(i, { prompt: e.target.value })} placeholder="متن سؤال" style={{ flex: 1, minWidth: 160 }} />
                            <select className="input" style={{ width: 'auto' }} value={qt} onChange={(e) => changeType(i, e.target.value)}>
                                {Object.entries(QTYPE).map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                            </select>
                            <button type="button" onClick={() => removeQ(i)} style={{ border: 0, background: 'none', color: '#e8505b', cursor: 'pointer', fontSize: 16 }}>✕</button>
                        </div>
                        {qt === 'desc' ? (
                            <div style={{ fontSize: 12.5, color: 'var(--muted)', background: '#f7f9fd', borderRadius: 10, padding: '8px 12px' }}>✍️ سؤال تشریحی — دانش‌آموز پاسخ متنی می‌نویسد و توسط معلم بررسی می‌شود (خودکار تصحیح نمی‌شود).</div>
                        ) : (
                            <div style={{ display: 'grid', gridTemplateColumns: qt === 'tf' ? '1fr 1fr' : '1fr 1fr', gap: 8 }}>
                                {q.choices.map((c, ci) => (
                                    <label key={ci} style={{ display: 'flex', alignItems: 'center', gap: 6, background: c.correct ? '#e3f7ec' : '#f0f3f9', borderRadius: 10, padding: '6px 10px' }}>
                                        <input type="radio" name={`correct-${i}`} checked={c.correct} onChange={() => setChoice(i, ci, { correct: true })} />
                                        {qt === 'tf'
                                            ? <span style={{ fontWeight: 700 }}>{c.value}</span>
                                            : <input value={c.value} onChange={(e) => setChoice(i, ci, { value: e.target.value })} placeholder={`گزینه ${fa(ci + 1)}`} style={{ border: 0, background: 'none', flex: 1, fontFamily: 'inherit', outline: 'none' }} />}
                                    </label>
                                ))}
                            </div>
                        )}
                    </div>
                    );
                })}

                <div style={{ display: 'flex', gap: 10, alignItems: 'center', marginTop: 8, flexWrap: 'wrap' }}>
                    <button type="button" onClick={() => addQ('mc')} className="btn btn-ghost btn-sm">➕ چهارگزینه‌ای</button>
                    <button type="button" onClick={() => addQ('tf')} className="btn btn-ghost btn-sm">➕ درست/نادرست</button>
                    <button type="button" onClick={() => addQ('desc')} className="btn btn-ghost btn-sm">➕ تشریحی</button>
                    <button type="button" onClick={() => setShowBank(!showBank)} className="btn btn-ghost btn-sm">🏦 بانک سؤالات ({fa(bank.length)})</button>
                    {form.data.questions.length > 0 && <button type="button" onClick={saveBank} className="btn btn-ghost btn-sm">💾 ذخیره در بانک</button>}
                    <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 14 }}>
                        <input type="checkbox" checked={form.data.publish} onChange={(e) => form.setData('publish', e.target.checked)} /> انتشار برای دانش‌آموزان
                    </label>
                    <button type="submit" disabled={form.processing || !form.data.questions.length} className="btn" style={{ marginInlineStart: 'auto' }}>
                        {editId ? '💾 ذخیره‌ی تغییرات' : 'ذخیره‌ی آزمون'} ({fa(form.data.questions.length)} سؤال)
                    </button>
                </div>
            </form>

            {/* بانک سؤالات */}
            {showBank && (
                <div className="panel">
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                        <h3 style={{ margin: 0 }}>🏦 بانک سؤالات</h3>
                        {bankLessons.length > 0 && (
                            <select className="input" style={{ width: 'auto' }} value={bankLesson} onChange={(e) => setBankLesson(e.target.value)}>
                                <option value="">همه‌ی درس‌ها</option>
                                {bankLessons.map((l) => <option key={l} value={l}>{l}</option>)}
                            </select>
                        )}
                        <span style={{ color: 'var(--muted)', fontSize: 12, marginInlineStart: 'auto' }}>سؤال‌های ذخیره‌شده را در آزمون بازاستفاده کن.</span>
                    </div>
                    {bankShown.length === 0 && <p style={{ color: 'var(--muted)', marginTop: 10 }}>بانک خالی است. با «💾 ذخیره در بانک» سؤال اضافه کن.</p>}
                    {bankShown.map((q) => (
                        <div key={q.id} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 0', borderBottom: '1px solid var(--line)', flexWrap: 'wrap' }}>
                            <span className="tag tag-info" style={{ fontSize: 11 }}>{QTYPE[q.type]}</span>
                            {q.lesson && <span style={{ color: 'var(--muted)', fontSize: 12 }}>{q.lesson}</span>}
                            <span style={{ flex: 1, minWidth: 140 }}>{q.prompt || '—'}</span>
                            <button onClick={() => insertFromBank(q)} className="btn btn-ghost btn-sm">➕ افزودن به آزمون</button>
                            <button onClick={() => delBank(q.id)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
                        </div>
                    ))}
                </div>
            )}

            <div className="panel">
                <h3>📚 آزمون‌های ساخته‌شده</h3>
                {exams.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز آزمونی نساخته‌ای.</p>}
                {exams.map((e) => (
                    <div key={e.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 0', borderBottom: '1px solid var(--line)', gap: 10, flexWrap: 'wrap' }}>
                        <div>
                            <b>{e.title}</b>
                            <div style={{ display: 'flex', gap: 6, marginTop: 5, flexWrap: 'wrap', alignItems: 'center' }}>
                                <span className="tag" style={{ background: `${DIFF[e.difficulty]?.[1]}22`, color: DIFF[e.difficulty]?.[1] }}>{DIFF[e.difficulty]?.[0]}</span>
                                {e.subject && <span className="tag tag-info">{e.subject}</span>}
                                {e.duration && <span style={{ color: 'var(--muted)', fontSize: 12 }}>⏱️ {fa(e.duration)} دقیقه</span>}
                                {e.jscheduled && <span style={{ color: 'var(--muted)', fontSize: 12 }}>🗓️ {e.jscheduled}</span>}
                                <span style={{ color: 'var(--muted)', fontSize: 12 }}>· {fa(e.count)} سؤال</span>
                                {e.taken > 0 && <span style={{ color: '#16a34a', fontSize: 12 }}>· {fa(e.taken)} شرکت‌کننده · میانگین {fa(e.avg)}٪</span>}
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                            <span className={`tag ${e.published ? 'tag-ok' : 'tag-warn'}`}>{e.published ? 'منتشرشده' : 'پیش‌نویس'}</span>
                            <Link href={route('teacher.exams.report', e.id)} className="btn btn-ghost btn-sm">📊 نتایج</Link>
                            <button onClick={() => loadExam(e)} className="btn btn-ghost btn-sm" title="ویرایش">✏️</button>
                            <button onClick={() => loadExam(e, true)} className="btn btn-ghost btn-sm" title="کپی و بازاستفاده">📋</button>
                            <button onClick={() => del(e.id)} className="btn btn-ghost btn-sm" title="حذف" style={{ color: '#e8505b' }}>🗑️</button>
                        </div>
                    </div>
                ))}
            </div>
        </DashLayout>
    );
}
