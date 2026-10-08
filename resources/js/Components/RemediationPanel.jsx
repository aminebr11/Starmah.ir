import { useState } from 'react';
import { useForm, router, usePage } from '@inertiajs/react';
import { useSort, SortBar, SortTh, firstName, lastName } from '@/lib/useSort';
import { useChapters } from '@/Components/Questions/QuestionChapter';

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
                <h3>🔁 مرورِ اشتباه‌ها</h3>
                <p>برای هر سؤالی که دانش‌آموز اشتباه می‌زند (آزمون، بازی، مأموریت) و هر نمره‌ی ضعیفِ شما در یک فصل، خودکار و فقط در حسابِ خودِ او مرور ساخته می‌شود: همان سؤال با گزینه‌های جابه‌جا + سؤال‌های مشابهِ همان فصل، با زمان‌بندی‌ای که بالا اعلام کرده‌اید. حداکثر نیمی از امتیازِ از دست‌رفته برمی‌گردد. خودتان هم می‌توانید برای هر دانش‌آموز و هر فصل مرور بفرستید.</p>
            </div>

            <AssignForm data={data} />

            {!t.total ? (
                <div className="rm-empty">هنوز مرورِ اشتباهی ساخته نشده — بعد از اولین آزمون، بازی یا مأموریتی که دانش‌آموزی اشتباه بزند (یا با فرمِ بالا)، اینجا پر می‌شود.</div>
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
                                        <th />
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
                                                <td>{r.status === 'open' && <button type="button" className="btn btn-ghost btn-sm" title="بستنِ این مرور" onClick={() => { if (confirm('این مرور بسته شود؟ امتیازی که تا الان جبران شده سرِ جایش می‌ماند.')) router.delete(route('teacher.remediations.destroy', r.id), { preserveScroll: true }); }}>✕</button>}</td>
                                            </tr>
                                        ))}
                                        {!rs.sorted.length && <tr><td colSpan="7" style={{ textAlign: 'center', color: 'var(--muted)' }}>موردی نیست.</td></tr>}
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

/** فرستادنِ مرور برای یک یا چند دانش‌آموز در یک فصل. */
function AssignForm({ data }) {
    const { flash } = usePage().props;
    const [open, setOpen] = useState(false);
    const f = useForm({ subject: data?.subjects?.[0] || '', chapter_id: '', student_ids: [] });
    const chapters = useChapters(data?.grade, f.data.subject);
    const roster = data?.roster || [];
    const toggle = (id) => f.setData('student_ids', f.data.student_ids.includes(id) ? f.data.student_ids.filter((x) => x !== id) : [...f.data.student_ids, id]);
    const err = f.errors.student_ids || f.errors.chapter_id || f.errors.subject;
    if (!open) {
        return <button type="button" className="btn btn-sm" style={{ marginBottom: 12 }} onClick={() => setOpen(true)}>➕ فرستادنِ مرور برای دانش‌آموز</button>;
    }
    return (
        <div className="rm-assign">
            <div className="rm-assign-row">
                <label>درس
                    <select className="input" value={f.data.subject} onChange={(e) => f.setData((d) => ({ ...d, subject: e.target.value, chapter_id: '' }))}>
                        <option value="">— درس —</option>
                        {(data?.subjects || []).map((s) => <option key={s} value={s}>{s}</option>)}
                    </select>
                </label>
                <label>فصل
                    <select className="input" value={f.data.chapter_id} onChange={(e) => f.setData('chapter_id', e.target.value)} disabled={!chapters.length}>
                        <option value="">{f.data.subject ? (chapters.length ? '— فصل —' : 'فصلی ثبت نشده') : 'اول درس'}</option>
                        {chapters.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                    </select>
                </label>
            </div>
            <div className="rm-assign-h">
                <b>دانش‌آموزان ({f.data.student_ids.length})</b>
                <button type="button" className="btn btn-ghost btn-sm" onClick={() => f.setData('student_ids', f.data.student_ids.length === roster.length ? [] : roster.map((r) => r.id))}>{f.data.student_ids.length === roster.length ? 'برداشتنِ همه' : 'همه‌ی کلاس'}</button>
            </div>
            <div className="rm-assign-chips">
                {roster.map((r) => <button type="button" key={r.id} className={`rm-chip ${f.data.student_ids.includes(r.id) ? 'on' : ''}`} onClick={() => toggle(r.id)}>{f.data.student_ids.includes(r.id) ? '✓ ' : ''}{r.name}</button>)}
            </div>
            {err && <div className="rm-err">{err}</div>}
            <div className="rm-assign-row">
                <button type="button" className="btn btn-sm" disabled={f.processing} onClick={() => f.post(route('teacher.remediations.store'), { preserveScroll: true, onSuccess: () => { f.setData('student_ids', []); setOpen(false); } })}>🔁 فرستادنِ مرور</button>
                <button type="button" className="btn btn-ghost btn-sm" onClick={() => setOpen(false)}>انصراف</button>
                <small style={{ color: 'var(--muted)' }}>سؤال‌ها از بانکِ همان فصل می‌آیند؛ ۳ نوبتِ فاصله‌دار، تا ۶ امتیاز.</small>
            </div>
        </div>
    );
}
