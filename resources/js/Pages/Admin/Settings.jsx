import { usePage, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';
import PasswordInput from '@/Components/PasswordInput';

/** موتورهای تصویرسازِ کاربرگ — «خودکار» پیش‌فرضِ درست برای اکثرِ مدارس است. */
const IMG_PROVIDERS = [
    { v: 'auto', t: '🪄 خودکار', d: 'هر کلیدی که ثبت شده استفاده می‌شود؛ اگر کلیدی نبود، موتورِ محلی' },
    { v: 'openai', t: '🟢 OpenAI', d: 'DALL·E / GPT Image' },
    { v: 'gemini', t: '🔵 Gemini', d: 'تصویرسازِ گوگل' },
    { v: 'local', t: '🎨 محلی', d: 'برداری، آنی، رایگان و بدونِ اینترنت' },
    { v: 'off', t: '⛔ خاموش', d: 'هیچ تصویری ساخته نشود' },
];

export default function Settings() {
    const { settings = {}, imageStatus = {}, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [test, setTest] = useState(null);
    const [testing, setTesting] = useState(false);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const runTest = async () => {
        setTesting(true); setTest(null);
        try {
            const { data } = await axios.post(route('admin.settings.test-image'));
            setTest(data);
        } catch {
            setTest({ ok: false, error: 'ارتباط با سرور برقرار نشد.' });
        } finally { setTesting(false); }
    };

    const form = useForm({
        ai_provider: settings.ai_provider || 'anthropic',
        anthropic_key: '', openai_key: '',
        anthropic_model: settings.anthropic_model || 'claude-haiku-4-5-20251001',
        openai_model: settings.openai_model || 'gpt-4o-mini',
        ws_image_provider: settings.ws_image_provider || 'auto',
        ws_image_model: settings.ws_image_model || 'gpt-image-1',
        gemini_image_model: settings.gemini_image_model || 'gemini-2.5-flash-image',
        openai_image_key: '', gemini_key: '',
    });
    const submit = (e) => {
        e.preventDefault();
        form.post(route('admin.settings.store'), {
            preserveScroll: true,
            onSuccess: () => { ['anthropic_key', 'openai_key', 'openai_image_key', 'gemini_key'].forEach((k) => form.setData(k, '')); setTest(null); },
        });
    };

    return (
        <DashLayout title="تنظیمات پلتفرم" roleLabel="ادمین کل" menu={adminMenu} active="settings">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <form onSubmit={submit} className="panel" style={{ maxWidth: 680 }}>
                <h3>🤖 هوش مصنوعی (Claude و ChatGPT)</h3>
                <p style={{ color: 'var(--muted)', fontSize: 13, marginTop: 0 }}>
                    کلید API را وارد کنید تا «تولید اطلاعیه با هوش مصنوعی» و «آزمون‌ساز هوشمند» فعال شود.
                    کلیدها امن ذخیره می‌شوند و کامل نمایش داده نمی‌شوند.
                </p>

                <div className="field"><label>سرویس پیش‌فرض</label>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        <button type="button" onClick={() => form.setData('ai_provider', 'anthropic')} className={`tag ${form.data.ai_provider === 'anthropic' ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '10px 16px' }}>🟣 Claude (Anthropic)</button>
                        <button type="button" onClick={() => form.setData('ai_provider', 'openai')} className={`tag ${form.data.ai_provider === 'openai' ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '10px 16px' }}>🟢 ChatGPT (OpenAI)</button>
                    </div>
                </div>

                <div style={{ border: '1px solid var(--line)', borderRadius: 16, padding: 16, margin: '14px 0' }}>
                    <div style={{ fontWeight: 800, marginBottom: 10 }}>🟣 Claude — Anthropic {settings.anthropic_set && <span className="tag tag-ok" style={{ marginInlineStart: 8 }}>تنظیم‌شده {settings.anthropic_hint}</span>}</div>
                    <div className="field"><label>کلید API (خالی بگذارید تا تغییر نکند)</label>
                        <PasswordInput value={form.data.anthropic_key} autoComplete="off" placeholder="sk-ant-..." onChange={(e) => form.setData('anthropic_key', e.target.value)} />
                    </div>
                    <div className="field" style={{ marginBottom: 0 }}><label>مدل</label>
                        <input className="input" value={form.data.anthropic_model} onChange={(e) => form.setData('anthropic_model', e.target.value)} dir="ltr" />
                    </div>
                </div>

                <div style={{ border: '1px solid var(--line)', borderRadius: 16, padding: 16, margin: '14px 0' }}>
                    <div style={{ fontWeight: 800, marginBottom: 10 }}>🟢 ChatGPT — OpenAI {settings.openai_set && <span className="tag tag-ok" style={{ marginInlineStart: 8 }}>تنظیم‌شده {settings.openai_hint}</span>}</div>
                    <div className="field"><label>کلید API (خالی بگذارید تا تغییر نکند)</label>
                        <PasswordInput value={form.data.openai_key} autoComplete="off" placeholder="sk-..." onChange={(e) => form.setData('openai_key', e.target.value)} />
                    </div>
                    <div className="field" style={{ marginBottom: 0 }}><label>مدل</label>
                        <input className="input" value={form.data.openai_model} onChange={(e) => form.setData('openai_model', e.target.value)} dir="ltr" />
                    </div>
                </div>

                {/* ═══ تصویرسازِ کاربرگ ═══ */}
                <div className="panel" style={{ background: '#f5f3ff', border: '1px solid #ddd6fe' }}>
                    <div style={{ fontWeight: 800, marginBottom: 8 }}>🎨 تصویرسازِ کاربرگ</div>
                    <p style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 0, lineHeight: 1.9 }}>
                        هنگامِ ساختِ کاربرگ، معلم می‌تواند برای کاربرگ یک تصویرِ اختصاصی بسازد.
                        دو راه هست: <b>هوش مصنوعی</b> (نیازمندِ کلیدِ OpenAI یا Gemini) و <b>موتورِ محلیِ ستاره ماه</b>
                        که تصویرِ برداری را همین‌جا روی سرور می‌سازد — آنی، رایگان و بدونِ نیاز به اینترنت.
                    </p>

                    {/* وضعیتِ واقعیِ فعلی */}
                    <div style={{ borderRadius: 14, padding: '11px 14px', marginBottom: 12, fontSize: 13, lineHeight: 1.9,
                        background: imageStatus.ai ? '#e6f7ee' : imageStatus.enabled ? '#eef3ff' : '#fdecee',
                        border: `1px solid ${imageStatus.ai ? '#b7e4cd' : imageStatus.enabled ? '#cfdcff' : '#f5c2c8'}` }}>
                        <b>موتورِ فعال: {imageStatus.label}</b>
                        <div style={{ opacity: .85 }}>{imageStatus.note}</div>
                    </div>

                    <div className="field"><label>موتور</label>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 8 }}>
                            {IMG_PROVIDERS.map((o) => (
                                <button type="button" key={o.v} onClick={() => form.setData('ws_image_provider', o.v)}
                                    style={{ textAlign: 'right', cursor: 'pointer', fontFamily: 'inherit', borderRadius: 12, padding: 10,
                                        border: form.data.ws_image_provider === o.v ? '2px solid var(--gold)' : '1px solid var(--line)',
                                        background: form.data.ws_image_provider === o.v ? '#fff8e8' : '#fff' }}>
                                    <div style={{ fontWeight: 800, fontSize: 13 }}>{o.t}</div>
                                    <div style={{ fontSize: 11, color: 'var(--muted)', marginTop: 2 }}>{o.d}</div>
                                </button>
                            ))}
                        </div>
                    </div>

                    <div style={{ border: '1px solid #ddd6fe', borderRadius: 14, padding: 14, marginTop: 10, background: '#fff' }}>
                        <div style={{ fontWeight: 800, fontSize: 13, marginBottom: 8 }}>🟢 OpenAI Images {settings.ws_image_set && <span className="tag tag-ok" style={{ marginInlineStart: 8 }}>تنظیم‌شده {settings.ws_image_hint}</span>}</div>
                        <div className="field"><label>کلیدِ تصویرساز (خالی = بدون تغییر؛ نبود → از کلیدِ OpenAI بالا استفاده می‌شود)</label>
                            <PasswordInput value={form.data.openai_image_key} autoComplete="off" placeholder="sk-..." onChange={(e) => form.setData('openai_image_key', e.target.value)} />
                        </div>
                        <div className="field" style={{ marginBottom: 0 }}><label>مدل</label>
                            <input className="input" value={form.data.ws_image_model} onChange={(e) => form.setData('ws_image_model', e.target.value)} dir="ltr" placeholder="gpt-image-1" />
                        </div>
                    </div>

                    <div style={{ border: '1px solid #ddd6fe', borderRadius: 14, padding: 14, marginTop: 10, background: '#fff' }}>
                        <div style={{ fontWeight: 800, fontSize: 13, marginBottom: 8 }}>🔵 Google Gemini {settings.gemini_set && <span className="tag tag-ok" style={{ marginInlineStart: 8 }}>تنظیم‌شده {settings.gemini_hint}</span>}</div>
                        <div className="field"><label>کلیدِ Google AI Studio (خالی = بدون تغییر)</label>
                            <PasswordInput value={form.data.gemini_key} autoComplete="off" placeholder="AIza..." onChange={(e) => form.setData('gemini_key', e.target.value)} />
                        </div>
                        <div className="field" style={{ marginBottom: 0 }}><label>مدل</label>
                            <input className="input" value={form.data.gemini_image_model} onChange={(e) => form.setData('gemini_image_model', e.target.value)} dir="ltr" placeholder="gemini-2.5-flash-image" />
                        </div>
                    </div>

                    <div style={{ marginTop: 12 }}>
                        <button type="button" onClick={runTest} disabled={testing} className="btn btn-ghost">
                            {testing ? 'در حال آزمایش…' : '🧪 آزمایشِ تصویرساز'}
                        </button>
                        <span style={{ fontSize: 11.5, color: 'var(--muted)', marginInlineStart: 10 }}>
                            یک تصویرِ آزمایشی می‌سازد و بلافاصله پاک می‌کند. تنظیماتِ ذخیره‌شده را آزمایش می‌کند، پس اول ذخیره کنید.
                        </span>
                        {test && (
                            <div style={{ marginTop: 10, fontSize: 13, borderRadius: 12, padding: '10px 13px', lineHeight: 1.9,
                                background: test.ok ? '#e6f7ee' : '#fdecee', color: test.ok ? '#1a8a52' : '#b0333f' }}>
                                {test.ok
                                    ? <>✅ تصویرساز کار می‌کند — موتور: <b>{test.label}</b>{test.ai ? ' (هوش مصنوعی)' : ' (محلی)'}
                                        {test.error ? <div style={{ opacity: .85 }}>ℹ️ {test.error}</div> : null}</>
                                    : <>❌ ناموفق — {test.error || 'دلیل مشخص نشد.'}</>}
                            </div>
                        )}
                    </div>
                </div>

                <button type="submit" disabled={form.processing} className="btn">💾 ذخیره‌ی تنظیمات</button>
            </form>
        </DashLayout>
    );
}
