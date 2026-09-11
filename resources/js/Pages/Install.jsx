import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import WebLayout from '@/Layouts/WebLayout';
import { canInstall, promptInstall, isStandalone, isIos } from '@/lib/pwa';

/**
 * راهنمای نصبِ اپ روی گوشی.
 *
 * ستاره ماه یک «اپِ وب نصب‌شدنی» (PWA) است: با یک لمس روی صفحه‌ی اصلیِ
 * گوشی می‌نشیند، آیکون و نامِ خودش را دارد، تمام‌صفحه باز می‌شود و
 * **با هر تغییری که روی سایت بدهید خودش به‌روز می‌شود** — بدون نیاز به
 * کافه‌بازار، گوگل‌پلی یا اپ‌استور و بدون نصبِ دوباره.
 */
export default function Install() {
    const [installable, setInstallable] = useState(false);
    const [installed, setInstalled] = useState(false);
    const [ios, setIos] = useState(false);
    const [msg, setMsg] = useState('');

    useEffect(() => {
        const sync = () => setInstallable(canInstall());
        sync();
        setInstalled(isStandalone());
        setIos(isIos());
        window.addEventListener('starmah:installability', sync);
        return () => window.removeEventListener('starmah:installability', sync);
    }, []);

    const doInstall = async () => {
        const r = await promptInstall();
        setMsg(r === 'accepted' ? 'نصب شد! آیکونِ ستاره ماه را روی صفحه‌ی اصلیِ گوشی ببین 🌟'
            : r === 'dismissed' ? 'نصب لغو شد. هر وقت خواستی دوباره از همین‌جا اقدام کن.'
                : '');
    };

    return (
        <WebLayout title="نصبِ اپ" active="">
            <Head title="نصبِ اپ ستاره ماه" />

            <div className="container" style={{ maxWidth: 780, padding: '30px 20px 70px' }}>
                <div className="install-hero">
                    <img src="/brand/hero-emblem-560.webp" alt="" width="96" height="96" />
                    <div>
                        <h1>ستاره ماه را روی گوشی نصب کن</h1>
                        <p>
                            مثلِ یک اپِ واقعی روی صفحه‌ی اصلی می‌نشیند، تمام‌صفحه باز می‌شود
                            و <b>با هر به‌روزرسانیِ سایت، خودش به‌روز می‌شود</b> — بدون نصبِ دوباره.
                        </p>
                    </div>
                </div>

                {installed && (
                    <div className="install-ok">
                        ✅ اپ همین حالا در حالتِ نصب‌شده باز است. چیزی برای انجام‌دادن نیست.
                    </div>
                )}

                {msg && <div className="install-ok">{msg}</div>}

                {!installed && installable && (
                    <div className="install-cta">
                        <button onClick={doInstall} className="btn">📲 نصبِ اپ روی این دستگاه</button>
                        <span>مرورگرِ تو نصبِ یک‌لمسی را پشتیبانی می‌کند.</span>
                    </div>
                )}

                <div className="install-grid">
                    <section className={`install-card ${ios ? 'on' : ''}`}>
                        <h2> آیفون و آیپد</h2>
                        <p className="sub">در مرورگرِ <b>Safari</b> باز کن (کروم روی آیفون این امکان را ندارد)</p>
                        <ol>
                            <li>سایت <b>starmah.ir</b> را در Safari باز کن</li>
                            <li>روی دکمه‌ی <b>اشتراک‌گذاری</b> بزن — مربعی با فلشِ رو به بالا، پایینِ صفحه</li>
                            <li>فهرست را پایین بکش و <b>«Add to Home Screen»</b> یا <b>«افزودن به صفحه اصلی»</b> را بزن</li>
                            <li>روی <b>Add</b> بزن — تمام!</li>
                        </ol>
                        <p className="note">
                            آیفون دکمه‌ی نصبِ خودکار ندارد؛ این تنها راهِ رسمیِ اپل است.
                        </p>
                    </section>

                    <section className={`install-card ${!ios ? 'on' : ''}`}>
                        <h2>🤖 اندروید</h2>
                        <p className="sub">در <b>Chrome</b> یا هر مرورگرِ مبتنی بر آن</p>
                        <ol>
                            <li>سایت <b>starmah.ir</b> را باز کن</li>
                            <li>اگر نوارِ <b>«افزودن به صفحه اصلی»</b> پایینِ صفحه آمد، همان را بزن</li>
                            <li>وگرنه منوی <b>⋮</b> گوشه‌ی بالا → <b>«نصب برنامه»</b> یا <b>«Install app»</b></li>
                            <li>تأیید کن — آیکون روی صفحه‌ی اصلی می‌نشیند</li>
                        </ol>
                        <p className="note">
                            یا همین بالا دکمه‌ی «نصبِ اپ» را بزن، اگر نمایش داده شده باشد.
                        </p>
                    </section>
                </div>

                <section className="install-faq">
                    <h3>چند نکته</h3>
                    <dl>
                        <dt>آیا باید هر بار دوباره نصب کنم؟</dt>
                        <dd>
                            نه. اپ محتوا را از خودِ سایت می‌گیرد، پس هر تغییری که روی سایت
                            انجام شود بلافاصله در اپ هم دیده می‌شود. وقتی نسخه‌ی تازه‌ای منتشر
                            شود، نواری پایینِ صفحه ظاهر می‌شود و با یک لمس به‌روز می‌شوی.
                        </dd>

                        <dt>حجمش چقدر است؟</dt>
                        <dd>تقریباً هیچ. برخلافِ اپ‌های معمولی، چیزی دانلود نمی‌شود.</dd>

                        <dt>بدونِ اینترنت کار می‌کند؟</dt>
                        <dd>
                            صفحه‌هایی که قبلاً باز کرده‌ای نمایش داده می‌شوند، اما برای
                            امتیازها، آزمون‌ها و پیام‌های تازه به اینترنت نیاز است.
                        </dd>
                    </dl>
                </section>

                <div style={{ textAlign: 'center', marginTop: 26 }}>
                    <Link href="/" className="btn btn-ghost">← بازگشت به صفحه‌ی اصلی</Link>
                </div>
            </div>
        </WebLayout>
    );
}
