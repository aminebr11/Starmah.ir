import { usePage, router, useForm } from '@inertiajs/react';
import { useState, useEffect, useMemo, createContext, useContext } from 'react';
import axios from 'axios';
import DashLayout, { adminMenu, schoolMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const TYPE = { mc: 'چهارگزینه‌ای', tf: 'درست/نادرست', desc: 'تشریحی', blank: 'جای خالی' };
const DIFF = { easy: 'آسان', medium: 'متوسط', hard: 'دشوار' };
const blankQ = () => ({ type: 'mc', prompt: '', topic: '', difficulty: 'medium', explanation: '', image: null, choices: [{ value: '', correct: true }, { value: '', correct: false }] });

// انتخابِ گروهی (برای حذف گروهی) — بدون prop-drilling
const SelCtx = createContext(null);

function useCurriculum(curriculum) {
    const levels = useMemo(() => curriculum.map((l) => l.level), [curriculum]);
    const gradesOf = (level) => (curriculum.find((l) => l.level === level)?.grades || []).map((g) => g.grade);
    const subjectsOf = (level, grade) => {
        const g = (curriculum.find((l) => l.level === level)?.grades || []).find((x) => x.grade === grade);
        return (g?.subjects || []).map((s) => s.name);
    };
    return { levels, gradesOf, subjectsOf };
}

export default function QuestionBank() {
    const { isSuper, grouped = [], curriculum = [], filters = {}, stats = {}, share, aiOn, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [tab, setTab] = useState('list');
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);
    const menu = isSuper ? adminMenu : schoolMenu;
    const cur = useCurriculum(curriculum);

    const [f, setF] = useState({ level: filters.level || '', grade: filters.grade || '', subject: filters.subject || '', difficulty: filters.difficulty || '', type: filters.type || '', search: filters.search || '' });
    const apply = () => router.get(route('bank.index'), f, { preserveState: true, preserveScroll: true });
    const reset = () => router.get(route('bank.index'), {}, { preserveState: true });

    // انتخابِ گروهی
    const [sel, setSel] = useState(() => new Set());
    const toggle = (id) => setSel((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
    const clearSel = () => setSel(new Set());
    const bulkDelete = () => {
        if (sel.size === 0) return;
        if (!confirm(`حذفِ ${fa(sel.size)} سؤالِ انتخاب‌شده؟`)) return;
        router.post(route('bank.bulk-destroy'), { ids: [...sel] }, { preserveScroll: true, onSuccess: clearSel });
    };

    return (
        <DashLayout title="بانک سؤالات" roleLabel={isSuper ? 'ادمین کل' : 'مدیر مدرسه'} menu={menu} active="bank">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div className="panel">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <h3 style={{ margin: 0 }}>🗄️ بانک سؤالات {isSuper ? '(کلِ پلتفرم)' : '(مدرسه‌ی شما)'}</h3>
                    <span className="tag tag-info">{fa(stats.total)} سؤال · {fa(stats.ai)} با AI</span>
                    <div style={{ marginInlineStart: 'auto', display: 'flex', gap: 6 }}>
                        <button onClick={() => setTab('list')} className={`btn btn-sm ${tab === 'list' ? '' : 'btn-ghost'}`}>📚 فهرست</button>
                        <button onClick={() => setTab('add')} className={`btn btn-sm ${tab === 'add' ? '' : 'btn-ghost'}`}>➕ ساخت سؤال</button>
                        {isSuper && <button onClick={() => setTab('share')} className={`btn btn-sm ${tab === 'share' ? '' : 'btn-ghost'}`}>🔗 اشتراک‌گذاری</button>}
                    </div>
                </div>

                <SelCtx.Provider value={{ isSuper, sel, toggle }}>
                    {tab === 'list' && (
                        <>
                            {isSuper && sel.size > 0 && (
                                <div style={{ display: 'flex', alignItems: 'center', gap: 10, background: '#fdecee', border: '1px solid #f5c2c7', borderRadius: 10, padding: '9px 12px', marginTop: 12 }}>
                                    <b style={{ color: '#b0333f' }}>{fa(sel.size)} سؤال انتخاب شده</b>
                                    <button onClick={bulkDelete} className="btn btn-sm" style={{ background: '#e8505b', marginInlineStart: 'auto' }}>🗑️ حذف گروهی</button>
                                    <button onClick={clearSel} className="btn btn-ghost btn-sm">لغو انتخاب</button>
                                </div>
                            )}
                            <ListTab {...{ f, setF, cur, apply, reset, grouped }} />
                        </>
                    )}
                    {tab === 'add' && <AddTab {...{ isSuper, cur, aiOn, onDone: () => setTab('list') }} />}
                    {tab === 'share' && isSuper && share && <ShareTab {...{ share, cur }} />}
                </SelCtx.Provider>
            </div>
        </DashLayout>
    );
}

/* ───────── فهرست: فیلترِ آبشاری + گروه‌بندیِ مقطع→کلاس→درس→شماره درس ───────── */
function ListTab({ f, setF, cur, apply, reset, grouped }) {
    const grades = cur.gradesOf(f.level);
    const subjects = cur.subjectsOf(f.level, f.grade);
    // اعمالِ خودکارِ فیلتر با تغییرِ هر گزینه (بدون نیاز به دکمه)
    const go = (next) => { setF(next); router.get(route('bank.index'), next, { preserveState: true, preserveScroll: true, replace: true }); };
    return (
        <>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(130px,1fr))', gap: 8, marginTop: 12 }}>
                <input className="input" value={f.search} onChange={(e) => setF({ ...f, search: e.target.value })} placeholder="🔍 جست‌وجو (Enter)" onKeyDown={(e) => e.key === 'Enter' && apply()} />
                <select className="input" value={f.level} onChange={(e) => go({ ...f, level: e.target.value, grade: '', subject: '' })}>
                    <option value="">همه‌ی مقاطع</option>{cur.levels.map((l) => <option key={l} value={l}>{l}</option>)}
                </select>
                <select className="input" value={f.grade} onChange={(e) => go({ ...f, grade: e.target.value, subject: '' })} disabled={!f.level}>
                    <option value="">همه‌ی کلاس‌ها</option>{grades.map((g) => <option key={g} value={g}>{g}</option>)}
                </select>
                <select className="input" value={f.subject} onChange={(e) => go({ ...f, subject: e.target.value })} disabled={!f.grade}>
                    <option value="">همه‌ی درس‌ها</option>{subjects.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
                <select className="input" value={f.type} onChange={(e) => go({ ...f, type: e.target.value })}><option value="">همه انواع</option>{Object.entries(TYPE).map(([v, t]) => <option key={v} value={v}>{t}</option>)}</select>
                <select className="input" value={f.difficulty} onChange={(e) => go({ ...f, difficulty: e.target.value })}><option value="">همه سطوح</option>{Object.entries(DIFF).map(([v, t]) => <option key={v} value={v}>{t}</option>)}</select>
                <button onClick={reset} className="btn btn-ghost btn-sm">پاک‌کردن</button>
            </div>

            <div style={{ marginTop: 14 }}>
                {grouped.length === 0 && <p style={{ color: 'var(--muted)' }}>سؤالی یافت نشد. از تبِ «ساخت سؤال» شروع کنید.</p>}
                {grouped.map((lv) => <LevelGroup key={lv.level} lv={lv} />)}
            </div>
        </>
    );
}

function LevelGroup({ lv }) {
    const [open, setOpen] = useState(true);
    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 14, marginBottom: 12, overflow: 'hidden' }}>
            <button onClick={() => setOpen(!open)} style={{ width: '100%', textAlign: 'start', border: 0, cursor: 'pointer', background: 'linear-gradient(135deg,#16264f,#0a1836)', color: '#fff', padding: '11px 14px', fontWeight: 800, fontSize: 15, display: 'flex', alignItems: 'center', gap: 8 }}>
                <span>{open ? '▾' : '▸'}</span> 🎓 مقطع: {lv.level}
                <span className="tag" style={{ background: 'rgba(255,255,255,.2)', color: '#fff', marginInlineStart: 'auto' }}>{fa(lv.count)} سؤال</span>
            </button>
            {open && (
                <div style={{ padding: 12 }}>
                    {lv.grades.map((g) => (
                        <div key={g.grade} style={{ marginBottom: 10 }}>
                            <div style={{ fontWeight: 800, color: 'var(--navy-800)', fontSize: 14, margin: '4px 0 6px', borderInlineStart: '4px solid var(--gold)', paddingInlineStart: 8 }}>📘 کلاس {g.grade}</div>
                            {g.subjects.map((s) => <SubjectGroup key={s.subject} s={s} />)}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function SubjectGroup({ s }) {
    const [open, setOpen] = useState(true);
    const count = (s.lessons || []).reduce((n, l) => n + l.items.length, 0);
    return (
        <div style={{ marginInlineStart: 10, marginBottom: 8 }}>
            <button onClick={() => setOpen(!open)} style={{ border: 0, background: '#eef3ff', color: '#2555c0', borderRadius: 10, padding: '5px 12px', cursor: 'pointer', fontWeight: 700, fontSize: 13, display: 'flex', alignItems: 'center', gap: 6 }}>
                {open ? '▾' : '▸'} 📖 {s.subject} <span style={{ opacity: .7 }}>({fa(count)})</span>
            </button>
            {open && (s.lessons || []).map((l) => (
                <div key={l.lesson_no} style={{ marginInlineStart: 12, marginTop: 8 }}>
                    <div style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--muted)', marginBottom: 6 }}>📑 {l.lesson_no === '—' ? 'بدون شماره درس' : `درس ${l.lesson_no}`} <span style={{ opacity: .7 }}>({fa(l.items.length)})</span></div>
                    <div style={{ display: 'grid', gap: 8 }}>{l.items.map((q) => <BankRow key={q.id} q={q} />)}</div>
                </div>
            ))}
        </div>
    );
}

/* ───────── ساخت سؤال: آبشاریِ مقطع→کلاس→درس→شماره درس + AI + سؤال دستی + عکس ───────── */
function AddTab({ isSuper, cur, aiOn, onDone }) {
    const add = useForm({ level: '', grade: '', subject: '', lesson_no: '', topic: '', scope: isSuper ? 'global' : 'school', questions: [blankQ()] });
    const grades = cur.gradesOf(add.data.level);
    const subjects = cur.subjectsOf(add.data.level, add.data.grade);

    const setQ = (i, k, v) => { const qs = [...add.data.questions]; qs[i] = { ...qs[i], [k]: v }; add.setData('questions', qs); };
    const setCh = (qi, ci, v) => { const qs = [...add.data.questions]; qs[qi].choices[ci].value = v; add.setData('questions', [...qs]); };
    const setCorrect = (qi, ci) => { const qs = [...add.data.questions]; qs[qi].choices = qs[qi].choices.map((c, j) => ({ ...c, correct: j === ci })); add.setData('questions', [...qs]); };
    const submitAdd = () => add.post(route('bank.store'), { preserveScroll: true, forceFormData: true, onSuccess: () => { add.reset(); add.setData('questions', [blankQ()]); onDone(); } });

    const [ai, setAi] = useState({ count: 5, type: 'mc', difficulty: 'medium', sample: false });
    const [aiBusy, setAiBusy] = useState(false); const [aiMsg, setAiMsg] = useState(null); const [aiRes, setAiRes] = useState([]);
    const runAi = async () => {
        setAiBusy(true); setAiMsg(null); setAiRes([]);
        try {
            const { data } = await axios.post(route('bank.ai'), { ...ai, subject: add.data.subject, topic: add.data.topic || add.data.subject, grade: add.data.grade });
            setAiMsg({ ok: data.ok, text: data.message }); if (data.ok) setAiRes(data.questions || []);
        } catch (e) { setAiMsg({ ok: false, text: e.response?.data?.message || 'خطا' }); }
        setAiBusy(false);
    };
    const addAiToList = () => { add.setData('questions', [...add.data.questions.filter((q) => q.prompt.trim()), ...aiRes.map((q) => ({ ...q, image: null, source: 'ai' }))]); setAiRes([]); setAiMsg(null); };

    const catReady = add.data.level && add.data.grade && add.data.subject;

    return (
        <div style={{ marginTop: 14 }}>
            <div style={{ background: '#f6f8fc', border: '1px solid var(--line)', borderRadius: 12, padding: 12 }}>
                <b style={{ fontSize: 13.5 }}>① دسته‌بندی (مقطع ← کلاس ← درس ← شماره درس)</b>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(140px,1fr))', gap: 10, marginTop: 8 }}>
                    <div className="field" style={{ margin: 0 }}><label>مقطع</label>
                        <select className="input" value={add.data.level} onChange={(e) => { add.setData('level', e.target.value); add.setData('grade', ''); add.setData('subject', ''); }}>
                            <option value="">— انتخاب —</option>{cur.levels.map((l) => <option key={l} value={l}>{l}</option>)}
                        </select></div>
                    <div className="field" style={{ margin: 0 }}><label>کلاس</label>
                        <select className="input" value={add.data.grade} onChange={(e) => { add.setData('grade', e.target.value); add.setData('subject', ''); }} disabled={!add.data.level}>
                            <option value="">— انتخاب —</option>{grades.map((g) => <option key={g} value={g}>{g}</option>)}
                        </select></div>
                    <div className="field" style={{ margin: 0 }}><label>درس</label>
                        <select className="input" value={add.data.subject} onChange={(e) => add.setData('subject', e.target.value)} disabled={!add.data.grade}>
                            <option value="">— انتخاب —</option>{subjects.map((s) => <option key={s} value={s}>{s}</option>)}
                        </select></div>
                    <div className="field" style={{ margin: 0 }}><label>شماره درس</label><input className="input" value={add.data.lesson_no} onChange={(e) => add.setData('lesson_no', e.target.value)} placeholder="مثلاً: ۳" dir="ltr" /></div>
                    <div className="field" style={{ margin: 0 }}><label>موضوع سؤال (اختیاری)</label><input className="input" value={add.data.topic} onChange={(e) => add.setData('topic', e.target.value)} placeholder="مثلاً: ضرب" /></div>
                    {isSuper && <div className="field" style={{ margin: 0 }}><label>دامنه</label><select className="input" value={add.data.scope} onChange={(e) => add.setData('scope', e.target.value)}><option value="global">سراسری (همه)</option><option value="school">فقط مدرسه</option></select></div>}
                </div>
                {!catReady && <div style={{ fontSize: 12, color: '#b0333f', marginTop: 6 }}>ابتدا مقطع، کلاس و درس را انتخاب کنید.</div>}
            </div>

            <div style={{ border: '1px solid #ddd6fe', borderRadius: 12, padding: 12, marginTop: 12, background: '#f5f3ff', opacity: catReady ? 1 : .55, pointerEvents: catReady ? 'auto' : 'none' }}>
                <b style={{ fontSize: 13.5 }}>② 🤖 ساخت سؤال با هوش مصنوعی {!aiOn && <span style={{ fontSize: 11, color: '#b0333f' }}>(کلید AI تنظیم نشده — «نمونه» را بزنید)</span>}</b>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'end', marginTop: 8 }}>
                    <div className="field" style={{ margin: 0 }}><label>تعداد</label><input type="number" min={1} max={20} className="input" style={{ width: 80 }} value={ai.count} onChange={(e) => setAi({ ...ai, count: +e.target.value })} dir="ltr" /></div>
                    <div className="field" style={{ margin: 0 }}><label>نوع</label><select className="input" value={ai.type} onChange={(e) => setAi({ ...ai, type: e.target.value })}><option value="mc">چهارگزینه‌ای</option><option value="tf">درست/نادرست</option><option value="desc">تشریحی</option></select></div>
                    <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 13 }}><input type="checkbox" checked={ai.sample} onChange={(e) => setAi({ ...ai, sample: e.target.checked })} /> حالت نمونه</label>
                    <button type="button" onClick={runAi} disabled={aiBusy || !add.data.subject} className="btn btn-sm">{aiBusy ? '…' : '✨ تولید'}</button>
                </div>
                {aiMsg && <div style={{ marginTop: 8, fontSize: 12.5, color: aiMsg.ok ? '#166534' : '#b91c1c', fontWeight: 700 }}>{aiMsg.text}</div>}
                {aiRes.length > 0 && <div style={{ marginTop: 8 }}>{aiRes.map((q, i) => <div key={i} style={{ fontSize: 13, padding: '3px 0' }}>• {q.prompt}</div>)}<button type="button" onClick={addAiToList} className="btn btn-sm" style={{ marginTop: 6 }}>➕ افزودن به فهرست</button></div>}
            </div>

            <div style={{ marginTop: 12, opacity: catReady ? 1 : .55, pointerEvents: catReady ? 'auto' : 'none' }}>
                <b style={{ fontSize: 13.5 }}>③ سؤال‌ها</b>
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
                        {/* افزودن عکس به سؤال */}
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginTop: 8, flexWrap: 'wrap' }}>
                            <label style={{ fontSize: 12.5, color: 'var(--muted)' }}>🖼️ عکس سؤال (اختیاری):</label>
                            <input type="file" accept="image/*" className="input" style={{ padding: 6, flex: 1, minWidth: 180 }} onChange={(e) => setQ(qi, 'image', e.target.files[0] || null)} />
                            {q.image && <span style={{ fontSize: 12, color: '#166534' }}>✓ {q.image.name}</span>}
                        </div>
                    </div>
                ))}
                <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
                    <button onClick={() => add.setData('questions', [...add.data.questions, blankQ()])} className="btn btn-ghost btn-sm">+ سؤال دستی</button>
                    <button onClick={submitAdd} disabled={add.processing || !catReady} className="btn" style={{ marginInlineStart: 'auto' }}>💾 ذخیره در بانک</button>
                </div>
            </div>
        </div>
    );
}

/* ───────── اشتراک‌گذاریِ دقیق: مقطع/کلاس/درس → مدرسه ───────── */
function ShareTab({ share, cur }) {
    const g = useForm({ school_id: '', level: '', grade: '', subject: '' });
    const grades = cur.gradesOf(g.data.level);
    const subjects = cur.subjectsOf(g.data.level, g.data.grade);
    const submit = () => { if (!g.data.school_id) return; g.post(route('bank.share'), { preserveScroll: true, onSuccess: () => g.setData('subject', '') }); };
    const remove = (id) => router.delete(route('bank.unshare', id), { preserveScroll: true });

    return (
        <div style={{ marginTop: 14 }}>
            <p style={{ color: 'var(--muted)', fontSize: 13.5 }}>
                تعیین کنید بانکِ سؤالاتِ سراسری در چه <b>مقطع/کلاس/درسی</b> برای چه <b>مدرسه‌ای</b> در دسترس باشد.
                هر فیلدی که خالی بماند یعنی «همه». پس از افزودن، معلمانِ آن مدرسه بنا به پایه‌ی تدریسیِ خود به این درس‌ها دسترسی می‌یابند.
            </p>
            <div style={{ background: '#f6f8fc', border: '1px solid var(--line)', borderRadius: 12, padding: 12 }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10 }}>
                    <div className="field" style={{ margin: 0 }}><label>مدرسه *</label>
                        <select className="input" value={g.data.school_id} onChange={(e) => g.setData('school_id', e.target.value)}>
                            <option value="">— انتخاب مدرسه —</option>{share.allSchools.map((s) => <option key={s.id} value={s.id}>{s.name}{s.level ? ` (${s.level})` : ''}</option>)}
                        </select></div>
                    <div className="field" style={{ margin: 0 }}><label>مقطع</label>
                        <select className="input" value={g.data.level} onChange={(e) => { g.setData('level', e.target.value); g.setData('grade', ''); g.setData('subject', ''); }}>
                            <option value="">همه‌ی مقاطع</option>{cur.levels.map((l) => <option key={l} value={l}>{l}</option>)}
                        </select></div>
                    <div className="field" style={{ margin: 0 }}><label>کلاس</label>
                        <select className="input" value={g.data.grade} onChange={(e) => { g.setData('grade', e.target.value); g.setData('subject', ''); }} disabled={!g.data.level}>
                            <option value="">همه‌ی کلاس‌ها</option>{grades.map((x) => <option key={x} value={x}>{x}</option>)}
                        </select></div>
                    <div className="field" style={{ margin: 0 }}><label>درس</label>
                        <select className="input" value={g.data.subject} onChange={(e) => g.setData('subject', e.target.value)} disabled={!g.data.grade}>
                            <option value="">همه‌ی درس‌ها</option>{subjects.map((x) => <option key={x} value={x}>{x}</option>)}
                        </select></div>
                </div>
                <button onClick={submit} disabled={!g.data.school_id || g.processing} className="btn" style={{ marginTop: 10 }}>➕ افزودن دسترسی</button>
            </div>
            <div style={{ marginTop: 14 }}>
                <b style={{ fontSize: 13.5 }}>دسترسی‌های فعال ({fa(share.grants.length)})</b>
                {share.grants.length === 0 && <p style={{ color: 'var(--muted)', fontSize: 13 }}>هنوز دسترسی‌ای تعریف نشده است.</p>}
                <div style={{ display: 'grid', gap: 8, marginTop: 8 }}>
                    {share.grants.map((gr) => (
                        <div key={gr.id} style={{ display: 'flex', alignItems: 'center', gap: 8, border: '1px solid var(--line)', borderRadius: 10, padding: '9px 12px', flexWrap: 'wrap' }}>
                            <span style={{ fontWeight: 800 }}>🏫 {gr.school}</span>
                            <span className="tag tag-info">{gr.level || 'همه‌ی مقاطع'}</span>
                            <span className="tag" style={{ background: '#eef3ff', color: '#2555c0' }}>{gr.grade || 'همه‌ی کلاس‌ها'}</span>
                            <span className="tag" style={{ background: '#e6f7ee', color: '#1a8a52' }}>{gr.subject || 'همه‌ی درس‌ها'}</span>
                            <button onClick={() => remove(gr.id)} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto', color: '#e8505b' }}>حذف دسترسی</button>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

/* ───────── ردیفِ سؤال با انتخاب/ویرایش/حذف + عکس + طراح ───────── */
function BankRow({ q }) {
    const { isSuper, sel, toggle } = useContext(SelCtx);
    const [edit, setEdit] = useState(false);
    const form = useForm({ prompt: q.prompt, choices: q.choices || [], explanation: q.explanation || '', subject: q.subject || '', grade: q.grade || '', level: q.level || '', topic: q.topic || '', difficulty: q.difficulty || 'medium' });
    const save = () => form.put(route('bank.update', q.id), { preserveScroll: true, onSuccess: () => setEdit(false) });
    const selected = sel.has(q.id);
    return (
        <div style={{ border: `1px solid ${selected ? '#e8505b' : 'var(--line)'}`, borderRadius: 12, padding: 12, background: selected ? '#fff5f5' : '#fff' }}>
            {edit ? (
                <div style={{ display: 'grid', gap: 8 }}>
                    <textarea className="input" rows={2} value={form.data.prompt} onChange={(e) => form.setData('prompt', e.target.value)} />
                    {(form.data.choices || []).map((c, ci) => (
                        <div key={ci} style={{ display: 'flex', gap: 8 }}>
                            <button onClick={() => form.setData('choices', form.data.choices.map((x, j) => ({ ...x, correct: j === ci })))} className="btn btn-sm" style={{ background: c.correct ? '#2bb673' : '#eef2f8', color: c.correct ? '#fff' : '#6b7794', border: 0 }}>{c.correct ? '✓' : '○'}</button>
                            <input className="input" value={c.value} onChange={(e) => { const ch = [...form.data.choices]; ch[ci] = { ...ch[ci], value: e.target.value }; form.setData('choices', ch); }} />
                        </div>
                    ))}
                    <input className="input" value={form.data.topic} onChange={(e) => form.setData('topic', e.target.value)} placeholder="موضوع سؤال" />
                    <input className="input" value={form.data.explanation} onChange={(e) => form.setData('explanation', e.target.value)} placeholder="توضیح آموزشی" />
                    <div style={{ display: 'flex', gap: 6 }}><button onClick={save} className="btn btn-sm">💾 ذخیره</button><button onClick={() => setEdit(false)} className="btn btn-ghost btn-sm">انصراف</button></div>
                </div>
            ) : (
                <>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap', marginBottom: 4 }}>
                        {isSuper && <input type="checkbox" checked={selected} onChange={() => toggle(q.id)} title="انتخاب برای حذف گروهی" />}
                        <span className="tag" style={{ background: q.source === 'ai' ? '#ede9fe' : '#e0f2fe', color: q.source === 'ai' ? '#6d28d9' : '#0369a1', fontSize: 11 }}>{q.source === 'ai' ? 'AI' : q.source === 'sample' ? 'نمونه' : 'دستی'}</span>
                        <span style={{ fontSize: 12, color: 'var(--muted)' }}>{TYPE[q.type]} · {DIFF[q.difficulty]}{q.topic ? ` · موضوع: ${q.topic}` : ''}</span>
                        {q.author && <span style={{ fontSize: 11.5, color: 'var(--muted-2)' }}>👤 طراح: {q.author}</span>}
                        {q.can_edit && (
                            <span style={{ marginInlineStart: 'auto', display: 'flex', gap: 4 }}>
                                <button onClick={() => setEdit(true)} className="btn btn-ghost btn-sm">✏️</button>
                                <button onClick={() => confirm('این سؤال حذف شود؟') && router.delete(route('bank.destroy', q.id), { preserveScroll: true })} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
                            </span>
                        )}
                    </div>
                    <b style={{ fontSize: 14 }}>{q.prompt}</b>
                    {q.media && <div style={{ marginTop: 6 }}><img src={q.media} alt="" style={{ maxWidth: '100%', maxHeight: 180, borderRadius: 8, border: '1px solid var(--line)' }} /></div>}
                    {(q.choices || []).length > 0 && <div style={{ fontSize: 12.5, color: 'var(--muted)', marginTop: 3 }}>{(q.choices || []).map((c) => (c.correct ? '✅ ' : '▫️ ') + c.value).join('   ')}</div>}
                </>
            )}
        </div>
    );
}
