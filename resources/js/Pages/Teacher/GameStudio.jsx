import { usePage, useForm, router, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const blankQ = () => ({ type: 'mc', prompt: '', points: 10, hint1: '', explanation: '', choices: [{ value: '', correct: true }, { value: '', correct: false }] });
const DEFAULT_RULES = { lives: 3, retry: true, show_answer: true, shuffle: false, pass: 50, group_race: false };

export default function GameStudio() {
    const { games = [], templates = [], themes = [], subjects = [], grade, groups = [], hasClass, editing, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [step, setStep] = useState(1);
    const [editId, setEditId] = useState(editing?.id ?? null);
    useEffect(() => { if (flash?.flash) { setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); window.scrollTo({ top: 0 }); } }, [flash]);

    const form = useForm(editing ? {
        ...editing,
        rules: { ...DEFAULT_RULES, ...(editing.rules || {}) },
        target_themes: editing.target_themes || [],
        target_students: editing.target_students || [],
        questions: editing.questions?.length ? editing.questions : [blankQ()],
    } : {
        title: '', description: '', template_key: templates[0]?.key || '', theme_id: themes[0]?.id || null,
        subject: '', grade: grade || '', difficulty: 'medium', status: 'draft',
        publish_at: '', close_at: '', rules: { ...DEFAULT_RULES },
        target_themes: [], target_students: [], questions: [blankQ()],
    });

    useEffect(() => { if (editing) { setEditId(editing.id); setStep(1); } }, [editing?.id]);

    const setR = (k, v) => form.setData('rules', { ...form.data.rules, [k]: v });
    const toggleTheme = (id) => form.setData('target_themes', form.data.target_themes.includes(id) ? form.data.target_themes.filter((x) => x !== id) : [...form.data.target_themes, id]);

    // سؤال‌ها
    const setQ = (i, k, v) => { const qs = [...form.data.questions]; qs[i] = { ...qs[i], [k]: v }; form.setData('questions', qs); };
    const setChoice = (qi, ci, v) => { const qs = [...form.data.questions]; const ch = [...qs[qi].choices]; ch[ci] = { ...ch[ci], value: v }; qs[qi] = { ...qs[qi], choices: ch }; form.setData('questions', qs); };
    const setCorrect = (qi, ci) => { const qs = [...form.data.questions]; qs[qi] = { ...qs[qi], choices: qs[qi].choices.map((c, j) => ({ ...c, correct: j === ci })) }; form.setData('questions', qs); };
    const addChoice = (qi) => { const qs = [...form.data.questions]; if (qs[qi].choices.length < 4) { qs[qi].choices = [...qs[qi].choices, { value: '', correct: false }]; form.setData('questions', [...qs]); } };
    const rmChoice = (qi, ci) => { const qs = [...form.data.questions]; if (qs[qi].choices.length > 2) { qs[qi].choices = qs[qi].choices.filter((_, j) => j !== ci); form.setData('questions', [...qs]); } };
    const setType = (qi, t) => { const qs = [...form.data.questions]; qs[qi] = { ...qs[qi], type: t, choices: t === 'tf' ? [{ value: 'درست', correct: true }, { value: 'نادرست', correct: false }] : (t === 'short' ? [{ value: '', correct: true }] : qs[qi].choices) }; form.setData('questions', qs); };
    // دستیار AI + بانک سؤال برای بازی
    const flavorTheme = themes.find((t) => t.id === form.data.theme_id);
    const [aiOpen, setAiOpen] = useState(false);
    const [ai, setAi] = useState({ count: 5, difficulty: 'easy', sample: false });
    const [aiBusy, setAiBusy] = useState(false); const [aiMsg, setAiMsg] = useState(null); const [aiRes, setAiRes] = useState([]);
    const runAi = async () => {
        setAiBusy(true); setAiMsg(null); setAiRes([]);
        try {
            const { data } = await axios.post(route('teacher.studio.ai'), { ...ai, subject: form.data.subject, topic: form.data.subject, grade: form.data.grade, flavor: flavorTheme?.name || '' });
            setAiMsg({ ok: data.ok, text: data.message }); if (data.ok) setAiRes((data.questions || []).map((q) => ({ ...q, _pick: true })));
        } catch (e) { setAiMsg({ ok: false, text: e.response?.data?.message || 'خطا' }); }
        setAiBusy(false);
    };
    const addAi = () => { const picked = aiRes.filter((q) => q._pick).map((q) => ({ type: q.type || 'mc', prompt: q.prompt, explanation: q.explanation, points: 10, choices: (q.choices || []).map((c) => ({ value: c.value, correct: !!c.correct })) })); form.setData('questions', [...form.data.questions.filter((q) => q.prompt.trim()), ...picked]); setAiRes([]); setAiMsg(null); };
    const [bankOpen, setBankOpen] = useState(false); const [bankQ, setBankQ] = useState([]); const [bankSearch, setBankSearch] = useState('');
    const [bankFacets, setBankFacets] = useState([]); const [bankSubject, setBankSubject] = useState(''); const [bankLesson, setBankLesson] = useState('');
    const loadBank = async () => { try { const { data } = await axios.get(route('teacher.studio.bank'), { params: { subject: bankSubject || form.data.subject, lesson_no: bankLesson, search: bankSearch } }); setBankFacets(data.facets || []); setBankQ((data.questions || []).map((q) => ({ ...q, _pick: false }))); } catch (e) { setBankQ([]); } };
    const bankLessons = (bankFacets.find((s) => s.subject === bankSubject)?.lessons) || [];
    const addBank = () => { const picked = bankQ.filter((q) => q._pick).map((q) => ({ type: 'mc', prompt: q.prompt, points: 10, choices: (q.choices || []).map((c) => ({ value: c.value, correct: !!c.correct })) })); form.setData('questions', [...form.data.questions.filter((q) => q.prompt.trim()), ...picked]); setBankOpen(false); };

    const addQ = () => form.setData('questions', [...form.data.questions, blankQ()]);
    const rmQ = (i) => form.data.questions.length > 1 && form.setData('questions', form.data.questions.filter((_, j) => j !== i));
    const moveQ = (i, d) => { const j = i + d; if (j < 0 || j >= form.data.questions.length) return; const qs = [...form.data.questions]; [qs[i], qs[j]] = [qs[j], qs[i]]; form.setData('questions', qs); };

    const save = (status) => {
        form.setData('status', status);
        const payload = { ...form.data, status };
        const opts = { preserveScroll: false };
        if (editId) router.put(route('teacher.studio.update', editId), payload, opts);
        else router.post(route('teacher.studio.store'), payload, opts);
    };
    const startNew = () => { router.visit(route('teacher.studio')); };

    const STEPS = ['اطلاعات پایه', 'قالب و تم', 'سؤال‌ها', 'قوانین', 'پیش‌نمایش'];
    const tmpl = templates.find((t) => t.key === form.data.template_key);

    if (!hasClass) {
        return <DashLayout title="استودیوی بازی" roleLabel="معلم" menu={teacherMenu} active="studio">
            <div className="panel"><b>ابتدا باید یک کلاس داشته باشید تا بتوانید بازی بسازید.</b></div>
        </DashLayout>;
    }

    return (
        <DashLayout title="استودیوی ساخت بازی" roleLabel="معلم" menu={teacherMenu} active="studio">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div className="panel">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <h3 style={{ margin: 0 }}>🎬 {editId ? 'ویرایش بازی' : 'استودیوی ساخت بازی'}</h3>
                    {editId && <button onClick={startNew} className="btn btn-ghost btn-sm">+ بازی جدید</button>}
                </div>

                {/* استپر */}
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', margin: '14px 0' }}>
                    {STEPS.map((s, i) => (
                        <button key={i} onClick={() => setStep(i + 1)}
                            className="btn btn-sm" style={{ border: 0, fontWeight: 700, background: step === i + 1 ? 'var(--gold)' : '#eef2f8', color: step === i + 1 ? '#3a2a00' : '#6b7794' }}>
                            {fa(i + 1)}. {s}
                        </button>
                    ))}
                </div>

                {/* گام ۱ — اطلاعات پایه */}
                {step === 1 && (
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 12 }}>
                        <Field label="عنوان بازی" err={form.errors.title}><input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً: نبرد ریاضی" /></Field>
                        <Field label="درس"><select className="input" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)}><option value="">— انتخاب —</option>{subjects.map((s, i) => <option key={i} value={s}>{s}</option>)}</select></Field>
                        <Field label="پایه"><input className="input" value={form.data.grade} onChange={(e) => form.setData('grade', e.target.value)} placeholder="مثلاً: چهارم" /></Field>
                        <Field label="سطح سختی"><select className="input" value={form.data.difficulty} onChange={(e) => form.setData('difficulty', e.target.value)}><option value="easy">آسان</option><option value="medium">متوسط</option><option value="hard">سخت</option></select></Field>
                        <Field label="تاریخ انتشار — شمسی (اختیاری)"><JalaliDatePicker withTime value={form.data.publish_at || ''} onChange={(v) => form.setData('publish_at', v)} placeholder="بلافاصله" /></Field>
                        <Field label="تاریخ پایان — شمسی (اختیاری)"><JalaliDatePicker withTime value={form.data.close_at || ''} onChange={(v) => form.setData('close_at', v)} placeholder="بدون پایان" /></Field>
                        <div style={{ gridColumn: '1/-1' }}><Field label="توضیح کوتاه"><textarea className="input" rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></Field></div>
                        {/* گروه‌های هدف */}
                        <div style={{ gridColumn: '1/-1' }}>
                            <label style={{ fontWeight: 700, display: 'block', marginBottom: 6 }}>برای کدام گروه‌ها؟ (خالی = همه‌ی دانش‌آموزان)</label>
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                {groups.filter((g) => g.theme_id).map((g) => (
                                    <button key={g.theme_id} onClick={() => toggleTheme(g.theme_id)} className="btn btn-sm"
                                        style={{ border: 0, fontWeight: 700, background: form.data.target_themes.includes(g.theme_id) ? 'var(--gold)' : '#eef2f8', color: form.data.target_themes.includes(g.theme_id) ? '#3a2a00' : '#6b7794' }}>
                                        {g.name} ({fa(g.students.length)})
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {/* گام ۲ — قالب و تم */}
                {step === 2 && (
                    <>
                        <label style={{ fontWeight: 800, display: 'block', marginBottom: 8 }}>۱) قالب بازی (مکانیک)</label>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(170px,1fr))', gap: 10 }}>
                            {templates.map((t) => (
                                <button key={t.key} onClick={() => form.setData('template_key', t.key)}
                                    style={{ textAlign: 'right', cursor: 'pointer', fontFamily: 'inherit', borderRadius: 14, padding: 12, border: form.data.template_key === t.key ? '2px solid var(--gold)' : '1px solid var(--line)', background: form.data.template_key === t.key ? '#fff8e8' : '#fff' }}>
                                    <div style={{ fontSize: 26 }}>{t.icon}</div>
                                    <div style={{ fontWeight: 800, fontSize: 14, marginTop: 4 }}>{t.name}</div>
                                    <div style={{ fontSize: 11.5, color: 'var(--muted)', marginTop: 2 }}>{t.description}</div>
                                </button>
                            ))}
                        </div>
                        <label style={{ fontWeight: 800, display: 'block', margin: '18px 0 8px' }}>۲) تم ظاهری</label>
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            {themes.map((t) => (
                                <button key={t.id} onClick={() => form.setData('theme_id', t.id)} className="btn btn-sm"
                                    style={{ border: 0, fontWeight: 700, background: form.data.theme_id === t.id ? 'var(--gold)' : '#eef2f8', color: form.data.theme_id === t.id ? '#3a2a00' : '#6b7794' }}>
                                    {t.emoji} {t.name}
                                </button>
                            ))}
                        </div>
                        <p style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 10 }}>💡 قالب = مکانیکِ بازی؛ تم = ظاهر و حسِ بازی. یک تم روی هر قالبی کار می‌کند.</p>
                    </>
                )}

                {/* گام ۳ — سؤال‌ها */}
                {step === 3 && (
                    <>
                        {/* دستیار AI + بانک سؤال */}
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 10 }}>
                            <button type="button" onClick={() => { setAiOpen(!aiOpen); setBankOpen(false); }} className="btn btn-ghost btn-sm">🤖 ساخت سؤال با هوش مصنوعی</button>
                            <button type="button" onClick={() => { setBankOpen(!bankOpen); setAiOpen(false); if (!bankOpen) loadBank(); }} className="btn btn-ghost btn-sm">🗄️ از بانک سؤالات</button>
                        </div>
                        {aiOpen && (
                            <div style={{ border: '1px solid #ddd6fe', borderRadius: 12, padding: 12, marginBottom: 10, background: '#f5f3ff' }}>
                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'end' }}>
                                    <div className="field" style={{ margin: 0 }}><label>تعداد</label><input type="number" min={1} max={15} className="input" style={{ width: 80 }} value={ai.count} onChange={(e) => setAi({ ...ai, count: +e.target.value })} dir="ltr" /></div>
                                    <div className="field" style={{ margin: 0 }}><label>سختی</label><select className="input" value={ai.difficulty} onChange={(e) => setAi({ ...ai, difficulty: e.target.value })}><option value="easy">آسان</option><option value="medium">متوسط</option><option value="hard">دشوار</option></select></div>
                                    <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 13 }}><input type="checkbox" checked={ai.sample} onChange={(e) => setAi({ ...ai, sample: e.target.checked })} /> نمونه</label>
                                    <button type="button" onClick={runAi} disabled={aiBusy || !form.data.subject} className="btn btn-sm">{aiBusy ? '…' : '✨ تولید'}</button>
                                    {flavorTheme && <span style={{ fontSize: 12, color: 'var(--muted)' }}>طعم: {flavorTheme.name}</span>}
                                </div>
                                {!form.data.subject && <div style={{ fontSize: 12, color: '#b45309', marginTop: 6 }}>ابتدا در گام ۱ «درس» را انتخاب کنید.</div>}
                                {aiMsg && <div style={{ marginTop: 8, fontSize: 12.5, color: aiMsg.ok ? '#166534' : '#b91c1c', fontWeight: 700 }}>{aiMsg.text}</div>}
                                {aiRes.length > 0 && <div style={{ marginTop: 8 }}>{aiRes.map((q, i) => <label key={i} style={{ display: 'flex', gap: 8, padding: '4px 0', fontSize: 13, cursor: 'pointer' }}><input type="checkbox" checked={q._pick} onChange={() => setAiRes(aiRes.map((x, j) => j === i ? { ...x, _pick: !x._pick } : x))} /><span>{q.prompt}</span></label>)}<button type="button" onClick={addAi} className="btn btn-sm" style={{ marginTop: 6 }}>➕ افزودن به بازی</button></div>}
                            </div>
                        )}
                        {bankOpen && (
                            <div style={{ border: '1px solid #bfdbfe', borderRadius: 12, padding: 12, marginBottom: 10, background: '#eff6ff' }}>
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(130px,1fr))', gap: 8 }}>
                                    <select className="input" value={bankSubject} onChange={(e) => { setBankSubject(e.target.value); setBankLesson(''); }}><option value="">همه‌ی درس‌ها</option>{bankFacets.map((s) => <option key={s.subject} value={s.subject}>{s.subject}</option>)}</select>
                                    <select className="input" value={bankLesson} onChange={(e) => setBankLesson(e.target.value)} disabled={!bankSubject}><option value="">همه شماره‌درس‌ها</option>{bankLessons.map((l) => <option key={l} value={l === '—' ? '' : l}>{l}</option>)}</select>
                                    <input className="input" value={bankSearch} onChange={(e) => setBankSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && loadBank()} placeholder="جست‌وجو در بانک" />
                                    <button type="button" onClick={loadBank} className="btn btn-sm">🔍 اعمال</button>
                                </div>
                                <div style={{ maxHeight: 220, overflowY: 'auto', marginTop: 8 }}>
                                    {bankQ.length === 0 && <div style={{ fontSize: 12.5, color: 'var(--muted)' }}>سؤالی در بانک یافت نشد.</div>}
                                    {bankQ.map((q, i) => <label key={q.id} style={{ display: 'flex', gap: 8, padding: '4px 0', fontSize: 13, cursor: 'pointer' }}><input type="checkbox" checked={q._pick} onChange={() => setBankQ(bankQ.map((x, j) => j === i ? { ...x, _pick: !x._pick } : x))} /><span>{q.prompt} <span style={{ color: 'var(--muted)' }}>({[q.subject, q.lesson_no ? `درس ${q.lesson_no}` : null].filter(Boolean).join(' · ')})</span></span></label>)}
                                </div>
                                {bankQ.some((q) => q._pick) && <button type="button" onClick={addBank} className="btn btn-sm" style={{ marginTop: 6 }}>➕ افزودن انتخابی‌ها</button>}
                            </div>
                        )}
                        {form.data.questions.map((q, qi) => (
                            <div key={qi} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 12, marginBottom: 10, background: 'var(--cream)' }}>
                                <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 8, flexWrap: 'wrap' }}>
                                    <span className="tag tag-info">{fa(qi + 1)}</span>
                                    <select className="input" value={q.type} onChange={(e) => setType(qi, e.target.value)} style={{ width: 'auto', padding: '6px 9px' }}>
                                        <option value="mc">چهارگزینه‌ای</option><option value="tf">درست/نادرست</option><option value="short">پاسخ کوتاه</option>
                                    </select>
                                    <input type="number" min={1} max={100} className="input" value={q.points} onChange={(e) => setQ(qi, 'points', +e.target.value)} title="امتیاز" style={{ width: 80, padding: '6px 9px' }} dir="ltr" />
                                    <span style={{ marginInlineStart: 'auto', display: 'flex', gap: 4 }}>
                                        <button onClick={() => moveQ(qi, -1)} className="btn btn-ghost btn-sm">▲</button>
                                        <button onClick={() => moveQ(qi, 1)} className="btn btn-ghost btn-sm">▼</button>
                                        <button onClick={() => rmQ(qi)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
                                    </span>
                                </div>
                                <input className="input" value={q.prompt} onChange={(e) => setQ(qi, 'prompt', e.target.value)} placeholder="متن سؤال" style={{ marginBottom: 8 }} />
                                {q.type === 'short' ? (
                                    <input className="input" value={q.choices[0]?.value || ''} onChange={(e) => setChoice(qi, 0, e.target.value)} placeholder="پاسخ صحیح" />
                                ) : (
                                    <div style={{ display: 'grid', gap: 6 }}>
                                        {q.choices.map((c, ci) => (
                                            <div key={ci} style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                                <button onClick={() => setCorrect(qi, ci)} className="btn btn-sm" style={{ flex: 'none', border: 0, background: c.correct ? '#2bb673' : '#eef2f8', color: c.correct ? '#fff' : '#6b7794' }}>{c.correct ? '✓' : '○'}</button>
                                                <input className="input" value={c.value} onChange={(e) => setChoice(qi, ci, e.target.value)} placeholder={`گزینه ${fa(ci + 1)}`} style={{ flex: 1 }} />
                                                {q.type === 'mc' && q.choices.length > 2 && <button onClick={() => rmChoice(qi, ci)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>✕</button>}
                                            </div>
                                        ))}
                                        {q.type === 'mc' && q.choices.length < 4 && <button onClick={() => addChoice(qi)} className="btn btn-ghost btn-sm" style={{ width: 'fit-content' }}>+ گزینه</button>}
                                    </div>
                                )}
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, marginTop: 8 }}>
                                    <input className="input" value={q.hint1 || ''} onChange={(e) => setQ(qi, 'hint1', e.target.value)} placeholder="راهنما (اختیاری)" />
                                    <input className="input" value={q.explanation || ''} onChange={(e) => setQ(qi, 'explanation', e.target.value)} placeholder="توضیح آموزشی پس از پاسخ (اختیاری)" />
                                </div>
                            </div>
                        ))}
                        <button onClick={addQ} className="btn btn-ghost">+ افزودن سؤال</button>
                        {form.errors.questions && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 6 }}>{form.errors.questions}</div>}
                    </>
                )}

                {/* گام ۴ — قوانین */}
                {step === 4 && (
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 14 }}>
                        <Field label="تعداد جان"><input type="number" min={1} max={10} dir="ltr" className="input" value={form.data.rules.lives} onChange={(e) => setR('lives', +e.target.value)} /></Field>
                        <Field label="حداقل نمره‌ی قبولی (٪)"><input type="number" min={0} max={100} dir="ltr" className="input" value={form.data.rules.pass} onChange={(e) => setR('pass', +e.target.value)} /></Field>
                        <Toggle label="امکان تلاش مجدد" v={form.data.rules.retry} on={(v) => setR('retry', v)} />
                        <Toggle label="نمایش جواب صحیح پس از خطا" v={form.data.rules.show_answer} on={(v) => setR('show_answer', v)} />
                        <Toggle label="ترتیب تصادفی سؤال‌ها" v={form.data.rules.shuffle} on={(v) => setR('shuffle', v)} />
                        <Toggle label="رقابت گروهی" v={form.data.rules.group_race} on={(v) => setR('group_race', v)} />
                    </div>
                )}

                {/* گام ۵ — پیش‌نمایش و انتشار */}
                {step === 5 && (
                    <div>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10 }}>
                            <Info k="قالب" v={`${tmpl?.icon || ''} ${tmpl?.name || form.data.template_key}`} />
                            <Info k="تم" v={themes.find((t) => t.id === form.data.theme_id)?.name || '—'} />
                            <Info k="سؤال‌ها" v={`${fa(form.data.questions.length)} سؤال`} />
                            <Info k="سطح" v={{ easy: 'آسان', medium: 'متوسط', hard: 'سخت' }[form.data.difficulty]} />
                            <Info k="گروه‌ها" v={form.data.target_themes.length ? `${fa(form.data.target_themes.length)} گروه` : 'همه'} />
                        </div>
                        <div style={{ display: 'flex', gap: 10, marginTop: 18, flexWrap: 'wrap' }}>
                            <button onClick={() => save('draft')} disabled={form.processing} className="btn btn-ghost">💾 ذخیره‌ی پیش‌نویس</button>
                            <button onClick={() => save('published')} disabled={form.processing} className="btn">🚀 انتشار بازی</button>
                        </div>
                        <p style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 10 }}>در صورت ویرایشِ بازیِ دارای نتیجه، نسخه‌ی جدید ساخته می‌شود تا گزارش‌های قبلی حفظ شوند.</p>
                    </div>
                )}

                {/* ناوبری گام‌ها */}
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 18 }}>
                    <button onClick={() => setStep(Math.max(1, step - 1))} className="btn btn-ghost btn-sm" disabled={step === 1}>→ قبلی</button>
                    {step < 5 && <button onClick={() => setStep(step + 1)} className="btn btn-sm">بعدی ←</button>}
                </div>
            </div>

            {/* فهرست بازی‌ها */}
            <div className="panel">
                <h3>🗄️ بازی‌های من ({fa(games.length)})</h3>
                {games.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز بازی‌ای نساخته‌ای.</p>}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(250px,1fr))', gap: 12, marginTop: 8 }}>
                    {games.map((g) => (
                        <div key={g.id} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 13, borderTop: `4px solid ${STATUS_COLOR[g.status]}` }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <span style={{ fontSize: 22 }}>{g.icon}</span>
                                <b style={{ flex: 1 }}>{g.title}</b>
                                <span className="tag" style={{ fontSize: 11, background: `${STATUS_COLOR[g.status]}22`, color: STATUS_COLOR[g.status] }}>{STATUS_LABEL[g.status]}</span>
                            </div>
                            <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 6 }}>
                                {g.template} · {g.theme_emoji || ''} {g.theme || ''} · {fa(g.questions)} سؤال · {fa(g.plays)} بازی‌شده
                            </div>
                            <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                                <Link href={route('teacher.studio.edit', g.id)} className="btn btn-ghost btn-sm">✏️ ویرایش</Link>
                                <Link href={route('teacher.studio.report', g.id)} className="btn btn-ghost btn-sm">📊 گزارش</Link>
                                {g.status !== 'published'
                                    ? <button onClick={() => router.post(route('teacher.studio.status', g.id), { status: 'published' }, { preserveScroll: true })} className="btn btn-ghost btn-sm">🚀 انتشار</button>
                                    : <button onClick={() => router.post(route('teacher.studio.status', g.id), { status: 'archived' }, { preserveScroll: true })} className="btn btn-ghost btn-sm">📁 آرشیو</button>}
                                <button onClick={() => router.post(route('teacher.studio.duplicate', g.id), {}, { preserveScroll: true })} className="btn btn-ghost btn-sm">📋</button>
                                <button onClick={() => confirm(`بازی «${g.title}» حذف شود؟ این کار قابل بازگشت نیست.`) && confirm('برای اطمینان، دوباره تأیید کنید.') && router.delete(route('teacher.studio.destroy', g.id), { preserveScroll: true })} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}

const STATUS_LABEL = { draft: 'پیش‌نویس', published: 'منتشر', archived: 'آرشیو', disabled: 'غیرفعال' };
const STATUS_COLOR = { draft: '#8896ad', published: '#2bb673', archived: '#e8862e', disabled: '#e8505b' };

function Field({ label, err, children }) {
    return <div className="field" style={{ margin: 0 }}><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
function Toggle({ label, v, on }) {
    return <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 14, cursor: 'pointer', padding: '10px 0' }}>
        <input type="checkbox" checked={!!v} onChange={(e) => on(e.target.checked)} /> {label}</label>;
}
function Info({ k, v }) {
    return <div style={{ background: 'var(--cream)', borderRadius: 12, padding: 12 }}><div style={{ fontSize: 11, color: 'var(--muted)' }}>{k}</div><b style={{ fontSize: 14 }}>{v}</b></div>;
}
