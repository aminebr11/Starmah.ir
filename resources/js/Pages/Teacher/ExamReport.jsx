import { usePage, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const pctColor = (p) => p === null ? 'var(--muted)' : p >= 75 ? '#16a34a' : p >= 50 ? '#d97706' : '#dc2626';
const SCORE_OPTS = [[1, '✅ کامل'], [0.5, '➗ نیمه'], [0, '❌ غلط']];

/** کارنامه و تحلیل نتایج یک آزمون — میانگین، درصد قبولی، توزیع نمرات و مقایسه‌ی دانش‌آموزان. */
export default function ExamReport() {
    const { exam, classroom, rows = [], summary = {}, buckets = {}, questionStats = [], hasDesc = false, printedAt } = usePage().props;
    const maxBucket = Math.max(1, ...Object.values(buckets));
    const descStudents = rows.filter((r) => r.done && (r.descAnswers || []).length > 0);

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

            {/* تحلیل سؤال‌به‌سؤال — نقاط ضعفِ کلاس */}
            {questionStats.length > 0 && (
                <div className="panel">
                    <h3 style={{ marginTop: 0 }}>🔍 تحلیل سؤال‌به‌سؤال (نقاط ضعف کلاس)</h3>
                    <p style={{ color: 'var(--muted)', fontSize: 13 }}>سؤال‌هایی که بیشترین اشتباه را داشته‌اند، ضعفِ مشترکِ کلاس‌اند و برای مرور مناسب‌اند.</p>
                    <div style={{ display: 'grid', gap: 8 }}>
                        {questionStats.map((q) => <QStat key={q.i} q={q} />)}
                    </div>
                </div>
            )}

            {/* پاسخِ هر دانش‌آموز به هر سؤال */}
            <div className="panel no-print">
                <h3 style={{ marginTop: 0 }}>🧑‍🎓 پاسخِ هر دانش‌آموز به هر سؤال</h3>
                {rows.filter((r) => r.done).length === 0 ? <p style={{ color: 'var(--muted)' }}>هنوز کسی شرکت نکرده است.</p>
                    : rows.filter((r) => r.done).map((r) => <StudentAnswers key={r.id} r={r} />)}
            </div>

            {/* تصحیح دستیِ پاسخ‌های تشریحی */}
            {hasDesc && (
                <div className="panel no-print">
                    <h3 style={{ marginTop: 0 }}>✍️ تصحیح پاسخ‌های تشریحی</h3>
                    {descStudents.length === 0 ? <p style={{ color: 'var(--muted)' }}>هنوز کسی پاسخ تشریحی نداده است.</p>
                        : descStudents.map((r) => <DescGrader key={r.id} exam={exam} r={r} />)}
                </div>
            )}
        </DashLayout>
    );
}

/** تصحیح پاسخ‌های تشریحیِ یک دانش‌آموز. */
function DescGrader({ exam, r }) {
    const [open, setOpen] = useState(false);
    const [scores, setScores] = useState(() => Object.fromEntries(r.descAnswers.map((d) => [d.i, d.score ?? 1])));
    const [busy, setBusy] = useState(false);
    const save = () => {
        setBusy(true);
        router.post(route('teacher.exams.grade', exam.id),
            { student_id: r.id, scores: r.descAnswers.map((d) => ({ i: d.i, score: scores[d.i] ?? 0 })) },
            { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setOpen(false) });
    };
    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 12, padding: 12, marginBottom: 10 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                <b>{r.name}</b>
                {r.descGraded ? <span className="tag tag-ok" style={{ fontSize: 11 }}>تصحیح‌شده</span> : <span className="tag tag-warn" style={{ fontSize: 11 }}>در انتظار تصحیح</span>}
                <span style={{ color: 'var(--muted)', fontSize: 12 }}>نمره‌ی فعلی: {fa(r.score)} از {fa(r.max)}</span>
                <button onClick={() => setOpen(!open)} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>{open ? 'بستن' : '✍️ تصحیح'}</button>
            </div>
            {open && (
                <div style={{ marginTop: 10 }}>
                    {r.descAnswers.map((d) => (
                        <div key={d.i} style={{ background: 'var(--cream)', borderRadius: 10, padding: 12, marginBottom: 8 }}>
                            <div style={{ fontWeight: 700, fontSize: 13, marginBottom: 4 }}>❓ {d.prompt}</div>
                            <div style={{ background: '#fff', border: '1px solid var(--line)', borderRadius: 8, padding: '8px 10px', fontSize: 14, minHeight: 40, whiteSpace: 'pre-wrap' }}>{d.answer || <span style={{ color: 'var(--muted)' }}>— بدون پاسخ —</span>}</div>
                            <div style={{ display: 'flex', gap: 6, marginTop: 8 }}>
                                {SCORE_OPTS.map(([v, t]) => (
                                    <button key={v} type="button" onClick={() => setScores((s) => ({ ...s, [d.i]: v }))}
                                        className={`tag ${scores[d.i] === v ? 'tag-ok' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '7px 12px' }}>{t}</button>
                                ))}
                            </div>
                        </div>
                    ))}
                    <button onClick={save} disabled={busy} className="btn btn-sm">💾 ثبت تصحیح و اعلام نتیجه</button>
                </div>
            )}
        </div>
    );
}

/** آمارِ یک سؤال در کل کلاس + فهرست دانش‌آموزانِ اشتباه. */
function QStat({ q }) {
    const [open, setOpen] = useState(false);
    const answered = q.correct + q.wrong;
    const pct = q.pct;
    const barColor = pct == null ? '#94a3b8' : pct >= 70 ? '#16a34a' : pct >= 40 ? '#f0952e' : '#dc2626';
    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 12, padding: 12 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                <span style={{ fontWeight: 800, color: 'var(--muted)' }}>سؤال {fa(q.i + 1)}</span>
                <span style={{ fontWeight: 700, fontSize: 13.5, flex: 1, minWidth: 160 }}>{q.prompt}</span>
                {q.type === 'desc'
                    ? <span className="tag" style={{ background: '#fef3c7', color: '#b45309' }}>تشریحی</span>
                    : <>
                        <span className="tag tag-ok" style={{ fontSize: 11 }}>✅ {fa(q.correct)}</span>
                        <span className="tag" style={{ background: '#fee2e2', color: '#b91c1c', fontSize: 11 }}>❌ {fa(q.wrong)}</span>
                        {q.blank > 0 && <span className="tag tag-warn" style={{ fontSize: 11 }}>بی‌پاسخ {fa(q.blank)}</span>}
                        <b style={{ color: barColor }}>{pct == null ? '—' : `${fa(pct)}٪`}</b>
                    </>}
            </div>
            {q.type !== 'desc' && answered > 0 && (
                <div style={{ height: 8, background: '#eef2f8', borderRadius: 6, overflow: 'hidden', marginTop: 8 }}>
                    <div style={{ height: '100%', width: `${pct}%`, background: barColor }} />
                </div>
            )}
            {q.type !== 'desc' && q.wrong > 0 && (
                <div style={{ marginTop: 6 }}>
                    <button onClick={() => setOpen(!open)} className="btn btn-ghost btn-sm">{open ? 'بستن' : `👀 ${fa(q.wrong)} نفر اشتباه زدند`}</button>
                    {open && <div style={{ fontSize: 12.5, color: 'var(--muted)', marginTop: 4 }}>{q.wrongNames.join('، ')}</div>}
                </div>
            )}
        </div>
    );
}

/** پاسخِ یک دانش‌آموز به همه‌ی سؤال‌ها (درست/نادرست/بی‌پاسخ). */
function StudentAnswers({ r }) {
    const [open, setOpen] = useState(false);
    const wrongCount = (r.perQuestion || []).filter((q) => q.ok === false).length;
    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 12, padding: 12, marginBottom: 8 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                <b>{r.name}</b>
                <span style={{ color: 'var(--muted)', fontSize: 12 }}>نمره: {fa(r.score)} از {fa(r.max)} ({fa(r.percent)}٪)</span>
                {wrongCount > 0 && <span className="tag" style={{ background: '#fee2e2', color: '#b91c1c', fontSize: 11 }}>{fa(wrongCount)} اشتباه</span>}
                <button onClick={() => setOpen(!open)} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>{open ? 'بستن' : 'مشاهده پاسخ‌ها'}</button>
            </div>
            {open && (
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 8 }}>
                    {(r.perQuestion || []).map((q) => {
                        const bg = q.type === 'desc' ? '#fef3c7' : q.ok ? '#dcfce7' : q.mine === '' ? '#f1f5f9' : '#fee2e2';
                        const col = q.type === 'desc' ? '#b45309' : q.ok ? '#166534' : q.mine === '' ? '#64748b' : '#b91c1c';
                        const icon = q.type === 'desc' ? '✍️' : q.ok ? '✅' : q.mine === '' ? '—' : '❌';
                        return (
                            <span key={q.i} title={q.mine ? `پاسخ: ${q.mine}` : 'بی‌پاسخ'} style={{ display: 'inline-flex', alignItems: 'center', gap: 4, background: bg, color: col, borderRadius: 8, padding: '5px 9px', fontSize: 12, fontWeight: 700 }}>
                                {fa(q.i + 1)} {icon}
                            </span>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
