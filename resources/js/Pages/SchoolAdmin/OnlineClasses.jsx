import { useState } from 'react';
import { router } from '@inertiajs/react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
import Mascot from '@/Components/Mascot';
import { fa, ReportModal } from '@/online/parts';

/** «🎥 کلاسِ آنلاین» — کنترلِ مدیرِ مدرسه: اجازه‌ی معلم‌ها، ناظرها، کلاس‌های زنده و گزارش. */
export default function OnlineClasses({ ready, on, max, media, teachers, rooms, stats }) {
    const [report, setReport] = useState(null);
    const grant = (user, field, value) => router.post(route('school.online.grant'), { user, field, value }, { preserveScroll: true });
    const grantAll = (value) => { if (confirm(value ? 'به همه‌ی معلم‌ها اجازه‌ی کلاسِ آنلاین داده شود؟' : 'اجازه‌ی همه‌ی معلم‌ها برداشته شود؟')) router.post(route('school.online.grant'), { all: true, field: 'can_host', value }, { preserveScroll: true }); };
    const end = (r) => { if (confirm(`کلاسِ «${r.title}» همین حالا تمام شود؟`)) router.post(route('online.end', r.id), {}, { preserveScroll: true }); };
    const live = rooms.filter((r) => r.status === 'live');

    return (
        <DashLayout title="کلاسِ آنلاین" roleLabel="مدیر مدرسه" menu={schoolMenu} active="online">
            <div className="at-hero oc-hero">
                <div className="oc-hero-m"><Mascot mood="idle" size={86} /></div>
                <div>
                    <h2>کنترلِ کلاس‌های آنلاینِ مدرسه</h2>
                    <p>به هر معلم اجازه‌ی برگزاری بدهید یا بردارید، ناظر تعیین کنید، وارد هر کلاسِ زنده شوید، آن را تمام کنید و حضور و غیابِ هر جلسه را ببینید.</p>
                </div>
            </div>
            {!ready && <div className="panel oc-warn">جدول‌های کلاسِ آنلاین هنوز ساخته نشده‌اند.</div>}
            {ready && !on && <div className="panel oc-warn">🔒 کلاسِ آنلاین برای مدرسه‌ی شما فعال نیست. فعال‌سازی با مدیرِ کلِ سامانه است.</div>}

            {ready && on && (
                <>
                    <div className="oc-stats">
                        <div><b>{fa(stats.live)}</b><small>کلاسِ زنده</small></div>
                        <div><b>{fa(stats.classes)}</b><small>کلاس در ۳۰ روز</small></div>
                        <div><b>{fa(stats.minutes)}</b><small>دقیقه آموزش</small></div>
                        <div><b>{fa(max)}</b><small>سقفِ نفرات</small></div>
                        <div><b>{media === 'livekit' ? 'سرور' : 'داخلی'}</b><small>حالتِ رسانه</small></div>
                    </div>

                    {live.length > 0 && (
                        <div className="oc-sec"><h3>🔴 در حالِ برگزاری</h3>
                            <div className="oc-cards">{live.map((r) => (
                                <div key={r.id} className="oc-card live">
                                    <div className="oc-card-h"><b>{r.title}</b><span className="oc-st live">{fa(r.online)} نفر</span></div>
                                    <div className="oc-card-m"><span>👩‍🏫 {r.teacher}</span><span>👥 {r.classroom}</span><span>⏱️ {fa(r.length)} دقیقه</span></div>
                                    <div className="oc-card-a">
                                        <a className="btn btn-sm" href={route('online.room', r.id)}>🛡️ ورود به‌عنوانِ ناظر</a>
                                        <button type="button" className="btn btn-sm btn-ghost" onClick={() => setReport(r.id)}>📋 حضور</button>
                                        <button type="button" className="btn btn-sm btn-danger" onClick={() => end(r)}>⏹ پایان</button>
                                    </div>
                                </div>
                            ))}</div>
                        </div>
                    )}

                    <div className="panel">
                        <div className="oc-row-h">
                            <h3>👩‍🏫 معلم‌ها</h3>
                            <span>
                                <button type="button" className="btn btn-sm" onClick={() => grantAll(true)}>✅ اجازه به همه</button>
                                <button type="button" className="btn btn-sm btn-ghost" onClick={() => grantAll(false)}>برداشتنِ همه</button>
                            </span>
                        </div>
                        <div className="oc-tlist">
                            {teachers.map((t) => (
                                <div key={t.id} className="oc-trow">
                                    <b>{t.name}</b>
                                    <small>{t.rooms ? `${fa(t.rooms)} کلاس` : 'بدونِ کلاس'}</small>
                                    <label className="oc-sw"><input type="checkbox" checked={t.can_host} onChange={(e) => grant(t.id, 'can_host', e.target.checked)} /><i />برگزاریِ کلاس</label>
                                    <label className="oc-sw"><input type="checkbox" checked={t.supervisor} onChange={(e) => grant(t.id, 'supervisor', e.target.checked)} /><i />ناظم / ناظر</label>
                                </div>
                            ))}
                            {!teachers.length && <div className="oc-empty">معلمی در مدرسه ثبت نشده است.</div>}
                        </div>
                        <small className="oc-note">«ناظم / ناظر» در منوی خودش صفحه‌ی «🛡️ نظارتِ کلاس‌ها» را می‌بیند. از آن‌جا وارد هر کلاسِ مدرسه می‌شود، میکروفون‌ها را کنترل می‌کند، پیامِ نامناسب را پنهان می‌کند، کلاس را تمام می‌کند و حضور و فیلم‌ها را می‌بیند. برای ناظمی که درس نمی‌دهد، از «معلم‌ها» یک حساب بسازید (لازم نیست کلاسی داشته باشد) و فقط همین کلید را روشن کنید.</small>
                    </div>

                    <div className="oc-sec"><h3>📚 همه‌ی کلاس‌ها</h3>
                        <div className="oc-table-wrap">
                            <table className="oc-rep">
                                <thead><tr><th>عنوان</th><th>معلم</th><th>کلاس</th><th>وضعیت</th><th>زمان</th><th>حاضر</th><th></th></tr></thead>
                                <tbody>{rooms.map((r) => (
                                    <tr key={r.id}>
                                        <td>{r.title}</td><td>{r.teacher}</td><td>{r.classroom}</td>
                                        <td>{r.status === 'live' ? '🔴 زنده' : r.status === 'scheduled' ? '🗓️ برنامه' : '✓ تمام'}</td>
                                        <td>{r.started || r.when || '—'}{r.length ? ` · ${fa(r.length)} دقیقه` : ''}</td>
                                        <td>{fa(r.present)}</td>
                                        <td>
                                            {r.present > 0 && <button type="button" className="oc-link" onClick={() => setReport(r.id)}>📋</button>}
                                            {r.recording && <a className="oc-link" href={route('online.recording', r.id)} target="_blank" rel="noreferrer">🎬</a>}
                                        </td>
                                    </tr>
                                ))}</tbody>
                            </table>
                            {!rooms.length && <div className="oc-empty">هنوز کلاسی برگزار نشده.</div>}
                        </div>
                    </div>
                </>
            )}
            {report && <ReportModal url={route('online.report', report)} onClose={() => setReport(null)} />}
        </DashLayout>
    );
}
