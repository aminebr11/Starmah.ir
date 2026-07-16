import { usePage, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';
import PasswordInput from '@/Components/PasswordInput';

export default function Settings() {
    const { settings = {}, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const form = useForm({
        ai_provider: settings.ai_provider || 'anthropic',
        anthropic_key: '', openai_key: '',
        anthropic_model: settings.anthropic_model || 'claude-haiku-4-5-20251001',
        openai_model: settings.openai_model || 'gpt-4o-mini',
        ws_image_provider: settings.ws_image_provider || 'off',
        ws_image_model: settings.ws_image_model || 'gpt-image-1',
        openai_image_key: '',
    });
    const submit = (e) => { e.preventDefault(); form.post(route('admin.settings.store'), { preserveScroll: true, onSuccess: () => { form.setData('anthropic_key', ''); form.setData('openai_key', ''); form.setData('openai_image_key', ''); } }); };

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

                {/* تصویرسازِ کاربرگ با هوش مصنوعی */}
                <div className="panel" style={{ background: '#f5f3ff', border: '1px solid #ddd6fe' }}>
                    <div style={{ fontWeight: 800, marginBottom: 8 }}>🎨 تصویرسازِ کاربرگ (OpenAI Images) {settings.ws_image_set && <span className="tag tag-ok" style={{ marginInlineStart: 8 }}>تنظیم‌شده {settings.ws_image_hint}</span>}</div>
                    <p style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 0 }}>اگر فعال باشد، معلم می‌تواند هنگام ساختِ کاربرگ، «تولید تصویر با هوش مصنوعی» را انتخاب کند. اگر خاموش باشد، کاربرگِ HTMLِ آماده استفاده می‌شود.</p>
                    <div className="field"><label>وضعیت</label>
                        <div style={{ display: 'flex', gap: 8 }}>
                            <button type="button" onClick={() => form.setData('ws_image_provider', 'off')} className={`tag ${form.data.ws_image_provider === 'off' ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '10px 16px' }}>خاموش</button>
                            <button type="button" onClick={() => form.setData('ws_image_provider', 'openai')} className={`tag ${form.data.ws_image_provider === 'openai' ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '10px 16px' }}>روشن (OpenAI)</button>
                        </div>
                    </div>
                    <div className="field"><label>کلید API تصویرساز (خالی = بدون تغییر؛ اگر خالی بماند از کلید OpenAI بالا استفاده می‌شود)</label>
                        <PasswordInput value={form.data.openai_image_key} autoComplete="off" placeholder="sk-..." onChange={(e) => form.setData('openai_image_key', e.target.value)} />
                    </div>
                    <div className="field" style={{ marginBottom: 0 }}><label>مدل تصویرساز</label>
                        <input className="input" value={form.data.ws_image_model} onChange={(e) => form.setData('ws_image_model', e.target.value)} dir="ltr" placeholder="gpt-image-1" />
                    </div>
                </div>

                <button type="submit" disabled={form.processing} className="btn">💾 ذخیره‌ی تنظیمات</button>
            </form>
        </DashLayout>
    );
}
