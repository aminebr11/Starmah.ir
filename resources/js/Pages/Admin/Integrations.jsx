import { usePage, useForm, router, Link } from '@inertiajs/react';
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
                {/* تنظیماتِ پیامک به پنلِ اختصاصیِ خودش منتقل شد تا دو جا
                    یک تنظیم را ننویسند و از هم دور نیفتند. */}
                <div className="panel">
                    <h3 style={{ marginTop: 0 }}>📱 سامانه‌ی پیامک</h3>
                    <p style={{ color: 'var(--muted)', fontSize: 13.5, lineHeight: 2 }}>
                        تنظیماتِ پیامک حالا در پنلِ اختصاصیِ خودش است: اتصالِ درگاه، دسترسی و سهمیه‌ی هر مدرسه،
                        گزارشِ مصرف و سابقه‌ی ارسال.
                    </p>
                    <Link href="/admin/sms" className="btn">📩 رفتن به سامانه‌ی پیامک</Link>
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
