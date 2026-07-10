import { usePage, router, Link } from '@inertiajs/react';
import { useState } from 'react';
import DateObjectImport from 'react-date-object';
import persianImport from 'react-date-object/calendars/persian';
import persianFaImport from 'react-date-object/locales/persian_fa';
import DashLayout, { teacherMenu, schoolMenu } from '@/Layouts/DashLayout';

const unwrap = (m) => (m && m.default) ? m.default : m;
const DateObject = unwrap(DateObjectImport);
const persian = unwrap(persianImport);
const persian_fa = unwrap(persianFaImport);
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const WD = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']; // شنبه..جمعه

/** فرم خالی حضور و غیاب ماهانه برای چاپ دستی. */
export default function MonthlySheet() {
    const { role, routes, classrooms = [], classroomId, classroom, students = [], meta = {} } = usePage().props;
    const menu = role === 'teacher' ? teacherMenu : schoolMenu;
    const [offset, setOffset] = useState(0); // جابه‌جایی ماه نسبت به ماه جاری

    // ماه شمسی هدف
    const base = new DateObject({ calendar: persian, locale: persian_fa });
    base.add(offset, 'months');
    const monthName = base.month.name;
    const year = base.year;
    const daysInMonth = base.month.length;
    // شماره‌ی روز هفته‌ی هر روز ماه (۰=شنبه)
    const first = new DateObject(base); first.day = 1;
    const startWd = first.weekDay.index; // 0=شنبه در persian locale
    const days = Array.from({ length: daysInMonth }, (_, i) => ({
        n: i + 1,
        wd: WD[(startWd + i) % 7],
        isFri: (startWd + i) % 7 === 6,
    }));

    const changeClass = (cid) => router.get(route(routes.monthlySheet), { classroom_id: cid }, { preserveState: false });

    return (
        <DashLayout title="فرم خالی ماهانه" roleLabel={role === 'teacher' ? 'معلم' : 'مدیر مدرسه'} menu={menu} active="attendance"
            actions={<>
                <Link href={route(routes.index)} className="btn btn-ghost btn-sm no-print">← بازگشت به ثبت</Link>
                <button onClick={() => window.print()} className="btn btn-sm no-print">🖨️ چاپ فرم</button>
            </>}>

            {/* کنترل‌ها (چاپ نمی‌شوند) */}
            <div className="panel no-print" style={{ display: 'flex', gap: 12, alignItems: 'center', flexWrap: 'wrap' }}>
                <b>ماه:</b>
                <button onClick={() => setOffset(offset - 1)} className="btn btn-ghost btn-sm">◄ ماه قبل</button>
                <span style={{ fontWeight: 800, minWidth: 120, textAlign: 'center' }}>{monthName} {fa(year)}</span>
                <button onClick={() => setOffset(offset + 1)} className="btn btn-ghost btn-sm">ماه بعد ►</button>
                {classrooms.length > 1 && (
                    <select value={classroomId} onChange={(e) => changeClass(e.target.value)} className="input" style={{ width: 'auto' }}>
                        {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                )}
                <span style={{ color: 'var(--muted)', fontSize: 13 }}>این فرم را چاپ کنید، دستی پر کنید و سپس در سامانه وارد کنید.</span>
            </div>

            {!classroom ? <div className="panel">کلاسی یافت نشد.</div> : (
                <div className="panel printable msheet-wrap">
                    {/* سربرگ */}
                    <div className="msheet-head">
                        <img src="/brand/logo-emblem.png" alt="" />
                        <div style={{ flex: 1, textAlign: 'center' }}>
                            <div className="msheet-title">فرم حضور و غیاب ماهانه</div>
                            <div className="msheet-sub">{meta.school || ''} — کلاس {classroom.name}{classroom.grade ? ` (پایه ${classroom.grade})` : ''}</div>
                        </div>
                        <div className="msheet-meta">
                            <div>ماه: <b>{monthName} {fa(year)}</b></div>
                            {meta.teacher && <div>معلم: <b>{meta.teacher}</b></div>}
                        </div>
                    </div>

                    <div style={{ overflowX: 'auto' }}>
                        <table className="msheet-tbl">
                            <thead>
                                <tr>
                                    <th className="msheet-name">نام دانش‌آموز</th>
                                    {days.map((d) => (
                                        <th key={d.n} className={d.isFri ? 'msheet-fri' : ''}>
                                            <div className="msheet-wd">{d.wd}</div>{fa(d.n)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {students.map((name, i) => (
                                    <tr key={i}>
                                        <td className="msheet-name">{fa(i + 1)}. {name}</td>
                                        {days.map((d) => <td key={d.n} className={d.isFri ? 'msheet-fri' : ''} />)}
                                    </tr>
                                ))}
                                {students.length === 0 && <tr><td className="msheet-name" colSpan={days.length + 1} style={{ textAlign: 'center', color: 'var(--muted)' }}>دانش‌آموزی در کلاس نیست.</td></tr>}
                            </tbody>
                        </table>
                    </div>

                    <div className="msheet-legend">راهنما: ح = حاضر · غ = غایب · ت = تأخیر · م = مرخصی</div>
                    <div className="report-signs" style={{ display: 'flex' }}><div>امضای معلم<span /></div><div>امضای مدیر مدرسه<span /></div></div>
                </div>
            )}
        </DashLayout>
    );
}
