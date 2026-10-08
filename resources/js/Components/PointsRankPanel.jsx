import { useState } from 'react';
import JalaliDatePicker from '@/Components/JalaliDatePicker';
import { SortBar, SortTh } from '@/lib/useSort';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/* ═══════════ رتبه‌بندیِ بازه‌ای: انتخابِ بازه، جدولِ مرتب‌شونده، چاپ ═══════════ */
const PRESETS = [
    { v: 'week', t: '📅 هفتگی' },
    { v: 'month', t: '🗓️ ماهیانه' },
    { v: 'day', t: '📌 یک روزِ خاص' },
    { v: 'year', t: '🎓 کلِ سالِ تحصیلی' },
    { v: 'custom', t: '🎯 بازه‌ی دلخواه' },
];

export default function PointsRankPanel({ rank, rs, range, preset, from, to, weeks, months, yearLabel, classroom, onApply, printUrl, onPick }) {
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
