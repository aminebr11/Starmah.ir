import { useState } from 'react';
import { usePage, router, Link } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const mins = (s) => s >= 60 ? `${fa(Math.floor(s / 60))}د` : `${fa(s)}ث`;

export default function SmartExamReport() {
    const { exam = {}, summary = {}, rows = [], perQuestion = [], studentAnswers = [], hard = [], weakTopics = [], buckets = {}, printedAt, gamesEnabled } = usePage().props;
    const maxB = Math.max(1, ...Object.values(buckets));

    return (
        <DashLayout title={`گزارش: ${exam.title}`} roleLabel="معلم" menu={teacherMenu} active="smart">
            <div className="smart-scope">
                <div className="smart-panel">
                    <div className="smart-h">📊 گزارش هوشمند «{exam.title}» <span className="smart-badge">{exam.subject || ''}</span>
                        <span style={{ marginInlineStart: 'auto', display: 'flex', gap: 6 }}>
                            {gamesEnabled && <button onClick={() => router.post(route('teacher.smart.buildgame', exam.id), {}, { preserveScroll: true })} className="smart-btn sm">🎮 ساخت بازی جبرانی</button>}
                            <button onClick={() => window.print()} className="smart-btn ghost sm">🖨️ چاپ/PDF</button>
                            <Link href={route('teacher.smart.lab')} className="smart-btn ghost sm">← بازگشت</Link>
                        </span>
                    </div>
                    <div className="smart-kpis" style={{ marginTop: 14 }}>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#3d7bf0,#2555c0)' }}><b>{fa(summary.started)}/{fa(summary.targeted || summary.started)}</b><span>شرکت‌کننده</span></div>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#2bb673,#1a8a52)' }}><b>{fa(summary.completed)}</b><span>تکمیل‌شده</span></div>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#6d28d9,#4c1d95)' }}><b>{fa(summary.avg)}٪</b><span>میانگین</span></div>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#0ea5b7,#0a7d8a)' }}><b>{fa(summary.pass)}٪</b><span>قبولی</span></div>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#e8862e,#c06712)' }}><b>{mins(summary.avgDuration || 0)}</b><span>میانگین زمان</span></div>
                    </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(300px,1fr))', gap: 16 }}>
                    <div className="smart-panel">
                        <div className="smart-h" style={{ fontSize: 15 }}>📈 توزیع نمرات</div>
                        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 8, height: 130, marginTop: 12 }}>
                            {Object.entries(buckets).map(([k, v]) => (
                                <div key={k} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'flex-end', height: '100%', gap: 4 }}>
                                    <b style={{ fontSize: 12, color: 'var(--sm-p1)' }}>{fa(v)}</b>
                                    <div style={{ width: '100%', maxWidth: 40, height: `${(v / maxB) * 100}%`, minHeight: 3, borderRadius: '6px 6px 0 0', background: 'linear-gradient(180deg,#6d28d9,#4c1d95)' }} />
                                    <span style={{ fontSize: 10, color: '#7c7595' }}>{k}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="smart-panel">
                        <div className="smart-h" style={{ fontSize: 15 }}>🔧 سؤال‌های دشوار</div>
                        {hard.length === 0 && <p className="smart-muted">سؤال بسیار دشواری دیده نمی‌شود.</p>}
                        {hard.map((h, i) => (
                            <div key={i} style={{ display: 'flex', justifyContent: 'space-between', gap: 8, padding: '7px 0', borderBottom: '1px solid var(--sm-line)' }}>
                                <span style={{ fontSize: 13 }}>{h.prompt}</span><b style={{ color: '#e8505b', flex: 'none' }}>{fa(h.pct)}٪</b>
                            </div>
                        ))}
                        {weakTopics.length > 0 && <div style={{ marginTop: 10 }}><b style={{ fontSize: 13 }}>مباحث ضعیف: </b>{weakTopics.map((w, i) => <span key={i} className="smart-tag sample" style={{ marginInlineEnd: 4 }}>{w.topic} ({fa(w.pct)}٪)</span>)}</div>}
                    </div>
                </div>

                <div className="smart-panel">
                    <div className="smart-h" style={{ fontSize: 15 }}>👥 عملکرد دانش‌آموزان</div>
                    {rows.length === 0 && <p className="smart-muted">هنوز کسی این آزمون را نداده.</p>}
                    {rows.length > 0 && <ReleaseTable rows={rows} examId={exam.id} />}
                </div>

                <div className="smart-panel">
                    <div className="smart-h" style={{ fontSize: 15 }}>❓ گزارش سؤال‌به‌سؤال (نقاط ضعف کلاس)</div>
                    <div style={{ display: 'grid', gap: 8, marginTop: 8 }}>
                        {perQuestion.map((p) => <SmartQStat key={p.i} p={p} />)}
                    </div>
                </div>

                {/* پاسخِ هر دانش‌آموز به هر سؤال */}
                {studentAnswers.length > 0 && (
                    <div className="smart-panel no-print">
                        <div className="smart-h" style={{ fontSize: 15 }}>🧑‍🎓 پاسخِ هر دانش‌آموز به هر سؤال</div>
                        <div style={{ display: 'grid', gap: 8, marginTop: 8 }}>
                            {studentAnswers.map((s, k) => (
                                <div key={k} style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                                    <b style={{ minWidth: 120, fontSize: 13.5 }}>{s.name}</b>
                                    <div style={{ display: 'flex', gap: 5, flexWrap: 'wrap' }}>
                                        {s.perQuestion.map((q) => {
                                            const bg = q.type === 'desc' ? '#fef3c7' : q.ok ? '#dcfce7' : q.blank ? '#f1f5f9' : '#fee2e2';
                                            const col = q.type === 'desc' ? '#b45309' : q.ok ? '#166534' : q.blank ? '#64748b' : '#b91c1c';
                                            const icon = q.type === 'desc' ? '✍️' : q.ok ? '✅' : q.blank ? '—' : '❌';
                                            return <span key={q.i} style={{ background: bg, color: col, borderRadius: 7, padding: '4px 8px', fontSize: 12, fontWeight: 700 }}>{fa(q.i + 1)} {icon}</span>;
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div style={{ textAlign: 'center', color: '#7c7595', fontSize: 12 }}>تهیه‌شده در {printedAt}</div>
            </div>
        </DashLayout>
    );
}

/** جدولِ عملکرد + آزادسازیِ آزمون (حذفِ تلاش‌ها تا امکانِ شرکتِ دوباره). */
function ReleaseTable({ rows, examId }) {
    const [sel, setSel] = useState([]);
    const [busy, setBusy] = useState(false);
    const toggle = (id) => setSel((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id]);
    const allSel = sel.length === rows.length && rows.length > 0;
    const release = (ids, label) => {
        if (busy) return;
        if (!confirm(`${label}\nتمام تلاش‌ها و پاسخ‌های ثبت‌شده پاک می‌شوند و امکانِ شرکتِ دوباره فراهم می‌گردد. این کار قابل بازگشت نیست.`)) return;
        setBusy(true);
        router.post(route('teacher.smart.release', examId), ids ? { student_ids: ids } : {}, {
            preserveScroll: true, onFinish: () => { setBusy(false); setSel([]); },
        });
    };
    return (
        <>
            <p className="smart-muted" style={{ fontSize: 12.5, marginTop: 4 }}>
                🔓 «آزادسازی آزمون» تلاش‌های ثبت‌شده را پاک می‌کند تا دانش‌آموز دوباره بتواند در آزمون شرکت کند. برای فردی، از دکمه‌ی «آزاد» جلوی نامش استفاده کنید؛ برای گروهی، چند نفر را تیک بزنید.
            </p>
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', margin: '10px 0' }}>
                <button onClick={() => release(sel, `آزادسازی برای ${fa(sel.length)} دانش‌آموزِ انتخاب‌شده`)} disabled={busy || sel.length === 0} className="smart-btn sm">🔓 آزادسازی انتخابی‌ها ({fa(sel.length)})</button>
                <button onClick={() => release(null, 'آزادسازی برای همه‌ی دانش‌آموزان')} disabled={busy} className="smart-btn ghost sm" style={{ color: '#e8505b' }}>🔓 آزادسازی همه</button>
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table className="tbl" style={{ marginTop: 8 }}>
                    <thead><tr>
                        <th><input type="checkbox" checked={allSel} onChange={(e) => setSel(e.target.checked ? rows.map((r) => r.student_id) : [])} /></th>
                        <th>#</th><th>نام</th><th>نمره</th><th>درصد</th><th>تلاش</th><th>زمان</th><th>وضعیت</th><th>آزادسازی</th>
                    </tr></thead>
                    <tbody>
                        {rows.map((r, i) => (
                            <tr key={i}>
                                <td><input type="checkbox" checked={sel.includes(r.student_id)} onChange={() => toggle(r.student_id)} /></td>
                                <td>{fa(i + 1)}</td><td style={{ fontWeight: 700 }}>{r.name}</td>
                                <td>{fa(r.score)}/{fa(r.max)}</td>
                                <td><span className={`tag ${r.percent >= 50 ? 'tag-ok' : 'tag-warn'}`}>{fa(r.percent)}٪</span></td>
                                <td>{fa(r.attempts)}</td><td>{mins(r.duration || 0)}</td>
                                <td>{r.status === 'completed' ? '✅' : r.status === 'needs_review' ? '📝 بررسی' : '⏳'}</td>
                                <td><button onClick={() => release([r.student_id], `آزادسازی برای «${r.name}»`)} disabled={busy} className="smart-btn ghost sm" style={{ fontSize: 11.5, color: '#e8505b' }}>🔓 آزاد</button></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}

/** آمارِ یک سؤال + فهرست اشتباه‌کنندگان. */
function SmartQStat({ p }) {
    const [open, setOpen] = useState(false);
    const barColor = p.pct == null ? '#94a3b8' : p.pct >= 70 ? '#16a34a' : p.pct >= 40 ? '#f0952e' : '#dc2626';
    return (
        <div style={{ border: '1px solid var(--sm-line, #eee)', borderRadius: 10, padding: 10 }}>
            <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                <span style={{ fontSize: 13, flex: 1 }}>{fa(p.i + 1)}. {p.prompt}</span>
                {p.type === 'desc'
                    ? <span className="smart-tag sample" style={{ fontSize: 11 }}>تشریحی</span>
                    : <>
                        <div style={{ width: 110, height: 10, borderRadius: 6, background: '#eee', overflow: 'hidden', flex: 'none' }}>
                            <div style={{ width: `${p.pct ?? 0}%`, height: '100%', background: barColor }} />
                        </div>
                        <b style={{ flex: 'none', fontSize: 12, minWidth: 36, color: barColor }}>{p.pct === null ? '—' : fa(p.pct) + '٪'}</b>
                    </>}
            </div>
            {p.type !== 'desc' && p.wrongNames?.length > 0 && (
                <div style={{ marginTop: 4 }}>
                    <button onClick={() => setOpen(!open)} className="smart-btn ghost sm" style={{ fontSize: 12 }}>{open ? 'بستن' : `👀 ${fa(p.wrongNames.length)} نفر اشتباه زدند`}</button>
                    {open && <div style={{ fontSize: 12.5, color: '#7c7595', marginTop: 4 }}>{p.wrongNames.join('، ')}</div>}
                </div>
            )}
        </div>
    );
}
