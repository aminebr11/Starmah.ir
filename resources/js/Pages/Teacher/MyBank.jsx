import { usePage, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import CurriculumFields from '@/Components/Questions/CurriculumFields';
import { QuestionCard } from '@/Components/Questions/AiQuestionPanel';
import { fa, TYPE_FA, DIFF_FA, SOURCE_FA } from '@/Components/Questions/shared';

/**
 * بانکِ سؤالاتِ معلم — هر سؤالی که در آزمون، بازی، کاربرگ یا مأموریت ساخته
 * شده (دستی یا با هوش مصنوعی) اینجا زیرِ پایه ← درس ← فصل پیدا می‌شود.
 * سؤال‌های قدیمیِ «بدونِ فصل» را می‌شود گروهی به فصلِ درست منتقل کرد.
 */
export default function MyBank() {
    const { tree = [], classes = [], total = 0, mine = 0, ai = 0, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [f, setF] = useState({ grade: '', subject: '', chapter_id: '', chapter: '', uncategorized: false, search: '', type: '', difficulty: '', source: '', mine: false });
    const [rows, setRows] = useState([]);
    const [count, setCount] = useState(0);
    const [busy, setBusy] = useState(false);
    const [open, setOpen] = useState({});
    const [sel, setSel] = useState({});
    const [edit, setEdit] = useState(null);
    const [moveCtx, setMoveCtx] = useState(null);

    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const load = async (x = f, offset = 0) => {
        setBusy(true);
        try {
            const { data } = await axios.get(route('teacher.mybank.list'), {
                params: {
                    grade: x.grade || undefined, subject: x.subject || undefined, chapter_id: x.chapter_id || undefined,
                    chapter: x.chapter || undefined, uncategorized: x.uncategorized ? 1 : undefined,
                    search: x.search || undefined, types: x.type ? [x.type] : undefined, difficulty: x.difficulty || undefined,
                    source: x.source || undefined, mine: x.mine ? 1 : undefined, offset,
                },
            });
            setRows((r) => (offset ? [...r, ...data.questions] : data.questions));
            setCount(data.total);
        } catch (e) { setRows([]); setCount(0); }
        setBusy(false);
    };
    useEffect(() => { load(); /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, []);

    const apply = (patch) => { const n = { ...f, ...patch }; setF(n); setSel({}); load(n); };
    const node = (grade, subject, ch) => apply({
        grade: grade || '', subject: subject || '', chapter_id: ch?.id || '',
        chapter: ch && !ch.id ? (ch.chapter || '') : '', uncategorized: !!ch && !ch.id && !ch.chapter,
    });
    const reload = () => router.reload({ only: ['tree', 'total', 'mine', 'ai'], onSuccess: () => load(f) });

    const chosen = rows.filter((r) => sel[r.id] && r.mine);
    const del = (r) => { if (confirm('این سؤال از بانک حذف شود؟ (آزمون‌ها و بازی‌هایی که از آن استفاده کرده‌اند دست‌نخورده می‌مانند)')) router.delete(route('teacher.mybank.destroy', r.id), { preserveScroll: true, onSuccess: reload }); };
    const saveEdit = () => router.put(route('teacher.mybank.update', edit.id), edit, { preserveScroll: true, onSuccess: () => { setEdit(null); reload(); } });
    const doMove = () => router.post(route('teacher.mybank.move'), { ids: chosen.map((r) => r.id), ...moveCtx }, { preserveScroll: true, onSuccess: () => { setMoveCtx(null); setSel({}); reload(); } });

    const on = (g, s, c) => f.grade === (g || '') && f.subject === (s || '')
        && (c === undefined ? (!f.chapter_id && !f.chapter && !f.uncategorized) : (c.id ? String(f.chapter_id) === String(c.id) : (c.chapter ? f.chapter === c.chapter : f.uncategorized)));

    return (
        <DashLayout title="بانکِ سؤالاتِ من" roleLabel="معلم" menu={teacherMenu} active="mybank">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div className="dash-cards" style={{ gridTemplateColumns: 'repeat(3,1fr)' }}>
                <div className="dcard"><div className="ic" style={{ background: '#e0f2fe' }}>🗄️</div><div className="lbl">سؤالِ در دسترس</div><div className="val">{fa(total)}</div></div>
                <div className="dcard"><div className="ic" style={{ background: '#fef3c7' }}>✍️</div><div className="lbl">ساخته‌ی خودم</div><div className="val">{fa(mine)}</div></div>
                <div className="dcard"><div className="ic" style={{ background: '#ede9fe' }}>🤖</div><div className="lbl">طراحی با هوش مصنوعی</div><div className="val">{fa(ai)}</div></div>
            </div>

            <div className="panel qk-panel qk-bank" style={{ padding: 16 }}>
                <div className="qk-bank-body">
                    <aside className="qk-tree" style={{ maxHeight: 640 }}>
                        <button type="button" className={`qk-node ${!f.grade ? 'on' : ''}`} onClick={() => node()}>همه‌ی سؤال‌ها <small>{fa(total)}</small></button>
                        {tree.map((g) => (
                            <div key={g.grade}>
                                <button type="button" className={`qk-node lvl1 ${on(g.grade) ? 'on' : ''}`} onClick={() => { setOpen((o) => ({ ...o, [g.grade]: !o[g.grade] })); node(g.grade); }}>
                                    {open[g.grade] ? '▾' : '▸'} پایه‌ی {g.grade} <small>{fa(g.count)}</small>
                                </button>
                                {open[g.grade] && g.subjects.map((s) => {
                                    const k = `${g.grade}|${s.subject}`;
                                    return (
                                        <div key={k}>
                                            <button type="button" className={`qk-node lvl2 ${on(g.grade, s.subject) ? 'on' : ''}`} onClick={() => { setOpen((o) => ({ ...o, [k]: !o[k] })); node(g.grade, s.subject); }}>
                                                {open[k] ? '▾' : '▸'} {s.subject} <small>{fa(s.count)}</small>
                                            </button>
                                            {open[k] && s.chapters.map((c, i) => (
                                                <button type="button" key={i} className={`qk-node lvl3 ${on(g.grade, s.subject, c) ? 'on' : ''}`} onClick={() => node(g.grade, s.subject, c)}>
                                                    {c.label} <small>{fa(c.count)}</small>
                                                </button>
                                            ))}
                                        </div>
                                    );
                                })}
                            </div>
                        ))}
                    </aside>

                    <div className="qk-list">
                        <div className="qk-filters">
                            <input className="qk-input" placeholder="🔍 جست‌وجو در متنِ سؤال…" value={f.search} onChange={(e) => setF({ ...f, search: e.target.value })} onKeyDown={(e) => e.key === 'Enter' && apply({})} />
                            <select className="qk-input" value={f.type} onChange={(e) => apply({ type: e.target.value })}>
                                <option value="">همه‌ی نوع‌ها</option>{['mc', 'tf', 'blank', 'desc'].map((t) => <option key={t} value={t}>{TYPE_FA[t]}</option>)}
                            </select>
                            <select className="qk-input" value={f.difficulty} onChange={(e) => apply({ difficulty: e.target.value })}>
                                <option value="">همه‌ی سطح‌ها</option>{['easy', 'medium', 'hard'].map((d) => <option key={d} value={d}>{DIFF_FA[d]}</option>)}
                            </select>
                            <select className="qk-input" value={f.source} onChange={(e) => apply({ source: e.target.value })}>
                                <option value="">همه‌ی منبع‌ها</option>{['ai', 'manual', 'worksheet'].map((s) => <option key={s} value={s}>{SOURCE_FA[s]}</option>)}
                            </select>
                            <label className="qk-check" style={{ whiteSpace: 'nowrap' }}><input type="checkbox" checked={f.mine} onChange={(e) => apply({ mine: e.target.checked })} /> فقط مالِ خودم</label>
                        </div>

                        {chosen.length > 0 && (
                            <div className="qk-note soft" style={{ marginTop: 0, marginBottom: 8 }}>
                                {fa(chosen.length)} سؤال انتخاب شده —
                                <button type="button" className="qk-link" onClick={() => setMoveCtx({ classroom_id: '', subject: f.subject, chapter_id: '', topic: '' })}> 📂 انتقال به پایه/درس/فصل</button>
                            </div>
                        )}
                        {moveCtx && (
                            <div className="qk-panel" style={{ background: '#fff' }}>
                                <b style={{ fontSize: 13.5 }}>📂 انتقالِ {fa(chosen.length)} سؤال به:</b>
                                <div style={{ marginTop: 8 }}><CurriculumFields classes={classes} value={moveCtx} onChange={(p) => setMoveCtx((m) => ({ ...m, ...p }))} showGoal={false} /></div>
                                <div className="qk-actions">
                                    <button type="button" className="qk-btn" onClick={doMove} disabled={!moveCtx.subject}>انتقال</button>
                                    <button type="button" className="qk-btn ghost" onClick={() => setMoveCtx(null)}>انصراف</button>
                                </div>
                            </div>
                        )}

                        <div className="qk-scroll" style={{ maxHeight: 620 }}>
                            {!busy && rows.length === 0 && <div className="qk-empty">سؤالی پیدا نشد. سؤال‌هایی که در آزمون‌ساز، استودیوی بازی یا کاربرگ می‌سازید خودکار اینجا ثبت می‌شوند.</div>}
                            {rows.map((r) => edit?.id === r.id ? (
                                <EditCard key={r.id} q={edit} setQ={setEdit} classes={classes} onSave={saveEdit} onCancel={() => setEdit(null)} />
                            ) : (
                                <div key={r.id} style={{ position: 'relative' }}>
                                    <QuestionCard q={r} picked={!!sel[r.id]} onToggle={() => r.mine && setSel((x) => ({ ...x, [r.id]: !x[r.id] }))}
                                        meta={[r.grade && `پایه‌ی ${r.grade}`, r.subject, r.chapter, r.author && `✍️ ${r.author}`, SOURCE_FA[r.source], r.used > 1 && `${fa(r.used)} بار استفاده`].filter(Boolean).join(' · ')} />
                                    {r.mine && (
                                        <div style={{ position: 'absolute', top: 8, insetInlineEnd: 10, display: 'flex', gap: 4 }}>
                                            <button type="button" className="qk-btn ghost sm" onClick={() => setEdit({ ...r, classroom_id: '', answer: r.answer || '' })}>✏️</button>
                                            <button type="button" className="qk-btn ghost sm" style={{ color: '#dc2626' }} onClick={() => del(r)}>🗑️</button>
                                        </div>
                                    )}
                                </div>
                            ))}
                            {busy && <div className="qk-muted" style={{ padding: 10 }}>در حالِ بارگذاری…</div>}
                        </div>
                        <div className="qk-actions">
                            <span className="qk-muted">{fa(rows.length)} از {fa(count)} سؤال</span>
                            {rows.length < count && <button type="button" className="qk-btn ghost sm" onClick={() => load(f, rows.length)} disabled={busy}>نمایشِ بیشتر</button>}
                        </div>
                    </div>
                </div>
            </div>
        </DashLayout>
    );
}

function EditCard({ q, setQ, classes, onSave, onCancel }) {
    const set = (k, v) => setQ({ ...q, [k]: v });
    const setChoice = (i, patch) => set('choices', q.choices.map((c, j) => (j === i ? { ...c, ...patch } : (patch.correct ? { ...c, correct: false } : c))));
    return (
        <div className="qk-panel" style={{ background: '#fff', borderColor: '#0ea5e9' }}>
            <div className="qk-grid">
                <label className="qk-field qk-wide"><span>متنِ سؤال</span>
                    <textarea className="qk-input" rows={2} value={q.prompt} onChange={(e) => set('prompt', e.target.value)} /></label>
                {(q.type === 'mc' || q.type === 'tf') ? (
                    <div className="qk-field qk-wide"><span>گزینه‌ها (دایره = پاسخِ درست)</span>
                        {q.choices.map((c, i) => (
                            <div key={i} className="qk-inline" style={{ marginBottom: 4 }}>
                                <button type="button" className={`qk-btn sm ${c.correct ? '' : 'ghost'}`} onClick={() => setChoice(i, { correct: true })}>{c.correct ? '✓' : '○'}</button>
                                <input className="qk-input" value={c.value} onChange={(e) => setChoice(i, { value: e.target.value })} disabled={q.type === 'tf'} />
                            </div>
                        ))}
                    </div>
                ) : (
                    <label className="qk-field qk-wide"><span>پاسخ</span><input className="qk-input" value={q.answer || ''} onChange={(e) => set('answer', e.target.value)} /></label>
                )}
                <label className="qk-field qk-wide"><span>توضیحِ آموزشی</span><input className="qk-input" value={q.explanation || ''} onChange={(e) => set('explanation', e.target.value)} /></label>
                <label className="qk-field"><span>راهنما</span><input className="qk-input" value={q.hint || ''} onChange={(e) => set('hint', e.target.value)} /></label>
                <label className="qk-field"><span>دشواری</span>
                    <select className="qk-input" value={q.difficulty || 'medium'} onChange={(e) => set('difficulty', e.target.value)}>
                        {['easy', 'medium', 'hard'].map((d) => <option key={d} value={d}>{DIFF_FA[d]}</option>)}
                    </select></label>
            </div>
            <div style={{ marginTop: 10 }}><CurriculumFields classes={classes} value={q} onChange={(p) => setQ((x) => ({ ...x, ...p }))} showGoal={false} /></div>
            <div className="qk-actions">
                <button type="button" className="qk-btn" onClick={onSave}>💾 ذخیره</button>
                <button type="button" className="qk-btn ghost" onClick={onCancel}>انصراف</button>
            </div>
        </div>
    );
}
