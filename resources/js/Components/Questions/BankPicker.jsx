import { useEffect, useState } from 'react';
import axios from 'axios';
import { fa, TYPE_FA, DIFF_FA, SOURCE_FA } from './shared';
import { QuestionCard } from './AiQuestionPanel';

/**
 * بازخوانی از بانکِ سؤالات — درختِ پایه ← درس ← فصل، با شمارش.
 *
 * پیش‌فرض روی همان پایه/درس/فصلِ فرم باز می‌شود، پس معلم اول سؤال‌های
 * همان فصل را می‌بیند. سؤال با همه‌ی جزئیاتش (توضیح، راهنما، دشواری،
 * سطحِ شناختی) و شناسه‌ی بانک برمی‌گردد تا پیوندش حفظ شود و دوباره در
 * بانک تکثیر نشود.
 *
 * props:
 *   endpoint     نشانیِ API (JSON: questions, tree)
 *   context      {grade, subject, chapter_id} — فیلترِ پیش‌فرض
 *   types        نوع‌های مجاز
 *   typeLabels
 *   existingIds  bank_idهای سؤال‌های فعلیِ فرم
 *   onAdd        (rows) => void
 */
export default function BankPicker({ endpoint, context = {}, types = ['mc', 'tf'], typeLabels = {}, existingIds = [], onAdd }) {
    const labels = { ...TYPE_FA, ...typeLabels };
    const [f, setF] = useState({
        grade: context.grade || '', subject: context.subject || '',
        chapter_id: context.chapter_id || '', chapter: '', uncategorized: false,
        search: '', type: '', difficulty: '', source: '',
    });
    const [tree, setTree] = useState([]);
    const [rows, setRows] = useState([]);
    const [pick, setPick] = useState({});
    const [busy, setBusy] = useState(false);
    const [open, setOpen] = useState({});

    const load = async (filters = f) => {
        setBusy(true);
        try {
            const params = {
                grade: filters.grade || undefined, subject: filters.subject || undefined,
                chapter_id: filters.chapter_id || undefined, chapter: filters.chapter || undefined,
                uncategorized: filters.uncategorized ? 1 : undefined,
                search: filters.search || undefined, difficulty: filters.difficulty || undefined,
                source: filters.source || undefined,
                types: filters.type ? [filters.type] : types,
            };
            const { data } = await axios.get(endpoint, { params });
            setRows(data.questions || []);
            setTree(data.tree || []);
        } catch (e) {
            setRows([]);
        }
        setBusy(false);
    };

    useEffect(() => { load(); /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, []);
    useEffect(() => {
        if (f.grade) setOpen((o) => ({ ...o, [f.grade]: true, [`${f.grade}|${f.subject}`]: true }));
    }, [f.grade, f.subject]);

    const apply = (patch) => { const n = { ...f, ...patch }; setF(n); setPick({}); load(n); };
    const selectNode = (grade, subject, ch) => apply({
        grade, subject: subject || '',
        chapter_id: ch?.id || '', chapter: ch && !ch.id ? (ch.chapter || '') : '',
        uncategorized: !!ch && !ch.id && !ch.chapter,
    });

    const chosen = rows.filter((r) => pick[r.id]);
    const add = () => { onAdd(chosen); setPick({}); };
    const inForm = (id) => existingIds.includes(id);
    const isNode = (g, s, ch) => f.grade === g && (s === undefined || f.subject === s)
        && (ch === undefined || (ch.id ? String(f.chapter_id) === String(ch.id) : (!f.chapter_id && (ch.chapter ? f.chapter === ch.chapter : f.uncategorized))));

    return (
        <div className="qk-panel qk-bank">
            <div className="qk-head">
                <b>🗄️ بانکِ سؤالات</b>
                <span className="qk-muted">سؤال‌های شما و همکارانِ مدرسه، دسته‌بندی‌شده بر اساسِ پایه، درس و فصل</span>
            </div>

            <div className="qk-bank-body">
                <aside className="qk-tree">
                    <button type="button" className={`qk-node ${!f.grade ? 'on' : ''}`} onClick={() => apply({ grade: '', subject: '', chapter_id: '', chapter: '', uncategorized: false })}>همه‌ی سؤال‌ها</button>
                    {tree.map((g) => (
                        <div key={g.grade}>
                            <button type="button" className={`qk-node lvl1 ${isNode(g.grade) && !f.subject ? 'on' : ''}`}
                                onClick={() => { setOpen((o) => ({ ...o, [g.grade]: !o[g.grade] })); selectNode(g.grade); }}>
                                {open[g.grade] ? '▾' : '▸'} پایه‌ی {g.grade} <small>{fa(g.count)}</small>
                            </button>
                            {open[g.grade] && g.subjects.map((s) => {
                                const k = `${g.grade}|${s.subject}`;
                                return (
                                    <div key={k}>
                                        <button type="button" className={`qk-node lvl2 ${isNode(g.grade, s.subject) && !f.chapter_id && !f.chapter && !f.uncategorized ? 'on' : ''}`}
                                            onClick={() => { setOpen((o) => ({ ...o, [k]: !o[k] })); selectNode(g.grade, s.subject); }}>
                                            {open[k] ? '▾' : '▸'} {s.subject} <small>{fa(s.count)}</small>
                                        </button>
                                        {open[k] && s.chapters.map((c, i) => (
                                            <button type="button" key={i} className={`qk-node lvl3 ${isNode(g.grade, s.subject, c) ? 'on' : ''}`}
                                                onClick={() => selectNode(g.grade, s.subject, c)}>
                                                {c.label} <small>{fa(c.count)}</small>
                                            </button>
                                        ))}
                                    </div>
                                );
                            })}
                        </div>
                    ))}
                    {!tree.length && !busy && <div className="qk-muted" style={{ padding: 8 }}>بانک هنوز خالی است.</div>}
                </aside>

                <div className="qk-list">
                    <div className="qk-filters">
                        <input className="qk-input" value={f.search} placeholder="🔍 جست‌وجو در متنِ سؤال…"
                            onChange={(e) => setF({ ...f, search: e.target.value })} onKeyDown={(e) => e.key === 'Enter' && apply({})} />
                        <select className="qk-input" value={f.type} onChange={(e) => apply({ type: e.target.value })}>
                            <option value="">همه‌ی نوع‌ها</option>
                            {types.map((t) => <option key={t} value={t}>{labels[t]}</option>)}
                        </select>
                        <select className="qk-input" value={f.difficulty} onChange={(e) => apply({ difficulty: e.target.value })}>
                            <option value="">همه‌ی سطح‌ها</option>
                            {['easy', 'medium', 'hard'].map((d) => <option key={d} value={d}>{DIFF_FA[d]}</option>)}
                        </select>
                        <select className="qk-input" value={f.source} onChange={(e) => apply({ source: e.target.value })}>
                            <option value="">همه‌ی منبع‌ها</option>
                            {['ai', 'manual', 'worksheet'].map((s) => <option key={s} value={s}>{SOURCE_FA[s]}</option>)}
                        </select>
                        <button type="button" className="qk-btn sm" onClick={() => apply({})}>{busy ? '…' : 'اعمال'}</button>
                    </div>

                    <div className="qk-scroll">
                        {busy && <div className="qk-muted" style={{ padding: 10 }}>در حالِ بارگذاری…</div>}
                        {!busy && rows.length === 0 && (
                            <div className="qk-empty">سؤالی با این فیلتر پیدا نشد.{f.chapter_id ? ' فصل را روی «همه» بگذارید یا با هوش مصنوعی برای این فصل سؤال بسازید.' : ''}</div>
                        )}
                        {!busy && rows.map((r) => (
                            <QuestionCard key={r.id} q={r} labels={labels} picked={!!pick[r.id]} disabled={inForm(r.id)}
                                onToggle={() => setPick((p) => ({ ...p, [r.id]: !p[r.id] }))}
                                meta={[r.grade && `پایه‌ی ${r.grade}`, r.subject, r.chapter, r.author && `✍️ ${r.author}`,
                                    r.source && SOURCE_FA[r.source], r.used > 1 && `${fa(r.used)} بار استفاده`].filter(Boolean).join(' · ')} />
                        ))}
                    </div>

                    <div className="qk-actions">
                        <button type="button" className="qk-btn" onClick={add} disabled={!chosen.length}>➕ افزودنِ {fa(chosen.length)} سؤال</button>
                        <span className="qk-muted">{fa(rows.length)} سؤال</span>
                    </div>
                </div>
            </div>
        </div>
    );
}
