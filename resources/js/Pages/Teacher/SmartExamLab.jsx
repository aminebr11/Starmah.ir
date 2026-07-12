import { usePage, useForm, router, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const blankQ = () => ({ type: 'mc', prompt: '', points: 1, difficulty: 'medium', explanation: '', source: 'manual', choices: [{ value: '', correct: true }, { value: '', correct: false }] });
const KINDS = [['diagnostic', 'تشخیصی'], ['practice', 'تمرینی'], ['class', 'کلاسی'], ['formal', 'رسمی'], ['remedial', 'جبرانی'], ['game', 'بازی‌محور']];
const DEFAULT_RULES = { duration: 20, attempts: 1, pass: 50, show_answer: true, show_result: true, shuffle: false, shuffle_choices: false, one_per_page: true };
const ST_COLOR = { draft: '#8896ad', review: '#0ea5b7', scheduled: '#e8862e', published: '#2bb673', closed: '#e8505b', archived: '#8896ad' };
const ST_LABEL = { draft: 'پیش‌نویس', review: 'آماده بررسی', scheduled: 'زمان‌بندی', published: 'منتشر', closed: 'بسته', archived: 'آرشیو' };

export default function SmartExamLab() {
    const { exams = [], buckets = {}, bankCount = 0, aiCount = 0, classroomsFull = [], groups = [], themes = [], aiEnabled, adaptiveEnabled, editing, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [step, setStep] = useState(1);
    const [editId, setEditId] = useState(editing?.id ?? null);
    const [aiBusy, setAiBusy] = useState(false);
    const [aiMsg, setAiMsg] = useState(null);
    useEffect(() => { if (flash?.flash) { setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); window.scrollTo({ top: 0 }); } }, [flash]);
    useEffect(() => { if (editing) { setEditId(editing.id); setStep(1); } }, [editing?.id]);

    const form = useForm(editing ? {
        ...editing, rules: { ...DEFAULT_RULES, ...(editing.rules || {}) },
        target_classrooms: editing.target_classrooms || [], target_themes: editing.target_themes || [],
        target_students: editing.target_students || [], questions: editing.questions?.length ? editing.questions : [blankQ()],
    } : {
        title: '', description: '', grade: classroomsFull[0]?.grade || '', subject: '', book: '', chapter: '', topic: '', goal: '',
        kind: 'practice', status: 'draft', adaptive: false, rules: { ...DEFAULT_RULES }, opens_at: '', closes_at: '',
        target_classrooms: [], target_themes: [], target_students: [], questions: [blankQ()],
    });

    // AI panel state (منوی بسته به‌صورت پیش‌فرض؛ اطلاعات از «اطلاعات پایه» می‌آید)
    const [aiOpen, setAiOpen] = useState(false);
    const [ai, setAi] = useState({ count: 5, type: 'mc', difficulty: 'medium', flavor: '', sample: false });
    const [aiResults, setAiResults] = useState([]);

    const setR = (k, v) => form.setData('rules', { ...form.data.rules, [k]: v });
    const toggleArr = (field, id) => form.setData(field, form.data[field].includes(id) ? form.data[field].filter((x) => x !== id) : [...form.data[field], id]);

    const setQ = (i, k, v) => { const qs = [...form.data.questions]; qs[i] = { ...qs[i], [k]: v }; form.setData('questions', qs); };
    const setChoice = (qi, ci, v) => { const qs = [...form.data.questions]; const ch = [...qs[qi].choices]; ch[ci] = { ...ch[ci], value: v }; qs[qi] = { ...qs[qi], choices: ch }; form.setData('questions', qs); };
    const setCorrect = (qi, ci) => { const qs = [...form.data.questions]; qs[qi] = { ...qs[qi], choices: qs[qi].choices.map((c, j) => ({ ...c, correct: j === ci })) }; form.setData('questions', qs); };
    const addChoice = (qi) => { const qs = [...form.data.questions]; if (qs[qi].choices.length < 4) { qs[qi].choices = [...qs[qi].choices, { value: '', correct: false }]; form.setData('questions', [...qs]); } };
    const setType = (qi, t) => { const qs = [...form.data.questions]; qs[qi] = { ...qs[qi], type: t, choices: t === 'tf' ? [{ value: 'درست', correct: true }, { value: 'نادرست', correct: false }] : (t === 'mc' ? (qs[qi].choices.length ? qs[qi].choices : blankQ().choices) : []) }; form.setData('questions', qs); };
    const addQ = () => form.setData('questions', [...form.data.questions, blankQ()]);
    const rmQ = (i) => form.data.questions.length > 1 && form.setData('questions', form.data.questions.filter((_, j) => j !== i));

    // نام تیمِ انتخاب‌شده برای «طعمِ» سؤال (اگر گروهی هدف باشد)
    const flavorTheme = themes.find((t) => form.data.target_themes.includes(t.id));
    const runAi = async () => {
        setAiBusy(true); setAiMsg(null); setAiResults([]);
        try {
            // اطلاعات پایه‌ی جدول به‌صورت خودکار به AI داده می‌شود (هم دستی هم AI از همین‌جا)
            const { data } = await axios.post(route('teacher.smart.ai'), {
                ...ai, subject: form.data.subject, grade: form.data.grade,
                topic: form.data.topic || form.data.chapter || form.data.subject,
                flavor: ai.flavor || (flavorTheme ? flavorTheme.name : ''),
            });
            setAiMsg({ ok: data.ok, mode: data.mode, text: data.message });
            if (data.ok) setAiResults((data.questions || []).map((q) => ({ ...q, source: data.mode === 'sample' ? 'sample' : 'ai', _pick: true })));
        } catch (e) { setAiMsg({ ok: false, text: e.response?.data?.message || 'خطا در ارتباط با سرویس' }); }
        setAiBusy(false);
    };
    const addAiPicked = () => {
        const picked = aiResults.filter((q) => q._pick).map(({ _pick, ...q }) => ({ ...q, points: 1 }));
        form.setData('questions', [...form.data.questions.filter((q) => q.prompt.trim()), ...picked]);
        setAiResults([]); setAiMsg(null); setStep(3);
    };

    const save = (status) => {
        const payload = { ...form.data, status };
        const opts = { preserveScroll: false };
        if (editId) router.put(route('teacher.smart.update', editId), payload, opts);
        else router.post(route('teacher.smart.store'), payload, opts);
    };

    const STEPS = ['اطلاعات پایه', 'مخاطب', 'سؤال‌ها', 'قوانین', 'انتشار'];
    const selClass = classroomsFull.find((c) => form.data.target_classrooms.includes(c.id)) || classroomsFull[0];
    const subjects = selClass?.subjects || [];

    return (
        <DashLayout title="آزمایشگاه هوشمند آزمون" roleLabel="معلم" menu={teacherMenu} active="smart">
            <div className="smart-scope">
                {banner && <div className="smart-panel" style={{ borderColor: 'var(--sm-acc)', background: '#f5f2ff' }}><b>{banner}</b></div>}

                {/* KPI ها */}
                {!editId && (
                    <div className="smart-kpis" style={{ marginBottom: 16 }}>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#2bb673,#1a8a52)' }}><b>{fa(buckets.published || 0)}</b><span>منتشرشده</span></div>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#8896ad,#5b6577)' }}><b>{fa(buckets.draft || 0)}</b><span>پیش‌نویس</span></div>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#0ea5b7,#0a7d8a)' }}><b>{fa(bankCount)}</b><span>بانک سؤال</span></div>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#6d28d9,#4c1d95)' }}><b>{fa(aiCount)}</b><span>سؤال AI</span></div>
                    </div>
                )}

                <div className="smart-panel">
                    <div className="smart-h">
                        🧪 {editId ? 'ویرایش آزمون هوشمند' : 'ساخت آزمون هوشمند'} <span className="smart-badge">آزمایشی</span>
                        <Link href={route('teacher.smart.bank')} className="smart-btn ghost sm" style={{ marginInlineStart: 'auto' }}>🗄️ بانک سؤال</Link>
                        {editId && <button onClick={() => router.visit(route('teacher.smart.lab'))} className="smart-btn ghost sm">+ آزمون جدید</button>}
                    </div>

                    <div className="smart-steps">
                        {STEPS.map((s, i) => <button key={i} onClick={() => setStep(i + 1)} className={`smart-step ${step === i + 1 ? 'on' : ''}`}>{fa(i + 1)}. {s}</button>)}
                    </div>

                    {/* گام ۱ */}
                    {step === 1 && (
                        <div className="smart-grid">
                            <F label="عنوان آزمون" err={form.errors.title}><input className="smart-input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً: آزمون تشخیصی فصل ۲" /></F>
                            <F label="نوع آزمون"><select className="smart-input" value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)}>{KINDS.map(([v, t]) => <option key={v} value={v}>{t}</option>)}</select></F>
                            <F label="پایه"><input className="smart-input" value={form.data.grade} onChange={(e) => form.setData('grade', e.target.value)} placeholder="چهارم" /></F>
                            <F label="درس"><input className="smart-input" list="sm-subjects" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} placeholder="مثلاً: علوم" /><datalist id="sm-subjects">{subjects.map((s, i) => <option key={i} value={s} />)}</datalist></F>
                            <F label="کتاب"><input className="smart-input" value={form.data.book} onChange={(e) => form.setData('book', e.target.value)} /></F>
                            <F label="فصل"><input className="smart-input" value={form.data.chapter} onChange={(e) => form.setData('chapter', e.target.value)} /></F>
                            <F label="مبحث"><input className="smart-input" value={form.data.topic} onChange={(e) => form.setData('topic', e.target.value)} /></F>
                            <F label="هدف آموزشی"><input className="smart-input" value={form.data.goal} onChange={(e) => form.setData('goal', e.target.value)} /></F>
                            <div style={{ gridColumn: '1/-1' }}><F label="توضیح آزمون"><textarea className="smart-input" rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></F></div>
                            {adaptiveEnabled && <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 14 }}><input type="checkbox" checked={form.data.adaptive} onChange={(e) => form.setData('adaptive', e.target.checked)} /> آزمون تطبیقی (سختی بر اساس پاسخ)</label>}
                        </div>
                    )}

                    {/* گام ۲ — مخاطب صریح */}
                    {step === 2 && (
                        <div>
                            <p className="smart-muted" style={{ marginBottom: 10 }}>کلاس، گروه یا دانش‌آموزِ هدف را صریح انتخاب کن. (بدون انتخاب = همه‌ی دانش‌آموزانِ کلاس‌های تو)</p>
                            <b style={{ fontSize: 13 }}>کلاس‌ها</b>
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', margin: '8px 0 14px' }}>
                                {classroomsFull.map((c) => <button key={c.id} onClick={() => toggleArr('target_classrooms', c.id)} className={`smart-chip ${form.data.target_classrooms.includes(c.id) ? 'on' : ''}`}>{c.name}</button>)}
                            </div>
                            <b style={{ fontSize: 13 }}>گروه‌ها (تیم‌ها)</b>
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', margin: '8px 0 14px' }}>
                                {groups.filter((g) => g.theme_id).map((g) => <button key={g.theme_id} onClick={() => toggleArr('target_themes', g.theme_id)} className={`smart-chip ${form.data.target_themes.includes(g.theme_id) ? 'on' : ''}`}>{g.name} ({fa(g.students.length)})</button>)}
                            </div>
                            <b style={{ fontSize: 13 }}>دانش‌آموزانِ مشخص</b>
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', margin: '8px 0' }}>
                                {groups.flatMap((g) => g.students).filter((s, i, arr) => arr.findIndex((x) => x.id === s.id) === i).map((s) => <button key={s.id} onClick={() => toggleArr('target_students', s.id)} className={`smart-chip ${form.data.target_students.includes(s.id) ? 'on' : ''}`}>{s.name}</button>)}
                            </div>
                        </div>
                    )}

                    {/* گام ۳ — سؤال‌ها (دستی + AI) */}
                    {step === 3 && (
                        <div>
                            {aiEnabled && (
                                <div className="smart-panel" style={{ background: '#f5f2ff', marginBottom: 14 }}>
                                    <button type="button" onClick={() => setAiOpen(!aiOpen)} className="smart-h" style={{ fontSize: 15, width: '100%', border: 0, background: 'transparent', cursor: 'pointer', fontFamily: 'inherit' }}>
                                        🤖 دستیار هوشمند طراحی سؤال
                                        <span className="smart-muted" style={{ fontSize: 12, fontWeight: 400, marginInlineStart: 8 }}>(اطلاعات از «اطلاعات پایه» گرفته می‌شود)</span>
                                        <span style={{ marginInlineStart: 'auto', fontSize: 18 }}>{aiOpen ? '▲' : '▼'}</span>
                                    </button>
                                    {aiOpen && (<>
                                    <div style={{ background: '#fff', borderRadius: 10, padding: '8px 12px', marginTop: 10, fontSize: 12.5 }} className="smart-muted">
                                        درس: <b>{form.data.subject || '—'}</b> · موضوع: <b>{form.data.topic || form.data.chapter || '—'}</b> · پایه: <b>{form.data.grade || '—'}</b>
                                        {!form.data.subject && <span style={{ color: '#b45309' }}> — ابتدا در گام ۱ «اطلاعات پایه» را کامل کنید.</span>}
                                    </div>
                                    <div className="smart-grid" style={{ marginTop: 10 }}>
                                        <F label="تعداد"><input type="number" min={1} max={20} className="smart-input" value={ai.count} onChange={(e) => setAi({ ...ai, count: +e.target.value })} dir="ltr" /></F>
                                        <F label="نوع"><select className="smart-input" value={ai.type} onChange={(e) => setAi({ ...ai, type: e.target.value })}><option value="mc">چهارگزینه‌ای</option><option value="tf">درست/نادرست</option><option value="desc">تشریحی</option></select></F>
                                        <F label="سختی"><select className="smart-input" value={ai.difficulty} onChange={(e) => setAi({ ...ai, difficulty: e.target.value })}><option value="easy">آسان</option><option value="medium">متوسط</option><option value="hard">دشوار</option></select></F>
                                        <F label="طعمِ تیم (اختیاری)"><select className="smart-input" value={ai.flavor} onChange={(e) => setAi({ ...ai, flavor: e.target.value })}><option value="">{flavorTheme ? `خودکار: ${flavorTheme.name}` : 'بدون طعم'}</option>{themes.map((t) => <option key={t.id} value={t.name}>{t.emoji} {t.name}</option>)}</select></F>
                                    </div>
                                    <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 13, marginTop: 8 }}><input type="checkbox" checked={ai.sample} onChange={(e) => setAi({ ...ai, sample: e.target.checked })} /> حالت نمونه (بدون کلید AI — سؤال‌های نمونه‌ی آزمایشی)</label>
                                    <button onClick={runAi} disabled={aiBusy || !form.data.subject} className="smart-btn" style={{ marginTop: 10 }}>{aiBusy ? '… در حال تولید' : '✨ تولید سؤال'}</button>
                                    {aiMsg && <div style={{ marginTop: 10, fontSize: 13, color: aiMsg.ok ? '#166534' : '#b91c1c', fontWeight: 700 }}>{aiMsg.mode === 'sample' && <span className="smart-tag sample">نمونه</span>} {aiMsg.text}</div>}
                                    {aiResults.length > 0 && (
                                        <div style={{ marginTop: 12 }}>
                                            <div className="smart-muted" style={{ marginBottom: 6 }}>سؤال‌های تولیدشده — تیک بزن و «افزودن به آزمون». هیچ سؤالی بدون تأیید تو وارد آزمون نمی‌شود.</div>
                                            {aiResults.map((q, i) => (
                                                <label key={i} className="smart-qcard" style={{ display: 'flex', gap: 10, cursor: 'pointer' }}>
                                                    <input type="checkbox" checked={q._pick} onChange={() => setAiResults(aiResults.map((x, j) => j === i ? { ...x, _pick: !x._pick } : x))} />
                                                    <div style={{ flex: 1 }}><b style={{ fontSize: 13.5 }}>{q.prompt}</b>
                                                        <div className="smart-muted" style={{ fontSize: 12 }}>{(q.choices || []).map((c) => c.value + (c.correct ? ' ✓' : '')).join(' · ') || (q.answer || '')}</div></div>
                                                </label>
                                            ))}
                                            <button onClick={addAiPicked} className="smart-btn sm">➕ افزودن سؤال‌های انتخابی</button>
                                        </div>
                                    )}
                                    </>)}
                                </div>
                            )}

                            {form.data.questions.map((q, qi) => (
                                <div key={qi} className="smart-qcard">
                                    <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 8, flexWrap: 'wrap' }}>
                                        <span className="smart-tag man" style={{ background: q.source === 'ai' ? '#ede9fe' : q.source === 'sample' ? '#fef3c7' : '#e0f2fe', color: q.source === 'ai' ? '#6d28d9' : q.source === 'sample' ? '#b45309' : '#0369a1' }}>{fa(qi + 1)} · {q.source === 'ai' ? 'AI' : q.source === 'sample' ? 'نمونه' : 'دستی'}</span>
                                        <select className="smart-input" style={{ width: 'auto', padding: '5px 8px' }} value={q.type} onChange={(e) => setType(qi, e.target.value)}>
                                            <option value="mc">چهارگزینه‌ای</option><option value="tf">درست/نادرست</option><option value="desc">تشریحی</option><option value="blank">جای خالی</option>
                                        </select>
                                        <input type="number" min={1} max={20} className="smart-input" style={{ width: 70, padding: '5px 8px' }} value={q.points} onChange={(e) => setQ(qi, 'points', +e.target.value)} title="بارم" dir="ltr" />
                                        <button onClick={() => rmQ(qi)} className="smart-btn ghost sm" style={{ marginInlineStart: 'auto', color: '#e8505b' }}>🗑️</button>
                                    </div>
                                    <input className="smart-input" value={q.prompt} onChange={(e) => setQ(qi, 'prompt', e.target.value)} placeholder="متن سؤال" style={{ marginBottom: 8 }} />
                                    {(q.type === 'mc' || q.type === 'tf') ? (
                                        <div style={{ display: 'grid', gap: 6 }}>
                                            {(q.choices || []).map((c, ci) => (
                                                <div key={ci} style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                                    <button onClick={() => setCorrect(qi, ci)} className="smart-btn sm" style={{ flex: 'none', background: c.correct ? '#2bb673' : '#eef2f8', color: c.correct ? '#fff' : '#6b7794' }}>{c.correct ? '✓' : '○'}</button>
                                                    <input className="smart-input" value={c.value} onChange={(e) => setChoice(qi, ci, e.target.value)} placeholder={`گزینه ${fa(ci + 1)}`} />
                                                </div>
                                            ))}
                                            {q.type === 'mc' && (q.choices || []).length < 4 && <button onClick={() => addChoice(qi)} className="smart-btn ghost sm" style={{ width: 'fit-content' }}>+ گزینه</button>}
                                        </div>
                                    ) : q.type === 'blank' ? (
                                        <input className="smart-input" value={q.answer || ''} onChange={(e) => setQ(qi, 'answer', e.target.value)} placeholder="پاسخ صحیح (متن)" />
                                    ) : <div className="smart-muted" style={{ fontSize: 12 }}>سؤال تشریحی — توسط معلم تصحیح می‌شود.</div>}
                                    <input className="smart-input" value={q.explanation || ''} onChange={(e) => setQ(qi, 'explanation', e.target.value)} placeholder="توضیح آموزشی پس از پاسخ (اختیاری)" style={{ marginTop: 8 }} />
                                </div>
                            ))}
                            <button onClick={addQ} className="smart-btn ghost">+ افزودن سؤال دستی</button>
                        </div>
                    )}

                    {/* گام ۴ — قوانین */}
                    {step === 4 && (
                        <div className="smart-grid">
                            <F label="مدت آزمون (دقیقه)"><input type="number" min={1} className="smart-input" value={form.data.rules.duration} onChange={(e) => setR('duration', +e.target.value)} dir="ltr" /></F>
                            <F label="تعداد تلاش مجاز"><input type="number" min={1} max={10} className="smart-input" value={form.data.rules.attempts} onChange={(e) => setR('attempts', +e.target.value)} dir="ltr" /></F>
                            <F label="حداقل نمره قبولی (٪)"><input type="number" min={0} max={100} className="smart-input" value={form.data.rules.pass} onChange={(e) => setR('pass', +e.target.value)} dir="ltr" /></F>
                            <F label="تاریخ شروع (شمسی)"><JalaliDatePicker withTime value={form.data.opens_at || ''} onChange={(v) => form.setData('opens_at', v)} placeholder="بلافاصله" /></F>
                            <F label="تاریخ پایان (شمسی)"><JalaliDatePicker withTime value={form.data.closes_at || ''} onChange={(v) => form.setData('closes_at', v)} placeholder="بدون پایان" /></F>
                            <div style={{ gridColumn: '1/-1', display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 6 }}>
                                {[['show_answer', 'نمایش پاسخ صحیح'], ['show_result', 'نمایش نتیجه به دانش‌آموز'], ['shuffle', 'ترتیب تصادفی سؤال'], ['shuffle_choices', 'جابه‌جایی گزینه‌ها'], ['one_per_page', 'یک سؤال در هر صفحه']].map(([k, t]) => (
                                    <label key={k} style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 13.5 }}><input type="checkbox" checked={!!form.data.rules[k]} onChange={(e) => setR(k, e.target.checked)} /> {t}</label>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* گام ۵ — پیش‌نمایش/انتشار */}
                    {step === 5 && (
                        <div>
                            <div className="smart-grid">
                                <Info k="سؤال‌ها" v={`${fa(form.data.questions.filter((q) => q.prompt.trim()).length)} سؤال`} />
                                <Info k="مخاطب" v={form.data.target_classrooms.length || form.data.target_themes.length || form.data.target_students.length ? 'انتخابی' : 'کل کلاس'} />
                                <Info k="مدت" v={`${fa(form.data.rules.duration)} دقیقه`} />
                                <Info k="تلاش" v={fa(form.data.rules.attempts)} />
                            </div>
                            <p className="smart-muted" style={{ marginTop: 10 }}>ویرایشِ آزمونِ دارای نتیجه، نسخه‌ی جدید می‌سازد تا نتایج قبلی حفظ شوند.</p>
                            <div style={{ display: 'flex', gap: 10, marginTop: 14, flexWrap: 'wrap' }}>
                                <button onClick={() => save('draft')} disabled={form.processing} className="smart-btn ghost">💾 ذخیره‌ی پیش‌نویس</button>
                                <button onClick={() => save('published')} disabled={form.processing} className="smart-btn">🚀 انتشار آزمون</button>
                            </div>
                        </div>
                    )}

                    <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 16 }}>
                        <button onClick={() => setStep(Math.max(1, step - 1))} className="smart-btn ghost sm" disabled={step === 1}>→ قبلی</button>
                        {step < 5 && <button onClick={() => setStep(step + 1)} className="smart-btn sm">بعدی ←</button>}
                    </div>
                </div>

                {/* فهرست آزمون‌ها */}
                <div className="smart-panel">
                    <div className="smart-h">🧪 آزمون‌های هوشمند من ({fa(exams.length)})</div>
                    {exams.length === 0 && <p className="smart-muted">هنوز آزمونی نساخته‌ای.</p>}
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(250px,1fr))', gap: 12, marginTop: 8 }}>
                        {exams.map((e) => (
                            <div key={e.id} className="smart-examcard" style={{ borderTop: `4px solid ${ST_COLOR[e.status]}` }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                    <b style={{ flex: 1 }}>{e.title}</b>
                                    <span className="st" style={{ background: ST_COLOR[e.status] }}>{ST_LABEL[e.status]}</span>
                                </div>
                                <div className="smart-muted" style={{ fontSize: 12, marginTop: 6 }}>{e.subject || ''}{e.topic ? ` · ${e.topic}` : ''} · {fa(e.questions)} سؤال · {fa(e.attempts)} تلاش{e.version > 1 ? ` · نسخه ${fa(e.version)}` : ''}</div>
                                <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                                    <Link href={route('teacher.smart.edit', e.id)} className="smart-btn ghost sm">✏️ ویرایش</Link>
                                    <Link href={route('teacher.smart.report', e.id)} className="smart-btn ghost sm">📊 گزارش</Link>
                                    {e.status !== 'published'
                                        ? <button onClick={() => router.post(route('teacher.smart.status', e.id), { status: 'published' }, { preserveScroll: true })} className="smart-btn ghost sm">🚀 انتشار</button>
                                        : <button onClick={() => router.post(route('teacher.smart.status', e.id), { status: 'closed' }, { preserveScroll: true })} className="smart-btn ghost sm">⏹️ بستن</button>}
                                    <button onClick={() => confirm(`آزمون «${e.title}» حذف شود؟`) && confirm('برای اطمینان دوباره تأیید کنید — آزمون‌های قدیمی آسیب نمی‌بینند.') && router.delete(route('teacher.smart.destroy', e.id), { preserveScroll: true })} className="smart-btn ghost sm" style={{ color: '#e8505b' }}>🗑️</button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </DashLayout>
    );
}

function F({ label, err, children }) { return <div className="smart-field"><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>; }
function Info({ k, v }) { return <div style={{ background: '#faf9ff', borderRadius: 12, padding: 12, border: '1px solid var(--sm-line)' }}><div className="smart-muted" style={{ fontSize: 11 }}>{k}</div><b style={{ fontSize: 14 }}>{v}</b></div>; }
