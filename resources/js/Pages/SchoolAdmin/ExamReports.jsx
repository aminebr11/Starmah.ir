import { usePage } from '@inertiajs/react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const pctColor = (p) => p == null ? 'var(--muted)' : p >= 75 ? '#16a34a' : p >= 50 ? '#d97706' : '#dc2626';

/** مدیر: گزارش عملکردِ آزمون‌های همه‌ی کلاس‌ها. */
export default function ExamReports() {
    const { exams = [], summary = {} } = usePage().props;

    return (
        <DashLayout title="گزارش آزمون‌ها" roleLabel="مدیر مدرسه" menu={schoolMenu} active="examreports"
            actions={<button onClick={() => window.print()} className="btn btn-sm no-print">🖨️ چاپ</button>}>
            <div className="report-cards">
                {[['📝 آزمون‌ها', summary.exams, 'var(--navy-700)'], ['✅ شرکت‌ها', summary.submissions, '#16a34a'], ['📈 میانگین کل', `${fa(summary.avg)}٪`, '#2563eb']].map(([t, v, c]) => (
                    <div key={t} className="report-card"><div className="rc-val" style={{ color: c }}>{typeof v === 'number' ? fa(v) : v}</div><div className="rc-lbl">{t}</div></div>
                ))}
            </div>

            <div className="panel">
                <h3 style={{ marginTop: 0 }}>📊 عملکرد آزمون‌ها به تفکیک کلاس</h3>
                {exams.length === 0 ? <p style={{ color: 'var(--muted)' }}>هنوز آزمونی ساخته نشده.</p> : (
                    <div style={{ overflowX: 'auto' }}>
                        <table className="tbl">
                            <thead><tr><th>آزمون</th><th>کلاس</th><th>معلم</th><th>تاریخ</th><th style={{ textAlign: 'center' }}>شرکت‌کننده</th><th style={{ textAlign: 'center' }}>میانگین</th><th style={{ textAlign: 'center' }}>درصد قبولی</th></tr></thead>
                            <tbody>
                                {exams.map((e) => (
                                    <tr key={e.id}>
                                        <td style={{ fontWeight: 700 }}>{e.title}</td>
                                        <td>{e.class || '—'}</td><td>{e.teacher || '—'}</td>
                                        <td style={{ color: 'var(--muted)', fontSize: 12.5 }}>{e.jdate}</td>
                                        <td style={{ textAlign: 'center' }}>{fa(e.taken)} / {fa(e.total)}</td>
                                        <td style={{ textAlign: 'center', fontWeight: 800, color: pctColor(e.avg) }}>{e.avg != null ? `${fa(e.avg)}٪` : '—'}</td>
                                        <td style={{ textAlign: 'center' }}>{e.pass != null ? <span className="report-pct" style={{ background: e.pass >= 75 ? '#dcfce7' : e.pass >= 50 ? '#fef9c3' : '#fee2e2', color: pctColor(e.pass) }}>{fa(e.pass)}٪</span> : '—'}</td>
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
