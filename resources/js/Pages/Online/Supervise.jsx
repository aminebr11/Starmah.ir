import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import Mascot from '@/Components/Mascot';
import { fa, ReportModal, RecordingsList } from '@/online/parts';

/**
 * «🛡️ نظارتِ کلاس‌ها» — برای ناظمی که مدیرِ مدرسه تعیین کرده: همه‌ی کلاس‌های آنلاینِ مدرسه،
 * ورود به هر کلاس به‌عنوانِ ناظر، گزارشِ حضور، پایانِ کلاس و فیلم‌ها.
 */
export default function Supervise({ school, rooms, recordings = [] }) {
    const [report, setReport] = useState(null);
    const live = rooms.filter((r) => r.status === 'live');
    const next = rooms.filter((r) => r.status === 'scheduled');
    const past = rooms.filter((r) => r.status === 'ended');
    const end = (r) => { if (confirm(`کلاسِ «${r.title}» همین حالا تمام شود؟`)) router.post(route('online.end', r.id), {}, { preserveScroll: true }); };

    // تعدادِ حاضرانِ کلاس‌های زنده هر ۲۰ ثانیه تازه شود
    useEffect(() => {
        const t = setInterval(() => { if (document.visibilityState === 'visible') router.reload({ only: ['rooms'], preserveScroll: true }); }, 20000);
        return () => clearInterval(t);
    }, []);

    return (
        <DashLayout title="نظارتِ کلاس‌ها" roleLabel="ناظم" menu={teacherMenu} active="online-sup">
            <div className="at-hero oc-hero">
                <div className="oc-hero-m"><Mascot mood="idle" size={86} /></div>
                <div>
                    <h2>🛡️ نظارت بر کلاس‌های آنلاین{school ? ` — ${school}` : ''}</h2>
                    <p>وارد هر کلاس شوید و کلاس را ببینید و بشنوید. اگر لازم شد، میکروفون‌ها را ببندید، پیامِ نامناسب را پنهان کنید، دانش‌آموزی را بیرون بگذارید یا کلاس را تمام کنید. حضور و غیاب و فیلمِ هر جلسه هم این‌جاست.</p>
                </div>
            </div>

            <div className="oc-sec"><h3>🔴 کلاس‌های در حالِ برگزاری ({fa(live.length)})</h3>
                {live.length === 0 && <div className="panel oc-empty-p"><p>الان کلاسِ زنده‌ای نیست.</p></div>}
                <div className="oc-cards">{live.map((r) => (
                    <div key={r.id} className="oc-card live">
                        <div className="oc-card-h"><b>{r.title}</b><span className="oc-st live">{fa(r.online)} نفر آنلاین</span></div>
                        <div className="oc-card-m"><span>👩‍🏫 {r.teacher}</span><span>👥 {r.classroom}</span>{r.length !== null && <span>⏱️ {fa(r.length)} دقیقه</span>}</div>
                        <div className="oc-card-a">
                            <a className="btn btn-sm" href={route('online.room', r.id)}>🛡️ ورود به‌عنوانِ ناظم</a>
                            <button type="button" className="btn btn-sm btn-ghost" onClick={() => setReport(r.id)}>📋 حضور</button>
                            <button type="button" className="btn btn-sm btn-danger" onClick={() => end(r)}>⏹ پایان</button>
                        </div>
                    </div>
                ))}</div>
            </div>

            {next.length > 0 && (
                <div className="oc-sec"><h3>🗓️ برنامه‌ریزی‌شده</h3>
                    <div className="oc-cards">{next.map((r) => (
                        <div key={r.id} className="oc-card">
                            <div className="oc-card-h"><b>{r.title}</b></div>
                            <div className="oc-card-m"><span>👩‍🏫 {r.teacher}</span><span>👥 {r.classroom}</span>{r.when && <span>🗓️ {r.when}</span>}</div>
                        </div>
                    ))}</div>
                </div>
            )}

            <RecordingsList recordings={recordings} title="🎬 فیلم‌های کلاس‌ها" />

            {past.length > 0 && (
                <div className="oc-sec"><h3>📚 کلاس‌های گذشته</h3>
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
        </DashLayout>
    );
}
