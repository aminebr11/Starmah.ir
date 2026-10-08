import { useState } from 'react';
import { useSort, SortBar, SortTh, firstName, lastName } from '@/lib/useSort';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * رصدِ «جبرانِ اشتباه» برای معلم.
 *
 * یادآوری‌ها فقط در حسابِ خودِ دانش‌آموز ساخته می‌شوند؛ معلم اینجا می‌بیند
 * چه کسی چه چیزی را اشتباه زده، کجای مسیرِ جبران است، چه کسی عقب مانده و
 * کدام فصل بیشترین اشتباه را دارد (یعنی شاید لازم است دوباره درس داده شود).
 */
const STATUS = { open: ['در حالِ جبران', '#e8862e'], done: ['جبران شد', '#1fa463'], closed: ['بسته شد', '#8a94ae'] };

export default function RemediationPanel({ data }) {
    const [tab, setTab] = useState('students');
    const t = data?.totals || {};
    const students = data?.students || [];
    const chapters = data?.chapters || [];
    const recent = data?.recent || [];
    const [who, setWho] = useState('');

    const ss = useSort(students, {
        name: (r) => firstName(r.name), family: (r) => lastName(r.name), total: 'total', open: 'open', due: 'due',
        overdue: 'overdue', done: 'done', recovered: 'recovered', last: 'last_raw',
    }, { id: 'rem-students', key: 'overdue', dir: 'desc', firstDir: { total: 'desc', open: 'desc', due: 'desc', overdue: 'desc', done: 'desc', recovered: 'desc', last: 'desc' } });
    const cs = useSort(chapters, { label: 'label', total: 'total', students: 'students', open: 'open', done: 'done' },
        { id: 'rem-chapters', key: 'total', dir: 'desc', firstDir: { total: 'desc', students: 'desc', open: 'desc', done: 'desc' } });
    const list = who ? recent.filter((r) => String(r.student_id) === String(who)) : recent;
    const rs = useSort(list, { created: 'created_raw', student: (r) => lastName(r.student), chapter: 'chapter', status: 'status', step: 'step' },
        { id: 'rem-recent', key: 'created', dir: 'desc', firstDir: { created: 'desc', step: 'desc' } });

    return (
        <div className="panel rm-panel">
            <div className="rm-head">
                <h3>🔁 جبرانِ اشتباه</h3>
                <p>برای هر سؤالی که دانش‌آموز اشتباه می‌زند، خودکار و فقط در حسابِ خودش تمرینِ جبرانی ساخته می‌شود (همان سؤال + سؤال‌های مشابهِ همان فصل، در ۳ نوبتِ فاصله‌دار). حداکثر نیمی از امتیازِ از دست‌رفته برمی‌گردد.</p>
            </div>

            {!t.total ? (
                <div className="rm-empty">هنوز یادآوریِ جبرانی ساخته نشده — بعد از اولین آزمون، بازی یا مأموریتی که دانش‌آموزی اشتباه بزند، اینجا پر می‌شود.</div>
            ) : (
                <>
                    <div className="rm-kpis">
                        <div><em>{fa(t.total)}</em><span>اشتباهِ ثبت‌شده</span></div>
                        <div className="warn"><em>{fa(t.open)}</em><span>در حالِ جبران</span></div>
                        <div className="ok"><em>{fa(t.done)}</em><span>جبران شد ({fa(t.rate)}٪)</span></div>
                        <div><em>{fa(t.students)}</em><span>دانش‌آموز</span></div>
                        <div><em>⚡ {fa(t.recovered)}</em><span>امتیازِ جبران‌شده</span></div>
                    </div>

                    <div className="rm-tabs">
                        <button className={tab === 'students' ? 'on' : ''} onClick={() => setTab('students')}>👥 دانش‌آموزان</button>
                        <button className={tab === 'chapters' ? 'on' : ''} onClick={() => setTab('chapters')}>📘 فصل‌ها</button>
                        <button className={tab === 'recent' ? 'on' : ''} onClick={() => setTab('recent')}>🕒 ریزِ یادآوری‌ها</button>
                    </div>

                    {tab === 'students' && (
                        <div style={{ overflowX: 'auto' }}>
                            <table className="tbl">
                                <thead><tr>
                                    <SortTh s={ss} k="family">دانش‌آموز</SortTh>
                                    <SortTh s={ss} k="total" first="desc">اشتباه</SortTh>
                                    <SortTh s={ss} k="due" first="desc">امروز آماده</SortTh>
                                    <SortTh s={ss} k="overdue" first="desc">عقب‌مانده</SortTh>
                                    <SortTh s={ss} k="done" first="desc">جبران‌شده</SortTh>
                                    <SortTh s={ss} k="recovered" first="desc">امتیازِ برگشته</SortTh>
                                    <SortTh s={ss} k="last" first="desc">آخرین تمرین</SortTh>
                                </tr></thead>
                                <tbody>
                                    {ss.sorted.map((r) => (
                                        <tr key={r.id} onClick={() => { setWho(String(r.id)); setTab('recent'); }} style={{ cursor: 'pointer' }} title="دیدنِ ریزِ یادآوری‌های این دانش‌آموز">
                                            <td style={{ fontWeight: 700 }}>{r.name}</td>
                                            <td>{fa(r.total)}</td>
                                            <td>{r.due ? <span className="rm-pill warn">{fa(r.due)}</span> : '—'}</td>
                                            <td>{r.overdue ? <span className="rm-pill bad">{fa(r.overdue)}</span> : '—'}</td>
                                            <td>{r.done ? <span className="rm-pill ok">{fa(r.done)}</span> : '—'}</td>
                                            <td>{fa(r.recovered)} <small style={{ color: 'var(--muted)' }}>از {fa(r.cap)}</small></td>
                                            <td style={{ color: 'var(--muted)', fontSize: 12 }}>{r.last || 'هنوز نه'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <p className="rm-note">«عقب‌مانده» یعنی بیش از ۳ روز است نوبتِ جبرانش رسیده و انجامش نداده — شاید یک یادآوریِ حضوری لازم باشد.</p>
                        </div>
                    )}

                    {tab === 'chapters' && (
                        <>
                            <SortBar s={cs} options={[['total', 'تعدادِ اشتباه', 'desc'], ['students', 'تعدادِ دانش‌آموز', 'desc'], ['open', 'در حالِ جبران', 'desc'], ['label', 'نامِ فصل']]} />
                            <div className="rm-chapters">
                                {cs.sorted.map((c) => {
                                    const pct = c.total ? Math.round((c.done / c.total) * 100) : 0;
                                    return (
                                        <div key={c.label} className="rm-ch">
                                            <div className="rm-ch-h"><b>📘 {c.label}</b><span>{fa(c.students)} دانش‌آموز · {fa(c.total)} اشتباه</span></div>
                                            <div className="rm-bar"><i style={{ width: `${pct}%` }} /></div>
                                            <small>{fa(c.done)} جبران شد · {fa(c.open)} در حالِ جبران</small>
                                        </div>
                                    );
                                })}
                            </div>
                            <p className="rm-note">فصلی که بیشترِ کلاس در آن اشتباه دارند، احتمالاً به یک بازگوییِ کلاسی نیاز دارد.</p>
                        </>
                    )}

                    {tab === 'recent' && (
                        <>
                            <div className="rm-filter">
                                <select className="input" value={who} onChange={(e) => setWho(e.target.value)}>
                                    <option value="">همه‌ی دانش‌آموزان</option>
                                    {students.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                            </div>
                            <div style={{ overflowX: 'auto' }}>
                                <table className="tbl">
                                    <thead><tr>
                                        <SortTh s={rs} k="student">دانش‌آموز</SortTh>
                                        <th>سؤال</th>
                                        <SortTh s={rs} k="chapter">فصل</SortTh>
                                        <SortTh s={rs} k="step" first="desc">نوبت</SortTh>
                                        <SortTh s={rs} k="status">وضعیت</SortTh>
                                        <SortTh s={rs} k="created" first="desc">ساخته‌شده</SortTh>
                                    </tr></thead>
                                    <tbody>
                                        {rs.sorted.map((r) => (
                                            <tr key={r.id}>
                                                <td style={{ fontWeight: 700, whiteSpace: 'nowrap' }}>{r.student}</td>
                                                <td style={{ maxWidth: 280 }}><div className="rm-q">{r.prompt}</div><small style={{ color: 'var(--muted)' }}>{r.source} — {r.title}</small></td>
                                                <td style={{ fontSize: 12.5 }}>{r.chapter || '—'}</td>
                                                <td>
                                                    <span className="rm-steps">{Array.from({ length: r.steps }).map((_, k) => <i key={k} className={k < r.step ? 'on' : ''} />)}</span>
                                                    <small style={{ color: 'var(--muted)' }}> {fa(r.tries)} بار</small>
                                                </td>
                                                <td>
                                                    <span className="rm-pill" style={{ background: `${STATUS[r.status]?.[1]}22`, color: STATUS[r.status]?.[1] }}>{STATUS[r.status]?.[0] || r.status}</span>
                                                    {r.status === 'open' && r.due && <div style={{ fontSize: 11, color: 'var(--muted)' }}>نوبت: {r.due}</div>}
                                                    {r.cap > 0 && <div style={{ fontSize: 11, color: 'var(--muted)' }}>⚡ {fa(r.recovered)} از {fa(r.cap)}</div>}
                                                </td>
                                                <td style={{ fontSize: 12, color: 'var(--muted)', whiteSpace: 'nowrap' }}>{r.created}</td>
                                            </tr>
                                        ))}
                                        {!rs.sorted.length && <tr><td colSpan="6" style={{ textAlign: 'center', color: 'var(--muted)' }}>موردی نیست.</td></tr>}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </>
            )}
        </div>
    );
}
