import { usePage, useForm, router } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const mins = (v) => (v ? `${fa(v)} دقیقه` : 'خاموش');

/** معلم: «🌿 سلامتِ دیجیتال» — یادآوریِ استراحت و سقفِ زمان برای هر کلاس. */
export default function Wellbeing() {
    const { ready = true, classrooms = [], classroomId, settings, students = [] } = usePage().props;
    const f = useForm({ classroom_id: classroomId, ...settings, all: false });
    const sel = (k, list) => (
        <select className="input" value={f.data[k]} onChange={(e) => f.setData(k, Number(e.target.value))}>
            {list.map((m) => <option key={m} value={m}>{k === 'break_minutes' ? `${fa(m)} دقیقه` : mins(m)}</option>)}
        </select>
    );

    return (
        <DashLayout title="سلامتِ دیجیتال" roleLabel="معلم" menu={teacherMenu} active="wellbeing">
            <div className="at-hero wb-hero">
                <div className="at-hero-ic">🌿</div>
                <div>
                    <h2>سلامتِ دیجیتالِ کلاس</h2>
                    <p>بعد از هر ۲۰ تا ۲۵ دقیقه کار، یک پنجره‌ی «استراحتِ کوتاه» با حرکت‌های کششی می‌آید. برای کلاس سقفِ روزانه و سقفِ «هر بار حضور» بگذارید؛ بعد از سقف، حساب قفل می‌شود و فقط والدین با رمزشان می‌توانند زمان را صفر کنند یا سقفی کمتر از سقفِ شما بگذارند. (مسابقه‌ی زنده‌ی کلاس قفل نمی‌شود.)</p>
                </div>
            </div>
            {!ready && <div className="panel" style={{ background: '#fff4f4' }}>جدول‌های این بخش هنوز روی سرور ساخته نشده‌اند؛ مدیرِ کل از «🩺 سلامتِ سیستم» مایگریشن‌ها را اجرا کند.</div>}
            {classrooms.length === 0 ? <div className="panel">هنوز کلاسی ندارید.</div> : (
                <>
                    <div className="panel wr-bar">
                        <select className="input" value={classroomId ?? ''} onChange={(e) => router.get(route('teacher.wellbeing'), { classroom: e.target.value })}>
                            {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                    <form className="panel at-form" onSubmit={(e) => { e.preventDefault(); f.post(route('teacher.wellbeing.settings'), { preserveScroll: true }); }}>
                        <div className="at-grid">
                            <label>🧘 یادآوریِ استراحت هر{sel('break_every', [0, 15, 20, 25, 30, 40])}</label>
                            <label>مدتِ استراحت{sel('break_minutes', [1, 2, 3, 5])}</label>
                            <label>⏰ سقفِ روزانه{sel('daily', [0, 20, 30, 45, 60, 90, 120, 180])}</label>
                            <label>🚪 سقفِ هر بار حضور{sel('session', [0, 15, 20, 30, 45, 60])}</label>
                        </div>
                        <p className="at-tip">«هر بار حضور» یعنی کارِ پیوسته؛ اگر دانش‌آموز ۲۰ دقیقه از سایت دور باشد، دورِ تازه شروع می‌شود.</p>
                        <label className="at-check"><input type="checkbox" checked={!!f.data.all} onChange={(e) => f.setData('all', e.target.checked)} /> همین تنظیم برای همه‌ی کلاس‌هایم</label>
                        <button type="submit" className="btn" disabled={f.processing}>💾 ذخیره</button>
                    </form>
                    <div className="panel">
                        <h3 style={{ marginTop: 0 }}>⏱️ زمانِ استفاده‌ی امروز</h3>
                        <div className="ew-table-wrap">
                            <table className="ew-table" style={{ minWidth: 560 }}>
                                <thead><tr><th>دانش‌آموز</th><th>امروز</th><th>این دور</th><th>۷ روزِ اخیر</th><th>سقفِ والدین</th><th>وضعیت</th><th /></tr></thead>
                                <tbody>
                                    {students.map((s) => (
                                        <tr key={s.id} className={s.locked ? 'warn' : ''}>
                                            <td><b>{s.name}</b></td>
                                            <td>{fa(s.today)} دقیقه</td>
                                            <td>{fa(s.session)} دقیقه</td>
                                            <td>{fa(s.week)} دقیقه</td>
                                            <td>{s.parent_daily || s.parent_session ? `${s.parent_daily ? `روزانه ${fa(s.parent_daily)}` : ''}${s.parent_session ? ` · هر بار ${fa(s.parent_session)}` : ''}` : '—'}</td>
                                            <td>{s.locked === 'daily' ? '🔒 سقفِ روزانه' : s.locked === 'session' ? '🔒 استراحت' : '✅'}{s.resets ? <small> · {fa(s.resets)} بار باز شد</small> : null}</td>
                                            <td>{(s.today > 0 || s.locked) && <button type="button" className="btn btn-sm btn-ghost" onClick={() => router.post(route('teacher.wellbeing.reset', s.id), {}, { preserveScroll: true })}>🔓 صفر کردن</button>}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </>
            )}
        </DashLayout>
    );
}
