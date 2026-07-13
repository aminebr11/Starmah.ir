import { usePage, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import DashLayout, { adminMenu, schoolMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const TYPE = { mc: 'چهارگزینه‌ای', tf: 'درست/نادرست', desc: 'تشریحی', blank: 'جای خالی' };
const DIFF = { easy: 'آسان', medium: 'متوسط', hard: 'دشوار' };
const blankQ = () => ({ type: 'mc', prompt: '', difficulty: 'medium', explanation: '', choices: [{ value: '', correct: true }, { value: '', correct: false }] });

export default function QuestionBank() {
    const { isSuper, questions = [], filters = {}, stats = {}, share, aiOn, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [tab, setTab] = useState('list');
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);
    const menu = isSuper ? adminMenu : schoolMenu;

    const [f, setF] = useState({ subject: filters.subject || '', grade: filters.grade || '', difficulty: filters.difficulty || '', type: filters.type || '', search: filters.search || '' });
    const apply = () => router.get(route('bank.index'), f, { preserveState: true });

    // افزودن سؤال (دستی + AI)
    const add = useForm({ subject: '', grade: '', topic: '', scope: isSuper ? 'global' : 'school', questions: [blankQ()] });
    const setQ = (i, k, v) => { const qs = [...add.data.questions]; qs[i] = { ...qs[i], [k]: v }; add.setData('questions', qs); };
    const setCh = (qi, ci, v) => { const qs = [...add.data.questions]; qs[qi].choices[ci].value = v; add.setData('questions', [...qs]); };
    const setCorrect = (qi, ci) => { const qs = [...add.data.questions]; qs[qi].choices = qs[qi].choices.map((c, j) => ({ ...c, correct: j === ci })); add.setData('questions', [...qs]); };
    const submitAdd = () => add.post(route('bank.store'), { preserveScroll: true, onSuccess: () => { add.reset(); add.setData('questions', [blankQ()]); setTab('list'); } });

    const [ai, setAi] = useState({ count: 5, type: 'mc', difficulty: 'medium', sample: false });
    const [aiBusy, setAiBusy] = useState(false); const [aiMsg, setAiMsg] = useState(null); const [aiRes, setAiRes] = useState([]);
    const runAi = async () => {
        setAiBusy(true); setAiMsg(null); setAiRes([]);
        try { const { data } = await axios.post(route('bank.ai'), { ...ai, subject: add.data.subject, topic: add.data.topic || add.data.subject, grade: add.data.grade });
            setAiMsg({ ok: data.ok, text: data.message }); if (data.ok) setAiRes(data.questions || []);
        } catch (e) { setAiMsg({ ok: false, text: e.response?.data?.message || 'خطا' }); }
        setAiBusy(false);
    };
    const addAiToList = () => { add.setData('questions', [...add.data.questions.filter((q) => q.prompt.trim()), ...aiRes.map((q) => ({ ...q, source: 'ai' }))]); setAiRes([]); setAiMsg(null); };

    // اشتراک‌گذاری (فقط ادمین کل)
    const shareForm = useForm({ scope: share?.scope || 'off', schools: share?.schools || [] });
    const toggleSchool = (id) => shareForm.setData('schools', shareForm.data.schools.includes(id) ? shareForm.data.schools.filter((x) => x !== id) : [...shareForm.data.schools, id]);

    return (
        <DashLayout title="بانک سؤالات" roleLabel={isSuper ? 'ادمین کل' : 'مدیر مدرسه'} menu={menu} active="bank">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div className="panel">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <h3 style={{ margin: 0 }}>🗄️ بانک سؤالات {isSuper ? '(کلِ پلتفرم)' : '(مدرسه‌ی شما)'}</h3>
                    <span className="tag tag-info">{fa(stats.total)} سؤال · {fa(stats.ai)} با AI</span>
                    <div style={{ marginInlineStart: 'auto', display: 'flex', gap: 6 }}>
                        <button onClick={() => setTab('list')} className={`btn btn-sm ${tab === 'list' ? '' : 'btn-ghost'}`}>فهرست</button>
                        <button onClick={() => setTab('add')} className={`btn btn-sm ${tab === 'add' ? '' : 'btn-ghost'}`}>➕ ساخت سؤال</button>
                        {isSuper && <button onClick={() => setTab('share')} className={`btn btn-sm ${tab === 'share' ? '' : 'btn-ghost'}`}>🔗 اشتراک‌گذاری</button>}
                    </div>
                </div>

                {tab === 'list' && (
                    <>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(130px,1fr))', gap: 8, marginTop: 12 }}>
                            <input className="input" value={f.search} onChange={(e) => setF({ ...f, search: e.target.value })} placeholder="🔍 جست‌وجو" />
                            <input className="input" value={f.subject} onChange={(e) => setF({ ...f, subject: e.target.value })} placeholder="درس" />
                            <input className="input" value={f.grade} onChange={(e) => setF({ ...f, grade: e.target.value })} placeholder="پایه" />
                            <select className="input" value={f.type} onChange={(e) => setF({ ...f, type: e.target.value })}><option value="">همه انواع</option>{Object.entries(TYPE).map(([v, t]) => <option key={v} value={v}>{t}</option>)}</select>
                            <select className="input" value={f.difficulty} onChange={(e) => setF({ ...f, difficulty: e.target.value })}><option value="">همه سطوح</option>{Object.entries(DIFF).map(([v, t]) => <option key={v} value={v}>{t}</option>)}</select>
                            <button onClick={apply} className="btn btn-sm">فیلتر</button>
                        </div>
                        <div style={{ display: 'grid', gap: 10, marginTop: 12 }}>
                            {questions.length === 0 && <p style={{ color: 'var(--muted)' }}>سؤالی یافت نشد.</p>}
                            {questions.map((q) => <BankRow key={q.id} q={q} />)}
                        </div>
                    </>
                )}

                {tab === 'add' && (
                    <div style={{ marginTop: 14 }}>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10 }}>
                            <div className="field" style={{ margin: 0 }}><label>درس</label><input className="input" value={add.data.subject} onChange={(e) => add.setData('subject', e.target.value)} /></div>
                            <div className="field" style={{ margin: 0 }}><label>پایه/مقطع</label><input className="input" value={add.data.grade} onChange={(e) => add.setData('grade', e.target.value)} /></div>
                            <div className="field" style={{ margin: 0 }}><label>مبحث</label><input className="input" value={add.data.topic} onChange={(e) => add.setData('topic', e.target.value)} /></div>
                            {isSuper && <div className="field" style={{ margin: 0 }}><label>دامنه</label><select className="input" value={add.data.scope} onChange={(e) => add.setData('scope', e.target.value)}><option value="global">سراسری (همه)</option><option value="school">فقط مدرسه</option></select></div>}
                        </div>

                        {/* دستیار AI */}
                        <div style={{ border: '1px solid #ddd6fe', borderRadius: 12, padding: 12, marginTop: 12, background: '#f5f3ff' }}>
                            <b style={{ fontSize: 13.5 }}>🤖 ساخت سؤال با هوش مصنوعی</b>
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'end', marginTop: 8 }}>
                                <div className="field" style={{ margin: 0 }}><label>تعداد</label><input type="number" min={1} max={20} className="input" style={{ width: 80 }} value={ai.count} onChange={(e) => setAi({ ...ai, count: +e.target.value })} dir="ltr" /></div>
                                <div className="field" style={{ margin: 0 }}><label>نوع</label><select className="input" value={ai.type} onChange={(e) => setAi({ ...ai, type: e.target.value })}><option value="mc">چهارگزینه‌ای</option><option value="tf">درست/نادرست</option><option value="desc">تشریحی</option></select></div>
                                <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 13 }}><input type="checkbox" checked={ai.sample} onChange={(e) => setAi({ ...ai, sample: e.target.checked })} /> نمونه</label>
                                <button type="button" onClick={runAi} disabled={aiBusy || !add.data.subject} className="btn btn-sm">{aiBusy ? '…' : '✨ تولید'}</button>
                            </div>
                            {aiMsg && <div style={{ marginTop: 8, fontSize: 12.5, color: aiMsg.ok ? '#166534' : '#b91c1c', fontWeight: 700 }}>{aiMsg.text}</div>}
                            {aiRes.length > 0 && <div style={{ marginTop: 8 }}>{aiRes.map((q, i) => <div key={i} style={{ fontSize: 13, padding: '3px 0' }}>• {q.prompt}</div>)}<button type="button" onClick={addAiToList} className="btn btn-sm" style={{ marginTop: 6 }}>➕ افزودن به فهرست</button></div>}
                        </div>

                        {/* سؤال‌های دستی */}
                        {add.data.questions.map((q, qi) => (
                            <div key={qi} style={{ border: '1px solid var(--line)', borderRadius: 12, padding: 12, marginTop: 10, background: 'var(--cream)' }}>
                                <div style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
                                    <select className="input" style={{ width: 'auto' }} value={q.type} onChange={(e) => setQ(qi, 'type', e.target.value)}>{Object.entries(TYPE).map(([v, t]) => <option key={v} value={v}>{t}</option>)}</select>
                                    <input className="input" value={q.prompt} onChange={(e) => setQ(qi, 'prompt', e.target.value)} placeholder="متن سؤال" style={{ flex: 1 }} />
                                    {add.data.questions.length > 1 && <button type="button" onClick={() => add.setData('questions', add.data.questions.filter((_, j) => j !== qi))} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>}
                                </div>
                                {(q.type === 'mc' || q.type === 'tf') && (q.choices || []).map((c, ci) => (
                                    <div key={ci} style={{ display: 'flex', gap: 8, marginTop: 4 }}>
                                        <button type="button" onClick={() => setCorrect(qi, ci)} className="btn btn-sm" style={{ background: c.correct ? '#2bb673' : '#eef2f8', color: c.correct ? '#fff' : '#6b7794', border: 0 }}>{c.correct ? '✓' : '○'}</button>
                                        <input className="input" value={c.value} onChange={(e) => setCh(qi, ci, e.target.value)} placeholder={`گزینه ${fa(ci + 1)}`} />
                                    </div>
                                ))}
                            </div>
                        ))}
                        <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
                            <button onClick={() => add.setData('questions', [...add.data.questions, blankQ()])} className="btn btn-ghost btn-sm">+ سؤال دستی</button>
                            <button onClick={submitAdd} disabled={add.processing} className="btn" style={{ marginInlineStart: 'auto' }}>💾 ذخیره در بانک</button>
                        </div>
                    </div>
                )}

                {tab === 'share' && isSuper && share && (
                    <div style={{ marginTop: 14 }}>
                        <p style={{ color: 'var(--muted)' }}>تعیین کنید بانکِ سؤالاتِ سراسری در اختیار کدام مدارس قرار گیرد.</p>
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            {[['off', 'هیچ مدرسه‌ای'], ['all', 'همه‌ی مدارس'], ['schools', 'مدارسِ منتخب']].map(([v, t]) => (
                                <button key={v} onClick={() => shareForm.setData('scope', v)} className={`btn btn-sm ${shareForm.data.scope === v ? '' : 'btn-ghost'}`}>{t}</button>
                            ))}
                        </div>
                        {shareForm.data.scope === 'schools' && (
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 12 }}>
                                {share.allSchools.map((s) => <button key={s.id} onClick={() => toggleSchool(s.id)} className={`btn btn-sm ${shareForm.data.schools.includes(s.id) ? '' : 'btn-ghost'}`}>{s.name}</button>)}
                            </div>
                        )}
                        <button onClick={() => shareForm.post(route('bank.share'), { preserveScroll: true })} className="btn" style={{ marginTop: 14 }}>💾 ذخیره</button>
                    </div>
                )}
            </div>
        </DashLayout>
    );
}

function BankRow({ q }) {
    const [edit, setEdit] = useState(false);
    const form = useForm({ prompt: q.prompt, choices: q.choices || [], explanation: q.explanation || '', subject: q.subject || '', grade: q.grade || '', topic: q.topic || '', difficulty: q.difficulty || 'medium' });
    const save = () => form.put(route('bank.update', q.id), { preserveScroll: true, onSuccess: () => setEdit(false) });
    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 12, padding: 12 }}>
            {edit ? (
                <div style={{ display: 'grid', gap: 8 }}>
                    <textarea className="input" rows={2} value={form.data.prompt} onChange={(e) => form.setData('prompt', e.target.value)} />
                    {(form.data.choices || []).map((c, ci) => (
                        <div key={ci} style={{ display: 'flex', gap: 8 }}>
                            <button onClick={() => form.setData('choices', form.data.choices.map((x, j) => ({ ...x, correct: j === ci })))} className="btn btn-sm" style={{ background: c.correct ? '#2bb673' : '#eef2f8', color: c.correct ? '#fff' : '#6b7794', border: 0 }}>{c.correct ? '✓' : '○'}</button>
                            <input className="input" value={c.value} onChange={(e) => { const ch = [...form.data.choices]; ch[ci] = { ...ch[ci], value: e.target.value }; form.setData('choices', ch); }} />
                        </div>
                    ))}
                    <input className="input" value={form.data.explanation} onChange={(e) => form.setData('explanation', e.target.value)} placeholder="توضیح آموزشی" />
                    <div style={{ display: 'flex', gap: 6 }}><button onClick={save} className="btn btn-sm">💾 ذخیره</button><button onClick={() => setEdit(false)} className="btn btn-ghost btn-sm">انصراف</button></div>
                </div>
            ) : (
                <>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap', marginBottom: 4 }}>
                        <span className="tag" style={{ background: q.source === 'ai' ? '#ede9fe' : '#e0f2fe', color: q.source === 'ai' ? '#6d28d9' : '#0369a1', fontSize: 11 }}>{q.source === 'ai' ? 'AI' : q.source === 'sample' ? 'نمونه' : 'دستی'}</span>
                        <span style={{ fontSize: 12, color: 'var(--muted)' }}>{TYPE[q.type]} · {DIFF[q.difficulty]}{q.subject ? ` · ${q.subject}` : ''}{q.grade ? ` · پایه ${q.grade}` : ''}{q.topic ? ` · ${q.topic}` : ''}</span>
                        {q.author && <span style={{ fontSize: 11.5, color: 'var(--muted-2)' }}>👤 {q.author}</span>}
                        <span style={{ marginInlineStart: 'auto', display: 'flex', gap: 4 }}>
                            <button onClick={() => setEdit(true)} className="btn btn-ghost btn-sm">✏️</button>
                            <button onClick={() => confirm('این سؤال حذف شود؟') && router.delete(route('bank.destroy', q.id), { preserveScroll: true })} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
                        </span>
                    </div>
                    <b style={{ fontSize: 14 }}>{q.prompt}</b>
                    {(q.choices || []).length > 0 && <div style={{ fontSize: 12.5, color: 'var(--muted)', marginTop: 3 }}>{(q.choices || []).map((c) => (c.correct ? '✅ ' : '▫️ ') + c.value).join('   ')}</div>}
                </>
            )}
        </div>
    );
}
