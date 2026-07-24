import { usePage } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import { AreaTrend, Donut, Heatmap, PAL } from '@/Components/Charts';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Reports() {
    const { classroom, report, crossSubject = {}, studentCount = 0, trend = [], heatmap = null } = usePage().props;
    if (!report) return <DashLayout title="گزارش کلاس" roleLabel="معلم" menu={teacherMenu} active="reports"><div className="panel"><p style={{ color: 'var(--muted)' }}>کلاسی برای گزارش نیست.</p></div></DashLayout>;

    const t = report.totals;
    const maxG = Math.max(1, ...report.by_group.map((g) => g.total));

    return (
        <DashLayout title={`گزارش کلاس ${classroom?.name ?? ''}`} roleLabel="معلم" menu={teacherMenu} active="reports"
            actions={<a href="/print/class" target="_blank" rel="noopener" className="btn btn-sm">🖨️ چاپِ گزارشِ کلاس</a>}>
            <div className="dash-cards">
                <Card ic="🎓" lbl="دانش‌آموز" v={t.students} />
                <Card ic="⭐" lbl="مجموع امتیاز" v={t.points} />
                <Card ic="🎯" lbl="فعالیت‌ها" v={t.activities} />
                <Card ic="🔥" lbl="درگیری هفته" v={`${t.engagement}٪`} />
            </div>

            {/* روند و عادتِ کلاس — نمای BI */}
            <div style={{ display: 'grid', gridTemplateColumns: '1.5fr 1fr', gap: 20 }} className="themes-grid">
                <div className="panel">
                    <h3>📈 نبضِ کلاس — امتیازِ روزانه (۲۸ روز)</h3>
                    <p style={{ color: 'var(--muted)', fontSize: 12, margin: '0 0 8px' }}>مجموعِ امتیازِ همه‌ی دانش‌آموزانِ کلاس در هر روز — افتِ ناگهانی یعنی کلاس سرد شده.</p>
                    <AreaTrend data={trend} color={PAL[0]} />
                </div>
                <div className="panel">
                    <h3>🗓️ عادتِ فعالیتِ کلاس (۶ هفته)</h3>
                    <p style={{ color: 'var(--muted)', fontSize: 12, margin: '0 0 10px' }}>کدام روزهای هفته کلاس فعال‌تر است؟</p>
                    {heatmap ? <Heatmap weeks={heatmap.weeks} days={heatmap.days} legend="فعالیت" /> : <p style={{ color: 'var(--muted)' }}>داده‌ای نیست.</p>}
                </div>
            </div>

            <TeacherCrossSubject data={crossSubject} studentCount={studentCount} />

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }} className="themes-grid">
                <div className="panel">
                    <h3>🏆 رقابت تیم‌ها</h3>
                    {report.by_group.map((g, i) => (
                        <Bar key={i} label={`${g.emoji} ${g.name}`} value={g.total} max={maxG} sub={`${fa(g.count)} نفر`} />
                    ))}
                </div>
                <div className="panel">
                    <h3>🧩 ترکیبِ امتیاز بر اساس نوعِ فعالیت</h3>
                    {report.by_type.length
                        ? <Donut items={report.by_type.map((g) => ({ label: g.label, value: g.points }))} centerLabel="مجموع" />
                        : <p style={{ color: 'var(--muted)' }}>هنوز امتیازی از فعالیت‌ها ثبت نشده.</p>}
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
                    <thead><tr><th>#</th><th>نام</th><th>تیم</th><th>امتیاز</th><th>تسلط</th><th>فعالیت</th><th>کارنامه</th></tr></thead>
                    <tbody>
                        {report.per_student.map((s, i) => (
                            <tr key={s.id}><td>{fa(i + 1)}</td><td style={{ fontWeight: 700 }}>{s.name}</td>
                                <td>{s.emoji} {s.group}</td><td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(s.xp)}</td>
                                <td><span className={`tag ${s.mastery >= 70 ? 'tag-ok' : s.mastery >= 40 ? 'tag-warn' : 'tag-info'}`}>{fa(s.mastery)}٪</span></td>
                                <td>{fa(s.activities)}</td>
                                <td><a href={`/print/student/${s.id}`} target="_blank" rel="noopener" title="چاپِ کارنامه‌ی جامع" className="btn btn-ghost btn-sm">🖨️</a></td></tr>
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

const chue = (p) => p == null ? '#8896ad' : p >= 70 ? '#2bb673' : p >= 50 ? '#e8862e' : '#e8505b';
const CSEC = { smart: '🧠 آزمون', game: '🎮 بازی', mission: '🎯 مأموریت', worksheet: '🎨 کاربرگ' };

/** تحلیلِ درس‌به‌درسِ همه‌ی کلاس‌ها (کلِ دانش‌آموزانِ معلم). */
function TeacherCrossSubject({ data, studentCount }) {
    const subjects = data?.subjects || [];
    return (
        <div className="panel">
            <h3>📚 تحلیل درس‌به‌درسِ همه‌ی کلاس‌ها ({fa(studentCount)} دانش‌آموز)</h3>
            {subjects.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز فعالیتِ نمره‌داری در بخش‌ها ثبت نشده است.</p>}
            {subjects.length > 0 && (
                <>
                    <p style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 0 }}>میانگینِ کلِ درس‌ها: <b style={{ color: chue(data.overall) }}>{fa(data.overall)}٪</b> · مجموعِ فعالیت‌ها: {fa(data.activities)} — درس‌های ضعیف‌تر بالاترند.</p>
                    <div style={{ overflowX: 'auto' }}>
                        <table className="tbl">
                            <thead><tr><th>درس</th><th>میانگین</th><th>فعالیت</th><th>تفکیک بخش‌ها</th></tr></thead>
                            <tbody>
                                {subjects.map((s, i) => (
                                    <tr key={i}>
                                        <td style={{ fontWeight: 700 }}>{s.subject}</td>
                                        <td><span style={{ display: 'inline-block', minWidth: 46, textAlign: 'center', borderRadius: 8, padding: '3px 8px', color: '#fff', fontWeight: 800, background: chue(s.pct) }}>{s.pct == null ? '—' : `${fa(s.pct)}٪`}</span></td>
                                        <td>{fa(s.activities)}</td>
                                        <td style={{ fontSize: 12 }}>
                                            {Object.entries(s.sections).map(([k, v]) => (
                                                <span key={k} style={{ display: 'inline-block', background: '#eef2f8', borderRadius: 12, padding: '2px 8px', margin: '0 3px 3px 0' }}>
                                                    {CSEC[k]}{v.pct != null ? ` ${fa(v.pct)}٪` : ''} ({fa(v.count)})
                                                </span>
                                            ))}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </div>
    );
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
