import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';
import Mascot from '@/Components/Mascot';
import { fa, ReportModal, RecordingsList } from '@/online/parts';

/** «🎥 کلاسِ آنلاین» — ساخت، شروع، گزارشِ حضور و فیلمِ ضبط‌شده (معلم). */
export default function OnlineClasses({ ready, allowed, schoolOn, media, max, rooms, classrooms, recordings = [] }) {
    const [open, setOpen] = useState(rooms.length === 0);
    const [editing, setEditing] = useState(null);
    const [report, setReport] = useState(null);
    const form = useForm({ title: '', classroom_id: classrooms[0]?.id ?? '', starts_at: '', duration_min: 45, recording_shared: true, now: false });
    const d = form.data;

    const submit = (now) => {
        form.transform((x) => ({ ...x, now }));
        const done = { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); setEditing(null); } };
        if (editing) form.put(route('teacher.online.update', editing), done);
        else form.post(route('teacher.online.store'), done);
    };
    const edit = (r) => {
        form.setData({ title: r.title, classroom_id: r.classroom_id ?? '', starts_at: r.starts_at || '', duration_min: r.duration_min || 45, recording_shared: r.recording_shared, now: false });
        setEditing(r.id); setOpen(true);
        setTimeout(() => document.querySelector('.oc-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    };
    const del = (r) => { if (confirm(`کلاسِ «${r.title}» حذف شود؟ گزارش و فیلمِ آن هم پاک می‌شود.`)) router.delete(route('teacher.online.destroy', r.id), { preserveScroll: true }); };
    const share = (r, shared) => router.post(route('online.recording.share', r.id), { shared }, { preserveScroll: true });
    const delRec = (r) => { if (confirm('فیلمِ ضبط‌شده حذف شود؟')) router.post(route('online.recording.share', r.id), { delete: true }, { preserveScroll: true }); };

    const live = rooms.filter((r) => r.status === 'live');
    const next = rooms.filter((r) => r.status === 'scheduled');
    const past = rooms.filter((r) => r.status === 'ended');

    return (
        <DashLayout title="کلاسِ آنلاین" roleLabel="معلم" menu={teacherMenu} active="online">
            <div className="at-hero oc-hero">
                <div className="oc-hero-m"><Mascot mood="wave" size={86} /></div>
                <div>
                    <h2>کلاسِ آنلاینِ ستاره‌ماه</h2>
                    <p>صدا و تصویر، تخته‌ی هوشمند با اسلاید و PDF، اشتراکِ صفحه، گفت‌وگو، دست‌بالا، سؤالِ سریع با امتیاز، ستاره‌دادن، تایمر، ضبطِ کلاس و گزارشِ حضور؛ در یک فضای شاد و کودکانه.</p>
                </div>
                {allowed && <button type="button" className="btn" onClick={() => { setEditing(null); form.reset(); setOpen(!open); }}>{open ? '✕ بستن' : '➕ کلاسِ تازه'}</button>}
            </div>

            {!ready && <div className="panel oc-warn">جدول‌های کلاسِ آنلاین هنوز روی سرور ساخته نشده‌اند؛ یک‌بار صفحه را تازه کنید یا از «سلامتِ سیستم» مایگریشن را اجرا کنید.</div>}
            {ready && !schoolOn && <div className="panel oc-warn">🔒 کلاسِ آنلاین برای مدرسه‌ی شما هنوز فعال نشده است (با مدیرِ سامانه هماهنگ کنید).</div>}
            {ready && schoolOn && !allowed && <div className="panel oc-warn">🔒 مدیرِ مدرسه هنوز اجازه‌ی برگزاریِ کلاسِ آنلاین را به شما نداده است.</div>}

            <RecordingsList recordings={recordings} manage
                onShare={(r, shared) => router.post(route('online.recording.share', r.room_id), { shared }, { preserveScroll: true })}
                onDelete={(r) => { if (confirm(`فیلمِ «${r.title}» (${r.at}) حذف شود؟`)) router.post(route('online.recording.share', r.room_id), { delete: true, rec: r.id }, { preserveScroll: true }); }} />

            {open && allowed && (
                <form className="panel at-form oc-form" onSubmit={(e) => { e.preventDefault(); submit(false); }}>
                    {editing && <div className="lv-edit-head"><b>✏️ ویرایشِ کلاس</b><button type="button" className="btn btn-sm btn-ghost" onClick={() => { setEditing(null); form.reset(); setOpen(false); }}>انصراف</button></div>}
                    <div className="at-grid">
                        <label>عنوان
                            <input className="input" value={d.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً ریاضی — جمعِ دو رقمی" />
                        </label>
                        <label>کلاس
                            <select className="input" value={d.classroom_id} onChange={(e) => form.setData('classroom_id', e.target.value)}>
                                {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name} ({fa(c.students)} نفر)</option>)}
                                <option value="">همه‌ی کلاس‌هایم</option>
                            </select>
                        </label>
                        <label>مدت (دقیقه)
                            <select className="input" value={d.duration_min} onChange={(e) => form.setData('duration_min', Number(e.target.value))}>
                                {[20, 30, 45, 60, 90, 120].map((m) => <option key={m} value={m}>{fa(m)} دقیقه</option>)}
                            </select>
                        </label>
                    </div>
                    {form.errors.title && <div className="rm-err">{form.errors.title}</div>}
                    <label className="oc-lbl">زمانِ شروع (اختیاری؛ بچه‌ها اعلان می‌گیرند)
                        <JalaliDatePicker value={d.starts_at} onChange={(v) => form.setData('starts_at', v)} withTime placeholder="انتخابِ تاریخ و ساعت" />
                    </label>
                    <label className="at-check"><input type="checkbox" checked={!!d.recording_shared} onChange={(e) => form.setData('recording_shared', e.target.checked)} /> فیلمِ ضبط‌شده برای بچه‌ها هم قابلِ دیدن باشد</label>
                    <div className="oc-form-go">
                        {!editing && <button type="button" className="btn at-go" disabled={form.processing || !d.title.trim()} onClick={() => submit(true)}>▶️ ساختن و شروعِ همین حالا</button>}
                        <button type="submit" className="btn btn-ghost" disabled={form.processing || !d.title.trim()}>{editing ? '💾 ذخیره' : '🗓️ فقط ساختن (برای بعد)'}</button>
                    </div>
                    <small className="oc-note">
                        {media === 'livekit' ? '🛰️ حالتِ سرورِ رسانه فعال است؛ مناسبِ کلاس‌های بزرگ.' : `⚡ حالتِ داخلی (بدونِ سرورِ جدا): برای کلاس‌های بزرگ‌تر از ۲۵ نفر بهتر است دوربین را خاموش و صدا و تخته را روشن نگه دارید.`}
                        {' '}سقفِ نفرات: {fa(max)}.
                    </small>
                </form>
            )}

            {[['live', '🔴 در حالِ برگزاری', live], ['next', '🗓️ برنامه‌ریزی‌شده', next], ['past', '📚 کلاس‌های گذشته', past]].map(([k, h, list]) => list.length > 0 && (
                <div key={k} className="oc-sec">
                    <h3>{h}</h3>
                    <div className="oc-cards">
                        {list.map((r) => (
                            <div key={r.id} className={`oc-card ${r.status}`}>
                                <div className="oc-card-h">
                                    <b>{r.title}</b>
                                    <span className={`oc-st ${r.status}`}>{r.status === 'live' ? `🔴 زنده · ${fa(r.online)} نفر` : r.status === 'scheduled' ? 'برنامه‌ریزی‌شده' : 'تمام‌شده'}</span>
                                </div>
                                <div className="oc-card-m">
                                    <span>👥 {r.classroom}</span>
                                    {r.when && <span>🗓️ {r.when}</span>}
                                    {r.status === 'ended' && r.started && <span>⏱️ {fa(r.length)} دقیقه · 🎓 {fa(r.present)} حاضر</span>}
                                </div>
                                <div className="oc-card-a">
                                    {r.status !== 'ended' && allowed && <a className="btn btn-sm" href={route('online.room', r.id)}>{r.status === 'live' ? '🎥 ورود' : '▶️ شروع'}</a>}
                                    {r.status !== 'live' && <button type="button" className="btn btn-sm btn-ghost" onClick={() => edit(r)}>✏️</button>}
                                    {r.present > 0 && <button type="button" className="btn btn-sm btn-ghost" onClick={() => setReport(r.id)}>📋 حضور</button>}
                                    {r.status !== 'live' && <button type="button" className="btn btn-sm btn-ghost" onClick={() => del(r)}>🗑️</button>}
                                </div>
                                {r.recording && (
                                    <div className="oc-card-rec">
                                        <a href={route('online.recording', r.id)} target="_blank" rel="noreferrer">🎬 دیدنِ فیلمِ کلاس{r.recording_size ? ` (${fa(Math.round(r.recording_size / 1048576))} مگ)` : ''}</a>
                                        <label><input type="checkbox" checked={r.recording_shared} onChange={(e) => share(r, e.target.checked)} /> برای بچه‌ها</label>
                                        <button type="button" onClick={() => delRec(r)}>حذف</button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            ))}
            {ready && rooms.length === 0 && !open && <div className="panel oc-empty-p"><Mascot mood="idle" size={70} /><p>هنوز کلاسی نساخته‌اید.</p></div>}
            {report && <ReportModal url={route('online.report', report)} onClose={() => setReport(null)} />}
        </DashLayout>
    );
}
