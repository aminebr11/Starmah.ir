import { usePage, Link } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const mins = (s) => s >= 60 ? `${fa(Math.floor(s / 60))}د ${fa(s % 60)}ث` : `${fa(s)}ث`;

export default function GameReport() {
    const { game = {}, summary = {}, rows = [], hardQuestions = [] } = usePage().props;

    return (
        <DashLayout title={`گزارش: ${game.title}`} roleLabel="معلم" menu={teacherMenu} active="studio">
            <div className="panel">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <h3 style={{ margin: 0 }}>📊 گزارش بازی «{game.title}»</h3>
                    <span className="tag tag-info">{game.template}</span>
                    <Link href={route('teacher.studio')} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>← بازگشت</Link>
                    <button onClick={() => window.print()} className="btn btn-sm">🖨️ چاپ/PDF</button>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(130px,1fr))', gap: 10, marginTop: 14 }}>
                    <Kpi v={fa(summary.started)} l="شروع کرده" c="#3d7bf0" />
                    <Kpi v={fa(summary.completed)} l="تکمیل کرده" c="#2bb673" />
                    <Kpi v={`${fa(summary.avg)}٪`} l="میانگین دقت" c="#a24cf0" />
                    <Kpi v={mins(summary.avgDuration || 0)} l="میانگین زمان" c="#e8862e" />
                    <Kpi v={fa(summary.hints)} l="استفاده از راهنما" c="#0ea5b7" />
                </div>
            </div>

            {hardQuestions.length > 0 && (
                <div className="panel">
                    <h3 style={{ fontSize: 15 }}>🔧 سؤال‌های دشوار (بیشترین پاسخ غلط)</h3>
                    <div style={{ display: 'grid', gap: 8, marginTop: 8 }}>
                        {hardQuestions.map((h, i) => (
                            <div key={i} style={{ display: 'flex', justifyContent: 'space-between', gap: 10, padding: '8px 12px', background: '#fff5f5', borderRadius: 10 }}>
                                <span style={{ fontSize: 13.5 }}>{h.prompt}</span>
                                <b style={{ color: '#e8505b', flex: 'none' }}>{fa(h.wrong)} پاسخ غلط</b>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="panel">
                <h3 style={{ fontSize: 15 }}>👥 عملکرد دانش‌آموزان</h3>
                {rows.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز کسی این بازی را انجام نداده.</p>}
                {rows.length > 0 && (
                    <div style={{ overflowX: 'auto' }}>
                        <table className="tbl">
                            <thead><tr><th>#</th><th>نام</th><th>امتیاز</th><th>دقت</th><th>وضعیت</th><th>راهنما</th><th>زمان</th></tr></thead>
                            <tbody>
                                {rows.map((r, i) => (
                                    <tr key={i}>
                                        <td>{fa(i + 1)}</td>
                                        <td style={{ fontWeight: 700 }}>{r.name}</td>
                                        <td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(r.score)}/{fa(r.max)}</td>
                                        <td><span className={`tag ${r.percent >= 50 ? 'tag-ok' : 'tag-warn'}`}>{fa(r.percent)}٪</span></td>
                                        <td>{r.status === 'completed' ? '✅ تکمیل' : '⏳ ناتمام'}</td>
                                        <td>{fa(r.hints)}</td>
                                        <td>{mins(r.duration || 0)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </DashLayout>
    );
}
function Kpi({ v, l, c }) {
    return <div style={{ borderRadius: 14, padding: 14, color: '#fff', textAlign: 'center', background: `linear-gradient(135deg,${c},${c}bb)` }}><b style={{ fontSize: 22, display: 'block' }}>{v}</b><span style={{ fontSize: 11.5, opacity: .9 }}>{l}</span></div>;
}
