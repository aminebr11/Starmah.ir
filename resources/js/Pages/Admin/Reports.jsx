import { usePage } from '@inertiajs/react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';
import { AreaTrend, PAL } from '@/Components/Charts';
import { useSort, SortTh } from '@/lib/useSort';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
function Card({ ic, lbl, v }) { return <div className="dcard"><div className="ic">{ic}</div><div className="lbl">{lbl}</div><div className="val">{fa(v)}</div></div>; }

export default function Reports() {
    const { report, trend = [] } = usePage().props;
    const rs = useSort(report?.per_school, { name: 'name', city: 'city', plan: 'plan', students: 'students', teachers: 'teachers', classes: 'classes', points: 'points' }, { id: 'admin-reports-schools', firstDir: { students: 'desc', teachers: 'desc', classes: 'desc', points: 'desc' } });
    if (!report) return <DashLayout title="گزارش‌ها" roleLabel="ادمین کل" menu={adminMenu} active="reports"><div className="panel">داده‌ای نیست.</div></DashLayout>;
    const t = report.totals;
    return (
        <DashLayout title="گزارش‌های پلتفرم" roleLabel="ادمین کل" menu={adminMenu} active="reports">
            <div className="dash-cards" style={{ gridTemplateColumns: 'repeat(5,1fr)' }}>
                <Card ic="🏫" lbl="مدارس" v={t.schools} />
                <Card ic="🎓" lbl="دانش‌آموزان" v={t.students} />
                <Card ic="👩‍🏫" lbl="معلم‌ها" v={t.teachers} />
                <Card ic="🎯" lbl="فعالیت‌ها" v={t.activities} />
                <Card ic="⭐" lbl="مجموع امتیاز" v={t.points} />
            </div>
            <div className="panel">
                <h3>📈 نبضِ پلتفرم — امتیازِ روزانه‌ی همه‌ی مدارس (۲۸ روز)</h3>
                <AreaTrend data={trend} color={PAL[0]} />
            </div>
            <div className="panel">
                <h3>🏫 عملکرد مدارس</h3>
                <table className="tbl">
                    <thead><tr><th>#</th><SortTh s={rs} k="name">مدرسه</SortTh><SortTh s={rs} k="city">شهر</SortTh><SortTh s={rs} k="plan">پلن</SortTh><SortTh s={rs} k="students">دانش‌آموز</SortTh><SortTh s={rs} k="teachers">معلم</SortTh><SortTh s={rs} k="classes">کلاس</SortTh><SortTh s={rs} k="points">مجموع امتیاز</SortTh></tr></thead>
                    <tbody>
                        {rs.sorted.map((s, i) => (
                            <tr key={s.id}>
                                <td>{fa(i + 1)}</td><td style={{ fontWeight: 700 }}>{s.name}</td><td>{s.city ?? '—'}</td>
                                <td><span className="tag tag-info">{s.plan}</span></td>
                                <td>{fa(s.students)}</td><td>{fa(s.teachers)}</td><td>{fa(s.classes)}</td>
                                <td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(s.points)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </DashLayout>
    );
}
