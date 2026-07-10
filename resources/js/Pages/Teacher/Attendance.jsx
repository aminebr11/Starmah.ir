import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const STATUSES = [
    { v: 'present', t: 'حاضر', ic: '✅', bg: '#e3f7ec', fg: '#177a45', on: '#22b573' },
    { v: 'absent', t: 'غایب', ic: '❌', bg: '#fdecec', fg: '#c0392b', on: '#e8505b' },
    { v: 'late', t: 'تأخیر', ic: '⏰', bg: '#fff3d6', fg: '#9a6a12', on: '#f5b53f' },
    { v: 'excused', t: 'موجه', ic: '📝', bg: '#e7efff', fg: '#2a5bb0', on: '#5b8def' },
];
const stMap = Object.fromEntries(STATUSES.map((s) => [s.v, s]));

export default function Attendance() {
    const { classroom, students = [], date, jdate, recentDates = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const initial = {};
    students.forEach((s) => { initial[s.id] = s.status || 'present'; });
    const [marks, setMarks] = useState(initial);
    useEffect(() => { const m = {}; students.forEach((s) => { m[s.id] = s.status || 'present'; }); setMarks(m); }, [date]);

    const form = useForm();
    const setAll = (v) => setMarks(Object.fromEntries(students.map((s) => [s.id, v])));
    const setOne = (id, v) => setMarks((m) => ({ ...m, [id]: v }));

    const changeDate = (d) => router.get(route('teacher.attendance'), { date: d }, { preserveState: false, preserveScroll: true });

    const save = () => {
        const records = students.map((s) => ({ student_id: s.id, status: marks[s.id] || 'present' }));
        router.post(route('teacher.attendance.store'), { date, records }, { preserveScroll: true });
    };

    const counts = STATUSES.map((s) => ({ ...s, n: students.filter((x) => (marks[x.id] || 'present') === s.v).length }));

    if (!classroom) {
        return <DashLayout title="حضور و غیاب" roleLabel="معلم" menu={teacherMenu} active="attendance">
            <div className="panel"><p style={{ color: 'var(--muted)' }}>ابتدا باید یک کلاس داشته باشی.</p></div>
        </DashLayout>;
    }

    return (
        <DashLayout title="حضور و غیاب" roleLabel="معلم" menu={teacherMenu} active="attendance">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            {/* هدر تاریخ + خلاصه */}
            <div className="panel" style={{ background: 'linear-gradient(135deg,#16264f,#0a1836)', border: 0, color: '#fff' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 14, flexWrap: 'wrap' }}>
                    <div>
                        <div style={{ fontWeight: 800, fontSize: 20 }}>✅ حضور و غیاب — {classroom.name}{classroom.grade ? ` (پایه ${classroom.grade})` : ''}</div>
                        <div style={{ color: '#c4d2f0', fontSize: 14, marginTop: 4 }}>📅 {jdate}</div>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <label style={{ fontSize: 13, color: '#c4d2f0' }}>تاریخ:</label>
                        <input type="date" value={date} onChange={(e) => changeDate(e.target.value)}
                            className="input" style={{ width: 'auto', padding: '8px 10px' }} />
                    </div>
                </div>
                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 14 }}>
                    {counts.map((c) => (
                        <div key={c.v} style={{ background: 'rgba(255,255,255,.08)', border: '1px solid rgba(255,255,255,.16)', borderRadius: 12, padding: '8px 14px', fontWeight: 700 }}>
                            {c.ic} {c.t}: <b style={{ color: c.on }}>{fa(c.n)}</b>
                        </div>
                    ))}
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
                                    <div style={{ fontWeight: 800 }}>{s.name}</div>
                                    {(s.absent > 0 || s.late > 0) && (
                                        <div style={{ fontSize: 11.5, color: 'var(--muted)', marginTop: 2 }}>
                                            {s.absent > 0 && <span style={{ color: '#e8505b' }}>❌ {fa(s.absent)} غیبت</span>}
                                            {s.absent > 0 && s.late > 0 && ' · '}
                                            {s.late > 0 && <span style={{ color: '#b9831a' }}>⏰ {fa(s.late)} تأخیر</span>}
                                        </div>
                                    )}
                                </div>
                                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                    {STATUSES.map((st) => (
                                        <button key={st.v} onClick={() => setOne(s.id, st.v)}
                                            style={{ cursor: 'pointer', fontFamily: 'inherit', fontWeight: 700, fontSize: 12.5, padding: '7px 11px', borderRadius: 10,
                                                border: cur === st.v ? `2px solid ${st.on}` : '1px solid var(--line)',
                                                background: cur === st.v ? st.on : '#fff', color: cur === st.v ? '#fff' : 'var(--muted)' }}>
                                            {st.ic} {st.t}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                    {students.length > 0 && (
                        <button onClick={save} disabled={form.processing} className="btn" style={{ width: '100%', marginTop: 16 }}>💾 ثبت حضور و غیاب</button>
                    )}
                </div>

                {/* سوابق اخیر */}
                <div className="panel">
                    <h3>🗓️ سوابق اخیر</h3>
                    {recentDates.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز سابقه‌ای ثبت نشده.</p>}
                    {recentDates.map((d) => (
                        <button key={d.date} onClick={() => changeDate(d.date)}
                            style={{ width: '100%', textAlign: 'right', fontFamily: 'inherit', cursor: 'pointer', border: d.date === date ? '1px solid var(--gold)' : '1px solid var(--line)', background: d.date === date ? '#fff8e8' : '#fff', borderRadius: 12, padding: '11px 13px', marginBottom: 8, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <span style={{ fontWeight: 700 }}>{d.jdate}</span>
                            <span className={`tag ${d.absents > 0 ? 'tag-warn' : 'tag-ok'}`}>{d.absents > 0 ? `${fa(d.absents)} غایب` : 'همه حاضر'}</span>
                        </button>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}
