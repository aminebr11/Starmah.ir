import { useState, useRef } from 'react';
import { usePage, router } from '@inertiajs/react';
import axios from 'axios';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const MODES = [
    { v: 'manual', ic: '✍️', t: 'دستی', d: 'سؤال‌ها را خودت می‌نویسی' },
    { v: 'upload', ic: '⬆️', t: 'بارگذاری فایل', d: 'یک کاربرگِ آماده را بارگذاری می‌کنی' },
    { v: 'ai', ic: '🤖', t: 'هوش مصنوعی', d: 'سؤال‌ها (و تصویر) با AI ساخته می‌شود' },
];
const blankQ = () => ({ prompt: '', type: 'mc', choices: [{ value: '', correct: true }, { value: '', correct: false }, { value: '', correct: false }, { value: '', correct: false }], answer: null });

/** کاربرگ‌سازِ سه‌حالته: دستی / بارگذاری فایل / هوش مصنوعی. */
export default function WorksheetCreate() {
    const { themes = [], curriculum = [], classrooms = [], imageAi = false } = usePage().props;
    const [mode, setMode] = useState('ai');
    const fileRef = useRef(null);
    const [file, setFile] = useState(null);
    const [spec, setSpec] = useState({
        title: '', level: '', grade: '', subject: '', lesson_no: '', topic: '', goal: '',
        classroom_id: '', publish: false, gen_image: false,
        theme: themes[0]?.key || 'stars', count: 6, difficulty: 'medium', type: 'mc',
    });
    const set = (k, v) => setSpec((s) => ({ ...s, [k]: v }));
    const gradesOf = (level) => (curriculum.find((l) => l.level === level)?.grades || []).map((g) => g.grade);
    const subjectsOf = (level, grade) => ((curriculum.find((l) => l.level === level)?.grades || []).find((x) => x.grade === grade)?.subjects || []).map((s) => s.name);
    const grades = gradesOf(spec.level);
    const subjects = subjectsOf(spec.level, spec.grade);

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
    const addManualQ = () => setQuestions((qs) => [...qs, blankQ()]);

    const save = () => {
        if (!spec.title.trim()) { setMsg({ t: 'err', m: 'عنوان کاربرگ را وارد کن.' }); return; }
        if (mode === 'upload' && !file) { setMsg({ t: 'err', m: 'فایلِ کاربرگ را انتخاب کن.' }); return; }
        if (mode !== 'upload' && questions.length === 0) { setMsg({ t: 'err', m: mode === 'ai' ? 'ابتدا سؤال‌ها را تولید کن.' : 'حداقل یک سؤال اضافه کن.' }); return; }
        setSaving(true);
        router.post(route('teacher.worksheets.store'), {
            title: spec.title, mode, level: spec.level, grade: spec.grade, subject: spec.subject, lesson_no: spec.lesson_no,
            classroom_id: spec.classroom_id || null, publish: spec.publish, gen_image: mode === 'ai' ? spec.gen_image : false,
            theme: spec.theme, spec: spec.topic + (spec.goal ? ' | ' + spec.goal : ''),
            questions: mode === 'upload' ? [] : questions,
            file: mode === 'upload' ? file : null,
        }, { forceFormData: mode === 'upload', onFinish: () => setSaving(false) });
    };

    const themeColors = { stars: '#3d7bf0', pitch: '#2bb673', blocks: '#4caf50', speed: '#e8862e', classic: '#3d7bf0' };

    return (
        <DashLayout title="ساخت کاربرگ هوشمند" roleLabel="معلم" menu={teacherMenu} active="assignments">
            {/* انتخابِ حالتِ ساخت */}
            <div className="panel">
                <h3 style={{ marginTop: 0 }}>روشِ ساختِ کاربرگ را انتخاب کن</h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 10 }}>
                    {MODES.map((m) => (
                        <button key={m.v} type="button" onClick={() => setMode(m.v)}
                            style={{ textAlign: 'right', cursor: 'pointer', fontFamily: 'inherit', borderRadius: 14, padding: 14, border: mode === m.v ? '2px solid var(--gold)' : '1px solid var(--line)', background: mode === m.v ? '#fff8e8' : '#fff' }}>
                            <div style={{ fontSize: 26 }}>{m.ic}</div>
                            <div style={{ fontWeight: 800, marginTop: 4 }}>{m.t}</div>
                            <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 2 }}>{m.d}</div>
                        </button>
                    ))}
                </div>
            </div>

            {mode === 'upload' && (
                <div className="panel">
                    <h3>⬆️ بارگذاریِ فایلِ کاربرگ</h3>
                    <Field label="عنوان کاربرگ">
                        <input className="input" value={spec.title} onChange={(e) => set('title', e.target.value)} placeholder="مثلاً: کاربرگ ریاضی درس ۳" />
                    </Field>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                        <Field label="مقطع"><select className="input" value={spec.level} onChange={(e) => { set('level', e.target.value); set('grade', ''); set('subject', ''); }}><option value="">— انتخاب —</option>{curriculum.map((l) => <option key={l.level} value={l.level}>{l.level}</option>)}</select></Field>
                        <Field label="کلاس"><select className="input" value={spec.grade} onChange={(e) => { set('grade', e.target.value); set('subject', ''); }} disabled={!spec.level}><option value="">— انتخاب —</option>{grades.map((g) => <option key={g} value={g}>{g}</option>)}</select></Field>
                        <Field label="درس"><select className="input" value={spec.subject} onChange={(e) => set('subject', e.target.value)} disabled={!spec.grade}><option value="">— انتخاب —</option>{subjects.map((s) => <option key={s} value={s}>{s}</option>)}</select></Field>
                        <Field label="شماره درس"><input className="input" value={spec.lesson_no} onChange={(e) => set('lesson_no', e.target.value)} placeholder="مثلاً: ۳" dir="ltr" /></Field>
                    </div>
                    <Field label="فایلِ کاربرگ (PDF/تصویر/Word — حداکثر ۲۰ مگابایت)">
                        <input ref={fileRef} type="file" accept=".pdf,.doc,.docx,image/*" className="input" style={{ padding: 9 }} onChange={(e) => setFile(e.target.files[0] || null)} />
                    </Field>
                    <Field label="انتشار برای کلاس (اختیاری)">
                        <select className="input" value={spec.classroom_id} onChange={(e) => set('classroom_id', e.target.value)}>
                            <option value="">— بدون انتشار (فقط ذخیره در بانک) —</option>
                            {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </Field>
                    <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 13, marginTop: 4 }}>
                        <input type="checkbox" checked={spec.publish} onChange={(e) => set('publish', e.target.checked)} disabled={!spec.classroom_id} />
                        انتشار فوری و اعلان به دانش‌آموزانِ کلاس
                    </label>
                    <button type="button" disabled={saving} onClick={save} className="btn" style={{ width: '100%', marginTop: 12 }}>{saving ? 'در حال بارگذاری…' : '⬆️ بارگذاری و ذخیره در بانک'}</button>
                    {msg && <div style={{ marginTop: 10, fontSize: 13, borderRadius: 10, padding: '9px 12px', background: msg.t === 'err' ? '#fdecee' : '#e6f7ee', color: msg.t === 'err' ? '#b0333f' : '#1a8a52' }}>{msg.m}</div>}
                </div>
            )}

            <div style={{ display: mode === 'upload' ? 'none' : 'grid', gap: 16, gridTemplateColumns: 'repeat(auto-fit,minmax(340px,1fr))' }}>
                {/* گام ۱: مشخصات */}
                <div className="panel">
                    <h3>① مشخصات کاربرگ</h3>
                    <Field label="عنوان کاربرگ">
                        <input className="input" value={spec.title} onChange={(e) => set('title', e.target.value)} placeholder="مثلاً: کاربرگ ضرب و ماجراجویی فضایی" />
                    </Field>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                        <Field label="مقطع">
                            <select className="input" value={spec.level} onChange={(e) => { set('level', e.target.value); set('grade', ''); set('subject', ''); }}>
                                <option value="">— انتخاب —</option>{curriculum.map((l) => <option key={l.level} value={l.level}>{l.level}</option>)}
                            </select>
                        </Field>
                        <Field label="کلاس">
                            <select className="input" value={spec.grade} onChange={(e) => { set('grade', e.target.value); set('subject', ''); }} disabled={!spec.level}>
                                <option value="">— انتخاب —</option>{grades.map((g) => <option key={g} value={g}>{g}</option>)}
                            </select>
                        </Field>
                        <Field label="درس">
                            <select className="input" value={spec.subject} onChange={(e) => set('subject', e.target.value)} disabled={!spec.grade}>
                                <option value="">— انتخاب —</option>{subjects.map((s) => <option key={s} value={s}>{s}</option>)}
                            </select>
                        </Field>
                        <Field label="شماره درس"><input className="input" value={spec.lesson_no} onChange={(e) => set('lesson_no', e.target.value)} placeholder="مثلاً: ۳" dir="ltr" /></Field>
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

                    {mode === 'ai' ? (
                        <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                            <button type="button" disabled={busy} onClick={() => runAi(false)} className="btn" style={{ flex: 1 }}>{busy ? 'در حال تولید…' : '✨ پیشنهاد سؤال با هوش مصنوعی'}</button>
                            <button type="button" disabled={busy} onClick={() => runAi(true)} className="btn btn-ghost" title="بدون کلید API — سؤال نمونه">نمونه</button>
                        </div>
                    ) : (
                        <button type="button" onClick={addManualQ} className="btn" style={{ width: '100%', marginTop: 8 }}>➕ افزودن سؤالِ دستی</button>
                    )}
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
                        <div style={{ marginTop: 14, borderTop: '1px solid var(--line)', paddingTop: 12 }}>
                            <b style={{ fontSize: 13.5 }}>③ انتشار</b>
                            <Field label="انتشار برای کلاس (اختیاری)">
                                <select className="input" value={spec.classroom_id} onChange={(e) => set('classroom_id', e.target.value)}>
                                    <option value="">— بدون انتشار (فقط ذخیره در بانک) —</option>
                                    {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            </Field>
                            <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 13, marginTop: 4 }}>
                                <input type="checkbox" checked={spec.publish} onChange={(e) => set('publish', e.target.checked)} disabled={!spec.classroom_id} />
                                انتشار فوری و اعلان به دانش‌آموزانِ کلاس
                            </label>
                            {imageAi && mode === 'ai' && (
                                <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 13, marginTop: 6 }}>
                                    <input type="checkbox" checked={spec.gen_image} onChange={(e) => set('gen_image', e.target.checked)} />
                                    🎨 تولید تصویرِ کاربرگ با هوش مصنوعی (ممکن است چند ثانیه طول بکشد)
                                </label>
                            )}
                            <button type="button" disabled={saving} onClick={save} className="btn" style={{ width: '100%', marginTop: 12 }}>{saving ? 'در حال ذخیره…' : '🖼️ ساخت کاربرگ و ذخیره در بانک'}</button>
                        </div>
                    )}
                </div>
            </div>
        </DashLayout>
    );
}

const Field = ({ label, children }) => (
    <div className="field"><label>{label}</label>{children}</div>
);
