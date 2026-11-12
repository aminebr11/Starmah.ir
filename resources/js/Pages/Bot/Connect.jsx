import { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import axios from 'axios';
import DashLayout, { teacherMenu, schoolMenu, adminMenu } from '@/Layouts/DashLayout';
import '@/../css/bot.css';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** معلم / مدیرِ مدرسه: اتصالِ حساب به باتِ «بله» یا «تلگرام». */
export default function BotConnect() {
    const { allowed, globalOn, schoolOn, isSchoolAdmin, menu, platforms = [], links = [], schoolLinks = [], flash } = usePage().props;
    const [code, setCode] = useState(null);
    const [busy, setBusy] = useState(null);
    const [err, setErr] = useState(null);
    const msg = typeof flash?.flash === 'string' ? flash.flash : flash?.flash?.message;

    const getCode = async (p) => {
        setBusy(p); setErr(null);
        try {
            const { data } = await axios.post(route('bot.code'), { platform: p.key });
            setCode({ ...data, label: p.label });
        } catch (e) {
            setErr(e?.response?.data?.message || 'گرفتنِ کد نشد؛ دوباره امتحان کنید.');
        } finally { setBusy(null); }
    };
    const toggle = (l, k) => router.post(route('bot.links.update', l.id), { brief: l.brief, notify: l.notify, [k]: !l[k] }, { preserveScroll: true });
    const unlink = (l) => confirm(`اتصالِ ${l.label} قطع شود؟`) && router.delete(route('bot.links.destroy', l.id), { preserveScroll: true });

    return (
        <DashLayout title="باتِ بله و تلگرام" roleLabel={menu === 'admin' ? 'ادمین کل' : menu === 'school' ? 'مدیر مدرسه' : 'معلم'} menu={menu === 'admin' ? adminMenu : menu === 'school' ? schoolMenu : teacherMenu} active="bot">
            {msg && <div className="panel bt-flash">{msg}</div>}
            <div className="bt-hero">
                <div className="bt-hero-ic">🤖</div>
                <div>
                    <h2>کارهای کلاس را از «بله» یا «تلگرام» انجام بده</h2>
                    <p>🔔 هر اعلانِ تازه‌ی سایت همان لحظه در بات هم می‌آید. حضور و غیاب، ثبتِ نمره، اطلاعیه و گزارش‌ها — فقط بنویس چه می‌خواهی؛ بات پیش‌نمایش می‌دهد و با «✅ تأیید» روی سایت ثبت می‌کند. هر کار تا ۱۰ دقیقه «↩️ برگردان» دارد.</p>
                </div>
            </div>

            {!globalOn && <div className="panel bt-warn">بات هنوز از طرفِ مدیرِ سامانه روشن نشده است.</div>}
            {globalOn && !schoolOn && <div className="panel bt-warn">بات برای این مدرسه خاموش است{isSchoolAdmin ? '؛ از پایینِ همین صفحه روشنش کنید.' : '؛ از مدیرِ مدرسه بخواهید روشنش کند.'}</div>}

            {allowed && (
                <div className="panel">
                    <h3>🔗 اتصال</h3>
                    <div className="bt-plats">
                        {platforms.map((p) => (
                            <button key={p.key} type="button" className={`bt-plat ${p.key}`} disabled={!p.ready || busy} onClick={() => getCode(p)}>
                                <span>{p.key === 'bale' ? '🟢' : '🔵'}</span>
                                <b>اتصال به {p.label}</b>
                                <small>{p.ready ? `@${p.username}` : 'هنوز راه‌اندازی نشده'}</small>
                            </button>
                        ))}
                    </div>
                    {err && <div className="bt-err">{err}</div>}
                    {code && (
                        <div className="bt-code-box">
                            <div>کدِ اتصال به {code.label} (۱۰ دقیقه اعتبار):</div>
                            <div className="bt-big" dir="ltr">{fa(code.code)}</div>
                            {code.url && <a className="btn" href={code.url} target="_blank" rel="noreferrer">📲 بازکردنِ بات و اتصالِ خودکار</a>}
                            <small>اگر پیوند کار نکرد: در {code.label} باتِ <b dir="ltr">@{code.bot}</b> را باز کنید، «شروع» را بزنید و همین کدِ ۶ رقمی را بفرستید.</small>
                        </div>
                    )}
                </div>
            )}

            {links.length > 0 && (
                <div className="panel">
                    <h3>📱 گفتگوهای وصل‌شده</h3>
                    {links.map((l) => (
                        <div key={l.id} className="bt-link">
                            <div><b>{l.platform === 'bale' ? '🟢' : '🔵'} {l.label}</b> <span dir="ltr">{l.username}</span><small>از {fa(l.since)}{l.seen ? ` · آخرین استفاده ${fa(l.seen)}` : ''}</small></div>
                            <div className="bt-link-a">
                                <label><input type="checkbox" checked={l.brief} onChange={() => toggle(l, 'brief')} /> 🌅 گزارشِ صبحگاهی</label>
                                <label><input type="checkbox" checked={l.notify} onChange={() => toggle(l, 'notify')} /> 🔔 اعلان‌های تازه</label>
                                <button type="button" className="btn btn-sm btn-ghost" onClick={() => unlink(l)}>🔌 قطعِ اتصال</button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="panel">
                <h3>💬 نمونه‌ی دستورها</h3>
                <ul className="bt-ex">
                    <li>🎙️ پیامِ صوتی بفرست یا معمولی بنویس — مثلِ گفتگو با یک دستیار</li>
                    <li>📋 «امروز همه هستن جز سارا و مریم، رضا هم دیر اومد» · «دیروز علی غایب بود»</li>
                    <li>📔 «ریاضی کسرها: علی خیلی خوب، سارا خوب، بقیه خوب» · «نمره علوم از ۱۰: سارا ۹»</li>
                    <li>⭐ «به سارا و علی ۱۰ امتیاز بده به‌خاطرِ همکاری»</li>
                    <li>📒 «برای رضا تذکرِ بی‌نظمی ثبت کن» (طبقِ موضوع‌های انضباطیِ مدرسه)</li>
                    <li>🎮 «یک بازیِ ۸ سؤالی از کسرها بساز» · 📝 «یک آزمونِ ۱۰ سؤالی از گیاهان»</li>
                    <li>📎 عکس یا PDFِ برگه‌ی نمونه‌سؤال یا صفحه‌ی کتاب بفرست → آزمون یا بازی</li>
                    <li>🗄 «بانکِ سؤال» و 👥 «دانش‌آموزان» از منوی بات</li>
                    <li>✍️ «برای کسایی که امشب روخوانی فرستادن خیلی خوب بزن» · یا از «صفِ تصحیح» صدای روخوانی را گوش بده و با یک دکمه نمره بده</li>
                    <li>🏆 «یک مسابقه‌ی زنده‌ی ۱۰ سؤالی از کسرها برای ساعت ۸ شب بساز» · 📨 پیام‌های بچه‌ها و پاسخ · ⭐ امتیازها</li>
                    <li>📢 «به بچه‌ها بگو فردا کتابِ علوم بیارن» · «به سارا بگو دفترش رو بیاره»</li>
                    <li>👤 «سارا توی ریاضی چطوره؟» · «گزارشِ امروز» · «چه کارهایی منتظرِ تصحیحه؟»</li>
                </ul>
            </div>

            {isSchoolAdmin && globalOn && (
                <div className="panel">
                    <h3>🏫 بات برای مدرسه</h3>
                    <label className="bt-switch">
                        <input type="checkbox" checked={schoolOn} onChange={(e) => router.post(route('bot.school'), { on: e.target.checked }, { preserveScroll: true })} />
                        <b>معلم‌های این مدرسه بتوانند از بات استفاده کنند</b>
                    </label>
                    {schoolLinks.length > 0 && (
                        <div className="bt-logs" style={{ marginTop: 10 }}>
                            {schoolLinks.map((l) => (
                                <div key={l.id} className="bt-log">
                                    <b>👤 {l.user}</b><span>{l.label}{l.seen ? ` · آخرین استفاده ${fa(l.seen)}` : ''}</span>
                                    <button type="button" className="btn btn-sm btn-ghost" onClick={() => unlink(l)}>🔌 قطع</button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </DashLayout>
    );
}
