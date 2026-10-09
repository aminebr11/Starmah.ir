import { useState } from 'react';
import SubmissionViewer from '@/Components/SubmissionViewer';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * فهرستِ کاربرگ‌های پرشده با عکسِ کوچک + فیلترِ «منتظرِ تصحیح / تصحیح‌شده» (و در صندوقِ کلی،
 * فیلترِ هر کاربرگ). بازکردنِ هر کدام نمایشگرِ تصحیح را باز می‌کند؛ ✕ دوباره به همین فهرست برمی‌گردد.
 */
export default function SubmissionsBoard({ initial = [], grades = {}, maxXp = 50, worksheets = null }) {
    const [list, setList] = useState(initial);
    const [open, setOpen] = useState(() => {
        // از اعلانِ زنگوله (?sub=…) همان کاربرگ مستقیم باز شود
        const id = typeof window !== 'undefined' ? Number(new URLSearchParams(window.location.search).get('sub')) : 0;
        const k = id ? initial.findIndex((x) => x.id === id) : -1;
        return k >= 0 ? { items: initial, index: k } : null;
    });
    const pendingAll = list.filter((s) => !s.graded).length;
    const [filter, setFilter] = useState(pendingAll > 0 && worksheets ? 'todo' : 'all');
    const [ws, setWs] = useState(0);

    const inWs = ws ? list.filter((s) => s.worksheet_id === ws) : list;
    const pending = inWs.filter((s) => !s.graded).length;
    const shown = filter === 'todo' ? inWs.filter((s) => !s.graded) : filter === 'done' ? inWs.filter((s) => s.graded) : inWs;

    const merge = (arr, sub) => arr.map((x) => (x.id === sub.id ? { ...x, ...sub } : x));
    // نمایشگر فهرستِ ثابتِ لحظه‌ی بازشدن را می‌گیرد (با فیلترِ «منتظرِ تصحیح» موردِ تصحیح‌شده از زیرِ دستش نپرد)
    const saved = (sub) => { setList((l) => merge(l, sub)); setOpen((o) => (o ? { ...o, items: merge(o.items, sub) } : o)); };

    if (!list.length) return <p style={{ color: 'var(--muted)' }}>هنوز کسی کاربرگِ پرشده نفرستاده است.</p>;

    return (
        <>
            <p style={{ color: 'var(--muted)', fontSize: 12.5, margin: '0 0 8px' }}>روی هر کاربرگ بزنید: همان‌جا باز می‌شود، روی برگه تیک/ضربدر بزنید و نمره و امتیاز بدهید. با ✕ به همین فهرست برمی‌گردید.</p>
            {worksheets && worksheets.length > 1 && (
                <div className="ws-filter">
                    <button type="button" className={!ws ? 'on' : ''} onClick={() => setWs(0)}>📚 همه‌ی کاربرگ‌ها</button>
                    {worksheets.map((w) => (
                        <button key={w.id} type="button" className={ws === w.id ? 'on' : ''} onClick={() => setWs(w.id)}>
                            {w.title} {w.pending > 0 ? `(⏳ ${fa(list.filter((s) => s.worksheet_id === w.id && !s.graded).length)})` : '✅'}
                        </button>
                    ))}
                </div>
            )}
            <div className="ws-filter">
                <button type="button" className={filter === 'todo' ? 'on' : ''} onClick={() => setFilter('todo')}>⏳ منتظرِ تصحیح ({fa(pending)})</button>
                <button type="button" className={filter === 'done' ? 'on' : ''} onClick={() => setFilter('done')}>✅ تصحیح‌شده ({fa(inWs.length - pending)})</button>
                <button type="button" className={filter === 'all' ? 'on' : ''} onClick={() => setFilter('all')}>همه ({fa(inWs.length)})</button>
            </div>
            {shown.length === 0 ? (
                <p style={{ color: 'var(--muted)' }}>{filter === 'todo' ? '🎉 همه تصحیح شده‌اند.' : 'موردی نیست.'}</p>
            ) : (
                <div className="ws-subs">
                    {shown.map((s, k) => (
                        <button key={s.id} type="button" className="ws-sub" onClick={() => setOpen({ items: shown, index: k })}>
                            <div className="ws-sub-thumb" style={!s.pdf ? { backgroundImage: `url("${s.marked_url || s.url}")` } : undefined}>
                                {s.pdf && '📄'}
                                <span className={`ws-sub-badge ${s.graded ? 'done' : ''}`}>{s.graded ? `✅ ${s.grade || 'تصحیح شد'}` : '⏳ تصحیح نشده'}</span>
                            </div>
                            <div className="ws-sub-b">
                                <b>👤 {s.student}</b>
                                {s.worksheet && worksheets && <span style={{ display: 'block' }}>📄 {s.worksheet}</span>}
                                <span>{s.date}{s.graded && s.xp > 0 ? ` · ⚡ ${fa(s.xp)}` : ''}</span>
                            </div>
                        </button>
                    ))}
                </div>
            )}
            {open && (
                <SubmissionViewer items={open.items} index={open.index} canGrade grades={grades} maxXp={maxXp}
                    onSaved={saved} onClose={() => setOpen(null)} />
            )}
        </>
    );
}
