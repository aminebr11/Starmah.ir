import { usePage, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { useRestart, RestartBar } from '@/Components/RestartTools';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import { useSort, SortTh, SortBar, firstName, lastName } from '@/lib/useSort';
import '@/../css/game-report.css';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const mins = (s) => s >= 60 ? `${fa(Math.floor(s / 60))}د ${fa(s % 60)}ث` : `${fa(s)}ث`;

export default function GameReport() {
    const { game = {}, summary = {}, rows = [], hardQuestions = [], errors = {} } = usePage().props;
    const D = 'desc';
    const rs = useSort(rows, { name: (r) => firstName(r.name), family: (r) => lastName(r.name), score: 'score', percent: 'percent', status: (r) => (r.status === 'completed' ? 1 : 0), hints: 'hints', duration: 'duration' },
        { id: 'teacher-game-report', firstDir: { score: D, percent: D, status: D, hints: D, duration: D } });
    const restart = useRestart('teacher.studio.release', game.id, 'game');
    const [sel, setSel] = useState([]);
    const toggle = (id) => setSel((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
    const allSel = rows.length > 0 && sel.length === rows.length;

    return (
        <DashLayout title={`گزارش: ${game.title}`} roleLabel="معلم" menu={teacherMenu} active="studio">
            <div className="panel">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <h3 style={{ margin: 0 }}>📊 گزارش بازی «{game.title}»</h3>
                    <span className="tag tag-info">{game.template}</span>
                    <Link href={route('teacher.studio')} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>← بازگشت</Link>
                    <a href={route('teacher.studio.report.print', game.id)} className="btn btn-sm">🖨️ چاپ/PDF</a>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(130px,1fr))', gap: 10, marginTop: 14 }}>
                    <Kpi v={fa(summary.started)} l="شروع کرده" c="#3d7bf0" />
                    <Kpi v={fa(summary.completed)} l="تکمیل کرده" c="#2bb673" />
                    <Kpi v={`${fa(summary.avg)}٪`} l="میانگین دقت" c="#a24cf0" />
                    <Kpi v={mins(summary.avgDuration || 0)} l="میانگین زمان" c="#e8862e" />
                    <Kpi v={fa(summary.hints)} l="استفاده از راهنما" c="#0ea5b7" />
                </div>
            </div>

            {hardQuestions.length > 0 && <HardQuestions game={game} list={hardQuestions} errors={errors} />}

            <div className="panel">
                <h3 style={{ fontSize: 15 }}>👥 عملکرد دانش‌آموزان</h3>
                <RestartBar r={restart} closed={game.closed} selected={sel} onSelectedDone={() => setSel([])} />
                {rows.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز کسی این بازی را انجام نداده.</p>}
                {rows.length > 0 && (
                    <div style={{ overflowX: 'auto' }}>
                        {rows.length > 1 && <SortBar s={rs} options={[['name', 'نام'], ['family', 'نام خانوادگی'], ['score', 'امتیاز'], ['percent', 'دقت']]} />}
                        <table className="tbl">
                            <thead><tr><th className="no-print"><input type="checkbox" checked={allSel} onChange={(e) => setSel(e.target.checked ? rows.map((r) => r.student_id) : [])} /></th><th>#</th><SortTh s={rs} k="family">نام</SortTh><SortTh s={rs} k="score">امتیاز</SortTh><SortTh s={rs} k="percent">دقت</SortTh><SortTh s={rs} k="status">وضعیت</SortTh><SortTh s={rs} k="hints">راهنما</SortTh><SortTh s={rs} k="duration">زمان</SortTh><th className="no-print">راه‌اندازیِ مجدد</th></tr></thead>
                            <tbody>
                                {rs.sorted.map((r, i) => (
                                    <tr key={i}>
                                        <td className="no-print"><input type="checkbox" checked={sel.includes(r.student_id)} onChange={() => toggle(r.student_id)} /></td>
                                        <td>{fa(i + 1)}</td>
                                        <td style={{ fontWeight: 700 }}>{r.name}</td>
                                        <td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(r.score)}/{fa(r.max)}</td>
                                        <td><span className={`tag ${r.percent >= 50 ? 'tag-ok' : 'tag-warn'}`}>{fa(r.percent)}٪</span></td>
                                        <td>{r.status === 'completed' ? '✅ تکمیل' : '⏳ ناتمام'}</td>
                                        <td>{fa(r.hints)}</td>
                                        <td>{mins(r.duration || 0)}</td>
                                        <td className="no-print"><button type="button" className="rs-one" disabled={restart.busy} onClick={() => restart.restart([r.student_id], `«${r.name}»`)}>🔁 دوباره</button></td>
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
/**
 * «🔧 سؤال‌های دشوار»: هر سؤالی که دستِ‌کم یک نفر غلط زده. با لمسِ هر سؤال فهرستِ کسانی که
 * اشتباه زده‌اند (و گزینه‌ای که انتخاب کرده‌اند) باز می‌شود. از همه یا بخشی از این سؤال‌ها
 * «آزمونِ جبرانی» (بازیِ تازه، پیش‌نویس) ساخته می‌شود.
 */
function HardQuestions({ game, list, errors }) {
    const [open, setOpen] = useState(null);
    const [pick, setPick] = useState(() => list.map((h) => h.id).filter(Boolean));
    const [make, setMake] = useState(false);
    const [audience, setAudience] = useState('wrong');
    const [title, setTitle] = useState('');
    const [busy, setBusy] = useState(false);
    const ids = list.map((h) => h.id).filter(Boolean);
    const all = ids.length > 0 && ids.every((id) => pick.includes(id));
    const toggle = (id) => setPick((p) => (p.includes(id) ? p.filter((x) => x !== id) : [...p, id]));
    const wrongCount = new Set(list.filter((h) => pick.includes(h.id)).flatMap((h) => h.students.map((s) => s.id))).size;
    const submit = () => {
        if (!pick.length) return;
        setBusy(true);
        router.post(route('teacher.studio.remedial', game.id), { questions: pick, audience, title }, { onFinish: () => setBusy(false) });
    };

    return (
        <div className="panel hq">
            <div className="hq-top">
                <h3 style={{ fontSize: 15, margin: 0 }}>🔧 سؤال‌های دشوار ({fa(list.length)})</h3>
                <button type="button" className="btn btn-sm" onClick={() => setMake((m) => !m)}>🔁 ساختِ آزمونِ جبرانی از سؤال‌های دشوار</button>
            </div>
            <p className="hq-note">روی هر سؤال بزنید تا ببینید چه کسانی اشتباه زده‌اند.</p>

            {make && (
                <div className="hq-make">
                    <label className="hq-all"><input type="checkbox" checked={all} onChange={() => setPick(all ? [] : ids)} /> همه‌ی سؤال‌های دشوار ({fa(ids.length)}) — یا فقط سؤال‌های دلخواه را در فهرستِ زیر تیک بزنید</label>
                    <div className="hq-aud">
                        <label className={audience === 'wrong' ? 'on' : ''}><input type="radio" checked={audience === 'wrong'} onChange={() => setAudience('wrong')} /> فقط برای کسانی که اشتباه زده‌اند ({fa(wrongCount)} نفر)</label>
                        <label className={audience === 'all' ? 'on' : ''}><input type="radio" checked={audience === 'all'} onChange={() => setAudience('all')} /> برای همان مخاطبانِ این بازی</label>
                    </div>
                    <input className="input" value={title} onChange={(e) => setTitle(e.target.value)} placeholder={`عنوان (خالی = «🔁 جبرانی: ${game.title}»)`} />
                    {errors.questions && <div className="hq-err">{errors.questions}</div>}
                    <button type="button" className="btn" disabled={busy || !pick.length} onClick={submit}>
                        {busy ? 'در حالِ ساخت…' : `✅ ساختِ آزمون با ${fa(pick.length)} سؤال`}
                    </button>
                    <small className="hq-note">بازیِ تازه به‌صورتِ پیش‌نویس ساخته می‌شود؛ صفحه‌ی ویرایشش باز می‌شود تا بازبینی و «انتشار» کنید.</small>
                </div>
            )}

            <div className="hq-list">
                {list.map((h, i) => (
                    <div key={h.id ?? i} className={`hq-q ${open === i ? 'open' : ''}`}>
                        <div className="hq-row">
                            {make && h.id && <input type="checkbox" checked={pick.includes(h.id)} onChange={() => toggle(h.id)} aria-label="انتخابِ سؤال" />}
                            <button type="button" className="hq-btn" onClick={() => setOpen(open === i ? null : i)} aria-expanded={open === i}>
                                <span className="hq-p">{fa(h.index + 1)}. {h.prompt}</span>
                                <b>{fa(h.wrong)} از {fa(h.answered)} غلط</b>
                                <i>{open === i ? '▲' : '▼'}</i>
                            </button>
                        </div>
                        {open === i && (
                            <div className="hq-who">
                                {h.answer && <div className="hq-ans">✅ پاسخِ درست: <b>{h.answer}</b></div>}
                                <div className="hq-names">
                                    {h.students.map((st) => (
                                        <span key={st.id}>❌ {st.name}<small>{st.picked ? `زده: ${st.picked}` : 'بی‌پاسخ'}</small></span>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

function Kpi({ v, l, c }) {
    return <div style={{ borderRadius: 14, padding: 14, color: '#fff', textAlign: 'center', background: `linear-gradient(135deg,${c},${c}bb)` }}><b style={{ fontSize: 22, display: 'block' }}>{v}</b><span style={{ fontSize: 11.5, opacity: .9 }}>{l}</span></div>;
}
