import { useEffect, useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const IDEAS = ['🎨 یک نقاشی بکش', '📚 یک کتابِ کاغذی بخوان', '⚽ کمی بازی و ورزش کن', '🧩 با خانواده یک بازیِ فکری کن', '🌱 به گلدان‌ها آب بده', '🧱 یک چیزِ تازه بساز'];

/** «⏰ وقتِ استراحت» — بعد از پر شدنِ سقفِ زمان؛ والدین با رمز باز می‌کنند. */
export default function TimeUp({ status, hasPin }) {
    const [parent, setParent] = useState(false);
    const [now, setNow] = useState(Date.now() / 1000);
    const l = status.limits;
    const f = useForm({ pin: '', daily: l.parent_daily || 0, session: l.parent_session || 0 });
    useEffect(() => { const id = setInterval(() => setNow(Date.now() / 1000), 1000); return () => clearInterval(id); }, []);
    const wait = status.unlock_at ? Math.max(0, Math.round(status.unlock_at - now)) : null;
    useEffect(() => { if (wait === 0) router.visit('/dashboard'); }, [wait]);
    const opts = (max) => [0, 15, 20, 30, 45, 60, 90, 120].filter((m) => m === 0 || !max || m <= max);

    return (
        <div className="tu">
            <Head title="وقتِ استراحت" />
            <div className="tu-card">
                <div className="tu-moon">🌙</div>
                <h1>{status.reason === 'daily' ? 'وقتِ امروزت تمام شد!' : 'وقتِ یک استراحتِ حسابی!'}</h1>
                <p>امروز <b>{fa(Math.round(status.used / 60))}</b> دقیقه با استارماه یاد گرفتی. آفرین! 🌟 حالا بهتر است چشم‌ها و ذهنت استراحت کنند.</p>
                {status.reason === 'session' && wait != null && (
                    <div className="tu-wait">⏳ می‌توانی {wait > 60 ? `${fa(Math.ceil(wait / 60))} دقیقه‌ی دیگر` : `${fa(wait)} ثانیه‌ی دیگر`} برگردی</div>
                )}
                {status.reason === 'daily' && <div className="tu-wait">🌅 فردا دوباره منتظرت هستیم</div>}
                <div className="tu-ideas">{IDEAS.map((i) => <span key={i}>{i}</span>)}</div>

                {!parent ? (
                    <button type="button" className="tu-parent-btn" onClick={() => setParent(true)}>👨‍👩‍👧 بازکردن توسطِ والدین</button>
                ) : !hasPin ? (
                    <div className="tu-note">رمزِ والدین برای این حساب تعیین نشده است؛ از معلم یا مدیرِ مدرسه بخواهید رمز را تنظیم کند. معلم هم می‌تواند زمان را باز کند.</div>
                ) : (
                    <form className="tu-form" onSubmit={(e) => { e.preventDefault(); f.post(route('time-up.unlock')); }}>
                        <b>والدینِ عزیز</b>
                        <small>با رمزِ والدین، زمانِ امروز صفر می‌شود. اگر بخواهید می‌توانید سقفی کمتر از سقفِ معلم هم بگذارید.</small>
                        <input value={f.data.pin} onChange={(e) => f.setData('pin', e.target.value.replace(/\D/g, '').slice(0, 8))} dir="ltr" inputMode="numeric" placeholder="• • • • •" autoFocus className="tu-pin" />
                        {f.errors.pin && <div className="tu-err">{f.errors.pin}</div>}
                        <div className="tu-row">
                            <label>سقفِ روزانه
                                <select value={f.data.daily} onChange={(e) => f.setData('daily', Number(e.target.value))}>
                                    {opts(l.class_daily).map((m) => <option key={m} value={m}>{m ? `${fa(m)} دقیقه` : (l.class_daily ? `سقفِ معلم (${fa(l.class_daily)})` : 'بدونِ سقف')}</option>)}
                                </select>
                            </label>
                            <label>هر بار حضور
                                <select value={f.data.session} onChange={(e) => f.setData('session', Number(e.target.value))}>
                                    {opts(l.class_session).map((m) => <option key={m} value={m}>{m ? `${fa(m)} دقیقه` : (l.class_session ? `سقفِ معلم (${fa(l.class_session)})` : 'بدونِ سقف')}</option>)}
                                </select>
                            </label>
                        </div>
                        <button type="submit" className="tu-go" disabled={f.processing || !f.data.pin}>🔓 صفر کردنِ زمان و ادامه</button>
                    </form>
                )}
                <button type="button" className="tu-out" onClick={() => router.post(route('logout'))}>خروج از حساب</button>
            </div>
        </div>
    );
}
