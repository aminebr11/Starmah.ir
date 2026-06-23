import { usePage } from '@inertiajs/react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
function Card({ ic, lbl, v }) { return <div className="dcard"><div className="ic">{ic}</div><div className="lbl">{lbl}</div><div className="val">{fa(v)}</div></div>; }

export default function Reports() {
    const { report } = usePage().props;
    if (!report) return <DashLayout title="گزارش‌ها" roleLabel="مدیر مدرسه" menu={schoolMenu} active="reports"><div className="panel">داده‌ای نیست.</div></DashLayout>;
    const t = report.totals;
    return (
        <DashLayout title="گزارش‌ها و تحلیل عملکرد" roleLabel="مدیر مدرسه" menu={schoolMenu} active="reports">
            <div className="dash-cards">
                <Card ic="👩‍🏫" lbl="معلم‌ها" v={t.teachers} />
                <Card ic="🎓" lbl="دانش‌آموزان" v={t.students} />
                <Card ic="🏛️" lbl="کلاس‌ها" v={t.classes} />
                <Card ic="⭐" lbl="مجموع امتیاز مدرسه" v={t.points} />
            </div>
            <div className="panel">
                <h3>📈 تحلیل عملکرد هر معلم</h3>
                <p style={{ color: 'var(--muted)', marginTop: -6 }}>بر اساس فعالیت‌سازی و درگیری دانش‌آموزان</p>
                <table className="tbl">
                    <thead><tr><th>#</th><th>معلم</th><th>کلاس</th><th>دانش‌آموز</th><th>فعالیت</th><th>امتیاز</th><th>درگیری</th><th>عملکرد</th></tr></thead>
                    <tbody>
                        {report.per_teacher.map((tt, i) => (
                            <tr key={tt.id}>
                                <td>{fa(i + 1)}</td><td style={{ fontWeight: 700 }}>{tt.name}</td>
                                <td>{tt.class ?? '—'}</td><td>{fa(tt.students)}</td><td>{fa(tt.activities)}</td>
                                <td style={{ color: 'var(--gold-2)', fontWeight: 700 }}>{fa(tt.points)}</td>
                                <td>{fa(tt.engagement)}٪</td>
                                <td><div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                    <div style={{ flex: 1, height: 8, borderRadius: 6, background: '#eef2f8', overflow: 'hidden', minWidth: 60 }}>
                                        <div style={{ width: `${tt.score}%`, height: '100%', background: tt.score >= 60 ? 'linear-gradient(90deg,#2bb673,#7be05a)' : 'linear-gradient(90deg,var(--gold),var(--gold-2))' }} />
                                    </div><span style={{ fontSize: 12, fontWeight: 700 }}>{fa(tt.score)}</span>
                                </div></td>
                            </tr>
                        ))}
                        {report.per_teacher.length === 0 && <tr><td colSpan="8" style={{ color: 'var(--muted)' }}>هنوز معلمی ثبت نشده.</td></tr>}
                    </tbody>
                </table>
            </div>
        </DashLayout>
    );
}
