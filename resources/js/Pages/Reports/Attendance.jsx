import { usePage, router, Link } from '@inertiajs/react';
import { useState } from 'react';
import DashLayout, { teacherMenu, schoolMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const iso = (d) => d.toISOString().slice(0, 10);
const PRESETS = [
    ['این هفته', () => { const n = new Date(); const s = new Date(n); s.setDate(n.getDate() - ((n.getDay() + 1) % 7)); return [iso(s), iso(n)]; }],
    ['این ماه', () => { const n = new Date(); return [iso(new Date(n.getFullYear(), n.getMonth(), 1)), iso(n)]; }],
    ['۳ ماه اخیر', () => { const n = new Date(); const s = new Date(n); s.setMonth(n.getMonth() - 3); return [iso(s), iso(n)]; }],
    ['امسال', () => { const n = new Date(); const s = new Date(n); s.setFullYear(n.getFullYear() - 1); return [iso(s), iso(n)]; }],
];

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const STATUS = { present: ['حضور', '#22c55e'], absent: ['غیبت', '#ef4444'], late: ['تأخیر', '#f59e0b'], excused: ['مرخصی', '#3b82f6'] };

/** مرکز گزارش حضور و غیاب — فیلتر، خلاصه‌ی آماری، نمودار و خروجی چاپ. */
export default function AttendanceReport() {
    const { role, classrooms = [], students = [], filters = {}, jfrom, jto, rows = [], summary = {}, daily = [], meta = {} } = usePage().props;
    const menu = role === 'teacher' ? teacherMenu : schoolMenu;
    const routeName = role === 'teacher' ? 'teacher.attendance.report' : 'school.attendance.report';

    const [f, setF] = useState({
        from: filters.from || '', to: filters.to || '',
        classroom_id: filters.classroom_id || '', student_id: filters.student_id || '', status: filters.status || '',
    });
    const set = (k, v) => setF((s) => ({ ...s, [k]: v, ...(k === 'classroom_id' ? { student_id: '' } : {}) }));
    const apply = () => router.get(route(routeName), f, { preserveState: true, preserveScroll: true });

    const [printMode, setPrintMode] = useState('both'); // list | summary | both
    const monthlyRoute = role === 'teacher' ? 'teacher.attendance.monthly' : 'school.attendance.monthly';

    const maxDay = Math.max(1, ...daily.map((d) => d.present + d.absent + d.late));

    return (
        <DashLayout title="سوابق و گزارشات حضور و غیاب" roleLabel={role === 'teacher' ? 'معلم' : 'مدیر مدرسه'} menu={menu} active="attendance"
            actions={<>
                <Link href={route(monthlyRoute, { classroom_id: f.classroom_id })} className="btn btn-ghost btn-sm no-print">🗓️ فرم خالی ماهانه</Link>
                <button onClick={() => window.print()} className="btn btn-sm no-print">🖨️ چاپ گزارش</button>
            </>}>

            {/* ===== فیلترها ===== */}
            <div className="panel no-print">
                <h3 style={{ marginTop: 0 }}>🔎 فیلتر گزارش</h3>
                <div className="report-filters">
                    <div className="field"><label>کلاس</label>
                        <select className="input" value={f.classroom_id} onChange={(e) => set('classroom_id', e.target.value)}>
                            {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}{c.grade ? ` — پایه ${c.grade}` : ''}{role !== 'teacher' && c.teacher ? ` (${c.teacher})` : ''}</option>)}
                        </select>
                    </div>
                    <div className="field"><label>دانش‌آموز</label>
                        <select className="input" value={f.student_id} onChange={(e) => set('student_id', e.target.value)}>
                            <option value="">همه‌ی دانش‌آموزان</option>
                            {students.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                    </div>
                    <div className="field"><label>وضعیت</label>
                        <select className="input" value={f.status} onChange={(e) => set('status', e.target.value)}>
                            <option value="">همه‌ی وضعیت‌ها</option>
                            {Object.entries(STATUS).map(([k, v]) => <option key={k} value={k}>{v[0]}</option>)}
                        </select>
                    </div>
                    <div className="field"><label>از تاریخ</label><JalaliDatePicker value={f.from} onChange={(v) => set('from', v)} placeholder="از تاریخ" /></div>
                    <div className="field"><label>تا تاریخ</label><JalaliDatePicker value={f.to} onChange={(v) => set('to', v)} placeholder="تا تاریخ" /></div>
                    <button onClick={apply} className="btn" style={{ alignSelf: 'end' }}>اعمال فیلتر</button>
                </div>
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 10 }}>
                    <span style={{ fontSize: 12, color: 'var(--muted)', alignSelf: 'center' }}>بازه‌ی سریع:</span>
                    {PRESETS.map(([label, fn]) => (
                        <button key={label} type="button" className="tag tag-info" style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit' }}
                            onClick={() => { const [from, to] = fn(); setF((s) => ({ ...s, from, to })); }}>{label}</button>
                    ))}
                </div>
                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 14, alignItems: 'center', borderTop: '1px solid var(--line)', paddingTop: 12 }}>
                    <span style={{ fontSize: 13, fontWeight: 700 }}>🖨️ محتوای چاپ:</span>
                    <div className="print-mode">
                        {[['both', 'کامل (جدول + گزارش کلی)'], ['list', 'فقط جدول تفکیکی'], ['summary', 'فقط گزارش کلی']].map(([v, t]) => (
                            <button key={v} type="button" className={`pm-btn ${printMode === v ? 'on' : ''}`} onClick={() => setPrintMode(v)}>{t}</button>
                        ))}
                    </div>
                </div>
            </div>

            <div className={`rep-print-${printMode}`}>
            {/* ===== سربرگ چاپ (فقط هنگام چاپ دیده می‌شود) ===== */}
            <div className="report-print-head">
                <img src="/brand/logo-emblem.png" alt="" />
                <div style={{ flex: 1, textAlign: 'center' }}>
                    <div className="rph-title">گزارش حضور و غیاب</div>
                    <div className="rph-school">{meta.school || 'مدرسه'}</div>
                </div>
                <div className="rph-meta">
                    <div>کلاس: <b>{meta.class || '—'}</b></div>
                    {meta.teacher && <div>معلم: <b>{meta.teacher}</b></div>}
                    <div>تاریخ چاپ: {meta.printedAt}</div>
                </div>
            </div>
            <div className="report-print-range">بازه‌ی گزارش: از {jfrom} تا {jto}</div>

            {/* ===== کارت‌های خلاصه ===== */}
            <div className="report-cards rep-summary">
                {[['👥 دانش‌آموزان', summary.students, 'var(--navy-700)'], ['📅 روزهای ثبت‌شده', summary.days, 'var(--navy-700)'],
                  ['✅ حضور', summary.present, '#22c55e'], ['❌ غیبت', summary.absent, '#ef4444'],
                  ['⏰ تأخیر', summary.late, '#f59e0b'], ['📝 مرخصی', summary.excused, '#3b82f6']].map(([t, v, c]) => (
                    <div key={t} className="report-card"><div className="rc-val" style={{ color: c }}>{fa(v ?? 0)}</div><div className="rc-lbl">{t}</div></div>
                ))}
                <div className="report-card" style={{ background: 'linear-gradient(135deg,#0f2a5e,#1d4ed8)', color: '#fff' }}>
                    <div className="rc-val" style={{ color: '#fff' }}>{fa(summary.percent ?? 100)}٪</div><div className="rc-lbl" style={{ color: '#dbe6ff' }}>درصد حضور</div>
                </div>
            </div>

            {/* ===== نمودار روزانه ===== */}
            {daily.length > 0 && (
                <div className="panel printable rep-summary">
                    <h3 className="print-title" style={{ marginTop: 0 }}>نمودار روزانه</h3>
                    <h3 className="no-print" style={{ marginTop: 0 }}>📊 نمودار روزانه (حضور / تأخیر / غیبت)</h3>
                    <div className="report-chart">
                        {daily.map((d, i) => (
                            <div key={i} className="rchart-col" title={d.date}>
                                <div className="rchart-bars">
                                    <div style={{ height: `${d.present / maxDay * 100}%`, background: '#22c55e' }} title={`حضور: ${d.present}`} />
                                    <div style={{ height: `${d.late / maxDay * 100}%`, background: '#f59e0b' }} title={`تأخیر: ${d.late}`} />
                                    <div style={{ height: `${d.absent / maxDay * 100}%`, background: '#ef4444' }} title={`غیبت: ${d.absent}`} />
                                </div>
                                <div className="rchart-lbl">{d.date}</div>
                            </div>
                        ))}
                    </div>
                    <div className="report-legend">
                        {[['حضور', '#22c55e'], ['تأخیر', '#f59e0b'], ['غیبت', '#ef4444']].map(([t, c]) => (
                            <span key={t}><i style={{ background: c }} />{t}</span>
                        ))}
                    </div>
                </div>
            )}

            {/* ===== جدول تفکیکی دانش‌آموزان ===== */}
            <div className="panel printable rep-list">
                <h3 className="no-print" style={{ marginTop: 0 }}>📋 جدول تفکیکی</h3>
                {rows.length === 0 ? <p className="no-print" style={{ color: 'var(--muted)' }}>در این بازه رکوردی ثبت نشده.</p> : (
                    <div style={{ overflowX: 'auto' }}>
                        <table className="tbl">
                            <thead><tr>
                                <th>#</th><th>دانش‌آموز</th><th style={{ textAlign: 'center' }}>حضور</th><th style={{ textAlign: 'center' }}>غیبت</th>
                                <th style={{ textAlign: 'center' }}>تأخیر</th><th style={{ textAlign: 'center' }}>مرخصی</th><th style={{ textAlign: 'center' }}>جمع</th><th style={{ textAlign: 'center' }}>درصد حضور</th>
                            </tr></thead>
                            <tbody>
                                {rows.map((r, i) => (
                                    <tr key={r.id}>
                                        <td>{fa(i + 1)}</td><td style={{ fontWeight: 700 }}>{r.name}</td>
                                        <td style={{ textAlign: 'center', color: '#16a34a' }}>{fa(r.present)}</td>
                                        <td style={{ textAlign: 'center', color: '#dc2626', fontWeight: r.absent ? 800 : 400 }}>{fa(r.absent)}</td>
                                        <td style={{ textAlign: 'center', color: '#d97706' }}>{fa(r.late)}</td>
                                        <td style={{ textAlign: 'center', color: '#2563eb' }}>{fa(r.excused)}</td>
                                        <td style={{ textAlign: 'center' }}>{fa(r.total)}</td>
                                        <td style={{ textAlign: 'center' }}>
                                            <span className="report-pct" style={{ background: r.percent >= 90 ? '#dcfce7' : r.percent >= 75 ? '#fef9c3' : '#fee2e2', color: r.percent >= 90 ? '#15803d' : r.percent >= 75 ? '#a16207' : '#b91c1c' }}>{fa(r.percent)}٪</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* امضاها (فقط چاپ) */}
                <div className="report-signs">
                    <div>امضای معلم<span /></div>
                    <div>امضای مدیر مدرسه<span /></div>
                    <div>مهر مدرسه<span /></div>
                </div>
            </div>
            </div>
        </DashLayout>
    );
}
