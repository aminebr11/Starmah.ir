import { useEffect, useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import RemediationPanel from '@/Components/RemediationPanel';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const when = (d) => (Number(d) === 0 ? 'همان روز' : Number(d) === 1 ? 'فردا' : `${fa(d)} روز بعد`);
const SOURCES = [['exam', '🧪 آزمون'], ['game', '🎮 بازی'], ['mission', '🎯 مأموریت'], ['grade', '📔 نمره‌ی ضعیفِ کلاسی']];

/**
 * «مرورِ اشتباه‌ها» — صفحه‌ی معلم.
 *
 * ۱) زمان‌بندی‌ای که معلم برای کلاس اعلام می‌کند (نوبتِ اول، تعدادِ نوبت، فاصله‌ها، …)؛
 *    هر اشتباهِ دانش‌آموز خودکار با همین زمان‌بندی مرور می‌شود.
 * ۲) رصدِ کلاس + فرستادنِ مرورِ دستی (RemediationPanel).
 */
export default function Review() {
    const { classrooms = [], classroom, plan: initial, remediation, errors = {} } = usePage().props;
    const [p, setP] = useState(initial);
    const [busy, setBusy] = useState(false);
    useEffect(() => setP(initial), [classroom?.id]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!classroom) {
        return (
            <DashLayout title="مرورِ اشتباه‌ها" roleLabel="معلم" menu={teacherMenu} active="review">
                <div className="panel"><p style={{ color: 'var(--muted)' }}>هنوز کلاسی به شما داده نشده است.</p></div>
            </DashLayout>
        );
    }

    const set = (k, v) => setP((o) => ({ ...o, [k]: v }));
    const setRounds = (n) => setP((o) => {
        const gaps = [...(o.gaps || [])];
        while (gaps.length < n - 1) gaps.push(gaps.length ? Math.min(30, gaps[gaps.length - 1] + 3) : 2);
        return { ...o, rounds: n, gaps: gaps.slice(0, n - 1) };
    });
    const setGap = (k, v) => setP((o) => ({ ...o, gaps: o.gaps.map((g, i) => (i === k ? v : g)) }));
    const save = () => {
        setBusy(true);
        router.put(route('teacher.review.plan'), { classroom_id: classroom.id, ...p }, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    // روزِ هر نوبت از لحظه‌ی اشتباه (اگر هر نوبت بارِ اول قبول شود)
    const days = [Number(p.first_delay)];
    (p.gaps || []).forEach((g) => days.push(days[days.length - 1] + Number(g)));

    return (
        <DashLayout title="مرورِ اشتباه‌ها" roleLabel="معلم" menu={teacherMenu} active="review"
            actions={classrooms.length > 1 && (
                <select className="input" style={{ width: 'auto' }} value={classroom.id}
                    onChange={(e) => router.get(route('teacher.review'), { classroom: e.target.value })}>
                    {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
            )}>
            <div className="panel rv-plan">
                <div className="rv-plan-h">
                    <div>
                        <h3 style={{ margin: 0 }}>🗓️ زمان‌بندیِ مرورِ «{classroom.name}»</h3>
                        <p>هر سؤالی که دانش‌آموز در آزمون یا بازی یا مأموریت اشتباه بزند، <b>خودکار و فقط برای خودِ او</b> یک مرور ساخته می‌شود:
                            همان سؤال با <b>گزینه‌های جابه‌جا</b> + سؤال‌های مشابه از همان فصل (از بانکِ سؤال؛ اگر کم بود، هوش مصنوعی می‌سازد). زمان‌بندی را اینجا شما اعلام می‌کنید.</p>
                    </div>
                    <label className="rv-switch">
                        <input type="checkbox" checked={!!p.enabled} onChange={(e) => set('enabled', e.target.checked)} />
                        <span>{p.enabled ? 'مرورِ خودکار روشن است' : 'مرورِ خودکار خاموش است'}</span>
                    </label>
                </div>

                <fieldset disabled={!p.enabled} className="rv-fs">
                    <div className="rv-grid">
                        <label>نوبتِ اول
                            <select className="input" value={p.first_delay} onChange={(e) => set('first_delay', Number(e.target.value))}>
                                {[0, 1, 2, 3, 4, 5, 7, 10, 14].map((d) => <option key={d} value={d}>{when(d)} بعد از اشتباه</option>)}
                            </select>
                        </label>
                        <label>تعدادِ نوبت تا «جبران شد»
                            <select className="input" value={p.rounds} onChange={(e) => setRounds(Number(e.target.value))}>
                                {[1, 2, 3, 4, 5].map((n) => <option key={n} value={n}>{fa(n)} نوبت</option>)}
                            </select>
                        </label>
                        {(p.gaps || []).map((g, k) => (
                            <label key={k}>فاصله‌ی نوبتِ {fa(k + 1)} تا {fa(k + 2)}
                                <select className="input" value={g} onChange={(e) => setGap(k, Number(e.target.value))}>
                                    {[1, 2, 3, 4, 5, 6, 7, 10, 14, 21, 30].map((d) => <option key={d} value={d}>{fa(d)} روز</option>)}
                                </select>
                            </label>
                        ))}
                        <label>اگر نوبتی قبول نشد
                            <select className="input" value={p.retry} onChange={(e) => set('retry', Number(e.target.value))}>
                                {[1, 2, 3, 5, 7].map((d) => <option key={d} value={d}>{d === 1 ? 'فردا' : `${fa(d)} روز بعد`} دوباره</option>)}
                            </select>
                        </label>
                        <label>نمره‌ی قبولیِ هر نوبت
                            <select className="input" value={p.pass} onChange={(e) => set('pass', Number(e.target.value))}>
                                {[50, 60, 70, 80, 100].map((d) => <option key={d} value={d}>{fa(d)}٪</option>)}
                            </select>
                        </label>
                        <label>سؤالِ مشابه کنارِ هر اشتباه
                            <select className="input" value={p.similar} onChange={(e) => set('similar', Number(e.target.value))}>
                                {[0, 1, 2, 3, 4].map((d) => <option key={d} value={d}>{d ? `${fa(d)} سؤال` : 'فقط خودِ سؤال'}</option>)}
                            </select>
                        </label>
                        <label>اشتباه در هر جلسه‌ی مرور
                            <select className="input" value={p.per_session} onChange={(e) => set('per_session', Number(e.target.value))}>
                                {[2, 3, 4, 5, 6, 8].map((d) => <option key={d} value={d}>{fa(d)} اشتباه</option>)}
                            </select>
                        </label>
                        <label>جبرانِ امتیاز
                            <select className="input" value={p.share} onChange={(e) => set('share', Number(e.target.value))}>
                                {[0, 20, 30, 40, 50].map((d) => <option key={d} value={d}>{d ? `تا ${fa(d)}٪ امتیازِ از دست‌رفته` : 'بدونِ امتیاز'}</option>)}
                            </select>
                        </label>
                    </div>

                    <div className="rv-src">
                        <b>اشتباه‌های کدام بخش مرور بسازد؟</b>
                        {SOURCES.map(([k, l]) => (
                            <label key={k}><input type="checkbox" checked={!!p.sources?.[k]} onChange={(e) => set('sources', { ...p.sources, [k]: e.target.checked })} /> {l}</label>
                        ))}
                    </div>

                    <div className="rv-timeline">
                        <span className="rv-step bad">❌ اشتباه</span>
                        {days.map((d, k) => (
                            <span key={k} style={{ display: 'contents' }}>
                                <span className="rv-arrow">←</span>
                                <span className="rv-step">نوبتِ {fa(k + 1)}<small>{d ? `روزِ ${fa(d)}` : 'همان روز'}</small></span>
                            </span>
                        ))}
                        <span className="rv-arrow">←</span>
                        <span className="rv-step ok">✅ جبران شد{Number(p.share) > 0 && <small>تا {fa(p.share)}٪ امتیاز</small>}</span>
                    </div>
                    <p className="rm-note">هر نوبت: همان سؤال‌ها با گزینه‌های جابه‌جا و ترتیبِ درهم + {fa(p.similar)} سؤالِ مشابه. قبولی با {fa(p.pass)}٪؛ اگر نشد {when(p.retry)} دوباره. سقفِ جبران ۵۰٪ است تا دانش‌آموزی که از اول درست زده همیشه جلوتر بماند.</p>
                </fieldset>

                {Object.keys(errors).length > 0 && <div className="rm-err">{Object.values(errors)[0]}</div>}
                <button className="btn" onClick={save} disabled={busy} style={{ marginTop: 10 }}>{busy ? 'در حالِ ذخیره…' : '💾 ذخیره‌ی زمان‌بندی'}</button>
            </div>

            {remediation ? <RemediationPanel data={remediation} /> : (
                <div className="panel"><p style={{ color: 'var(--muted)' }}>رصدِ مرورها پس از اجرای مایگریشن‌ها در دسترس است.</p></div>
            )}
        </DashLayout>
    );
}
