import { usePage, useForm, router, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu, schoolMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const STATUSES = [
    { v: 'present', t: 'حاضر', ic: '✅', bg: '#e3f7ec', fg: '#177a45', on: '#22b573' },
    { v: 'absent', t: 'غایب', ic: '❌', bg: '#fdecec', fg: '#c0392b', on: '#e8505b' },
    { v: 'late', t: 'تأخیر', ic: '⏰', bg: '#fff3d6', fg: '#9a6a12', on: '#f5b53f' },
    { v: 'excused', t: 'موجه', ic: '📝', bg: '#e7efff', fg: '#2a5bb0', on: '#5b8def' },
];
const stMap = Object.fromEntries(STATUSES.map((s) => [s.v, s]));

export default function Manage() {
    const { role, routes, classrooms = [], classroomId, classroom, students = [], date, jdate, hasRecords, recentGroups = [], flash } = usePage().props;
    const menu = role === 'teacher' ? teacherMenu : schoolMenu;
    const roleLabel = role === 'teacher' ? 'معلم' : 'مدیر مدرسه';
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const initial = {};
    students.forEach((s) => { initial[s.id] = s.status || 'present'; });
    const [marks, setMarks] = useState(initial);
    useEffect(() => { const m = {}; students.forEach((s) => { m[s.id] = s.status || 'present'; }); setMarks(m); }, [date, classroomId]);

    const form = useForm();
    const setAll = (v) => setMarks(Object.fromEntries(students.map((s) => [s.id, v])));
    const setOne = (id, v) => setMarks((m) => ({ ...m, [id]: v }));

    const nav = (patch) => router.get(route(routes.index), { classroom_id: classroomId, date, ...patch }, { preserveState: false, preserveScroll: true });
    const changeDate = (d) => nav({ date: d });
    const changeClass = (cid) => nav({ classroom_id: cid });

    const save = () => {
        const records = students.map((s) => ({ student_id: s.id, status: marks[s.id] || 'present' }));
        router.post(route(routes.store), { classroom_id: classroomId, date, records }, { preserveScroll: true });
    };
    const delDay = () => { if (confirm(`تمام رکوردهای حضور و غیاب این روز (${jdate}) حذف شود؟`)) router.delete(route(routes.destroyDay), { data: { classroom_id: classroomId, date }, preserveScroll: true }); };
    const delOne = (sid, name) => { if (confirm(`رکورد «${name}» در این روز حذف شود؟`)) router.delete(route(routes.destroyOne), { data: { classroom_id: classroomId, student_id: sid, date }, preserveScroll: true }); };

    const counts = STATUSES.map((s) => ({ ...s, n: students.filter((x) => (marks[x.id] || 'present') === s.v).length }));

    const noClass = !classroom;

    return (
        <DashLayout title="ثبت حضور و غیاب" roleLabel={roleLabel} menu={menu} active="attendance"
            actions={classroom && <>
                <Link href={route(routes.monthlySheet, { classroom_id: classroomId })} className="btn btn-ghost btn-sm">🗓️ فرم خالی ماهانه</Link>
                <Link href={route(routes.report)} className="btn btn-ghost btn-sm">📋 سوابق و گزارشات</Link>
            </>}>
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            {noClass ? (
                <div className="panel"><p style={{ color: 'var(--muted)' }}>{role === 'teacher' ? 'ابتدا باید یک کلاس داشته باشی.' : 'هنوز کلاسی در مدرسه ثبت نشده است.'}</p></div>
            ) : (
                <>
                    {/* هدر تاریخ + انتخاب کلاس + خلاصه */}
                    <div className="panel" style={{ background: 'linear-gradient(135deg,#16264f,#0a1836)', border: 0, color: '#fff' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 14, flexWrap: 'wrap' }}>
                            <div>
                                <div style={{ fontWeight: 800, fontSize: 20 }}>✅ حضور و غیاب — {classroom.name}{classroom.grade ? ` (پایه ${classroom.grade})` : ''}</div>
                                <div style={{ color: '#c4d2f0', fontSize: 14, marginTop: 4 }}>📅 {jdate}</div>
                            </div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                                {classrooms.length > 1 && (
                                    <select value={classroomId} onChange={(e) => changeClass(e.target.value)} className="input" style={{ width: 'auto', padding: '8px 10px' }}>
                                        {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}{c.grade ? ` — پایه ${c.grade}` : ''}</option>)}
                                    </select>
                                )}
                                <label style={{ fontSize: 13, color: '#c4d2f0' }}>تاریخ:</label>
                                <span style={{ width: 150 }}><JalaliDatePicker value={date} onChange={changeDate} /></span>
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 14, alignItems: 'center' }}>
                            {counts.map((c) => (
                                <div key={c.v} style={{ background: 'rgba(255,255,255,.08)', border: '1px solid rgba(255,255,255,.16)', borderRadius: 12, padding: '8px 14px', fontWeight: 700 }}>
                                    {c.ic} {c.t}: <b style={{ color: c.on }}>{fa(c.n)}</b>
                                </div>
                            ))}
                            {hasRecords && <button onClick={delDay} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto', background: 'rgba(232,80,91,.15)', color: '#ffb3b3', border: '1px solid rgba(232,80,91,.4)' }}>🗑️ حذف رکورد این روز</button>}
                        </div>
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: '1.6fr 1fr', gap: 20, alignItems: 'start', marginTop: 20 }} className="themes-grid">
                        {/* لیست دانش‌آموزان */}
                        <div className="panel">
                            <h3>👥 دانش‌آموزان ({fa(students.length)})
                                <button onClick={() => setAll('present')} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>✅ همه حاضر</button>
                            </h3>
                            {students.length === 0 && <p style={{ color: 'var(--muted)' }}>دانش‌آموزی در کلاس نیست.</p>}
                            {students.map((s) => {
                                const cur = marks[s.id] || 'present';
                                const sc = stMap[cur];
                                return (
                                    <div key={s.id} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 10px', borderBottom: '1px solid var(--line)', flexWrap: 'wrap', borderRight: `4px solid ${sc.on}`, borderRadius: 4, marginBottom: 4, background: sc.bg + '55' }}>
                                        <div style={{ flex: 1, minWidth: 140 }}>
                                            <div style={{ fontWeight: 800, display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                                                {s.name}
                                                {s.recorded && <span title="ثبت‌شده" style={{ fontSize: 10, color: '#22b573' }}>● ثبت‌شده</span>}
                                                {s.by && <span style={{ fontSize: 10.5, color: 'var(--muted)', fontWeight: 600 }}>✍️ {s.by.role}: {s.by.name}</span>}
                                            </div>
                                            {(s.absent > 0 || s.late > 0) && (
                                                <div style={{ fontSize: 11.5, color: 'var(--muted)', marginTop: 2 }}>
                                                    {s.absent > 0 && <span style={{ color: '#e8505b' }}>❌ {fa(s.absent)} غیبت</span>}
                                                    {s.absent > 0 && s.late > 0 && ' · '}
                                                    {s.late > 0 && <span style={{ color: '#b9831a' }}>⏰ {fa(s.late)} تأخیر</span>}
                                                </div>
                                            )}
                                        </div>
                                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
                                            {/* کلاس att-status-btn به‌جای استایلِ inline، تا قاعده‌ی
                                                هدفِ لمسیِ ۴۴px روی موبایل بتواند اعمال شود. */}
                                            {STATUSES.map((st) => (
                                                <button key={st.v} onClick={() => setOne(s.id, st.v)}
                                                    className={`att-status-btn ${cur === st.v ? 'on' : ''}`}
                                                    style={cur === st.v ? { borderColor: st.on, background: st.on } : undefined}>
                                                    {st.ic} {st.t}
                                                </button>
                                            ))}
                                            {s.recorded && <button onClick={() => delOne(s.id, s.name)} className="icon-btn-touch" title="حذف رکورد این دانش‌آموز در این روز" style={{ cursor: 'pointer', border: 0, background: 'none', color: '#e8505b', fontSize: 15 }}>🗑️</button>}
                                        </div>
                                    </div>
                                );
                            })}
                            {students.length > 0 && (
                                <button onClick={save} disabled={form.processing} className="btn" style={{ width: '100%', marginTop: 16 }}>💾 ثبت حضور و غیاب</button>
                            )}
                        </div>

                        {/* سوابق اخیر — دسته‌بندی بر اساس سال ← ماه */}
                        <div className="panel">
                            <h3>🗓️ سوابق اخیر</h3>
                            {recentGroups.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز سابقه‌ای ثبت نشده.</p>}
                            {recentGroups.map((y) => (
                                <RecentYear key={y.year} y={y} date={date} changeDate={changeDate} multiYear={recentGroups.length > 1} />
                            ))}
                        </div>
                    </div>
                </>
            )}
        </DashLayout>
    );
}

/** یک سالِ سوابق؛ اگر بیش از یک سال باشد، عنوان سال هم نمایش داده می‌شود. */
function RecentYear({ y, date, changeDate, multiYear }) {
    return (
        <div style={{ marginBottom: multiYear ? 10 : 0 }}>
            {multiYear && (
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '6px 0 8px', fontWeight: 900, color: 'var(--navy-700)' }}>
                    <span style={{ width: 6, height: 16, borderRadius: 4, background: 'linear-gradient(var(--gold),var(--gold-2))' }} />
                    سال {y.year}
                </div>
            )}
            {y.months.map((m, i) => (
                <RecentMonth key={m.label} m={m} date={date} changeDate={changeDate} defaultOpen={i === 0} />
            ))}
        </div>
    );
}

/** یک ماهِ سوابق — جمع‌شونده. روزهای همان ماه زیر آن می‌آیند. */
function RecentMonth({ m, date, changeDate, defaultOpen }) {
    const [open, setOpen] = useState(defaultOpen);
    const totalAbsents = m.days.reduce((a, d) => a + d.absents, 0);
    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 12, marginBottom: 8, overflow: 'hidden' }}>
            <button onClick={() => setOpen(!open)}
                style={{ width: '100%', display: 'flex', alignItems: 'center', gap: 8, padding: '10px 12px', background: '#f7f9fd', border: 0, fontFamily: 'inherit', cursor: 'pointer', fontWeight: 800, color: 'var(--navy-700)' }}>
                <span style={{ fontSize: 12, opacity: .6 }}>{open ? '▼' : '◄'}</span>
                📅 {m.label}
                <span style={{ marginInlineStart: 'auto', display: 'flex', gap: 6 }}>
                    <span className="tag tag-info" style={{ fontSize: 11 }}>{fa(m.days.length)} روز</span>
                    {totalAbsents > 0 && <span className="tag tag-warn" style={{ fontSize: 11 }}>{fa(totalAbsents)} غیبت</span>}
                </span>
            </button>
            {open && (
                <div style={{ padding: 8 }}>
                    {m.days.map((d) => (
                        <button key={d.date} onClick={() => changeDate(d.date)}
                            style={{ width: '100%', textAlign: 'right', fontFamily: 'inherit', cursor: 'pointer', border: d.date === date ? '1px solid var(--gold)' : '1px solid var(--line)', background: d.date === date ? '#fff8e8' : '#fff', borderRadius: 10, padding: '9px 11px', marginBottom: 6 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
                                <span style={{ fontWeight: 700 }}>{d.weekday} {d.short}</span>
                                <span className={`tag ${d.absents > 0 ? 'tag-warn' : 'tag-ok'}`} style={{ fontSize: 11 }}>{d.absents > 0 ? `${fa(d.absents)} غایب` : 'همه حاضر'}</span>
                            </div>
                            {d.by && <div style={{ fontSize: 10.5, color: 'var(--muted)', marginTop: 3, textAlign: 'right' }}>✍️ آخرین ثبت/اصلاح: {d.by.role} {d.by.name}</div>}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
