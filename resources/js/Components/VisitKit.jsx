import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import PersonCell from '@/Components/PersonCell';
import { useSort, SortTh, SortBar, firstName, lastName } from '@/lib/useSort';
import { PAL, OK, WARN, CRIT } from '@/Components/Charts';

/**
 * قطعه‌های مشترکِ گزارشِ بازدید و مشارکت — ادمینِ کل، مدیرِ مدرسه و معلم.
 */
export const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
export const tone = (v) => (v == null ? 'var(--muted)' : v >= 70 ? OK : v >= 40 ? WARN : CRIT);
export const mins = (m) => (m >= 60 ? `${fa(Math.floor(m / 60))} ساعت${m % 60 ? ` و ${fa(m % 60)} دقیقه` : ''}` : `${fa(m)} دقیقه`);

export function PeriodPicker({ days, periods = [7, 30, 90], extra = {} }) {
    return (
        <div className="vk-period" role="tablist" aria-label="بازه‌ی گزارش">
            {periods.map((p) => (
                <button key={p} type="button" className={p === days ? 'on' : ''}
                    onClick={() => router.get(window.location.pathname, { ...extra, days: p }, { preserveScroll: true, preserveState: false })}>
                    {fa(p)} روزِ اخیر
                </button>
            ))}
        </div>
    );
}

export function Kpi({ icon, label, value, sub, color, pulse }) {
    return (
        <div className="vk-kpi" style={color ? { '--vk': color } : undefined}>
            <div className="vk-kpi-ic">{pulse ? <i className="vk-live" /> : icon}</div>
            <div className="vk-kpi-b">
                <div className="vk-kpi-l">{label}</div>
                <div className="vk-kpi-v">{value}</div>
                {sub && <div className="vk-kpi-s">{sub}</div>}
            </div>
        </div>
    );
}

export function Dot({ on }) {
    return <i className={`vk-dot ${on ? 'on' : ''}`} title={on ? 'آنلاین' : 'آفلاین'} />;
}

export function Pct({ v, w = 70 }) {
    if (v == null) return <span style={{ color: 'var(--muted)', fontSize: 12 }}>—</span>;
    return (
        <span className="vk-pct">
            <span className="vk-pct-bar" style={{ width: w }}><i style={{ width: `${Math.min(100, v)}%`, background: tone(v) }} /></span>
            <b style={{ color: tone(v) }}>{fa(v)}٪</b>
        </span>
    );
}

/** ستون‌های روزانه: اعضا (+ مهمان‌ها) — با برچسبِ هاور. */
export function DailyBars({ data = [], guests = false }) {
    const [h, setH] = useState(null);
    const max = Math.max(1, ...data.map((d) => d.members + (guests ? d.guests : 0)));
    const step = data.length > 40 ? 7 : data.length > 10 ? 3 : 1;
    if (!data.length) return <div className="vk-empty">داده‌ای نیست</div>;
    return (
        <div className="vk-daily">
            <div className="vk-daily-bars" dir="ltr" onMouseLeave={() => setH(null)}>
                {data.map((d, i) => (
                    <div key={d.date} className={`vk-col ${h === i ? 'on' : ''}`} onMouseEnter={() => setH(i)} onClick={() => setH(i)}>
                        {guests && <i className="g" style={{ height: `${(d.guests / max) * 100}%` }} />}
                        <i className="m" style={{ height: `${(d.members / max) * 100}%` }} />
                    </div>
                ))}
            </div>
            <div className="vk-daily-x" dir="ltr">
                {data.map((d, i) => <span key={d.date}>{i % step === 0 || i === data.length - 1 ? d.label : ''}</span>)}
            </div>
            <div className="vk-daily-info">
                {h != null ? (
                    <><b>{data[h].label}</b> · {fa(data[h].members)} کاربر{guests && <> · {fa(data[h].guests)} مهمان</>} · {fa(data[h].hits)} صفحه</>
                ) : (
                    <span className="vk-legend"><i className="m" /> کاربرانِ واردشده {guests && <><i className="g" /> مهمان‌ها</>} <span style={{ color: 'var(--muted)' }}>— روی هر روز بزنید</span></span>
                )}
            </div>
        </div>
    );
}

/** شدتِ بازدید در ۲۴ ساعت. */
export function HoursStrip({ hours = [] }) {
    const max = Math.max(1, ...hours);
    const peak = hours.indexOf(Math.max(...hours));
    return (
        <div>
            <div className="vk-hours" dir="ltr">
                {hours.map((v, i) => (
                    <div key={i} title={`ساعتِ ${i}: ${v}`}>
                        <i style={{ height: `${Math.max(4, (v / max) * 100)}%`, opacity: v ? 0.35 + 0.65 * (v / max) : 0.15 }} />
                        <span>{i % 3 === 0 ? fa(i) : ''}</span>
                    </div>
                ))}
            </div>
            {hours.some(Boolean) && <div className="vk-note">🔥 شلوغ‌ترین ساعت: <b>{fa(peak)} تا {fa(peak + 1)}</b></div>}
        </div>
    );
}

/** رتبه‌بندیِ گروه‌ها (کلاس/تیم/مدرسه) بر اساسِ مشارکت. */
export function RankList({ items = [], title, unit = 'عضو', empty = 'هنوز داده‌ای نیست' }) {
    const rs = useSort(items, { score: 'score', label: 'label', active: 'active', tasks: 'tasks', minutes: 'minutes' }, { firstDir: { score: 'desc', active: 'desc', tasks: 'desc', minutes: 'desc' } });
    if (!items.length) return <div className="vk-empty">{empty}</div>;
    const medal = ['🥇', '🥈', '🥉'];
    return (
        <div className="vk-rank">
            {title && <div className="vk-rank-t">{title}</div>}
            {items.length > 2 && <SortBar s={rs} options={[['score', 'مشارکت'], ['label', 'نام'], ['active', 'فعال'], ['tasks', 'کار'], ['minutes', 'زمان']]} />}
            {rs.sorted.map((g) => [g, items.indexOf(g)]).map(([g, i]) => (
                <div key={i} className={`vk-rank-row ${i === 0 ? 'top' : ''}`}>
                    <span className="vk-rank-n">{medal[i] || fa(i + 1)}</span>
                    <div className="vk-rank-b">
                        <div className="vk-rank-name">{g.emoji} {g.label}</div>
                        <div className="vk-rank-sub">{fa(g.active)} از {fa(g.members)} {unit} فعال · {fa(g.tasks)} کار · {mins(g.minutes)}</div>
                        <div className="vk-rank-bar"><i style={{ width: `${g.score}%`, background: tone(g.score) }} /></div>
                    </div>
                    <div className="vk-rank-s">
                        <b style={{ color: tone(g.score) }}>{fa(g.score)}</b><small>امتیازِ مشارکت</small>
                    </div>
                </div>
            ))}
        </div>
    );
}

const STATUS = [
    ['', 'همه'], ['online', '🟢 آنلاین'], ['active', 'فعال در این بازه'], ['inactive', '💤 غیرفعالِ ۷ روز'], ['never', '🚫 هرگز وارد نشده'],
];

function statusOk(r, s) {
    if (!s) return true;
    if (s === 'online') return r.online;
    if (s === 'active') return r.days > 0;
    if (s === 'never') return r.never;
    if (s === 'inactive') return !r.online && (!r.last_seen || Date.parse(r.last_seen) < Date.now() - 7 * 864e5);
    return true;
}

/**
 * جدولِ افراد با جست‌وجو، فیلترِ وضعیت/کلاس و مرتب‌سازی با کلیک روی سرِ ستون.
 * columns: [{ k, t, render?, sort? , num? }]
 */
export function PeopleTable({ rows = [], columns = [], classOptions, initialSort = 'score', emptyText = 'کسی پیدا نشد.', subOf }) {
    const [q, setQ] = useState('');
    const [st, setSt] = useState('');
    const [cls, setCls] = useState('');
    const [open, setOpen] = useState(null);

    const filtered = useMemo(() => {
        const t = q.trim();
        return rows.filter((r) => (!t || r.name.includes(t)) && statusOk(r, st) && (!cls || String(r.class_id) === cls));
    }, [rows, q, st, cls]);

    // مرتب‌سازی با کلیک روی سرِ ستون — عددها اولِ کار نزولی، متن‌ها صعودی
    const TEXT_COLS = ['class', 'classes', 'children'];
    const getters = { name: (r) => firstName(r.name), family: (r) => lastName(r.name) };
    const firstDir = {};
    columns.forEach((c) => {
        if (c.sort === false) return;
        const key = c.sort || c.k;
        getters[key] = key;
        if (!TEXT_COLS.includes(key)) firstDir[key] = 'desc';
    });
    const srt = useSort(filtered, getters, { id: 'people-' + columns.map((c) => c.k).join('-'), key: initialSort, dir: 'desc', firstDir });
    const list = srt.sorted;

    const head = (c) => (c.sort === false
        ? <th key={c.k}>{c.t}</th>
        : <SortTh key={c.k} s={srt} k={c.sort || c.k}>{c.t}</SortTh>);

    return (
        <div>
            <div className="vk-filters">
                <input className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔍 جست‌وجوی نام…" />
                <select className="input" value={st} onChange={(e) => setSt(e.target.value)}>
                    {STATUS.map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                </select>
                {classOptions?.length > 1 && (
                    <select className="input" value={cls} onChange={(e) => setCls(e.target.value)}>
                        <option value="">همه‌ی کلاس‌ها</option>
                        {classOptions.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                )}
                <span className="vk-count">{fa(list.length)} نفر</span>
            </div>
            {rows.length > 1 && <SortBar s={srt} options={[['name', 'نام'], ['family', 'نام خانوادگی'], ...(getters[initialSort] ? [[initialSort, (columns.find((c) => (c.sort || c.k) === initialSort)?.t) || 'امتیاز']] : [])]} />}
            <div className="vk-tblwrap">
                <table className="tbl vk-tbl">
                    <thead><tr><th>#</th><SortTh s={srt} k="family">نام</SortTh>{columns.map(head)}</tr></thead>
                    <tbody>
                        {list.map((r, i) => (
                            <FragmentRow key={r.id} r={r} i={i} columns={columns} subOf={subOf} open={open === r.id} toggle={() => setOpen(open === r.id ? null : r.id)} />
                        ))}
                        {list.length === 0 && <tr><td colSpan={columns.length + 2} style={{ color: 'var(--muted)' }}>{emptyText}</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function FragmentRow({ r, i, columns, subOf, open, toggle }) {
    return (
        <>
            <tr className={subOf ? 'vk-click' : ''} onClick={subOf ? toggle : undefined}>
                <td>{fa(i + 1)}</td>
                <td>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                        <Dot on={r.online} />
                        <PersonCell name={r.name} avatar={r.avatar} size={28} />
                        {subOf && <span className="vk-more">{open ? '▴' : '▾'}</span>}
                    </div>
                </td>
                {columns.map((c) => <td key={c.k}>{c.render ? c.render(r) : fa(r[c.k] ?? '—')}</td>)}
            </tr>
            {open && subOf && <tr className="vk-sub"><td colSpan={columns.length + 2}>{subOf(r)}</td></tr>}
        </>
    );
}

/** کاشی‌های ریزِ جزئیات داخلِ ردیفِ باز. */
export function Chips({ items }) {
    return (
        <div className="vk-chips">
            {items.filter(Boolean).map(([ic, l, v], i) => (
                <span key={i} className="vk-chip"><span>{ic}</span>{l}<b>{typeof v === 'number' ? fa(v) : v}</b></span>
            ))}
        </div>
    );
}

/** کارت‌های «برترین‌ها» و «نیازمندِ پیگیری». */
export function Spotlight({ best = [], worst = [], bestTitle = '🌟 پرمشارکت‌ترین‌ها', worstTitle = '🔔 نیازمندِ پیگیری', worstHint }) {
    return (
        <div className="vk-spot">
            <div className="vk-spot-card good">
                <h4>{bestTitle}</h4>
                {best.length ? best.map((r, i) => (
                    <div key={r.id} className="vk-spot-row"><span>{['🥇', '🥈', '🥉', '٤', '٥'][i]}</span><PersonCell name={r.name} avatar={r.avatar} size={26} /><b style={{ color: tone(r.score) }}>{fa(r.score)}</b></div>
                )) : <div className="vk-empty">هنوز کسی نیست</div>}
            </div>
            <div className="vk-spot-card bad">
                <h4>{worstTitle}</h4>
                {worst.length ? worst.map((r) => (
                    <div key={r.id} className="vk-spot-row"><span>💤</span><PersonCell name={r.name} avatar={r.avatar} size={26} /><small>{r.last_seen_label}</small></div>
                )) : <div className="vk-empty">همه فعال‌اند 👏</div>}
                {worstHint && <div className="vk-note">{worstHint}</div>}
            </div>
        </div>
    );
}

export { PAL };

/* ---------- ستون‌های آماده ---------- */
const seen = (r) => <span style={{ color: r.online ? OK : 'inherit', fontWeight: r.online ? 800 : 400 }}>{r.last_seen_label}</span>;

export function studentColumns({ showClass = true } = {}) {
    return [
        showClass && { k: 'class', t: 'کلاس', render: (r) => r.class || '—' },
        { k: 'last_seen_label', t: 'آخرین حضور', sort: 'last_seen', render: seen },
        { k: 'days', t: 'روزهای حضور', render: (r) => <>{fa(r.days)} <small style={{ color: 'var(--muted)' }}>({fa(r.presence)}٪)</small></> },
        { k: 'hits', t: 'بازدیدِ صفحه' },
        { k: 'minutes', t: 'زمانِ فعال', render: (r) => mins(r.minutes) },
        { k: 'tasks', t: 'کارِ انجام‌شده' },
        { k: 'completion', t: 'انجامِ وظایف', render: (r) => <Pct v={r.completion} w={54} /> },
        { k: 'xp', t: 'امتیاز (XP)' },
        { k: 'score', t: 'مشارکت', render: (r) => <Pct v={r.score} w={54} /> },
    ].filter(Boolean);
}

export function studentDetail(r) {
    return (
        <Chips items={[
            ['🎯', 'مأموریت', r.missions], ['📅', 'روزهای مأموریت', r.mission_days], ['📚', 'محتوای دیده‌شده', r.contents],
            ['🎮', 'بازی', r.games], ['🧠', 'آزمون', r.exams], ['📝', 'کاربرگ', r.worksheets], ['📒', 'تکلیف', r.homework],
            ['✅', 'از وظایفِ این بازه', `${fa(r.completed ?? 0)} از ${fa(r.assigned ?? 0)}`],
            ['🔑', 'دفعاتِ ورود', r.logins], r.last_login && ['🕒', 'آخرین ورود', r.last_login],
            r.team && ['🏅', 'تیم', `${r.team_emoji || ''} ${r.team}`],
        ]} />
    );
}

export function parentColumns() {
    return [
        { k: 'children', t: 'فرزند', render: (r) => r.children || '—' },
        { k: 'last_seen_label', t: 'آخرین حضور', sort: 'last_seen', render: seen },
        { k: 'days', t: 'روزهای حضور', render: (r) => <>{fa(r.days)} <small style={{ color: 'var(--muted)' }}>({fa(r.presence)}٪)</small></> },
        { k: 'hits', t: 'بازدیدِ صفحه' },
        { k: 'minutes', t: 'زمانِ فعال', render: (r) => mins(r.minutes) },
        { k: 'logins', t: 'دفعاتِ ورود' },
    ];
}

export function teacherColumns() {
    return [
        { k: 'classes', t: 'کلاس', render: (r) => r.classes || '—' },
        { k: 'last_seen_label', t: 'آخرین حضور', sort: 'last_seen', render: seen },
        { k: 'days', t: 'روزهای حضور', render: (r) => <>{fa(r.days)} <small style={{ color: 'var(--muted)' }}>({fa(r.presence)}٪)</small></> },
        { k: 'minutes', t: 'زمانِ فعال', render: (r) => mins(r.minutes) },
        { k: 'produced', t: 'محتوا/بازی/آزمون' },
        { k: 'classwork', t: 'نمره و حضور' },
        { k: 'comms', t: 'پیام' },
        { k: 'student_rate', t: 'حضورِ شاگردان', render: (r) => <Pct v={r.students ? r.student_rate : null} w={50} /> },
        { k: 'student_completion', t: 'وظایفِ شاگردان', render: (r) => <Pct v={r.student_completion} w={50} /> },
        { k: 'score', t: 'عملکرد', render: (r) => <Pct v={r.score} w={54} /> },
    ];
}

export function teacherDetail(r) {
    return (
        <Chips items={[
            ['📚', 'محتوا', r.contents], ['🎯', 'مأموریت', r.missions], ['🎮', 'بازی', r.games], ['🧠', 'آزمون', r.exams],
            ['📝', 'کاربرگ', r.worksheets], ['📒', 'تکلیف', r.assignments], ['🏅', 'فعالیتِ کلاسی', r.activities],
            ['📔', 'ستونِ نمره', r.grades], ['✅', 'روزِ حضور و غیاب', r.attendance], ['⭐', 'ثبتِ انضباط', r.discipline],
            ['💬', 'پیام', r.messages], ['🔐', 'پیامِ محرمانه', r.notes],
            ['🎓', 'شاگردانِ فعال', `${fa(r.students_active)} از ${fa(r.students)}`], ['⚡', 'XPِ شاگردان', r.student_xp],
            ['🔑', 'دفعاتِ ورود', r.logins], r.last_login && ['🕒', 'آخرین ورود', r.last_login],
        ]} />
    );
}
