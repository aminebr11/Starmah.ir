import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

/** پنلِ درگاه‌ها: سامانه‌ی پیامک + سامانه‌ی پرداخت (سازگار با چند سرویس‌دهنده). */
export default function Integrations() {
    const { sms, pay, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash); }, [flash]);

    const form = useForm({
        sms_enabled: sms.enabled, sms_provider: sms.provider, sms_sender: sms.sender,
        sms_http_method: sms.http_method, sms_url_template: sms.url_template, sms_body_template: sms.body_template, sms_api_key: '',
        pay_enabled: pay.enabled, pay_provider: pay.provider, pay_sandbox: pay.sandbox, pay_merchant_id: '',
    });
    const save = (e) => { e.preventDefault(); form.put(route('admin.integrations.store'), { preserveScroll: true }); };

    const [testPhone, setTestPhone] = useState('');
    const testSms = () => { if (testPhone) router.post(route('admin.integrations.test-sms'), { phone: testPhone }, { preserveScroll: true }); };

    const bannerText = banner && (typeof banner === 'string' ? banner : banner.message);
    const bannerErr = banner && typeof banner === 'object' && banner.type === 'error';

    return (
        <DashLayout title="درگاه‌ها (پیامک و پرداخت)" roleLabel="ادمین کل" menu={adminMenu} active="integrations">
            {bannerText && <div className="panel" style={{ borderColor: bannerErr ? '#e8505b' : 'var(--gold)', background: bannerErr ? '#fdecec' : '#fff8e8' }}><b>{bannerText}</b></div>}

            <form onSubmit={save}>
                {/* ---------- سامانه‌ی پیامک ---------- */}
                <div className="panel">
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                        <h3 style={{ margin: 0 }}>📱 سامانه‌ی پیامک</h3>
                        <label style={{ marginInlineStart: 'auto', display: 'flex', alignItems: 'center', gap: 8, fontSize: 13.5, fontWeight: 700 }}>
                            <input type="checkbox" checked={form.data.sms_enabled} onChange={(e) => form.setData('sms_enabled', e.target.checked)} /> فعال
                        </label>
                    </div>
                    <p style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 6 }}>
                        برای ورود/بازیابیِ رمز با پیامک و اطلاع‌رسانی استفاده می‌شود. آدرسِ سرویسِ پیامکِ خود را با
                        جای‌گیرها وارد کنید: <code>{'{api_key} {sender} {to} {message}'}</code>
                    </p>
                    <div className="grid grid-2-form">
                        <Field label="نامِ سرویس‌دهنده (دلخواه)"><input className="input" value={form.data.sms_provider} onChange={(e) => form.setData('sms_provider', e.target.value)} placeholder="مثلاً: kavenegar / off" /></Field>
                        <Field label="شماره/نامِ فرستنده"><input className="input" value={form.data.sms_sender} onChange={(e) => form.setData('sms_sender', e.target.value)} dir="ltr" placeholder="10008663" /></Field>
                    </div>
                    <div className="grid grid-2-form">
                        <Field label="روشِ درخواست">
                            <select className="input" value={form.data.sms_http_method} onChange={(e) => form.setData('sms_http_method', e.target.value)}>
                                <option value="GET">GET</option><option value="POST">POST</option>
                            </select>
                        </Field>
                        <Field label="کلیدِ API">
                            <input className="input" value={form.data.sms_api_key} onChange={(e) => form.setData('sms_api_key', e.target.value)} dir="ltr"
                                placeholder={sms.api_key_set ? `ذخیره‌شده (${sms.api_key_hint}) — برای تغییر وارد کنید` : 'کلیدِ API'} />
                        </Field>
                    </div>
                    <Field label="قالبِ آدرسِ ارسال (URL Template)">
                        <input className="input" value={form.data.sms_url_template} onChange={(e) => form.setData('sms_url_template', e.target.value)} dir="ltr"
                            placeholder="https://api.example.com/send?key={api_key}&from={sender}&to={to}&text={message}" />
                    </Field>
                    {form.data.sms_http_method === 'POST' && (
                        <Field label="قالبِ بدنه (JSON) — اختیاری">
                            <textarea className="input" rows={2} value={form.data.sms_body_template} onChange={(e) => form.setData('sms_body_template', e.target.value)} dir="ltr"
                                placeholder='{"receptor":"{to}","sender":"{sender}","message":"{message}"}' style={{ resize: 'vertical' }} />
                        </Field>
                    )}
                    {/* آزمایش */}
                    <div style={{ display: 'flex', gap: 8, alignItems: 'end', flexWrap: 'wrap', marginTop: 6, background: '#f6f8fc', border: '1px solid var(--line)', borderRadius: 12, padding: 12 }}>
                        <div className="field" style={{ margin: 0, flex: 1, minWidth: 180 }}>
                            <label>ارسالِ پیامکِ آزمایشی به</label>
                            <input className="input" value={testPhone} onChange={(e) => setTestPhone(e.target.value)} placeholder="09xxxxxxxxx" dir="ltr" />
                        </div>
                        <button type="button" onClick={testSms} className="btn btn-ghost">📤 تستِ پیامک</button>
                        <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>ابتدا تنظیمات را ذخیره کنید، سپس تست بگیرید.</span>
                    </div>
                </div>

                {/* ---------- سامانه‌ی پرداخت ---------- */}
                <div className="panel" style={{ marginTop: 16 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                        <h3 style={{ margin: 0 }}>💳 سامانه‌ی پرداخت</h3>
                        <label style={{ marginInlineStart: 'auto', display: 'flex', alignItems: 'center', gap: 8, fontSize: 13.5, fontWeight: 700 }}>
                            <input type="checkbox" checked={form.data.pay_enabled} onChange={(e) => form.setData('pay_enabled', e.target.checked)} /> فعال
                        </label>
                    </div>
                    <p style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 6 }}>
                        برای فروشِ آنلاینِ اشتراکِ مدارس. اگر خاموش باشد، درخواست‌ها برای تأییدِ دستیِ شما ثبت می‌شوند.
                    </p>
                    <div className="grid grid-2-form">
                        <Field label="درگاه">
                            <select className="input" value={form.data.pay_provider} onChange={(e) => form.setData('pay_provider', e.target.value)}>
                                <option value="off">خاموش (تأییدِ دستی)</option>
                                <option value="zarinpal">زرین‌پال</option>
                                <option value="idpay">آی‌دی‌پی</option>
                            </select>
                        </Field>
                        <Field label="مرچنت/کلیدِ درگاه">
                            <input className="input" value={form.data.pay_merchant_id} onChange={(e) => form.setData('pay_merchant_id', e.target.value)} dir="ltr"
                                placeholder={pay.merchant_set ? `ذخیره‌شده (${pay.merchant_hint}) — برای تغییر وارد کنید` : 'Merchant ID / API Key'} />
                        </Field>
                    </div>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13.5 }}>
                        <input type="checkbox" checked={form.data.pay_sandbox} onChange={(e) => form.setData('pay_sandbox', e.target.checked)} /> حالتِ آزمایشی (Sandbox)
                    </label>
                    <div style={{ marginTop: 10, fontSize: 12, color: 'var(--muted)', background: '#f6f8fc', border: '1px solid var(--line)', borderRadius: 10, padding: '9px 12px' }}>
                        آدرسِ بازگشت (Callback) به‌صورتِ خودکار تنظیم می‌شود؛ در پنلِ درگاه نیازی به واردکردنِ دستی نیست.
                    </div>
                </div>

                <div style={{ marginTop: 16, display: 'flex', gap: 10 }}>
                    <button type="submit" disabled={form.processing} className="btn">💾 ذخیره‌ی تنظیمات</button>
                </div>
            </form>
        </DashLayout>
    );
}

function Field({ label, children }) {
    return <div className="field"><label>{label}</label>{children}</div>;
}
