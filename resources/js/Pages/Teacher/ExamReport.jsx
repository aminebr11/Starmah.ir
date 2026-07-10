import { usePage, Link } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const pctColor = (p) => p === null ? 'var(--muted)' : p >= 75 ? '#16a34a' : p >= 50 ? '#d97706' : '#dc2626';

/** کارنامه و تحلیل نتایج یک آزمون — میانگین، درصد قبولی، توزیع نمرات و مقایسه‌ی دانش‌آموزان. */
export default function ExamReport() {
    const { exam, classroom, rows = [], summary = {}, buckets = {}, printedAt } = usePage().props;
    const maxBucket = Math.max(1, ...Object.values(buckets));

    return (
        <DashLayout title="نتایج آزمون" roleLabel="معلم" menu={teacherMenu} active="exams"
            actions={<><Link href={route('teacher.exams')} className="btn btn-ghost btn-sm no-print">← بازگشت</Link><button onClick={() => window.print()} className="btn btn-sm no-print">🖨️ چاپ</button></>}>

            <div className="report-print-head">
                <img src="/brand/logo-emblem.png" alt="" />
                <div style={{ flex: 1, textAlign: 'center' }}>
                    <div className="rph-title">کارنامه‌ی آزمون</div>
                    <div className="rph-school">{exam?.title}</div>
                </div>
                <div className="rph-meta"><div>کلاس: <b>{classroom?.name || '—'}</b></div><div>تاریخ چاپ: {printedAt}</div></div>
            </div>

            <div className="panel no-print" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <h3 style={{ margin: 0 }}>📊 {exam?.title}</h3>
                <span className="tag tag-info">{classroom?.name}</span>
            </div>

            {/* خلاصه */}
            <div className="report-cards">
                {[['👥 کل', summary.total, 'var(--navy-700)'], ['✅ شرکت‌کننده', summary.taken, '#16a34a'],
                  ['📈 میانگین', `${fa(summary.avg)}٪`, '#2563eb'], ['🏆 بیشترین', `${fa(summary.max)}٪`, '#16a34a'],
                  ['📉 کمترین', `${fa(summary.min)}٪`, '#dc2626'], ['🎯 درصد قبولی', `${fa(summary.passRate)}٪`, '#7c3aed']].map(([t, v, c]) => (
                    <div key={t} className="report-card"><div className="rc-val" style={{ color: c }}>{typeof v === 'number' ? fa(v) : v}</div><div className="rc-lbl">{t}</div></div>
                ))}
            </div>

            {/* توزیع نمرات */}
            <div className="panel printable">
                <h3 className="no-print" style={{ marginTop: 0 }}>📊 توزیع نمرات</h3>
                <h3 className="print-title" style={{ marginTop: 0 }}>توزیع نمرات</h3>
                <div className="report-chart" style={{ height: 150 }}>
                    {Object.entries(buckets).map(([label, count]) => (
                        <div key={label} className="rchart-col">
                            <div className="rchart-bars">
                                <div style={{ height: `${count / maxBucket * 100}%`, background: 'linear-gradient(#1d4ed8,#3b82f6)', width: 26 }} title={`${count} نفر`} />
                            </div>
                            <div style={{ fontSize: 11, color: 'var(--muted)', marginTop: 6, writingMode: 'horizontal-tb' }}>{label}<br /><b style={{ color: 'var(--navy-700)' }}>{fa(count)}</b></div>
                        </div>
                    ))}
                </div>
            </div>

            {/* جدول مقایسه */}
            <div className="panel printable">
                <h3 className="no-print" style={{ marginTop: 0 }}>📋 نمرات دانش‌آموزان (مرتب‌شده)</h3>
                <div style={{ overflowX: 'auto' }}>
                    <table className="tbl">
                        <thead><tr><th>رتبه</th><th>دانش‌آموز</th><th style={{ textAlign: 'center' }}>نمره</th><th style={{ textAlign: 'center' }}>درصد</th><th style={{ textAlign: 'center' }}>وضعیت</th><th>زمان ارسال</th></tr></thead>
                        <tbody>
                            {rows.map((r, i) => (
                                <tr key={r.id}>
                                    <td>{r.done ? fa(i + 1) : '—'}</td>
                                    <td style={{ fontWeight: 700 }}>{r.name}</td>
                                    <td style={{ textAlign: 'center' }}>{r.done ? `${fa(r.score)} از ${fa(r.max)}` : '—'}</td>
                                    <td style={{ textAlign: 'center', fontWeight: 800, color: pctColor(r.percent) }}>{r.done ? `${fa(r.percent)}٪` : '—'}</td>
                                    <td style={{ textAlign: 'center' }}>
                                        {!r.done ? <span className="tag tag-warn">شرکت نکرده</span>
                                            : r.percent >= 50 ? <span className="tag tag-ok">قبول</span> : <span className="tag" style={{ background: '#fee2e2', color: '#b91c1c' }}>مردود</span>}
                                    </td>
                                    <td style={{ color: 'var(--muted)', fontSize: 12 }}>{r.jdate || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="report-signs"><div>امضای معلم<span /></div><div>امضای مدیر مدرسه<span /></div></div>
            </div>
        </DashLayout>
    );
}
