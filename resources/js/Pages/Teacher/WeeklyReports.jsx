import { useState } from 'react';
import { usePage, useForm, router } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const STATUS = { draft: ['پیش‌نویس', '#eef1f8', '#5d6886'], approved: ['✅ تأییدشده', '#e3f7ec', '#138a4f'], sent: ['📬 فرستاده شد', '#e8f1ff', '#2e6fd8'], skipped: ['⏭️ نفرست', '#fff1de', '#c96a10'] };
const MODES = [
    ['manual', '✋', 'دستی', 'هر وقت خواستید می‌سازید و می‌فرستید.'],
    ['approve', '👀', 'خودکار، بعد از تأییدِ من', 'سرِ موعد پیش‌نویس ساخته می‌شود و به شما خبر می‌دهد؛ با یک دکمه تأیید و ارسال.'],
    ['auto', '⚡', 'کاملاً خودکار', 'سرِ موعد ساخته و برای والدین فرستاده می‌شود.'],
];

function Report({ r, picked, onPick }) {
    const [note, setNote] = useState(r.note || '');
    const [open, setOpen] = useState(false);
    const d = r.data || {};
    const [label, bg, color] = STATUS[r.status] || STATUS.draft;
    const save = () => note !== (r.note || '') && router.patch(route('teacher.weekly.update', r.id), { note }, { preserveScroll: true, preserveState: true });
    const setStatus = (status) => router.patch(route('teacher.weekly.update', r.id), { status }, { preserveScroll: true });
    const locked = r.status === 'sent';

    return (
        <div className={`wr-card ${r.status}`}>
            <div className="wr-head">
                {!locked && r.status !== 'skipped' && <input type="checkbox" checked={picked} onChange={onPick} aria-label="انتخاب" />}
                <b>{r.student}</b>
                <span className="wr-badge" style={{ background: bg, color }}>{label}{r.sent ? ` · ${r.sent}` : ''}{r.sms_status === 'sent' ? ' · 📱' : r.sms_status === 'failed' ? ' · ⚠️ پیامک نرسید' : r.sms_status === 'no-phone' ? ' · بدونِ شماره' : ''}</span>
            </div>
            <div className="wr-chips">
                <span>⭐ {fa(d.xp ?? 0)} امتیاز</span>
                <span>📚 {fa(d.total_acts ?? 0)} فعالیت</span>
                {d.accuracy != null && <span>🎯 {fa(d.accuracy)}٪ دقت</span>}
                {d.minutes != null && <span>⏱️ {fa(d.minutes)} دقیقه</span>}
                {(d.attendance?.absent ?? 0) > 0 && <span className="bad">📅 {fa(d.attendance.absent)} غیبت</span>}
            </div>
            <ul className="wr-hl">{(d.highlights || []).map((h, i) => <li key={i}>{h}</li>)}</ul>
            {r.activity && (
                <div className="wr-act">
                    <b>⏱️ ۱۰ دقیقه با فرزندم: {r.activity.emoji} {r.activity.title}</b>
                    {open && <ol>{r.activity.steps.map((s, i) => <li key={i}>{s}</li>)}</ol>}
                    <button type="button" onClick={() => setOpen(!open)}>{open ? 'بستن' : 'مراحل'}</button>
                </div>
            )}
            <textarea className="input" rows={2} value={note} disabled={locked} onChange={(e) => setNote(e.target.value)} onBlur={save}
                placeholder="یادداشتِ شخصیِ شما برای والدین (اختیاری) — مثلاً: «این هفته در کارِ گروهی خیلی کمک کرد 👏»" maxLength={600} />
            {!locked && (
                <div className="wr-actions">
                    {r.status !== 'approved' && r.status !== 'skipped' && <button type="button" className="btn btn-sm btn-ghost" onClick={() => setStatus('approved')}>✅ تأیید</button>}
                    {r.status !== 'skipped' ? <button type="button" className="btn btn-sm btn-ghost" onClick={() => setStatus('skipped')}>⏭️ این هفته نفرست</button>
                        : <button type="button" className="btn btn-sm btn-ghost" onClick={() => setStatus('draft')}>↩️ برگرداندن</button>}
                    {r.status !== 'skipped' && <button type="button" className="btn btn-sm" onClick={() => { save(); router.post(route('teacher.weekly.send'), { ids: [r.id] }, { preserveScroll: true }); }}>📬 فرستادن</button>}
                </div>
            )}
            <details className="wr-sms"><summary>📱 متنِ پیامک</summary><pre>{r.sms_text}</pre></details>
        </div>
    );
}

/** معلم: «📬 گزارشِ هفتگیِ والدین». */
export default function WeeklyReports() {
    const { ready = true, classrooms = [], classroomId, week, weeks = [], settings, days = [], smsReady, reports = [] } = usePage().props;
    const f = useForm({ classroom_id: classroomId, ...settings, all: false });
    const [picked, setPicked] = useState({});
    const go = (params) => router.get(route('teacher.weekly'), { classroom: classroomId, week, ...params }, { preserveScroll: true });
    const sendable = reports.filter((r) => r.status === 'draft' || r.status === 'approved');
    const ids = Object.keys(picked).filter((k) => picked[k]).map(Number);
    const sendAll = (list) => list.length && confirm(`گزارشِ ${fa(list.length)} دانش‌آموز برای والدین فرستاده شود؟`) && router.post(route('teacher.weekly.send'), { ids: list }, { preserveScroll: true, onSuccess: () => setPicked({}) });

    return (
        <DashLayout title="گزارشِ هفتگیِ والدین" roleLabel="معلم" menu={teacherMenu} active="weekly">
            <div className="at-hero wr-hero">
                <div className="at-hero-ic">📬</div>
                <div>
                    <h2>گزارشِ هفتگیِ والدین + «۱۰ دقیقه با فرزندم»</h2>
                    <p>آخرِ هر هفته برای هر دانش‌آموز یک گزارشِ کوتاه و مهربان ساخته می‌شود: امتیاز، فعالیت‌ها، دقت، نقطه‌ی قوت، جایی که تمرین می‌خواهد، حضور و نمره‌ها — به‌علاوه‌ی یک فعالیتِ ۱۰ دقیقه‌ای برای خانه. شما یادداشت می‌گذارید و می‌فرستید (درونِ برنامه در «بخشِ والدین» و/یا پیامک).</p>
                </div>
            </div>
            {!ready && <div className="panel" style={{ background: '#fff4f4' }}>جدول‌های این بخش هنوز روی سرور ساخته نشده‌اند؛ مدیرِ کل از «🩺 سلامتِ سیستم» مایگریشن‌ها را اجرا کند.</div>}
            {classrooms.length === 0 ? <div className="panel">هنوز کلاسی ندارید.</div> : (
                <>
                    <div className="panel wr-bar">
                        <select className="input" value={classroomId ?? ''} onChange={(e) => go({ classroom: e.target.value })}>
                            {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                        <select className="input" value={week} onChange={(e) => go({ week: e.target.value })}>
                            {weeks.map((w) => <option key={w.value} value={w.value}>{w.label}</option>)}
                        </select>
                        <button type="button" className="btn" onClick={() => router.post(route('teacher.weekly.build'), { classroom_id: classroomId, week }, { preserveScroll: true })}>
                            🛠️ {reports.length ? 'به‌روز کردنِ آمار' : 'ساختنِ گزارش‌ها'}
                        </button>
                        {sendable.length > 0 && <button type="button" className="btn wr-send" onClick={() => sendAll(ids.length ? ids : sendable.map((r) => r.id))}>📬 {ids.length ? `فرستادنِ ${fa(ids.length)} انتخاب‌شده` : `تأیید و ارسالِ همه (${fa(sendable.length)})`}</button>}
                    </div>

                    <details className="panel wr-settings">
                        <summary>⚙️ تنظیماتِ ارسال برای این کلاس — حالت: <b>{MODES.find((m) => m[0] === settings.mode)?.[2]}</b> · {days[settings.day]} ساعتِ {fa(settings.hour)}</summary>
                        <form onSubmit={(e) => { e.preventDefault(); f.post(route('teacher.weekly.settings'), { preserveScroll: true }); }} className="at-form" style={{ marginTop: 10 }}>
                            <div className="at-kinds">
                                {MODES.map(([k, ic, t, sub]) => (
                                    <button key={k} type="button" className={`at-kind ${f.data.mode === k ? 'on' : ''}`} onClick={() => f.setData('mode', k)}><span>{ic}</span><b>{t}</b><small>{sub}</small></button>
                                ))}
                            </div>
                            <div className="at-grid">
                                <label>روزِ ارسال
                                    <select className="input" value={f.data.day} onChange={(e) => f.setData('day', Number(e.target.value))}>{days.map((d, i) => <option key={i} value={i}>{d}</option>)}</select>
                                </label>
                                <label>از ساعتِ
                                    <select className="input" value={f.data.hour} onChange={(e) => f.setData('hour', Number(e.target.value))}>{Array.from({ length: 17 }, (_, i) => i + 7).map((h) => <option key={h} value={h}>{fa(h)}:۰۰</option>)}</select>
                                </label>
                            </div>
                            <label className="at-check"><input type="checkbox" checked={!!f.data.app} onChange={(e) => f.setData('app', e.target.checked)} /> 📲 درونِ برنامه («بخشِ والدین» و اعلانِ حسابِ والد)</label>
                            <label className="at-check"><input type="checkbox" checked={!!f.data.sms} disabled={!smsReady} onChange={(e) => f.setData('sms', e.target.checked)} /> 📱 پیامک به والدین {!smsReady && <small style={{ color: 'var(--muted)' }}>(پیامکِ مدرسه فعال نیست)</small>}</label>
                            <label className="at-check"><input type="checkbox" checked={!!f.data.all} onChange={(e) => f.setData('all', e.target.checked)} /> همین تنظیم برای همه‌ی کلاس‌هایم</label>
                            <button type="submit" className="btn" disabled={f.processing}>💾 ذخیره‌ی تنظیمات</button>
                        </form>
                    </details>

                    {reports.length === 0 ? (
                        <div className="panel" style={{ textAlign: 'center', color: 'var(--muted)', lineHeight: 2 }}>
                            برای این هفته هنوز گزارشی ساخته نشده است.<br />
                            {settings.mode !== 'manual' ? `سرِ موعد (${days[settings.day]} ساعتِ ${fa(settings.hour)}) خودکار ساخته می‌شود؛ یا همین حالا «ساختنِ گزارش‌ها» را بزنید.` : 'دکمه‌ی «ساختنِ گزارش‌ها» را بزنید.'}
                        </div>
                    ) : (
                        <div className="wr-grid">
                            {reports.map((r) => <Report key={r.id} r={r} picked={!!picked[r.id]} onPick={() => setPicked({ ...picked, [r.id]: !picked[r.id] })} />)}
                        </div>
                    )}
                </>
            )}
        </DashLayout>
    );
}
