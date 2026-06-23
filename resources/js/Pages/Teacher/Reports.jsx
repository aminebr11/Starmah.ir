import { usePage } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Reports() {
    const { classroom, report } = usePage().props;
    if (!report) return <DashLayout title="گزارش کلاس" roleLabel="معلم" menu={teacherMenu} active="reports"><div className="panel"><p style={{ color: 'var(--muted)' }}>کلاسی برای گزارش نیست.</p></div></DashLayout>;

    const t = report.totals;
    const maxG = Math.max(1, ...report.by_group.map((g) => g.total));
    const maxT = Math.max(1, ...report.by_type.map((g) => g.points));

    return (
        <DashLayout title={`گزارش کلاس ${classroom?.name ?? ''}`} roleLabel="معلم" menu={teacherMenu} active="reports">
            <div className="dash-cards">
                <Card ic="🎓" lbl="دانش‌آموز" v={t.students} />
                <Card ic="⭐" lbl="مجموع امتیاز" v={t.points} />
                <Card ic="🎯" lbl="فعالیت‌ها" v={t.activities} />
                <Card ic="🔥" lbl="درگیری هفته" v={`${t.engagement}٪`} />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }} className="themes-grid">
                <div className="panel">
                    <h3>🏆 رقابت تیم‌ها</h3>
                    {report.by_group.map((g, i) => (
                        <Bar key={i} label={`${g.emoji} ${g.name}`} value={g.total} max={maxG} sub={`${fa(g.count)} نفر`} />
                    ))}
                </div>
                <div className="panel">
                    <h3>📊 امتیاز بر اساس نوع فعالیت</h3>
                    {report.by_type.length ? report.by_type.map((g, i) => (
                        <Bar key={i} label={g.label} value={g.points} max={maxT} sub={`${fa(g.count)} بار`} />
                    )) : <p style={{ color: 'var(--muted)' }}>هنوز امتیازی از فعالیت‌ها ثبت نشده.</p>}
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }} className="themes-grid">
                <div className="panel">
                    <h3>🥇 برترین‌ها</h3>
                    {report.top.map((s, i) => (
                        <Row key={s.id} i={i} name={s.name} v={s.xp} />
                    ))}
                </div>
                <div className="panel">
                    <h3>💪 نیازمند توجه</h3>
                    {report.needs_help.map((s, i) => (
                        <Row key={s.id} i={i} name={s.name} v={s.xp} tone="warn" />
                    ))}
                </div>
            </div>

            <div className="panel">
                <h3>👥 جدول کامل دانش‌آموزان</h3>
                <table className="tbl">
                    <thead><tr><th>#</th><th>نام</th><th>تیم</th><th>امتیاز</th><th>تسلط</th><th>فعالیت</th></tr></thead>
                    <tbody>
                        {report.per_student.map((s, i) => (
                            <tr key={s.id}><td>{fa(i + 1)}</td><td style={{ fontWeight: 700 }}>{s.name}</td>
                                <td>{s.emoji} {s.group}</td><td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(s.xp)}</td>
                                <td><span className={`tag ${s.mastery >= 70 ? 'tag-ok' : s.mastery >= 40 ? 'tag-warn' : 'tag-info'}`}>{fa(s.mastery)}٪</span></td>
                                <td>{fa(s.activities)}</td></tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </DashLayout>
    );
}

function Card({ ic, lbl, v }) {
    return <div className="dcard"><div className="ic">{ic}</div><div className="lbl">{lbl}</div><div className="val">{fa(v)}</div></div>;
}
function Bar({ label, value, max, sub }) {
    return (
        <div style={{ margin: '12px 0' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 5, fontSize: 13 }}>
                <b>{label}</b><span style={{ color: 'var(--muted)' }}>{fa(value)} {sub ? `· ${sub}` : ''}</span>
            </div>
            <div style={{ height: 10, borderRadius: 8, background: '#eef2f8', overflow: 'hidden' }}>
                <div style={{ width: `${(value / max) * 100}%`, height: '100%', background: 'linear-gradient(90deg,var(--gold),var(--gold-2))' }} />
            </div>
        </div>
    );
}
function Row({ i, name, v, tone }) {
    return (
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 0', borderBottom: '1px solid var(--line)' }}>
            <span><b style={{ color: i === 0 ? 'var(--gold-2)' : 'var(--muted)' }}>{fa(i + 1)}.</b> {name}</span>
            <span className={`tag ${tone === 'warn' ? 'tag-warn' : 'tag-ok'}`}>{fa(v)} امتیاز</span>
        </div>
    );
}
