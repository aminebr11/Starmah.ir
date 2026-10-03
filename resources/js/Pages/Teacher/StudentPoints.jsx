import { usePage, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import { useSort, SortBar, SortTh, firstName, lastName } from '@/lib/useSort';
import PointsTrend from '@/Components/PointsTrend';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const QUICK = [5, 10, 20, -5, -10];

/** مدیریت امتیازاتِ دانش‌آموز — افزودن/کسر امتیاز، حذف ردیف، پاک‌کردن کل سابقه. */
export default function StudentPoints() {
    const { students = [], selected, ledger = [], rank = [], range = {}, weeks = [], months = [], yearLabel = '', classroom = '', trend = null, flash } = usePage().props;
    const [tab, setTab] = useState('manage'); // manage | rank
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const ss = useSort(students, { name: (r) => firstName(r.name), family: (r) => lastName(r.name), total: 'total' }, { id: 'teacher-points-students', firstDir: { total: 'desc' } });
    const ls = useSort(ledger, { amount: 'amount', date: 'date_raw', reason: 'reason' }, { id: 'teacher-points-ledger', firstDir: { amount: 'desc', date: 'desc' } });

    const keep = { preserveState: true, preserveScroll: true, only: ['students', 'selected', 'ledger', 'rank', 'range', 'trend', 'flash'] };
    const pick = (id) => router.get(route('teacher.points'), { student: id, ...rangeQuery }, keep);

    // ───── بازه‌ی گزارش ─────
    const [preset, setPreset] = useState(range.preset || 'week');
    const [from, setFrom] = useState(range.from || '');
    const [to, setTo] = useState(range.to || '');
    const rangeQuery = { range: range.preset, from: range.from, to: range.to };
    const applyRange = (p, f, t) => {
        setPreset(p); setFrom(f || ''); setTo(t || '');
        router.get(route('teacher.points'), { student: selected?.id || undefined, range: p, from: f || undefined, to: t || undefined }, keep);
    };
    const printUrl = `${route('teacher.points.print')}?range=${preset}${from ? `&from=${from}` : ''}${to ? `&to=${to}` : ''}`;

    const rs = useSort(rank, {
        rank: 'rank', name: (r) => firstName(r.name), family: (r) => lastName(r.name),
        xp: 'xp', plus: 'plus', minus: 'minus', entries: 'entries', team: 'team', last: 'last_raw',
    }, { key: 'rank', id: 'teacher-points-rank', firstDir: { xp: 'desc', plus: 'desc', minus: 'desc', entries: 'desc', last: 'desc' } });

    const form = useForm({ student_id: selected?.id || '', amount: 10, reason: '' });
    useEffect(() => { form.setData('student_id', selected?.id || ''); }, [selected?.id]);
    const submit = (amt) => {
        const amount = amt ?? form.data.amount;
        if (!selected || !amount) return;
        router.post(route('teacher.points.adjust'), { student_id: selected.id, amount, reason: form.data.reason || (amount >= 0 ? 'امتیاز تشویقی معلم' : 'کسر امتیاز توسط معلم') }, { preserveScroll: true, onSuccess: () => form.setData('reason', '') });
    };
    const delEntry = (id) => { if (confirm('این ردیفِ امتیاز حذف شود؟')) router.delete(route('teacher.points.entry.destroy', id), { preserveScroll: true }); };
    const clearAll = () => { if (selected && confirm(`کلِ سابقه‌ی امتیازاتِ «${selected.name}» پاک شود؟ این کار برگشت‌پذیر نیست.`)) router.post(route('teacher.points.clear'), { student_id: selected.id }, { preserveScroll: true }); };

    // انتخابِ گروهیِ ردیف‌ها
    const [sel, setSel] = useState([]);
    useEffect(() => { setSel([]); }, [selected?.id, ledger.length]);
    const toggle = (id) => setSel((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id]);
    const allSel = ledger.length > 0 && sel.length === ledger.length;
    const delSelected = () => { if (sel.length && confirm(`${sel.length} ردیفِ امتیازِ انتخاب‌شده حذف شود؟`)) router.post(route('teacher.points.destroyMany'), { ids: sel }, { preserveScroll: true, onSuccess: () => setSel([]) }); };

    return (
        <DashLayout title="مدیریت امتیازات" roleLabel="معلم" menu={teacherMenu} active="points">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div className="panel no-print" style={{ display: 'flex', gap: 8, padding: 10, flexWrap: 'wrap' }}>
                <button onClick={() => setTab('manage')} className={`btn btn-sm ${tab === 'manage' ? '' : 'btn-ghost'}`}>⚡ مدیریتِ امتیاز</button>
                <button onClick={() => setTab('rank')} className={`btn btn-sm ${tab === 'rank' ? '' : 'btn-ghost'}`}>🏆 رتبه‌بندی و گزارشِ بازه‌ای</button>
            </div>

            {tab === 'rank' && (
                <RankPanel rank={rank} rs={rs} range={range} preset={preset} from={from} to={to}
                    weeks={weeks} months={months} yearLabel={yearLabel} classroom={classroom}
                    onApply={applyRange} printUrl={printUrl} onPick={(id) => { pick(id); setTab('manage'); }} />
            )}

            <div style={{ display: tab === 'manage' ? 'grid' : 'none', gridTemplateColumns: 'minmax(220px,300px) 1fr', gap: 16, alignItems: 'start' }} className="themes-grid">
                {/* فهرست دانش‌آموزان */}
                <div className="panel">
                    <h3 style={{ marginTop: 0 }}>🎓 دانش‌آموزان</h3>
                    {students.length === 0 && <p style={{ color: 'var(--muted)' }}>دانش‌آموزی در کلاس‌های شما نیست.</p>}
                    {students.length > 1 && <SortBar s={ss} options={[['name', 'نام'], ['family', 'نام خانوادگی'], ['total', 'امتیاز']]} />}
                    <div style={{ display: 'grid', gap: 6 }}>
                        {ss.sorted.map((s) => (
                            <button key={s.id} onClick={() => pick(s.id)}
                                style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8, textAlign: 'start', border: selected?.id === s.id ? '2px solid var(--gold)' : '1px solid var(--line)', background: selected?.id === s.id ? '#fff8e8' : '#fff', borderRadius: 10, padding: '9px 12px', cursor: 'pointer', fontFamily: 'inherit' }}>
                                <span style={{ fontWeight: 700 }}>{s.name}</span>
                                <span className="tag" style={{ background: '#eef3ff', color: '#2555c0' }}>⚡{fa(s.total)}</span>
                            </button>
                        ))}
                    </div>
                </div>

                {/* مدیریت امتیازِ دانش‌آموزِ انتخاب‌شده */}
                <div className="panel">
                    {!selected ? (
                        <div style={{ textAlign: 'center', padding: 30, color: 'var(--muted)' }}>
                            <div style={{ fontSize: 40 }}>⚡</div>
                            <p>یک دانش‌آموز را از فهرست انتخاب کنید تا امتیازاتش را مدیریت کنید.</p>
                        </div>
                    ) : (
                        <>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                                <h3 style={{ margin: 0 }}>{selected.name}</h3>
                                <span className="tag tag-info">مجموع: ⚡{fa(selected.total)}</span>
                                <button onClick={clearAll} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto', color: '#e8505b' }}>🗑️ پاک‌کردن کل سابقه</button>
                            </div>

                            {/* افزودن/کسر امتیاز */}
                            <div style={{ background: '#f6f8fc', border: '1px solid var(--line)', borderRadius: 12, padding: 12, marginTop: 12 }}>
                                <div style={{ display: 'flex', gap: 8, alignItems: 'end', flexWrap: 'wrap' }}>
                                    <div className="field" style={{ margin: 0 }}><label>مقدار (منفی = کسر)</label><input type="number" className="input" style={{ width: 110 }} value={form.data.amount} onChange={(e) => form.setData('amount', +e.target.value)} dir="ltr" /></div>
                                    <div className="field" style={{ margin: 0, flex: 1, minWidth: 160 }}><label>علت</label><input className="input" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} placeholder="مثلاً: مشارکت در کلاس" /></div>
                                    <button onClick={() => submit()} className="btn">ثبت</button>
                                </div>
                                <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                                    {QUICK.map((v) => (
                                        <button key={v} onClick={() => submit(v)} className="btn btn-sm" style={{ background: v >= 0 ? '#2bb673' : '#e8505b' }}>{v >= 0 ? '+' : ''}{fa(v)}</button>
                                    ))}
                                </div>
                            </div>

                            {trend && <div style={{ marginTop: 14 }}><PointsTrend data={trend} title={`📊 روندِ امتیاز و رتبه‌ی ${selected.name}`} /></div>}

                            {/* سابقه‌ی امتیازات */}
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginTop: 12 }}>
                                <h3 style={{ margin: 0 }}>📜 سابقه‌ی امتیازات ({fa(ledger.length)})</h3>
                                {ledger.length > 0 && (
                                    <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 13, cursor: 'pointer' }}>
                                        <input type="checkbox" checked={allSel} onChange={(e) => setSel(e.target.checked ? ledger.map((x) => x.id) : [])} /> انتخابِ همه
                                    </label>
                                )}
                                {sel.length > 0 && <button onClick={delSelected} className="btn btn-sm" style={{ marginInlineStart: 'auto', background: '#e8505b' }}>🗑️ حذفِ انتخابی‌ها ({fa(sel.length)})</button>}
                            </div>
                            {ledger.length === 0 && <p style={{ color: 'var(--muted)' }}>سابقه‌ای ثبت نشده است.</p>}
                            {ledger.length > 1 && <SortBar s={ls} options={[['date', 'تاریخ'], ['amount', 'مقدار'], ['reason', 'علت']]} />}
                            <div style={{ display: 'grid', gap: 6, marginTop: 8 }}>
                                {ls.sorted.map((e) => (
                                    <div key={e.id} style={{ display: 'flex', alignItems: 'center', gap: 10, border: sel.includes(e.id) ? '1px solid #e8505b' : '1px solid var(--line)', background: sel.includes(e.id) ? '#fdecee' : '#fff', borderRadius: 10, padding: '8px 12px' }}>
                                        <input type="checkbox" checked={sel.includes(e.id)} onChange={() => toggle(e.id)} />
                                        <span style={{ fontWeight: 900, minWidth: 48, color: e.kind === 'plus' ? '#16a34a' : '#dc2626' }}>{e.kind === 'plus' ? '+' : ''}{fa(e.amount)}</span>
                                        <span style={{ flex: 1, fontSize: 13.5 }}>{e.reason}</span>
                                        <span style={{ color: 'var(--muted)', fontSize: 12 }}>{e.date}</span>
                                        <button onClick={() => delEntry(e.id)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </DashLayout>
    );
}

/* ═══════════ رتبه‌بندیِ بازه‌ای: انتخابِ بازه، جدولِ مرتب‌شونده، چاپ ═══════════ */
const PRESETS = [
    { v: 'week', t: '📅 هفتگی' },
    { v: 'month', t: '🗓️ ماهیانه' },
    { v: 'day', t: '📌 یک روزِ خاص' },
    { v: 'year', t: '🎓 کلِ سالِ تحصیلی' },
    { v: 'custom', t: '🎯 بازه‌ی دلخواه' },
];

function RankPanel({ rank, rs, range, preset, from, to, weeks, months, yearLabel, classroom, onApply, printUrl, onPick }) {
    const [cFrom, setCFrom] = useState(from);
    const [cTo, setCTo] = useState(to);
    const sum = rank.reduce((a, r) => ({ xp: a.xp + r.xp, plus: a.plus + r.plus, minus: a.minus + r.minus, n: a.n + r.entries }), { xp: 0, plus: 0, minus: 0, n: 0 });
    const top = rank.filter((r) => r.xp > 0).slice(0, 3);

    return (
        <>
            <div className="panel no-print">
                <h3 style={{ marginTop: 0 }}>🗓️ بازه‌ی گزارش</h3>
                <div className="pr-presets">
                    {PRESETS.map((p) => (
                        <button key={p.v} type="button" className={`btn btn-sm ${preset === p.v ? '' : 'btn-ghost'}`}
                            onClick={() => onApply(p.v, p.v === 'custom' ? cFrom : '', p.v === 'custom' ? cTo : '')}>{p.t}</button>
                    ))}
                </div>

                <div className="pr-pick">
                    {preset === 'week' && (
                        <select className="input" value={from || weeks[0]?.key || ''} onChange={(e) => onApply('week', e.target.value)}>
                            {weeks.map((w) => <option key={w.key} value={w.key}>{w.label}</option>)}
                        </select>
                    )}
                    {preset === 'month' && (
                        <select className="input" value={from || months[0]?.key || ''} onChange={(e) => onApply('month', e.target.value)}>
                            {months.map((m) => <option key={m.key} value={m.key}>{m.label}</option>)}
                        </select>
                    )}
                    {preset === 'day' && (
                        <div style={{ maxWidth: 220 }}><JalaliDatePicker value={from} onChange={(v) => onApply('day', v)} placeholder="انتخابِ روز" /></div>
                    )}
                    {preset === 'year' && <span className="pr-note">🎓 سالِ تحصیلیِ {yearLabel} — از ۱ مهر تا امروز</span>}
                    {preset === 'custom' && (
                        <>
                            <div style={{ flex: '1 1 150px', maxWidth: 200 }}><JalaliDatePicker value={cFrom} onChange={setCFrom} placeholder="از تاریخ" /></div>
                            <div style={{ flex: '1 1 150px', maxWidth: 200 }}><JalaliDatePicker value={cTo} onChange={setCTo} placeholder="تا تاریخ" /></div>
                            <button type="button" className="btn btn-sm" disabled={!cFrom} onClick={() => onApply('custom', cFrom, cTo)}>اعمال</button>
                        </>
                    )}
                </div>

                <div className="pr-sel">
                    <b>{range.label}</b>
                    <a href={printUrl} target="_blank" rel="noopener" className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>🖨️ چاپِ این گزارش</a>
                </div>
            </div>

            <div className="panel printable">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 8 }}>
                    <h3 style={{ margin: 0 }}>🏆 رتبه‌بندیِ امتیاز{classroom ? ` — ${classroom}` : ''}</h3>
                    <span className="tag tag-info">{range.label}</span>
                </div>

                {top.length > 0 && (
                    <div className="pr-podium no-print">
                        {[top[1], top[0], top[2]].filter(Boolean).map((r) => (
                            <button type="button" key={r.id} className={`pr-pod r${r.rank}`} onClick={() => onPick(r.id)}>
                                <span className="pr-medal">{r.rank === 1 ? '🥇' : r.rank === 2 ? '🥈' : '🥉'}</span>
                                <b>{r.name}</b>
                                <em>⭐ {fa(r.xp)}</em>
                                <small>{fa(r.entries)} تراکنش</small>
                            </button>
                        ))}
                    </div>
                )}

                <div className="pr-sum">
                    <div><em>{fa(rank.length)}</em><span>دانش‌آموز</span></div>
                    <div><em>⭐ {fa(sum.xp)}</em><span>جمعِ امتیازِ بازه</span></div>
                    <div className="ok"><em>+{fa(sum.plus)}</em><span>دریافتی</span></div>
                    <div className="bad"><em>−{fa(sum.minus)}</em><span>کسرشده</span></div>
                    <div><em>{fa(sum.n)}</em><span>تراکنش</span></div>
                </div>

                <SortBar s={rs} options={[['rank', 'رتبه'], ['name', 'نام'], ['family', 'نام خانوادگی'], ['xp', 'امتیاز', 'desc']]} />
                <div style={{ overflowX: 'auto' }}>
                    <table className="tbl">
                        <thead>
                            <tr>
                                <SortTh s={rs} k="rank">رتبه</SortTh>
                                <SortTh s={rs} k="family">دانش‌آموز</SortTh>
                                <SortTh s={rs} k="team">تیم</SortTh>
                                <SortTh s={rs} k="xp" first="desc">امتیازِ بازه</SortTh>
                                <SortTh s={rs} k="plus" first="desc">دریافتی</SortTh>
                                <SortTh s={rs} k="minus" first="desc">کسرشده</SortTh>
                                <SortTh s={rs} k="entries" first="desc">تراکنش</SortTh>
                                <SortTh s={rs} k="last" first="desc">آخرین امتیاز</SortTh>
                            </tr>
                        </thead>
                        <tbody>
                            {rs.sorted.map((r) => (
                                <tr key={r.id} onClick={() => onPick(r.id)} style={{ cursor: 'pointer' }} title="برای مدیریتِ امتیازِ این دانش‌آموز کلیک کنید">
                                    <td><span className={`pr-rank r${r.rank <= 3 ? r.rank : ''}`}>{fa(r.rank)}</span></td>
                                    <td style={{ fontWeight: 700 }}>{r.name}</td>
                                    <td style={{ color: 'var(--muted)', fontSize: 12.5 }}>{r.team || '—'}</td>
                                    <td style={{ fontWeight: 900, color: r.xp > 0 ? '#16a34a' : r.xp < 0 ? '#dc2626' : 'var(--muted)' }}>{fa(r.xp)}</td>
                                    <td style={{ color: '#16a34a' }}>{r.plus ? `+${fa(r.plus)}` : '—'}</td>
                                    <td style={{ color: '#dc2626' }}>{r.minus ? `−${fa(r.minus)}` : '—'}</td>
                                    <td>{fa(r.entries)}</td>
                                    <td style={{ color: 'var(--muted)', fontSize: 12 }}>{r.last || '—'}</td>
                                </tr>
                            ))}
                            {rank.length === 0 && <tr><td colSpan="8" style={{ textAlign: 'center', color: 'var(--muted)' }}>در این بازه امتیازی ثبت نشده است.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
