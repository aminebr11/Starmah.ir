import { usePage, useForm, router, Link } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';
import CurriculumFields from '@/Components/Questions/CurriculumFields';
import AiQuestionPanel from '@/Components/Questions/AiQuestionPanel';
import BankPicker from '@/Components/Questions/BankPicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const blankQ = () => ({ type: 'mc', prompt: '', points: 1, difficulty: 'medium', explanation: '', source: 'manual', choices: [{ value: '', correct: true }, { value: '', correct: false }] });
const KINDS = [['diagnostic', 'تشخیصی'], ['practice', 'تمرینی'], ['class', 'کلاسی'], ['formal', 'رسمی'], ['remedial', 'جبرانی'], ['game', 'بازی‌محور']];
const DEFAULT_RULES = { duration: 20, attempts: 1, pass: 50, show_answer: true, show_result: true, shuffle: false, shuffle_choices: false, one_per_page: true };

export default function SmartExamLab() {
    const { classroomsFull = [], classes = [], groups = [], themes = [], aiEnabled, adaptiveEnabled, editing, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [step, setStep] = useState(1);
    const [editId, setEditId] = useState(editing?.id ?? null);
    const [panel, setPanel] = useState(null); // 'ai' | 'bank' | null
    useEffect(() => { if (flash?.flash) { setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); window.scrollTo({ top: 0 }); } }, [flash]);
    useEffect(() => { if (editing) { setEditId(editing.id); setStep(1); } }, [editing?.id]);

    const form = useForm(editing ? {
        ...editing, rules: { ...DEFAULT_RULES, ...(editing.rules || {}) },
        target_classrooms: editing.target_classrooms || [], target_themes: editing.target_themes || [],
        target_students: editing.target_students || [], questions: editing.questions?.length ? editing.questions : [blankQ()],
    } : {
        title: '', description: '', classroom_id: '', level: '', grade: classroomsFull[0]?.grade || '', subject: '', book: '', chapter_id: '', chapter: '', topic: '', goal: '',
        kind: 'practice', status: 'draft', adaptive: false, rules: { ...DEFAULT_RULES }, opens_at: '', closes_at: '',
        target_classrooms: [], target_themes: [], target_students: [], questions: [blankQ()],
    });

    const setR = (k, v) => form.setData('rules', { ...form.data.rules, [k]: v });
    const toggleArr = (field, id) => form.setData(field, form.data[field].includes(id) ? form.data[field].filter((x) => x !== id) : [...form.data[field], id]);

    const setQ = (i, k, v) => { const qs = [...form.data.questions]; qs[i] = { ...qs[i], [k]: v }; form.setData('questions', qs); };
    const setChoice = (qi, ci, v) => { const qs = [...form.data.questions]; const ch = [...qs[qi].choices]; ch[ci] = { ...ch[ci], value: v }; qs[qi] = { ...qs[qi], choices: ch }; form.setData('questions', qs); };
    const setCorrect = (qi, ci) => { const qs = [...form.data.questions]; qs[qi] = { ...qs[qi], choices: qs[qi].choices.map((c, j) => ({ ...c, correct: j === ci })) }; form.setData('questions', qs); };
    const addChoice = (qi) => { const qs = [...form.data.questions]; if (qs[qi].choices.length < 4) { qs[qi].choices = [...qs[qi].choices, { value: '', correct: false }]; form.setData('questions', [...qs]); } };
    const setType = (qi, t) => { const qs = [...form.data.questions]; qs[qi] = { ...qs[qi], type: t, choices: t === 'tf' ? [{ value: 'درست', correct: true }, { value: 'نادرست', correct: false }] : (t === 'mc' ? (qs[qi].choices.length ? qs[qi].choices : blankQ().choices) : []) }; form.setData('questions', qs); };
    const addQ = () => form.setData('questions', [...form.data.questions, blankQ()]);
    const rmQ = (i) => form.data.questions.length > 1 && form.setData('questions', form.data.questions.filter((_, j) => j !== i));

    // نام تیمِ هدف برای «فضای داستانی»ِ سؤال‌ها (اگر گروهی هدف باشد)
    const flavorTheme = themes.find((t) => form.data.target_themes.includes(t.id));
    const setCtx = (patch) => form.setData((d) => ({ ...d, ...patch, ...(patch.subject !== undefined ? { book: patch.subject } : {}) }));

    // افزودنِ سؤال از دستیار یا بانک — همه‌ی جزئیات (توضیح، دشواری، سطح، شناسه‌ی بانک) حفظ می‌شود
    const addQuestions = (list, from) => {
        const mapped = list.map((q) => ({
            type: q.type || 'mc', prompt: q.prompt, points: 1,
            choices: (q.type === 'mc' || q.type === 'tf') ? (q.choices || []).map((c) => ({ value: c.value, correct: !!c.correct })) : [],
            answer: q.answer || '', explanation: q.explanation || '', difficulty: q.difficulty || 'medium',
            bloom: q.bloom || null, topic: q.topic || '', source: from === 'bank' ? 'bank' : (q.source || 'ai'),
            bank_id: from === 'bank' ? q.id : null,
        }));
        form.setData('questions', [...form.data.questions.filter((q) => q.prompt.trim()), ...mapped]);
        setPanel(null);
    };

    /**
     * ذخیره/انتشار با خودِ فرم (نه router) تا خطاهای سرور در form.errors بنشیند و
     * روی صفحه دیده شود. پیش از این هر ایرادی بی‌صدا رد می‌شد و دکمه‌ی «انتشار»
     * بی‌واکنش به نظر می‌آمد.
     */
    const isBlankQ = (q) => !(q.prompt || '').trim() && !(q.choices || []).some((c) => (c.value || '').trim()) && !String(q.answer || '').trim();
    const errBox = useRef(null);
    const [saving, setSaving] = useState(null);
    const save = (status) => {
        const qs = form.data.questions.filter((q) => !isBlankQ(q));
        if (qs.length && qs.length !== form.data.questions.length) form.setData('questions', qs);
        form.transform((d) => ({ ...d, status, questions: qs.length ? qs : d.questions }));
        setSaving(status);
        const opts = {
            preserveScroll: true,
            onFinish: () => setSaving(null),
            onError: () => setTimeout(() => errBox.current?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 60),
        };
        if (editId) form.put(route('teacher.smart.update', editId), opts);
        else form.post(route('teacher.smart.store'), opts);
    };

    // خلاصه‌ی خطاها با شماره‌ی سؤال و گامِ مربوط
    const STEP_OF = (k) => (k.startsWith('questions') ? 3 : k.startsWith('target') ? 2 : (k.startsWith('rules') || ['opens_at', 'closes_at'].includes(k)) ? 4 : 1);
    const errorList = Object.entries(form.errors || {}).map(([k, msg]) => {
        const m = k.match(/^questions\.(\d+)\./);
        const qn = m ? Number(m[1]) : null;
        return { k, text: qn !== null && !/^سؤالِ/.test(msg) ? `سؤالِ ${fa(qn + 1)}: ${msg}` : msg, step: STEP_OF(k), qn };
    });
    const qErr = (qi) => Object.entries(form.errors || {}).filter(([k]) => k.startsWith(`questions.${qi}.`)).map(([, v]) => v);
    const goTo = (e) => {
        setStep(e.step);
        if (e.qn !== null) setTimeout(() => document.getElementById(`sq-${e.qn}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 80);
    };

    const STEPS = ['اطلاعات پایه', 'مخاطب', 'سؤال‌ها', 'قوانین', 'انتشار'];

    return (
        <DashLayout title="آزمایشگاه هوشمند آزمون" roleLabel="معلم" menu={teacherMenu} active="smart">
            <div className="smart-scope">
                {banner && <div className="smart-panel" style={{ borderColor: 'var(--sm-acc)', background: '#f5f2ff' }}><b>{banner}</b></div>}

                <div className="ch-builder-bar">
                    <b>{editId ? '✏️ ویرایشِ آزمون' : '✨ ساختِ آزمونِ جدید'}</b>
                    <Link href={route('teacher.smart.lab')} className="btn btn-ghost btn-sm">← بازگشت به آزمون‌های من</Link>
                </div>
                <div className="smart-panel">
                    <div className="smart-h">
                        🧪 {editId ? 'ویرایش آزمون هوشمند' : 'ساخت آزمون هوشمند'}
                        {editId && <button onClick={() => save(form.data.status || 'draft')} disabled={form.processing} className="smart-btn sm" style={{ marginInlineStart: 'auto' }}>💾 ذخیره‌ی تغییرات</button>}
                        {editId && <button onClick={() => router.visit(route('teacher.smart.create'))} className="smart-btn ghost sm">+ آزمون جدید</button>}
                    </div>

                    <div className="smart-steps">
                        {STEPS.map((s, i) => <button key={i} onClick={() => setStep(i + 1)} className={`smart-step ${step === i + 1 ? 'on' : ''}`}>{fa(i + 1)}. {s}</button>)}
                    </div>

                    {errorList.length > 0 && (
                        <div ref={errBox} className="gs-errors" role="alert">
                            <b>⚠️ آزمون هنوز ذخیره/منتشر نشد — این موارد را درست کن:</b>
                            <ul>
                                {errorList.map((e) => (
                                    <li key={e.k}>
                                        <span>{e.text}</span>
                                        {(step !== e.step || e.qn !== null) && (
                                            <button type="button" onClick={() => goTo(e)}>برو به {e.qn !== null ? `سؤالِ ${fa(e.qn + 1)}` : `گامِ ${fa(e.step)}`} ←</button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* گام ۱ */}
                    {step === 1 && (
                        <div className="smart-grid">
                            <F label="عنوان آزمون" err={form.errors.title}><input className="smart-input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً: آزمون تشخیصی فصل ۲" /></F>
                            <F label="نوع آزمون"><select className="smart-input" value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)}>{KINDS.map(([v, t]) => <option key={v} value={v}>{t}</option>)}</select></F>
                            <div style={{ gridColumn: '1/-1' }}>
                                <CurriculumFields classes={classes} value={form.data} onChange={setCtx} errors={form.errors} />
                            </div>
                            <div style={{ gridColumn: '1/-1' }}><F label="توضیح آزمون"><textarea className="smart-input" rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></F></div>
                            <div style={{ gridColumn: '1/-1', background: '#f5f2ff', border: '1px solid #e5ddff', borderRadius: 12, padding: 12 }}>
                                <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 14, fontWeight: 700 }}><input type="checkbox" checked={form.data.adaptive} onChange={(e) => form.setData('adaptive', e.target.checked)} /> 🎯 آزمون تطبیقی (سختی بر اساس پاسخ)</label>
                                <div className="smart-muted" style={{ fontSize: 12.5, marginTop: 6, lineHeight: 1.9 }}>
                                    در حالت تطبیقی، سؤال‌ها بر اساس <b>سطحِ دشواری</b> مرتب می‌شوند و از آسان شروع می‌شوند؛ اگر دانش‌آموز پاسخِ درست بدهد، سؤالِ بعدی دشوارتر و اگر اشتباه بدهد، ساده‌تر ارائه می‌شود.
                                    این کار سطحِ واقعیِ دانش‌آموز را دقیق‌تر می‌سنجد و تجربه‌ی منصفانه‌تری می‌سازد. (برای اثرگذاری، در بانک/طراحیِ سؤال، سطحِ دشواریِ سؤال‌ها را مشخص کنید.)
                                </div>
                            </div>
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

                    {/* گام ۳ — سؤال‌ها (دستی + AI + بانک) */}
                    {step === 3 && (
                        <div>
                            <div className="qk-actions" style={{ marginTop: 0, marginBottom: 12 }}>
                                {aiEnabled && <button type="button" onClick={() => setPanel(panel === 'ai' ? null : 'ai')} className={`smart-btn ${panel === 'ai' ? '' : 'ghost'}`}>🤖 طراحی با هوش مصنوعی</button>}
                                <button type="button" onClick={() => setPanel(panel === 'bank' ? null : 'bank')} className={`smart-btn ${panel === 'bank' ? '' : 'ghost'}`}>🗄️ از بانکِ سؤالات</button>
                                <span className="smart-muted" style={{ fontSize: 12.5 }}>{fa(form.data.questions.filter((q) => q.prompt.trim()).length)} سؤال در آزمون</span>
                            </div>
                            {panel === 'ai' && aiEnabled && (
                                <AiQuestionPanel onContext={setCtx} endpoint={route('teacher.smart.ai')} context={form.data} classes={classes}
                                    types={['mc', 'tf', 'blank', 'desc']} maxCount={20} kind={form.data.kind}
                                    defaults={{ count: 5, types: ['mc'], difficulty: form.data.kind === 'diagnostic' ? 'mixed' : 'medium' }}
                                    flavors={themes} flavorDefault={flavorTheme?.name || ''}
                                    existing={form.data.questions.map((q) => q.prompt).filter((p) => p && p.trim())}
                                    onAdd={(list) => addQuestions(list, 'ai')} />
                            )}
                            {panel === 'bank' && (
                                <BankPicker endpoint={route('teacher.smart.bankpick')} context={form.data}
                                    types={['mc', 'tf', 'blank', 'desc']}
                                    existingIds={form.data.questions.map((q) => q.bank_id).filter(Boolean)}
                                    onAdd={(rows) => addQuestions(rows, 'bank')} />
                            )}

                            {form.data.questions.map((q, qi) => (
                                <div key={qi} id={`sq-${qi}`} className="smart-qcard" style={qErr(qi).length ? { border: '2px solid #e8505b', background: '#fff5f5' } : undefined}>
                                    {qErr(qi).map((m, k) => <div key={k} style={{ color: '#c0392b', fontSize: 12.5, fontWeight: 700, marginBottom: 6 }}>⚠️ {m}</div>)}
                                    <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 8, flexWrap: 'wrap' }}>
                                        <span className="smart-tag man" style={{ background: q.source === 'ai' ? '#ede9fe' : q.source === 'sample' ? '#fef3c7' : '#e0f2fe', color: q.source === 'ai' ? '#6d28d9' : q.source === 'sample' ? '#b45309' : '#0369a1' }}>{fa(qi + 1)} · {q.source === 'ai' ? 'AI' : q.source === 'sample' ? 'نمونه' : q.source === 'bank' ? 'بانک' : 'دستی'}</span>
                                        <select className="smart-input" style={{ width: 'auto', padding: '5px 8px' }} value={q.type} onChange={(e) => setType(qi, e.target.value)}>
                                            <option value="mc">چهارگزینه‌ای</option><option value="tf">درست/نادرست</option><option value="desc">تشریحی</option><option value="blank">جای خالی</option>
                                        </select>
                                        <input type="number" min={1} max={20} className="smart-input" style={{ width: 70, padding: '5px 8px' }} value={q.points} onChange={(e) => setQ(qi, 'points', +e.target.value)} title="بارم" dir="ltr" />
                                        <select className="smart-input" style={{ width: 'auto', padding: '5px 8px' }} value={q.difficulty || 'medium'} onChange={(e) => setQ(qi, 'difficulty', e.target.value)} title="دشواری (برای آزمونِ تطبیقی)">
                                            <option value="easy">آسان</option><option value="medium">متوسط</option><option value="hard">دشوار</option>
                                        </select>
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
                                    ) : (
                                        <textarea className="smart-input" rows={2} value={q.answer || ''} onChange={(e) => setQ(qi, 'answer', e.target.value)} placeholder="پاسخِ نمونه برای تصحیحِ معلم (سؤالِ تشریحی دستی تصحیح می‌شود)" />
                                    )}
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
                            <F label="تاریخ شروع (شمسی)"><JalaliDatePicker withTime value={form.data.opens_at || ''} onChange={(v) => form.setData('opens_at', v)} placeholder="بلافاصله" />{form.data.opens_at && <button type="button" onClick={() => form.setData('opens_at', '')} className="smart-btn ghost sm" style={{ marginTop: 5 }}>✕ پاک‌کردن (انتشار فوری)</button>}</F>
                            <F label="تاریخ پایان (شمسی)"><JalaliDatePicker withTime value={form.data.closes_at || ''} onChange={(v) => form.setData('closes_at', v)} placeholder="بدون پایان" />{form.data.closes_at && <button type="button" onClick={() => form.setData('closes_at', '')} className="smart-btn ghost sm" style={{ marginTop: 5 }}>✕ پاک‌کردن (بدون مهلت)</button>}</F>
                            <div style={{ gridColumn: '1/-1' }} className="smart-muted">🗓️ اگر تاریخ شروع خالی بماند، آزمون با انتشار بلافاصله در دسترسِ دانش‌آموزان قرار می‌گیرد. اگر تاریخ بگذارید، دقیقاً در همان زمان به‌طور خودکار برای دانش‌آموزان باز می‌شود.</div>
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
                            {/* پیش از انتشار، آزمون را از چشمِ دانش‌آموز ببین */}
                            <div style={{ marginTop: 14, border: '1px solid var(--line)', borderRadius: 14, padding: 12, background: '#fffaf0' }}>
                                <b style={{ fontSize: 13.5 }}>👁️ پیش‌نمایشِ واقعی</b>
                                <div className="smart-muted" style={{ marginTop: 4, lineHeight: 1.9 }}>
                                    آزمون را دقیقاً مثلِ دانش‌آموز بده، پاسخِ درستِ هر سؤال را بازبینی کن، برگرد و اصلاح کن و بعد منتشر کن.
                                    هیچ تلاشی ثبت نمی‌شود و هیچ نمره‌ای ذخیره نمی‌شود.
                                </div>
                                {editId
                                    ? <a href={route('teacher.smart.preview', editId)} className="smart-btn sm" style={{ marginTop: 10, display: 'inline-block' }}>👁️ اجرای پیش‌نمایش</a>
                                    : <div style={{ fontSize: 12.5, color: '#b0333f', marginTop: 8 }}>اول «ذخیره‌ی پیش‌نویس» را بزن تا بتوانی پیش‌نمایش بگیری.</div>}
                            </div>

                            <div style={{ display: 'flex', gap: 10, marginTop: 14, flexWrap: 'wrap' }}>
                                <button onClick={() => save('draft')} disabled={form.processing} className="smart-btn ghost">{saving === 'draft' ? 'در حالِ ذخیره…' : '💾 ذخیره‌ی پیش‌نویس'}</button>
                                <button onClick={() => save('published')} disabled={form.processing} className="smart-btn">{saving === 'published' ? 'در حالِ انتشار…' : '🚀 انتشار آزمون'}</button>
                            </div>
                        </div>
                    )}

                    <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 16 }}>
                        <button onClick={() => setStep(Math.max(1, step - 1))} className="smart-btn ghost sm" disabled={step === 1}>→ قبلی</button>
                        {step < 5 && <button onClick={() => setStep(step + 1)} className="smart-btn sm">بعدی ←</button>}
                    </div>
                </div>

            </div>
        </DashLayout>
    );
}

function F({ label, err, children }) { return <div className="smart-field"><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>; }
function Info({ k, v }) { return <div style={{ background: '#faf9ff', borderRadius: 12, padding: 12, border: '1px solid var(--sm-line)' }}><div className="smart-muted" style={{ fontSize: 11 }}>{k}</div><b style={{ fontSize: 14 }}>{v}</b></div>; }
