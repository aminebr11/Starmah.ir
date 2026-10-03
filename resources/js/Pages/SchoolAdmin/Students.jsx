import { usePage, useForm, router } from '@inertiajs/react';
import JalaliDatePicker from '@/Components/JalaliDatePicker';
import { useState, useEffect, useRef, Fragment } from 'react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
import PersonCell from '@/Components/PersonCell';
import ListSearch, { normalizeFa } from '@/Components/ListSearch';
import StudentRecord from '@/Components/StudentRecord';
import { useSort, SortTh, SortBar, firstName, lastName } from '@/lib/useSort';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Students() {
    const { students = [], classrooms = [], teachers = [], school, audit = [], themes = [], grades = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const [q, setQ] = useState('');
    const [printId, setPrintId] = useState(null);
    const printedAt = new Date().toLocaleDateString('fa-IR');

    // گزارش‌ساز: انتخابِ جهت و ستون‌های فهرستِ چاپی
    const ROSTER_COLS = [
        { key: 'name', label: 'نام و نام‌خانوادگی' }, { key: 'national_id', label: 'کدِ ملی' },
        { key: 'birth', label: 'تاریخِ تولد' }, { key: 'grade', label: 'پایه' },
        { key: 'class', label: 'کلاس / معلم' }, { key: 'team', label: 'تیم' },
        { key: 'phone', label: 'موبایل' }, { key: 'guardian', label: 'سرپرست' },
        { key: 'g_phone', label: 'موبایلِ سرپرست' }, { key: 'xp', label: 'امتیاز' },
    ];
    const [builder, setBuilder] = useState(false);
    const [orient, setOrient] = useState('portrait');
    const [cols, setCols] = useState(ROSTER_COLS.map((c) => c.key));
    const toggleCol = (k) => setCols((cs) => cs.includes(k) ? cs.filter((x) => x !== k) : [...cs, k]);
    const openRoster = () => {
        const ordered = ROSTER_COLS.filter((c) => cols.includes(c.key)).map((c) => c.key);
        const url = `/print/roster?orient=${orient}&cols=${ordered.join(',')}&back=${encodeURIComponent(window.location.href)}`;
        window.open(url, '_blank', 'noopener');
    };

    // چاپِ لیستِ یک کلاس
    useEffect(() => {
        if (printId !== null) {
            const t = setTimeout(() => { window.print(); setPrintId(null); }, 120);
            return () => clearTimeout(t);
        }
    }, [printId]);

    // پرونده‌ی کاملِ دانش‌آموز (نمایش / ویرایش / چاپ) — به‌جای ویرایشِ چندفیلدیِ قبلی
    const [openId, setOpenId] = useState(null);
    const openStudent = students.find((s) => s.id === openId) || null;
    const del = (s) => { if (confirm(`دانش‌آموز «${s.name}» حذف شود؟`)) router.delete(route('manage.users.destroy', s.id), { preserveScroll: true }); };
    const moveStudent = (s, cid) => { if (cid && router.post(route('manage.users.move', s.id), { classroom_id: cid }, { preserveScroll: true })); };
    const reassignTeacher = (cid, tid) => { if (tid) router.post(route('manage.classrooms.teacher', cid), { teacher_id: tid }, { preserveScroll: true }); };

    // جست‌وجوی نرمال‌شده: «ی/ك» عربی و نیم‌فاصله دیگر مانعِ یافتن نمی‌شوند
    const nq = normalizeFa(q);
    const shown = students.filter((s) => !nq
        || normalizeFa(s.name).includes(nq)
        || normalizeFa(s.phone).includes(nq)
        || normalizeFa(s.national_id).includes(nq)
        || normalizeFa(s.class).includes(nq)
        || normalizeFa(s.guardian_name).includes(nq));

    // مرتب‌سازیِ مشترکِ همه‌ی جدول‌های کلاس (کلیک روی سرِ ستون)
    const st = useSort(shown, {
        name: (r) => firstName(r.name), family: (r) => lastName(r.name), phone: 'phone', national_id: 'national_id',
        guardian: 'guardian_name', xp: 'xp',
    }, { id: 'school-students', firstDir: { xp: 'desc' } });
    const au = useSort(audit, { when: 'when_raw', actor: 'actor', action: 'action', summary: 'summary' }, { id: 'school-students-audit', firstDir: { when: 'desc' } });

    // گروه‌بندی بر اساس کلاس
    const groups = classrooms.map((c) => ({ cls: c, list: st.sorted.filter((s) => s.classroom_id === c.id) }));
    const noClass = st.sorted.filter((s) => !s.classroom_id);

    const printClass = classrooms.find((c) => c.id === printId);
    const printList = printClass ? students.filter((s) => s.classroom_id === printClass.id) : [];

    return (
        <DashLayout title="دانش‌آموزان" roleLabel="مدیر مدرسه" menu={schoolMenu} active="students"
            actions={<button onClick={() => setBuilder((b) => !b)} className="btn btn-sm">🖨️ گزارش‌سازِ فهرست</button>}>
            {banner && <div className="panel no-print" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            {/* گزارش‌سازِ فهرستِ چاپی */}
            {builder && (
                <div className="panel no-print" style={{ border: '2px solid var(--gold)', background: '#fffdf6' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 12 }}>
                        <h3 style={{ margin: 0 }}>🖨️ گزارش‌سازِ فهرستِ دانش‌آموزان</h3>
                        <span style={{ fontSize: 12.5, color: 'var(--muted)' }}>جهتِ صفحه و ستون‌های دلخواه را انتخاب کن، سپس «باز کردنِ گزارش».</span>
                    </div>

                    <div style={{ display: 'flex', gap: 8, marginBottom: 14, flexWrap: 'wrap' }}>
                        <button onClick={() => setOrient('portrait')} className={`btn btn-sm ${orient === 'portrait' ? '' : 'btn-ghost'}`}>📄 عمودی (Portrait)</button>
                        <button onClick={() => setOrient('landscape')} className={`btn btn-sm ${orient === 'landscape' ? '' : 'btn-ghost'}`}>📃 افقی (Landscape)</button>
                        <span style={{ fontSize: 12, color: 'var(--muted)', alignSelf: 'center' }}>
                            {orient === 'landscape' ? 'برای جدول‌های پهن با ستونِ زیاد مناسب است.' : 'برای فهرستِ ساده و ستونِ کم مناسب است.'}
                        </span>
                    </div>

                    <div style={{ fontWeight: 700, fontSize: 13, marginBottom: 6 }}>ستون‌های گزارش ({fa(cols.length)} انتخاب‌شده):</div>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 14 }}>
                        {ROSTER_COLS.map((c) => (
                            <label key={c.key} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, padding: '6px 12px', borderRadius: 20, cursor: 'pointer',
                                border: cols.includes(c.key) ? '1.5px solid var(--gold)' : '1px solid var(--line)', background: cols.includes(c.key) ? '#fff8e8' : '#fff' }}>
                                <input type="checkbox" checked={cols.includes(c.key)} onChange={() => toggleCol(c.key)} />{c.label}
                            </label>
                        ))}
                    </div>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        <button onClick={openRoster} disabled={!cols.length} className="btn">📄 باز کردنِ گزارش</button>
                        <button onClick={() => setCols(ROSTER_COLS.map((c) => c.key))} className="btn btn-ghost btn-sm">همه‌ی ستون‌ها</button>
                        <button onClick={() => setCols(['name', 'class', 'guardian', 'g_phone'])} className="btn btn-ghost btn-sm">فقط تماسِ سرپرست</button>
                    </div>
                </div>
            )}

            <div className="panel no-print" style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                <h3 style={{ margin: 0 }}>🎓 دانش‌آموزان مدرسه ({fa(students.length)})</h3>
                <div style={{ marginInlineStart: 'auto', minWidth: 220, flex: '0 1 300px' }}>
                    <ListSearch value={q} onChange={setQ} placeholder="جست‌وجوی نام، موبایل، کد ملی، کلاس یا سرپرست…" />
                </div>
                {q && <span className="list-count">{fa(shown.length)} از {fa(students.length)}</span>}
                {students.length > 1 && <div style={{ flexBasis: '100%' }}><SortBar s={st} options={[['name', 'نام'], ['family', 'نام خانوادگی'], ['xp', 'امتیاز'], ['national_id', 'کد ملی'], ['guardian', 'سرپرست']]} /></div>}
            </div>

            {students.length === 0 && <div className="panel no-print"><p style={{ color: 'var(--muted)' }}>هنوز دانش‌آموزی ثبت‌نام نکرده.</p></div>}

            {/* هر کلاس در یک بخش */}
            {groups.map(({ cls, list }) => (
                <div key={cls.id} className="panel no-print">
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 6 }}>
                        <h3 style={{ margin: 0 }}>🏫 {cls.name}{cls.grade ? ` — پایه ${cls.grade}` : ''} <span style={{ fontSize: 13, fontWeight: 500, color: 'var(--muted)' }}>({fa(list.length)} دانش‌آموز)</span></h3>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginInlineStart: 'auto', flexWrap: 'wrap' }}>
                            <label style={{ fontSize: 12.5, color: 'var(--muted)' }}>معلم:</label>
                            <select className="input" style={{ width: 'auto', padding: '7px 10px', fontSize: 13 }} value={cls.teacher_id ?? ''} onChange={(e) => reassignTeacher(cls.id, e.target.value)}>
                                <option value="">— انتخاب معلم —</option>
                                {teachers.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                            </select>
                            <button onClick={() => setPrintId(cls.id)} className="btn btn-ghost btn-sm">🖨️ چاپ لیست کلاس</button>
                        </div>
                    </div>
                    <StudentTable st={st} list={list} classrooms={classrooms} currentClassId={cls.id} onOpen={setOpenId} del={del} moveStudent={moveStudent} />
                </div>
            ))}

            {noClass.length > 0 && (
                <div className="panel no-print">
                    <h3 style={{ marginTop: 0 }}>❓ بدون کلاس ({fa(noClass.length)})</h3>
                    <StudentTable st={st} list={noClass} classrooms={classrooms} currentClassId={0} onOpen={setOpenId} del={del} moveStudent={moveStudent} />
                </div>
            )}

            {/* سوابق تغییرات */}
            <div className="panel no-print">
                <h3 style={{ marginTop: 0 }}>📋 سوابق تغییرات (چه کسی چه چیزی را تغییر داد)</h3>
                {audit.length === 0 ? <p style={{ color: 'var(--muted)' }}>هنوز تغییری ثبت نشده.</p> : (
                    <div style={{ overflowX: 'auto' }}>
                        <table className="tbl">
                            <thead><tr><SortTh s={au} k="when">زمان</SortTh><SortTh s={au} k="actor">کاربر</SortTh><SortTh s={au} k="action">اقدام</SortTh><SortTh s={au} k="summary">توضیح</SortTh></tr></thead>
                            <tbody>{au.sorted.map((a, i) => (
                                <tr key={i}><td style={{ whiteSpace: 'nowrap', fontSize: 12.5, color: 'var(--muted)' }}>{a.when}</td>
                                    <td style={{ fontWeight: 700 }}>{a.actor} <span style={{ fontSize: 11, color: 'var(--muted)' }}>({a.role})</span></td>
                                    <td><span className="tag tag-info">{a.action}</span></td><td style={{ fontSize: 13 }}>{a.summary}</td></tr>
                            ))}</tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* ===== لیستِ چاپِ کلاس (فقط هنگام چاپ) ===== */}
            {printClass && (
                <div className="roster-print">
                    <div className="roster-head">
                        <img src="/brand/logo-mark-240.webp" alt="" />
                        <div style={{ flex: 1, textAlign: 'center' }}>
                            <div className="rh-title">لیست دانش‌آموزان کلاس</div>
                            <div className="rh-sub">{school?.name} — {printClass.name}{printClass.grade ? ` (پایه ${printClass.grade})` : ''}</div>
                        </div>
                        <div className="rh-meta"><div>معلم: <b>{printClass.teacher || '—'}</b></div><div>تعداد: <b>{fa(printList.length)}</b></div><div>تاریخ چاپ: {printedAt}</div></div>
                    </div>
                    <table className="roster-tbl">
                        <thead><tr><th>#</th><th>نام و نام خانوادگی</th><th>کد ملی</th><th>موبایل</th><th>تاریخ تولد</th><th>نام ولی</th><th>تلفن ولی</th></tr></thead>
                        <tbody>{printList.map((s, i) => (
                            <tr key={s.id}><td>{fa(i + 1)}</td><td>{s.name}</td><td>{s.national_id || '—'}</td><td dir="ltr">{s.phone || '—'}</td>
                                <td>{s.jbirth || '—'}</td><td>{s.guardian_name || '—'}</td><td dir="ltr">{s.guardian_phone || '—'}</td></tr>
                        ))}</tbody>
                    </table>
                    <div className="roster-signs"><div>امضای معلم<span /></div><div>امضای مدیر مدرسه<span /></div><div>مهر مدرسه<span /></div></div>
                </div>
            )}
            {openStudent && (
                <StudentRecord s={openStudent} themes={themes} grades={grades} onClose={() => setOpenId(null)} />
            )}
        </DashLayout>
    );
}

function StudentTable({ st, list, classrooms, currentClassId, onOpen, del, moveStudent }) {
    if (list.length === 0) return <p style={{ color: 'var(--muted)' }}>دانش‌آموزی در این کلاس نیست.</p>;
    return (
        <div style={{ overflowX: 'auto' }}>
            <table className="tbl">
                <thead><tr><th>#</th><SortTh s={st} k="family">نام</SortTh><SortTh s={st} k="phone">موبایل</SortTh><SortTh s={st} k="national_id">کد ملی</SortTh><SortTh s={st} k="guardian">سرپرست</SortTh><SortTh s={st} k="xp">امتیاز</SortTh><th>تغییر کلاس</th><th style={{ textAlign: 'left' }}>عملیات</th></tr></thead>
                <tbody>{list.map((s, i) => (
                    <tr key={s.id}>
                        <td>{fa(i + 1)}</td>
                        <td>
                            <button type="button" onClick={() => onOpen(s.id)} title="بازکردنِ پرونده"
                                style={{ border: 0, background: 'none', padding: 0, cursor: 'pointer', font: 'inherit', textAlign: 'start' }}>
                                <PersonCell name={s.name} avatar={s.avatar} size={32} />
                            </button>
                        </td>
                        <td dir="ltr">{s.phone || '—'}</td>
                        <td dir="ltr">{s.national_id || '—'}</td>
                        <td style={{ fontSize: 12.5 }}>{s.guardian_name || '—'}{s.guardian_phone ? <div dir="ltr" style={{ color: 'var(--muted)', fontSize: 11.5 }}>{s.guardian_phone}</div> : null}</td>
                        <td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(s.xp)}</td>
                        <td>
                            <select className="input" style={{ width: 'auto', padding: '6px 9px', fontSize: 12.5 }} value="" onChange={(e) => moveStudent(s, e.target.value)}>
                                <option value="">انتقال به…</option>
                                {classrooms.filter((c) => c.id !== currentClassId).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </td>
                        <td style={{ textAlign: 'left', whiteSpace: 'nowrap' }}>
                            <button onClick={() => onOpen(s.id)} className="btn btn-ghost btn-sm" title="پرونده‌ی کامل">📋</button>
                            <a href={`/print/student/${s.id}/card`} target="_blank" rel="noreferrer" className="btn btn-ghost btn-sm" title="چاپِ شناسنامه" style={{ marginInlineStart: 4 }}>🖨️</a>
                            <button onClick={() => del(s)} className="btn btn-ghost btn-sm" title="حذف" style={{ color: '#e8505b', marginInlineStart: 4 }}>🗑️</button>
                        </td>
                    </tr>
                ))}</tbody>
            </table>
        </div>
    );
}

function F({ label, err, children }) {
    return <div className="field" style={{ margin: 0 }}><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
