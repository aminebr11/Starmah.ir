import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import Mascot from '@/Components/Mascot';
import { fa, Modal, ReportModal, RecordingsList } from '@/online/parts';
import '@/../css/supervise.css';

const LV = { ok: '✅ رو به راه', info: 'ℹ️ قابلِ توجه', warn: '⚠️ نیازِ توجه', crit: '🚨 جدی' };

/**
 * «🛡️ نظارتِ کلاس‌ها» — برای ناظری که مدیرِ مدرسه چند کلاس به او سپرده.
 * فقط همان کلاس‌ها: کلاسِ آنلاینِ زنده (ورود، کنترل، پایان)، حضورِ امروز، هشدارها،
 * دانش‌آموزان و کلاس‌های آنلاینِ گذشته با حضور و فیلم.
 */
export default function Supervise({ school, classes = [], alerts = [], onlineOn = false, rooms = [], recordings = [] }) {
    const [report, setReport] = useState(null);
    const [roster, setRoster] = useState(null);
    const live = rooms.filter((r) => r.status === 'live');
    const next = rooms.filter((r) => r.status === 'scheduled');
    const past = rooms.filter((r) => r.status === 'ended');
    const end = (r) => { if (confirm(`کلاسِ «${r.title}» همین حالا تمام شود؟`)) router.post(route('online.end', r.id), {}, { preserveScroll: true }); };

    // کلاس‌های زنده و حاضران هر ۲۰ ثانیه تازه شوند
    useEffect(() => {
        if (!onlineOn) return undefined;
        const t = setInterval(() => { if (document.visibilityState === 'visible') router.reload({ only: ['rooms'], preserveScroll: true }); }, 20000);
        return () => clearInterval(t);
    }, [onlineOn]);

    return (
        <DashLayout title="نظارتِ کلاس‌ها" roleLabel="ناظر" menu={teacherMenu} active="online-sup">
            <div className="at-hero oc-hero">
                <div className="oc-hero-m"><Mascot mood="idle" size={86} /></div>
                <div>
                    <h2>🛡️ نظارتِ کلاس‌ها{school ? ` — ${school}` : ''}</h2>
                    <p>مدیرِ مدرسه این کلاس‌ها را به شما سپرده است. وضعیتشان را ببینید، حضور و غیاب بزنید، و واردِ کلاسِ آنلاینشان شوید. در کلاس می‌توانید میکروفون‌ها را ببندید، پیام را پنهان کنید، دانش‌آموز را بیرون بگذارید یا کلاس را تمام کنید.</p>
                    <div className="sv-chips">{classes.map((c) => <span key={c.id}>{c.name}</span>)}</div>
                </div>
            </div>

            {onlineOn && (
                <div className="oc-sec"><h3>🔴 کلاس‌های آنلاینِ در حالِ برگزاری ({fa(live.length)})</h3>
                    {live.length === 0 && <div className="panel oc-empty-p"><p>الان هیچ‌کدام از کلاس‌های شما آنلاین نیست.</p></div>}
                    <div className="oc-cards">{live.map((r) => (
                        <div key={r.id} className="oc-card live">
                            <div className="oc-card-h"><b>{r.title}</b><span className="oc-st live">{fa(r.online)} نفر آنلاین</span></div>
                            <div className="oc-card-m"><span>👩‍🏫 {r.teacher}</span><span>👥 {r.classroom}</span>{r.length !== null && <span>⏱️ {fa(r.length)} دقیقه</span>}</div>
                            <div className="oc-card-a">
                                <a className="btn btn-sm" href={route('online.room', r.id)}>🛡️ ورود به‌عنوانِ ناظر</a>
                                <button type="button" className="btn btn-sm btn-ghost" onClick={() => setReport(r.id)}>📋 حضور</button>
                                <button type="button" className="btn btn-sm btn-danger" onClick={() => end(r)}>⏹ پایان</button>
                            </div>
                        </div>
                    ))}</div>
                </div>
            )}

            <div className="oc-sec"><h3>📚 کلاس‌های زیرِ نظرِ شما ({fa(classes.length)})</h3>
                <div className="sv-grid">{classes.map((c) => (
                    <div key={c.id} className={`sv-card ${c.level}`}>
                        <div className="sv-h">
                            <div><b>{c.name}</b><small>{c.grade ? `پایه‌ی ${c.grade} · ` : ''}👩‍🏫 {c.teacher || 'بدونِ معلم'}</small></div>
                            <span className={`sv-lv ${c.level}`}>{LV[c.level] || LV.ok}</span>
                        </div>
                        <div className="sv-stats">
                            <div><b>{fa(c.students)}</b><small>دانش‌آموز</small></div>
                            <div><b>{c.active !== null ? `${fa(c.active)}٪` : '—'}</b><small>فعال در این هفته</small></div>
                            <div><b>{c.accuracy !== null ? `${fa(c.accuracy)}٪` : '—'}</b><small>دقتِ پاسخ‌ها</small></div>
                        </div>
                        <div className={`sv-today ${c.today.recorded ? '' : 'none'}`}>
                            {c.today.recorded
                                ? <>✅ حضورِ امروز: {fa(c.today.present)} حاضر · {fa(c.today.absent)} غایب{c.today.late ? ` · ${fa(c.today.late)} تأخیر` : ''}</>
                                : <>⏳ حضور و غیابِ امروز هنوز ثبت نشده.</>}
                            {c.inactive_count > 0 && <div style={{ marginTop: 4 }}>😴 {fa(c.inactive_count)} نفر بیش از ۱۰ روز فعالیتی نداشته‌اند.</div>}
                        </div>
                        <div className="sv-acts">
                            <a className="btn btn-sm" href={route('teacher.attendance', { classroom_id: c.id })}>✅ حضور و غیاب</a>
                            <a className="btn btn-sm btn-ghost" href={route('teacher.attendance.report', { classroom_id: c.id })}>📋 گزارشِ حضور</a>
                            <button type="button" className="btn btn-sm btn-ghost" onClick={() => setRoster(c)}>👥 دانش‌آموزان</button>
                        </div>
                    </div>
                ))}</div>
            </div>

            {alerts.length > 0 && (
                <div className="oc-sec"><h3>🚨 هشدارهای این کلاس‌ها ({fa(alerts.length)})</h3>
                    <div className="sv-alerts">{alerts.map((a, i) => (
                        <div key={i} className={`sv-alert ${a.level}`}>
                            <b>{a.title} — {a.classroom}</b>
                            <p>{a.detail}</p>
                            {a.tip && <p>💡 {a.tip}</p>}
                        </div>
                    ))}</div>
                </div>
            )}

            {onlineOn && next.length > 0 && (
                <div className="oc-sec"><h3>🗓️ کلاس‌های آنلاینِ برنامه‌ریزی‌شده</h3>
                    <div className="oc-cards">{next.map((r) => (
                        <div key={r.id} className="oc-card">
                            <div className="oc-card-h"><b>{r.title}</b></div>
                            <div className="oc-card-m"><span>👩‍🏫 {r.teacher}</span><span>👥 {r.classroom}</span>{r.when && <span>🗓️ {r.when}</span>}</div>
                        </div>
                    ))}</div>
                </div>
            )}

            {onlineOn && <RecordingsList recordings={recordings} title="🎬 فیلمِ کلاس‌های آنلاین" />}

            {onlineOn && past.length > 0 && (
                <div className="oc-sec"><h3>📚 کلاس‌های آنلاینِ گذشته</h3>
                    <div className="oc-table-wrap">
                        <table className="oc-rep">
                            <thead><tr><th>عنوان</th><th>معلم</th><th>کلاس</th><th>زمان</th><th>حاضر</th><th /></tr></thead>
                            <tbody>{past.map((r) => (
                                <tr key={r.id}>
                                    <td>{r.title}</td><td>{r.teacher}</td><td>{r.classroom}</td>
                                    <td>{r.started || '—'}{r.length ? ` · ${fa(r.length)} دقیقه` : ''}</td>
                                    <td>{fa(r.present)}</td>
                                    <td>{r.present > 0 && <button type="button" className="oc-link" onClick={() => setReport(r.id)}>📋 حضور</button>}</td>
                                </tr>
                            ))}</tbody>
                        </table>
                    </div>
                </div>
            )}

            {report && <ReportModal url={route('online.report', report)} onClose={() => setReport(null)} />}
            {roster && (
                <Modal label="دانش‌آموزان" onClose={() => setRoster(null)}>
                        <div className="oc-modal-h"><b>👥 دانش‌آموزانِ {roster.name}</b><button type="button" onClick={() => setRoster(null)} aria-label="بستن">×</button></div>
                        {roster.roster.length === 0 ? <p>هنوز دانش‌آموزی در این کلاس نیست.</p> : (
                            <div className="oc-table-wrap">
                                <table className="sv-roster">
                                    <thead><tr><th>نام</th><th>امتیازِ این هفته</th><th>آخرین فعالیت</th></tr></thead>
                                    <tbody>{roster.roster.map((s) => (
                                        <tr key={s.id}><td>{s.name}</td><td>{fa(s.xp_week)}</td><td className={s.last ? '' : 'dim'}>{s.last || 'هنوز فعالیتی ندارد'}</td></tr>
                                    ))}</tbody>
                                </table>
                            </div>
                        )}
                </Modal>
            )}
        </DashLayout>
    );
}
