import { useForm, usePage } from '@inertiajs/react';
import DashLayout, { adminMenu, schoolMenu } from '@/Layouts/DashLayout';
import Mascot from '@/Components/Mascot';
import '@/../css/mahoo-settings.css';

/**
 * «🌙 ماهو» — تنظیماتِ دستیار. ادمینِ کل: کارهای مجاز در کلِ سامانه و ظاهر/صدا.
 * مدیرِ مدرسه: چه کسانی ماهو را ببینند، هوش مصنوعی، و کارهای مجازِ معلم‌ها و دانش‌آموزان.
 */
export default function Settings({ scope, defs = [], roles = {}, global = {}, school = null, access = null, aiReady, voiceReady }) {
    const { flash } = usePage().props;
    const isSchool = scope === 'school';
    const src = isSchool ? school : global;
    const f = useForm({
        abilities: src?.abilities ?? {},
        entrance: !!src?.entrance, sound: !!src?.sound, voice: !!src?.voice,
        access: access ?? undefined,
    });
    const setAb = (k, r, v) => f.setData('abilities', { ...f.data.abilities, [k]: r ? { ...(f.data.abilities[k] ?? {}), [r]: v } : v });
    const save = (e) => { e.preventDefault(); f.put(route(isSchool ? 'school.mahoo.save' : 'admin.mahoo.save'), { preserveScroll: true }); };
    const shown = isSchool ? defs.filter((d) => d.roles.some((r) => roles[r])) : defs;
    const Toggle = ({ on, onChange, disabled, label }) => (
        <label className={`ms-sw ${disabled ? 'off' : ''}`}>
            <input type="checkbox" checked={!!on && !disabled} disabled={disabled} onChange={(e) => onChange(e.target.checked)} /><i />{label}
        </label>
    );

    return (
        <DashLayout title="ماهو، دستیارِ هوشمند" roleLabel={isSchool ? 'مدیر مدرسه' : 'ادمین کل'} menu={isSchool ? schoolMenu : adminMenu} active="mahoo">
            <form onSubmit={save} className="ms">
                <div className="ms-hero">
                    <div className="ms-hero-m"><Mascot mood="wave" size={120} /></div>
                    <div>
                        <h2>🌙 ماهو، دستیارِ همه‌کاره</h2>
                        <p>{isSchool
                            ? 'تعیین کنید ماهو برای چه کسانی دیده شود و چه کارهایی را برای معلم‌ها و دانش‌آموزانِ مدرسه‌ی شما انجام دهد. ماهو هیچ کاری را بدونِ «✅ تأییدِ» خودِ کاربر انجام نمی‌دهد.'
                            : 'کارهایی که ماهو در کلِ سامانه انجام می‌دهد و ظاهر و صدایش را تعیین کنید. مدیرِ هر مدرسه از میانِ کارهای روشن، برای مدرسه‌ی خودش انتخاب می‌کند.'}</p>
                        <div className="ms-chips">
                            <span className={aiReady ? 'ok' : ''}>{aiReady ? '✨ هوش مصنوعی وصل است' : 'هوش مصنوعی وصل نیست (پاسخ‌های آماده)'}</span>
                            <span className={voiceReady ? 'ok' : ''}>{voiceReady ? '🔊 صدای گفتاری آماده است' : '🔇 صدای گفتاری تنظیم نشده'}</span>
                        </div>
                    </div>
                </div>
                {flash?.flash && <div className="ms-flash">{typeof flash.flash === 'string' ? flash.flash : flash.flash.message}</div>}

                {isSchool && access && (
                    <section className="ms-sec">
                        <h3>👀 چه کسانی ماهو را ببینند؟</h3>
                        <div className="ms-row">
                            <Toggle on={f.data.access.students} onChange={(v) => f.setData('access', { ...f.data.access, students: v })} label="🎒 دانش‌آموزان" />
                            <Toggle on={f.data.access.teachers} onChange={(v) => f.setData('access', { ...f.data.access, teachers: v })} label="👩‍🏫 معلم‌ها" />
                            <Toggle on={f.data.access.ai} onChange={(v) => f.setData('access', { ...f.data.access, ai: v })} label="✨ پاسخِ هوش مصنوعی" />
                        </div>
                        <small>بدونِ هوش مصنوعی هم ماهو با داده‌ی واقعیِ هر کاربر پاسخ می‌دهد و کارها را انجام می‌دهد (بی‌هزینه).</small>
                    </section>
                )}

                <section className="ms-sec">
                    <h3>🛠️ کارهایی که ماهو انجام می‌دهد</h3>
                    <div className="ms-grid">
                        {shown.map((d) => {
                            const gOff = isSchool && !global.abilities?.[d.key];
                            return (
                                <div key={d.key} className={`ms-card ${gOff ? 'dim' : ''}`}>
                                    <b>{d.title}</b>
                                    <p>{d.desc}</p>
                                    {gOff && <small className="ms-warn">ادمینِ کل این کار را برای کلِ سامانه خاموش کرده است.</small>}
                                    <div className="ms-row">
                                        {isSchool
                                            ? d.roles.filter((r) => roles[r]).map((r) => (
                                                <Toggle key={r} disabled={gOff} on={f.data.abilities?.[d.key]?.[r]} onChange={(v) => setAb(d.key, r, v)} label={roles[r]} />
                                            ))
                                            : <Toggle on={f.data.abilities?.[d.key]} onChange={(v) => setAb(d.key, null, v)} label="روشن" />}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </section>

                <section className="ms-sec">
                    <h3>🎬 ظاهر و صدا</h3>
                    <div className="ms-row wrap">
                        <Toggle disabled={isSchool && !global.entrance} on={f.data.entrance} onChange={(v) => f.setData('entrance', v)} label="🪂 ورودِ نمایشی (از بالا می‌افتد و خوش‌آمد می‌گوید)" />
                        <Toggle disabled={isSchool && !global.sound} on={f.data.sound} onChange={(v) => f.setData('sound', v)} label="🔔 صداهای بامزه (لمس، پیام، تایمر)" />
                        <Toggle disabled={(isSchool && !global.voice)} on={f.data.voice} onChange={(v) => f.setData('voice', v)} label="🗣️ خوش‌آمدِ گفتاری" />
                    </div>
                    <small>هر کاربر هم می‌تواند صدای ماهو را برای خودش خاموش کند. اگر سیستمِ کاربر «کاهشِ حرکت» را روشن کرده باشد، انیمیشن‌ها ساده می‌شوند.</small>
                </section>

                <div className="ms-bar">
                    <button type="submit" className="btn" disabled={f.processing}>{f.processing ? 'در حالِ ذخیره…' : '💾 ذخیره‌ی تنظیماتِ ماهو'}</button>
                </div>
            </form>
        </DashLayout>
    );
}
