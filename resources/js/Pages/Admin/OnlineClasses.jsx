import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';
import { fa } from '@/online/parts';

/** «🎥 کلاسِ آنلاین» — ادمینِ کل: سرورِ رسانه، سرورهای اتصال و دسترسیِ هر مدرسه. */
export default function OnlineClasses({ ready, settings, active, schools }) {
    const form = useForm({ ...settings, livekit_secret: '' });
    const d = form.data;
    const [maxes, setMaxes] = useState(() => Object.fromEntries(schools.map((s) => [s.id, s.max])));
    const save = (e) => { e.preventDefault(); form.put(route('admin.online.settings'), { preserveScroll: true }); };
    const school = (s, enabled) => router.put(route('admin.online.school', s.id), { enabled, max: maxes[s.id] || s.max }, { preserveScroll: true });

    return (
        <DashLayout title="کلاسِ آنلاین" roleLabel="مدیر کل" menu={adminMenu} active="online">
            {!ready && <div className="panel oc-warn">جدول‌های کلاسِ آنلاین هنوز ساخته نشده‌اند.</div>}
            <form className="panel oc-admin" onSubmit={save}>
                <h3>🛰️ حالتِ رسانه <span className="oc-st">{active === 'livekit' ? 'فعال: سرورِ LiveKit' : 'فعال: داخلی'}</span></h3>
                <div className="at-kinds">
                    {[['builtin', '⚡', 'داخلی (بدونِ سرورِ جدا)', 'صدا و تصویرِ معلم مستقیم به مرورگرِ بچه‌ها می‌رود. هزینه ندارد؛ برای صدا و تخته تا حدودِ ۴۰ نفر و برای تصویر تا ۱۵–۲۰ نفر روان است.'],
                        ['livekit', '🛰️', 'سرورِ رسانه‌ی LiveKit', 'مثلِ اسکای‌روم: ۵۰ تا چند صد نفر با تصویرِ پایدار. یک سرورِ مجازی (VPS) با LiveKit لازم است.']].map(([k, ic, t, sub]) => (
                        <button key={k} type="button" className={`at-kind ${d.media === k ? 'on' : ''}`} onClick={() => form.setData('media', k)}>
                            <span>{ic}</span><b>{t}</b><small>{sub}</small>
                        </button>
                    ))}
                </div>
                {d.media === 'livekit' && (
                    <div className="at-grid">
                        <label>نشانیِ سرور (wss://)<input className="input" dir="ltr" value={d.livekit_url} onChange={(e) => form.setData('livekit_url', e.target.value)} placeholder="wss://live.example.ir" /></label>
                        <label>API Key<input className="input" dir="ltr" value={d.livekit_key} onChange={(e) => form.setData('livekit_key', e.target.value)} /></label>
                        <label>API Secret {settings.livekit_secret_set && <small>(ذخیره شده؛ برای تغییر بنویسید)</small>}<input className="input" dir="ltr" type="password" value={d.livekit_secret} onChange={(e) => form.setData('livekit_secret', e.target.value)} placeholder={settings.livekit_secret_set ? '••••••••' : ''} /></label>
                    </div>
                )}
                {form.errors.livekit_url && <div className="rm-err">{form.errors.livekit_url}</div>}
                <div className="at-grid">
                    <label>سقفِ ارسالیِ معلم در حالتِ داخلی (کیلوبیت)
                        <select className="input" value={d.budget} onChange={(e) => form.setData('budget', Number(e.target.value))}>
                            {[1500, 2000, 3000, 4000, 6000, 8000].map((v) => <option key={v} value={v}>{fa(v)}</option>)}
                        </select>
                    </label>
                    <label>سقفِ پیش‌فرضِ نفرات<input className="input" type="number" min={2} max={1000} value={d.default_max} onChange={(e) => form.setData('default_max', Number(e.target.value))} /></label>
                    <label className="at-check"><input type="checkbox" checked={!!d.recording} onChange={(e) => form.setData('recording', e.target.checked)} /> ضبطِ کلاس مجاز باشد</label>
                </div>
                <label className="oc-lbl">سرورهای STUN/TURN (هر خط: نشانی، یا «نشانی نام‌کاربری رمز»)
                    <textarea className="input" dir="ltr" rows={4} value={d.ice} onChange={(e) => form.setData('ice', e.target.value)} placeholder={'stun:stun.l.google.com:19302\nturn:turn.example.ir:3478 user pass'} />
                </label>
                <small className="oc-note">برای اینترنتِ همراهِ ایران یک سرورِ TURN داخلی (مثلاً coturn روی همان VPS) اتصال را خیلی مطمئن‌تر می‌کند.</small>
                <button type="submit" className="btn" disabled={form.processing}>💾 ذخیره‌ی تنظیمات</button>
            </form>

            <div className="panel">
                <h3>🏫 دسترسیِ مدرسه‌ها</h3>
                <div className="oc-tlist">
                    {schools.map((s) => (
                        <div key={s.id} className="oc-trow">
                            <b>{s.name}</b>
                            <small>{s.city || ''}{s.classes ? ` · ${fa(s.classes)} کلاس در ۳۰ روز` : ''}{s.live ? ` · 🔴 ${fa(s.live)} زنده` : ''}</small>
                            <label className="oc-max">سقف <input type="number" min={2} max={1000} value={maxes[s.id] ?? s.max} onChange={(e) => setMaxes({ ...maxes, [s.id]: Number(e.target.value) })} onBlur={() => s.enabled && maxes[s.id] !== s.max && school(s, true)} /> نفر</label>
                            <label className="oc-sw"><input type="checkbox" checked={s.enabled} onChange={(e) => school(s, e.target.checked)} /><i />فعال</label>
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}
