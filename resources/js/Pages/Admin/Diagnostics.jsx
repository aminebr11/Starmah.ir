import { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import axios from 'axios';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * سلامتِ سیستم — هر چیزی که روی هاست می‌تواند «خطای ۵۰۰» بسازد، یک‌جا:
 * مایگریشنِ اجرانشده، ستون/جدولِ ناموجود، charset، خطاهای اخیر، و آزمایشِ واقعیِ ذخیره.
 */
export default function Diagnostics() {
    const { env = {}, pending = [], schema = [], charset = [], errors = [], autoFail = null } = usePage().props;
    const [busy, setBusy] = useState(null);
    const [out, setOut] = useState(null);
    const [test, setTest] = useState(null);

    const call = async (name, url) => {
        setBusy(name); setOut(null);
        try {
            const { data } = await axios.post(url, {}, { headers: { Accept: 'application/json' } });
            setOut({ ok: data.ok, text: data.output || '' });
            router.reload({ preserveScroll: true });
        } catch (e) {
            setOut({ ok: false, text: e?.response?.data?.message || 'درخواست ناموفق بود.' });
        } finally { setBusy(null); }
    };
    const runTest = async () => {
        setBusy('test'); setTest(null);
        try {
            const { data } = await axios.post(route('admin.diagnostics.test'), {}, { headers: { Accept: 'application/json' } });
            setTest(data);
        } catch (e) {
            setTest({ ok: false, steps: [{ name: 'اجرای آزمایش', ok: false, error: e?.response?.data?.message || `خطای ${e?.response?.status || 'شبکه'}` }] });
        } finally { setBusy(null); }
    };

    const healthy = !pending.length && !schema.length && !charset.length;

    return (
        <DashLayout title="سلامتِ سیستم" roleLabel="ادمینِ کل" menu={adminMenu} active="diagnostics">
            <div className={`panel dg-hero ${healthy ? 'ok' : 'bad'}`}>
                <span className="dg-ic">{healthy ? '✅' : '⚠️'}</span>
                <div>
                    <h3>{healthy ? 'پایگاه‌داده و ساختارِ برنامه سالم است' : 'چند مشکل پیدا شد — این‌ها می‌توانند «خطای ۵۰۰» بسازند'}</h3>
                    <p>این صفحه روی <b>خودِ هاست</b> اجرا می‌شود. بعد از هر به‌روزرسانی یک‌بار بازش کنید.</p>
                </div>
            </div>

            <div className="panel">
                <h3>🧪 آزمایشِ واقعیِ ذخیره‌ی آزمون و بازی</h3>
                <p className="dg-muted">یک آزمون و یک بازیِ نمونه با سؤالی مثلِ خروجیِ هوش مصنوعی روی پایگاه‌داده‌ی هاست ساخته، در بانک ثبت و اعلان‌سازی می‌شود و بعد <b>همه‌چیز برگردانده می‌شود</b> (هیچ داده‌ای نمی‌ماند). اگر مرحله‌ای خطا بدهد، علتِ دقیقش همین‌جا نوشته می‌شود.</p>
                <button className="btn" onClick={runTest} disabled={!!busy}>{busy === 'test' ? 'در حالِ آزمایش…' : '▶️ اجرای آزمایش'}</button>
                {test && (
                    <div className={`dg-test ${test.ok ? 'ok' : 'bad'}`}>
                        <b>{test.ok ? '✅ همه‌ی مراحل موفق بود — ذخیره‌ی آزمون و بازی روی این هاست سالم است.' : '❌ یکی از مراحل خطا داد:'}</b>
                        {(test.steps || []).map((s, i) => (
                            <div key={i} className={`dg-step ${s.ok ? 'ok' : 'bad'}`}>
                                <span>{s.ok ? '✓' : '✗'} {s.name}{s.note ? ` — ${s.note}` : ''}</span>
                                {!s.ok && <div className="dg-err">{s.error}{s.at ? <small> ({s.at})</small> : null}{s.raw && <pre>{s.raw}</pre>}</div>}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <div className="panel">
                <h3>🗃️ مایگریشن‌های اجرانشده {pending.length > 0 && <span className="tag tag-bad">{fa(pending.length)}</span>}</h3>
                {pending.length === 0 ? <p className="dg-muted">✅ همه‌ی مایگریشن‌ها اجرا شده‌اند.</p> : (
                    <>
                        <p className="dg-muted">این تغییراتِ پایگاه‌داده هنوز روی هاست اعمال نشده‌اند. با دکمه‌ی زیر همان «php artisan migrate --force» اجرا می‌شود (پیش از آن از پایگاه‌داده پشتیبان بگیرید).</p>
                        <ul className="dg-list">{pending.map((m) => <li key={m} dir="ltr">{m}</li>)}</ul>
                        {autoFail && (
                            <div className="dg-muted" style={{ background: '#fff4f4', border: '1px solid #f3c4c4', borderRadius: 10, padding: 10, margin: '8px 0' }}>
                                <b>آخرین تلاشِ خودکار ناموفق بود ({autoFail.at}):</b>
                                <pre dir="ltr" style={{ whiteSpace: 'pre-wrap', fontSize: 11.5, margin: '6px 0 0', maxHeight: 220, overflow: 'auto' }}>{autoFail.text}</pre>
                            </div>
                        )}
                        <button className="btn" onClick={() => { if (confirm('مایگریشن‌ها اجرا شوند؟ پیشنهاد: اول از پایگاه‌داده پشتیبان بگیرید.')) call('migrate', route('admin.diagnostics.migrate')); }} disabled={!!busy}>{busy === 'migrate' ? 'در حالِ اجرا…' : '🛠️ اجرای مایگریشن‌ها'}</button>
                    </>
                )}
            </div>

            <div className="panel">
                <h3>🧩 ستون‌ها و جدول‌های لازم {schema.length > 0 && <span className="tag tag-bad">{fa(schema.length)}</span>}</h3>
                {schema.length === 0 ? <p className="dg-muted">✅ همه‌ی ستون‌ها و جدول‌های لازم برای آزمون، بازی، بانکِ سؤال، اعلان‌ها و گالری وجود دارند.</p> : (
                    <>
                        <p className="dg-muted">این ستون‌ها در پایگاه‌داده‌ی هاست نیستند؛ هر صفحه‌ای که از آن‌ها استفاده کند «خطای ۵۰۰» می‌دهد. معمولاً با «اجرای مایگریشن‌ها» درست می‌شوند.</p>
                        <table className="tbl"><thead><tr><th>جدول</th><th>ستون‌های ناموجود</th></tr></thead>
                            <tbody>{schema.map((r) => <tr key={r.table}><td dir="ltr">{r.table}</td><td dir="ltr">{r.missing.join(', ')}</td></tr>)}</tbody></table>
                    </>
                )}
            </div>

            {charset.length > 0 && (
                <div className="panel">
                    <h3>🔤 جدول‌های بدونِ utf8mb4 <span className="tag tag-warn">{fa(charset.length)}</span></h3>
                    <p className="dg-muted">این جدول‌ها ایموجی و برخی نویسه‌ها را نمی‌پذیرند و ذخیره‌ی متنِ دارای ایموجی (مثلاً سؤال‌های هوش مصنوعی) در آن‌ها خطای ۵۰۰ می‌دهد. از phpMyAdmin: <code dir="ltr">ALTER TABLE نام CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code></p>
                    <table className="tbl"><thead><tr><th>جدول</th><th>collation</th></tr></thead>
                        <tbody>{charset.map((r) => <tr key={r.table}><td dir="ltr">{r.table}</td><td dir="ltr">{r.collation}</td></tr>)}</tbody></table>
                </div>
            )}

            <div className="panel">
                <h3>🧹 کش‌ها</h3>
                <p className="dg-muted">اگر بعد از ریختنِ فایل‌های تازه رفتارِ قدیمی دیده می‌شود (یا خطای «Class not found»)، کشِ لاراول و کشِ PHP را پاک کنید.</p>
                <button className="btn btn-ghost" onClick={() => call('clear', route('admin.diagnostics.clear'))} disabled={!!busy}>{busy === 'clear' ? '…' : '🧹 پاک‌کردنِ همه‌ی کش‌ها'}</button>
            </div>

            {out && <div className={`panel dg-out ${out.ok ? 'ok' : 'bad'}`}><pre dir="ltr">{out.text || (out.ok ? 'انجام شد.' : 'ناموفق')}</pre></div>}

            <div className="panel">
                <h3>📜 آخرین خطاهای سرور</h3>
                {errors.length === 0 ? <p className="dg-muted">✅ خطای ثبت‌شده‌ای در لاگ نیست.</p> : (
                    <div className="dg-errors">
                        {errors.map((e, i) => (
                            <div key={i} className="dg-errrow">
                                <small dir="ltr">{e.at}</small>
                                <div dir="auto">{e.message}</div>
                            </div>
                        ))}
                    </div>
                )}
                <p className="dg-muted" style={{ marginTop: 8 }}>اگر پیامی با «کدِ پیگیری» دیدید (مثلاً هنگامِ انتشارِ آزمون)، همان کد اینجا در متنِ خطا هست.</p>
            </div>

            <div className="panel">
                <h3>⚙️ محیطِ اجرا</h3>
                <div className="dg-env">
                    {[['PHP', env.php], ['Laravel', env.laravel], ['پایگاه‌داده', env.db], ['نامِ پایگاه‌داده', env.database], ['منطقه‌ی زمانی', env.timezone],
                        ['حالتِ دیباگ', env.debug ? 'روشن (روی سایتِ اصلی خاموش باشد)' : 'خاموش'], ['opcache', env.opcache], ['بازخوانیِ فایل‌ها در opcache', env.opcache_revalidate],
                        ['حداکثر زمانِ اجرا', `${fa(env.max_execution_time)} ثانیه`], ['حافظه', env.memory_limit]].map(([k, v]) => (
                        <div key={k}><span>{k}</span><b dir="auto">{v ?? '—'}</b></div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}
