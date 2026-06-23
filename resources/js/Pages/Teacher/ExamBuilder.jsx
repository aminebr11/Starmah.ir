import { usePage, useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const emptyQ = () => ({ prompt: '', choices: [{ value: '', correct: true }, { value: '', correct: false }, { value: '', correct: false }, { value: '', correct: false }] });

export default function ExamBuilder() {
    const { classroom, exams = [], aiEnabled, flash } = usePage().props;
    const [mode, setMode] = useState('ai'); // ai | manual
    const [topic, setTopic] = useState('');
    const [aiCount, setAiCount] = useState(5);
    const [busy, setBusy] = useState(false);

    const form = useForm({ title: '', type: 'exam', publish: true, questions: [] });

    const generate = async () => {
        if (!topic.trim()) { alert('موضوع آزمون را بنویس'); return; }
        setBusy(true);
        try {
            const { data } = await axios.post(route('teacher.exams.generate'), { topic, count: aiCount });
            form.setData('questions', data.questions);
            if (!form.data.title) form.setData('title', `آزمون ${topic}`);
        } finally { setBusy(false); }
    };

    const addQ = () => form.setData('questions', [...form.data.questions, emptyQ()]);
    const setQ = (i, patch) => form.setData('questions', form.data.questions.map((q, k) => k === i ? { ...q, ...patch } : q));
    const setChoice = (i, ci, patch) => setQ(i, { choices: form.data.questions[i].choices.map((c, k) => k === ci ? { ...c, ...patch } : (patch.correct ? { ...c, correct: false } : c)) });
    const removeQ = (i) => form.setData('questions', form.data.questions.filter((_, k) => k !== i));

    const submit = (e) => { e.preventDefault(); form.post(route('teacher.exams.store')); };

    if (!classroom) return <DashLayout title="آزمون‌ساز" roleLabel="معلم" menu={teacherMenu} active="exams"><div className="panel">ابتدا کلاس بساز.</div></DashLayout>;

    return (
        <DashLayout title="آزمون‌ساز" roleLabel="معلم" menu={teacherMenu} active="exams">
            {flash?.flash && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{typeof flash.flash === 'string' ? flash.flash : flash.flash.message}</b></div>}

            <form onSubmit={submit} className="panel">
                <h3>📝 ساخت آزمون جدید</h3>
                <div style={{ display: 'flex', gap: 8, marginBottom: 14 }}>
                    <button type="button" onClick={() => setMode('ai')} className={`tag ${mode === 'ai' ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 16px' }}>🤖 با هوش مصنوعی</button>
                    <button type="button" onClick={() => setMode('manual')} className={`tag ${mode === 'manual' ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 16px' }}>✍️ دستی</button>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 12 }}>
                    <div className="field"><label>عنوان آزمون</label><input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً: آزمون ریاضی مهر" /></div>
                    <div className="field"><label>نوع</label>
                        <select className="input" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                            <option value="exam">آزمون</option><option value="quiz">کوییز</option>
                        </select>
                    </div>
                </div>

                {mode === 'ai' && (
                    <div style={{ background: 'var(--cream)', borderRadius: 14, padding: 14, marginBottom: 14 }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr auto', gap: 10, alignItems: 'end' }} className="sched-form">
                            <div className="field" style={{ margin: 0 }}><label>موضوع آزمون</label><input className="input" value={topic} onChange={(e) => setTopic(e.target.value)} placeholder="مثلاً: ضرب و تقسیم، جمله‌سازی..." /></div>
                            <div className="field" style={{ margin: 0 }}><label>تعداد سؤال</label><input type="number" min="1" max="20" className="input" value={aiCount} onChange={(e) => setAiCount(+e.target.value)} /></div>
                            <button type="button" onClick={generate} disabled={busy} className="btn">{busy ? 'در حال ساخت...' : '✨ تولید سؤال'}</button>
                        </div>
                        {!aiEnabled && <div style={{ color: 'var(--muted)', fontSize: 12, marginTop: 8 }}>کلید هوش مصنوعی تنظیم نشده؛ فعلاً سؤال‌های پایه تولید می‌شود. با تنظیم ANTHROPIC_API_KEY در .env، تولید واقعی AI فعال می‌شود.</div>}
                    </div>
                )}

                {/* لیست سؤال‌ها (قابل ویرایش) */}
                {form.data.questions.map((q, i) => (
                    <div key={i} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 14, marginBottom: 10 }}>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 8 }}>
                            <b style={{ color: 'var(--gold-2)' }}>{fa(i + 1)}.</b>
                            <input className="input" value={q.prompt} onChange={(e) => setQ(i, { prompt: e.target.value })} placeholder="متن سؤال" />
                            <button type="button" onClick={() => removeQ(i)} style={{ border: 0, background: 'none', color: '#e8505b', cursor: 'pointer', fontSize: 16 }}>✕</button>
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                            {q.choices.map((c, ci) => (
                                <label key={ci} style={{ display: 'flex', alignItems: 'center', gap: 6, background: c.correct ? '#e3f7ec' : '#f0f3f9', borderRadius: 10, padding: '6px 10px' }}>
                                    <input type="radio" name={`correct-${i}`} checked={c.correct} onChange={() => setChoice(i, ci, { correct: true })} />
                                    <input value={c.value} onChange={(e) => setChoice(i, ci, { value: e.target.value })} placeholder={`گزینه ${fa(ci + 1)}`} style={{ border: 0, background: 'none', flex: 1, fontFamily: 'inherit', outline: 'none' }} />
                                </label>
                            ))}
                        </div>
                    </div>
                ))}

                <div style={{ display: 'flex', gap: 10, alignItems: 'center', marginTop: 8, flexWrap: 'wrap' }}>
                    <button type="button" onClick={addQ} className="btn btn-ghost btn-sm">➕ سؤال دستی</button>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 14 }}>
                        <input type="checkbox" checked={form.data.publish} onChange={(e) => form.setData('publish', e.target.checked)} /> انتشار برای دانش‌آموزان
                    </label>
                    <button type="submit" disabled={form.processing || !form.data.questions.length} className="btn" style={{ marginInlineStart: 'auto' }}>ذخیره‌ی آزمون ({fa(form.data.questions.length)} سؤال)</button>
                </div>
            </form>

            <div className="panel">
                <h3>📚 آزمون‌های ساخته‌شده</h3>
                {exams.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز آزمونی نساخته‌ای.</p>}
                {exams.map((e) => (
                    <div key={e.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 0', borderBottom: '1px solid var(--line)' }}>
                        <b>{e.title}</b>
                        <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                            <span style={{ color: 'var(--muted)', fontSize: 13 }}>{fa(e.count)} سؤال</span>
                            <span className={`tag ${e.published ? 'tag-ok' : 'tag-warn'}`}>{e.published ? 'منتشرشده' : 'پیش‌نویس'}</span>
                        </div>
                    </div>
                ))}
            </div>
        </DashLayout>
    );
}
