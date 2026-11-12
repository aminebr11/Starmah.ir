import { useState } from 'react';
import { usePage, useForm, router } from '@inertiajs/react';
import axios from 'axios';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';
import relaySrc from '../../../../docs/bot-relay/worker.js?raw';
import '@/../css/bot.css';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const ACTIONS = { attendance: '📋 حضور و غیاب', grades: '📔 نمره', announce: '📢 اطلاعیه', award: '⭐ امتیاز', discipline: '📒 انضباط', game: '🎮 بازی' };

/** ادمینِ کل: باتِ معلم (بله و تلگرام) — توکن‌ها، ثبتِ نشانی، واسطه‌ی تلگرام، دفترِ کارها. */
export default function AdminBot() {
    const { enabled, settings = {}, defaults = {}, webhooks = {}, stats, logs = [], ready, flash, brain = {} } = usePage().props;
    const form = useForm({ enabled, ...settings });
    const d = form.data;
    const [copied, setCopied] = useState(false);
    const [diag, setDiag] = useState({});   // {bale: {busy, steps}, telegram: …}
    const diagnose = async (p) => {
        setDiag((x) => ({ ...x, [p]: { busy: true } }));
        try {
            const { data } = await axios.post(route('admin.bot.diagnose', p));
            setDiag((x) => ({ ...x, [p]: { steps: data.steps } }));
        } catch (e) {
            setDiag((x) => ({ ...x, [p]: { steps: [{ ok: false, title: 'عیب‌یابی انجام نشد', detail: e?.response?.status ? `خطای ${e.response.status}` : 'اتصال قطع شد', fix: 'صفحه را تازه کنید و دوباره بزنید.' }] } }));
        }
    };
    const Diag = ({ p }) => {
        const v = diag[p];
        if (!v) return null;
        if (v.busy) return <div className="bt-diag">⏳ در حالِ بررسی روی سرور…</div>;
        const firstBad = v.steps.findIndex((x) => !x.ok);
        return (
            <div className="bt-diag">
                {v.steps.map((x, i) => (
                    <div key={i} className={`bt-step ${x.ok ? 'ok' : 'bad'} ${i === firstBad ? 'first' : ''}`}>
                        <b>{x.ok ? '✅' : '❌'} {x.title}</b>
                        {x.detail && <code dir="ltr">{x.detail}</code>}
                        {!x.ok && x.fix && <span>👉 {x.fix}</span>}
                    </div>
                ))}
                {firstBad === -1 && <div className="bt-step ok first"><b>🎉 همه‌چیز درست است. در بات «/start» را بزنید و کدِ ۶ رقمی را بفرستید.</b></div>}
            </div>
        );
    };
    const save = (e) => { e.preventDefault(); form.post(route('admin.bot.save'), { preserveScroll: true }); };
    const hook = (p) => router.post(route('admin.bot.webhook', p), {}, { preserveScroll: true });
    const copy = async () => { try { await navigator.clipboard.writeText(relaySrc); setCopied(true); setTimeout(() => setCopied(false), 1800); } catch { /* */ } };
    const msg = typeof flash?.flash === 'string' ? flash.flash : flash?.flash?.message;
    const F = (k, label, ph, ltr = true) => (
        <label className="bt-f"><span>{label}</span>
            <input className="input" value={d[k] ?? ''} onChange={(e) => form.setData(k, e.target.value)} placeholder={ph} dir={ltr ? 'ltr' : undefined} />
            {form.errors[k] && <em>{form.errors[k]}</em>}
        </label>
    );

    return (
        <DashLayout title="باتِ بله و تلگرام" roleLabel="ادمین کل" menu={adminMenu} active="bot">
            {msg && <div className="panel bt-flash">{msg}</div>}
            {!ready && <div className="panel bt-warn">جدول‌های بات هنوز ساخته نشده‌اند؛ از «🩺 سلامتِ سیستم» به‌روزرسانیِ پایگاه‌داده را اجرا کنید.</div>}

            <div className="bt-hero">
                <div className="bt-hero-ic">🤖</div>
                <div>
                    <h2>باتِ معلم</h2>
                    <p>معلم در «بله» یا «تلگرام» می‌نویسد: «همه حاضرن جز سارا»، «ریاضی: علی خیلی خوب…»، «اطلاعیه: …» یا «گزارشِ امروز». بات پیش‌نمایش می‌دهد و پس از تأیید، روی سایت ثبت می‌کند.</p>
                    {stats && <div className="bt-stats"><span>🔗 بله: {fa(stats.links?.bale || 0)}</span><span>🔗 تلگرام: {fa(stats.links?.telegram || 0)}</span><span>⚡ کارهای امروز: {fa(stats.today)}</span></div>}
                </div>
            </div>

            <form onSubmit={save}>
                <div className="panel">
                    <label className="bt-switch">
                        <input type="checkbox" checked={!!d.enabled} onChange={(e) => form.setData('enabled', e.target.checked)} />
                        <b>بات روشن باشد</b> <small>(مدیرِ هر مدرسه هم می‌تواند برای مدرسه‌ی خودش خاموشش کند)</small>
                    </label>
                    {F('site_url', '🌐 نشانیِ سایت (برای پیوندها و دریافتِ پیام)', defaults.site_url)}
                </div>

                <div className="panel bt-card">
                    <h3>🟢 بله <small>— مستقیم از سرورِ داخلِ ایران</small></h3>
                    <ol className="bt-steps">
                        <li>در «بله» به <b dir="ltr">@botfather</b> پیام بدهید، باتِ تازه بسازید و توکن را بگیرید.</li>
                        <li>توکن را این‌جا بگذارید، «ذخیره» و بعد «🔗 اتصال و ثبتِ نشانی» را بزنید.</li>
                    </ol>
                    <div className="bt-grid">
                        {F('bale_token', '🔑 توکنِ بات', '123456789:ABC…')}
                        {F('bale_username', '🏷️ نامِ کاربریِ بات (خودکار پر می‌شود)', 'starmah_bot')}
                        {F('bale_api', 'نشانیِ API (معمولاً خالی)', defaults.bale)}
                    </div>
                    <div className="bt-row">
                        <button type="button" className="btn btn-sm" onClick={() => hook('bale')} disabled={!settings.bale_token}>🔗 اتصال و ثبتِ نشانی</button>
                        <button type="button" className="btn btn-sm btn-ghost" onClick={() => diagnose('bale')}>🩺 عیب‌یابی</button>
                        <code dir="ltr">{webhooks.bale}</code>
                    </div>
                    <Diag p="bale" />
                </div>

                <div className="panel bt-card">
                    <h3>🔵 تلگرام <small>— از راهِ واسطه (سرورِ ایران به تلگرام دسترسی ندارد)</small></h3>
                    <ol className="bt-steps">
                        <li>در تلگرام به <b dir="ltr">@BotFather</b> پیام بدهید و توکن بگیرید.</li>
                        <li>یک Worker در Cloudflare بسازید (رایگان) و کدِ پایین را در آن بگذارید؛ متغیرهای <code>SITE</code> = نشانیِ سایت و <code>KEY</code> = یک رمزِ طولانی را تنظیم کنید.</li>
                        <li>
                            برای Worker یک <b>دامنه‌ی اختصاصی</b> بگذارید (مثلاً <code dir="ltr">tg.starmah.ir</code> روی Cloudflare).
                            دامنه‌ی <code dir="ltr">workers.dev</code> معمولاً از ایران باز نمی‌شود.
                        </li>
                        <li>
                            «نشانیِ API» را <code dir="ltr">https://tg.starmah.ir</code> بگذارید و «نشانیِ دریافت» را <code dir="ltr">https://tg.starmah.ir/hook</code>.
                            «کلیدِ واسطه» را همان <code>KEY</code> بگذارید. بعد «ذخیره» و «🔗 اتصال و ثبتِ نشانی» را بزنید.
                        </li>
                    </ol>
                    <div className="bt-grid">
                        {F('telegram_token', '🔑 توکنِ بات', '123456789:ABC…')}
                        {F('telegram_username', '🏷️ نامِ کاربریِ بات (خودکار پر می‌شود)', 'StarmahBot')}
                        {F('telegram_api', 'نشانیِ API ← واسطه', 'https://tg.starmah.ir')}
                        {F('telegram_webhook', 'نشانیِ دریافت ← واسطه', 'https://tg.starmah.ir/hook')}
                        {F('telegram_relay_key', '🔐 کلیدِ واسطه (KEY)', 'یک رمزِ طولانی')}
                    </div>
                    <div className="bt-row">
                        <button type="button" className="btn btn-sm" onClick={() => hook('telegram')} disabled={!settings.telegram_token}>🔗 اتصال و ثبتِ نشانی</button>
                        <button type="button" className="btn btn-sm btn-ghost" onClick={() => diagnose('telegram')}>🩺 عیب‌یابی</button>
                        <code dir="ltr">{webhooks.telegram}</code>
                    </div>
                    <Diag p="telegram" />
                    <details className="bt-code">
                        <summary>📄 کدِ واسطه (Cloudflare Worker)</summary>
                        <button type="button" className="btn btn-sm btn-ghost" onClick={copy}>{copied ? '✅ کپی شد' : '📋 کپی'}</button>
                        <pre dir="ltr">{relaySrc}</pre>
                    </details>
                </div>

                <div className="panel bt-card">
                    <h3>🧠 هوشِ دستیار و 🎙️ پیامِ صوتی</h3>
                    <div className="bt-brain">
                        <span className={brain.ai ? 'on' : ''}>{brain.ai ? `🧠 گفتگوی آزاد با هوش مصنوعیِ سایت (${brain.ai}) روشن است` : '🧠 هوش مصنوعیِ سایت تنظیم نشده؛ بات فقط دستورهای ساده را می‌فهمد'}</span>
                        <span className={brain.voice ? 'on' : ''}>{brain.voice ? '🎙️ پیامِ صوتی فعال است' : '🎙️ پیامِ صوتی هنوز سرویسِ «صدا به متن» ندارد'}</span>
                    </div>
                    <p className="bt-muted" style={{ fontSize: 12.5, lineHeight: 1.9 }}>
                        بات برای فهمِ گفتگو از همان هوش مصنوعیِ «مرکزِ هوش مصنوعی» استفاده می‌کند (و اجازه‌ی هوش مصنوعیِ هر مدرسه رعایت می‌شود).
                        برای پیامِ صوتی، اگر هوش مصنوعیِ سایت OpenAI یا سازگار با آن است یا کلیدِ Gemini دارید، کاری لازم نیست؛
                        وگرنه یک سرویسِ «صدا به متن» سازگار با OpenAI (مثلاً واسطه‌های ایرانی) را این‌جا بدهید.
                    </p>
                    <div className="bt-grid">
                        {F('stt_base', '🎙️ نشانیِ سرویسِ صدا به متن (اختیاری)', 'https://api.example.ir/v1')}
                        {F('stt_key', '🔑 کلیدِ سرویس', 'sk-…')}
                        {F('stt_model', 'مدل', 'whisper-1')}
                    </div>
                </div>

                <div className="bt-save"><button type="submit" className="btn" disabled={form.processing}>💾 ذخیره‌ی تنظیمات</button></div>
            </form>

            <div className="panel">
                <h3>📜 آخرین کارهای انجام‌شده از بات</h3>
                {logs.length === 0 ? <p className="bt-muted">هنوز کاری از بات انجام نشده.</p> : (
                    <div className="bt-logs">
                        {logs.map((l) => (
                            <div key={l.id} className={`bt-log ${l.undone ? 'undone' : ''}`}>
                                <b>{ACTIONS[l.action] || l.action}</b>
                                <span>{l.summary}</span>
                                <small>👤 {l.user} · {l.platform} · {fa(l.at)}{l.undone ? ' · ↩️ برگردانده شد' : ''}</small>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </DashLayout>
    );
}
