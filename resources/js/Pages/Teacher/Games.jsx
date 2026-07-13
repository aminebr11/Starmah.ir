import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const blankQ = () => ({ prompt: '', choices: [{ value: '', correct: true }, { value: '', correct: false }] });

export default function Games() {
    const { classroom, games = [], subjects = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [editId, setEditId] = useState(null);
    useEffect(() => { if (flash?.flash) { setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); window.scrollTo({ top: 0, behavior: 'smooth' }); } }, [flash]);

    const form = useForm({
        title: '', description: '', mode: 'quiz', subject: '', points: 20,
        scheduled_at: '', publish: true, questions: [blankQ()],
    });

    const startNew = () => { setEditId(null); form.reset(); form.setData('questions', [blankQ()]); window.scrollTo({ top: 0, behavior: 'smooth' }); };
    const startEdit = (g) => {
        setEditId(g.id);
        form.setData({
            title: g.title, description: g.description || '', mode: g.mode || 'quiz',
            subject: g.subject || '', points: g.points, scheduled_at: '', publish: g.status === 'active',
            questions: (g.questions || []).map((q) => ({ prompt: q.prompt, choices: q.choices })),
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const setQ = (i, key, val) => { const qs = [...form.data.questions]; qs[i] = { ...qs[i], [key]: val }; form.setData('questions', qs); };
    const setChoice = (qi, ci, val) => { const qs = [...form.data.questions]; const ch = [...qs[qi].choices]; ch[ci] = { ...ch[ci], value: val }; qs[qi] = { ...qs[qi], choices: ch }; form.setData('questions', qs); };
    const setCorrect = (qi, ci) => { const qs = [...form.data.questions]; qs[qi] = { ...qs[qi], choices: qs[qi].choices.map((c, j) => ({ ...c, correct: j === ci })) }; form.setData('questions', qs); };
    const addChoice = (qi) => { const qs = [...form.data.questions]; if (qs[qi].choices.length < 4) { qs[qi] = { ...qs[qi], choices: [...qs[qi].choices, { value: '', correct: false }] }; form.setData('questions', qs); } };
    const rmChoice = (qi, ci) => { const qs = [...form.data.questions]; if (qs[qi].choices.length > 2) { qs[qi] = { ...qs[qi], choices: qs[qi].choices.filter((_, j) => j !== ci) }; form.setData('questions', qs); } };
    const addQ = () => form.setData('questions', [...form.data.questions, blankQ()]);
    const rmQ = (i) => form.setData('questions', form.data.questions.filter((_, j) => j !== i));

    // دستیار هوشمند طراحی سؤالِ بازی (منوی بسته؛ اطلاعات از فرم بالا)
    const [aiOpen, setAiOpen] = useState(false);
    const [ai, setAi] = useState({ count: 5, difficulty: 'easy', sample: false });
    const [aiBusy, setAiBusy] = useState(false);
    const [aiMsg, setAiMsg] = useState(null);
    const [aiRes, setAiRes] = useState([]);
    const runAi = async () => {
        setAiBusy(true); setAiMsg(null); setAiRes([]);
        try {
            const { data } = await axios.post(route('teacher.games.ai'), { ...ai, subject: form.data.subject, topic: form.data.subject });
            setAiMsg({ ok: data.ok, mode: data.mode, text: data.message });
            if (data.ok) setAiRes((data.questions || []).map((q) => ({ ...q, _pick: true })));
        } catch (e) { setAiMsg({ ok: false, text: e.response?.data?.message || 'خطا' }); }
        setAiBusy(false);
    };
    const addAi = () => {
        const picked = aiRes.filter((q) => q._pick).map((q) => ({ prompt: q.prompt, choices: (q.choices || []).map((c) => ({ value: c.value, correct: !!c.correct })) }));
        form.setData('questions', [...form.data.questions.filter((q) => q.prompt.trim()), ...picked]);
        setAiRes([]); setAiMsg(null);
    };

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: false, onSuccess: () => { form.reset(); form.setData('questions', [blankQ()]); setEditId(null); } };
        if (editId) form.put(route('teacher.games.update', editId), opts);
        else form.post(route('teacher.games.store'), opts);
    };

    if (!classroom) {
        return <DashLayout title="بازی‌ها" roleLabel="معلم" menu={teacherMenu} active="games">
            <div className="panel"><b>ابتدا باید یک کلاس داشته باشید.</b></div>
        </DashLayout>;
    }

    return (
        <DashLayout title="بازی‌ها" roleLabel="معلم" menu={teacherMenu} active="games">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            {/* سازنده‌ی بازی */}
            <div className="panel">
                <h3>{editId ? '✏️ ویرایش بازی' : '🎮 ساخت بازی جدید'} <span style={{ color: 'var(--muted)', fontSize: 13, fontWeight: 400 }}>— کلاس {classroom.name}</span></h3>
                <form onSubmit={submit}>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: 12, marginTop: 10 }}>
                        <Field label="عنوان بازی" err={form.errors.title}><input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً: نبرد ضرب" /></Field>
                        <Field label="نوع بازی"><select className="input" value={form.data.mode} onChange={(e) => form.setData('mode', e.target.value)}>
                            <option value="quiz">🎯 چهارگزینه‌ای</option>
                            <option value="truefalse">✅ درست/نادرست</option>
                            <option value="memory">🧠 چالش سریع</option>
                        </select></Field>
                        <Field label="درس"><select className="input" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)}>
                            <option value="">— انتخاب —</option>
                            {subjects.map((s, i) => { const nm = typeof s === 'string' ? s : s.name; return <option key={i} value={nm}>{typeof s === 'string' ? s : `${s.icon || ''} ${s.name}`}</option>; })}
                        </select></Field>
                        <Field label="امتیاز (XP) کل بازی" err={form.errors.points}><input type="number" min={1} max={500} className="input" value={form.data.points} onChange={(e) => form.setData('points', e.target.value)} dir="ltr" /></Field>
                        <Field label="زمان انتشار (اختیاری)"><input type="datetime-local" className="input" value={form.data.scheduled_at} onChange={(e) => form.setData('scheduled_at', e.target.value)} dir="ltr" /></Field>
                    </div>

                    <div style={{ marginTop: 16 }}>
                        <b style={{ fontSize: 14 }}>سؤال‌های بازی</b>

                        {/* دستیار هوشمند طراحی سؤال */}
                        <div style={{ border: '1px solid #ddd6fe', borderRadius: 14, padding: 12, marginTop: 10, background: '#f5f3ff' }}>
                            <button type="button" onClick={() => setAiOpen(!aiOpen)} style={{ width: '100%', textAlign: 'right', border: 0, background: 'transparent', cursor: 'pointer', fontFamily: 'inherit', fontWeight: 800, fontSize: 14, display: 'flex', alignItems: 'center', gap: 8 }}>
                                🤖 دستیار هوشمند طراحی سؤال <span style={{ color: 'var(--muted)', fontWeight: 400, fontSize: 12 }}>(بر اساس درسِ همین بازی)</span>
                                <span style={{ marginInlineStart: 'auto' }}>{aiOpen ? '▲' : '▼'}</span>
                            </button>
                            {aiOpen && (
                                <div style={{ marginTop: 10 }}>
                                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'end' }}>
                                        <div className="field" style={{ margin: 0 }}><label>تعداد</label><input type="number" min={1} max={15} className="input" style={{ width: 80 }} value={ai.count} onChange={(e) => setAi({ ...ai, count: +e.target.value })} dir="ltr" /></div>
                                        <div className="field" style={{ margin: 0 }}><label>سختی</label><select className="input" value={ai.difficulty} onChange={(e) => setAi({ ...ai, difficulty: e.target.value })}><option value="easy">آسان</option><option value="medium">متوسط</option><option value="hard">دشوار</option></select></div>
                                        <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 13 }}><input type="checkbox" checked={ai.sample} onChange={(e) => setAi({ ...ai, sample: e.target.checked })} /> حالت نمونه</label>
                                        <button type="button" onClick={runAi} disabled={aiBusy || !form.data.subject} className="btn btn-sm">{aiBusy ? '…' : '✨ تولید'}</button>
                                    </div>
                                    {!form.data.subject && <div style={{ fontSize: 12, color: '#b45309', marginTop: 6 }}>ابتدا «درس» را در بالای فرم انتخاب کنید.</div>}
                                    {aiMsg && <div style={{ marginTop: 8, fontSize: 12.5, color: aiMsg.ok ? '#166534' : '#b91c1c', fontWeight: 700 }}>{aiMsg.text}</div>}
                                    {aiRes.length > 0 && (
                                        <div style={{ marginTop: 8 }}>
                                            {aiRes.map((q, i) => (
                                                <label key={i} style={{ display: 'flex', gap: 8, padding: '6px 0', cursor: 'pointer', fontSize: 13 }}>
                                                    <input type="checkbox" checked={q._pick} onChange={() => setAiRes(aiRes.map((x, j) => j === i ? { ...x, _pick: !x._pick } : x))} />
                                                    <span>{q.prompt} <span style={{ color: 'var(--muted)' }}>({(q.choices || []).map((c) => c.value + (c.correct ? '✓' : '')).join('، ')})</span></span>
                                                </label>
                                            ))}
                                            <button type="button" onClick={addAi} className="btn btn-sm" style={{ marginTop: 6 }}>➕ افزودن به بازی</button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        {form.data.questions.map((q, qi) => (
                            <div key={qi} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 12, marginTop: 10, background: 'var(--cream)' }}>
                                <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 8 }}>
                                    <span className="tag tag-info">{fa(qi + 1)}</span>
                                    <input className="input" value={q.prompt} onChange={(e) => setQ(qi, 'prompt', e.target.value)} placeholder="متن سؤال" style={{ flex: 1 }} />
                                    {form.data.questions.length > 1 && <button type="button" onClick={() => rmQ(qi)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>}
                                </div>
                                <div style={{ display: 'grid', gap: 6 }}>
                                    {q.choices.map((c, ci) => (
                                        <div key={ci} style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                            <button type="button" onClick={() => setCorrect(qi, ci)} title="پاسخ درست"
                                                className="btn btn-sm" style={{ flex: 'none', background: c.correct ? '#2bb673' : '#eef2f8', color: c.correct ? '#fff' : '#6b7794', border: 0 }}>{c.correct ? '✓ درست' : 'انتخاب'}</button>
                                            <input className="input" value={c.value} onChange={(e) => setChoice(qi, ci, e.target.value)} placeholder={`گزینه ${fa(ci + 1)}`} style={{ flex: 1 }} />
                                            {q.choices.length > 2 && <button type="button" onClick={() => rmChoice(qi, ci)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b', flex: 'none' }}>✕</button>}
                                        </div>
                                    ))}
                                    {q.choices.length < 4 && <button type="button" onClick={() => addChoice(qi)} className="btn btn-ghost btn-sm" style={{ width: 'fit-content' }}>+ گزینه</button>}
                                </div>
                            </div>
                        ))}
                        <button type="button" onClick={addQ} className="btn btn-ghost btn-sm" style={{ marginTop: 10 }}>+ افزودن سؤال</button>
                    </div>

                    <div style={{ display: 'flex', gap: 10, alignItems: 'center', marginTop: 16, flexWrap: 'wrap' }}>
                        <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 14 }}>
                            <input type="checkbox" checked={form.data.publish} onChange={(e) => form.setData('publish', e.target.checked)} /> انتشار فوری (دانش‌آموزان ببینند)
                        </label>
                        <button type="submit" disabled={form.processing} className="btn" style={{ marginInlineStart: 'auto' }}>{editId ? '💾 ذخیره‌ی تغییرات' : '🎮 ساخت بازی'}</button>
                        {editId && <button type="button" onClick={startNew} className="btn btn-ghost">انصراف</button>}
                    </div>
                </form>
            </div>

            {/* بانک بازی‌ها */}
            <div className="panel">
                <h3>🗄️ بانک بازی‌ها ({fa(games.length)})</h3>
                {games.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز بازی‌ای نساخته‌ای.</p>}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(260px,1fr))', gap: 12, marginTop: 8 }}>
                    {games.map((g) => (
                        <div key={g.id} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 14, borderTop: `4px solid ${g.live ? '#2bb673' : '#c4ccda'}` }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <span style={{ fontSize: 22 }}>🎮</span>
                                <b style={{ flex: 1 }}>{g.title}</b>
                                <span className={`tag ${g.live ? 'tag-ok' : 'tag-info'}`} style={{ fontSize: 11 }}>{g.live ? 'منتشر' : 'متوقف'}</span>
                            </div>
                            <div style={{ fontSize: 12.5, color: 'var(--muted)', marginTop: 6 }}>
                                {g.subject ? `${g.subject} · ` : ''}{fa(g.count)} سؤال · {fa(g.points)} امتیاز · {fa(g.plays)} بازی‌شده
                            </div>
                            {g.jscheduled && <div style={{ fontSize: 11.5, color: '#a5570f', marginTop: 4 }}>⏰ انتشار: {g.jscheduled}</div>}
                            <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                                <button onClick={() => startEdit(g)} className="btn btn-ghost btn-sm">✏️ ویرایش</button>
                                <button onClick={() => router.post(route('teacher.games.toggle', g.id), {}, { preserveScroll: true })} className="btn btn-ghost btn-sm">{g.status === 'active' ? '⏸️ توقف' : '▶️ انتشار'}</button>
                                <button onClick={() => router.post(route('teacher.games.duplicate', g.id), {}, { preserveScroll: true })} className="btn btn-ghost btn-sm">📋 کپی</button>
                                <button onClick={() => confirm(`بازی «${g.title}» حذف شود؟`) && router.delete(route('teacher.games.destroy', g.id), { preserveScroll: true })} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}

function Field({ label, err, children }) {
    return <div className="field" style={{ margin: 0 }}><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
