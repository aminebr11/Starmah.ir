import { usePage, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** نمونه‌ی آماده برای شروعِ تم‌ساز — مار و پله‌ی ساده با جای‌گیرها */
const SAMPLE_HTML = `<div class="my-board">
  <div class="my-track">
    <span class="my-char">{{char}}</span>
    <div class="my-fill" style="width:{{percent}}%"></div>
  </div>
  <p class="my-text">🎲 خانه‌ی {{pos}} از {{total}} — امتیاز: {{score}}</p>
</div>`;
const SAMPLE_CSS = `.my-board{padding:10px;text-align:center}
.my-track{position:relative;height:26px;background:rgba(255,255,255,.15);border-radius:20px;overflow:hidden}
.my-fill{height:100%;background:linear-gradient(90deg,#facc15,#f59e0b);border-radius:20px;transition:width .6s}
.my-char{position:absolute;inset-inline-end:8px;top:0;font-size:18px;z-index:2}
.my-text{margin-top:8px;font-weight:800;font-size:13px;color:#fff}`;

export default function GameTemplates() {
    const { templates = [], flash, errors = {} } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [editId, setEditId] = useState(null);
    const [builderId, setBuilderId] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const form = useForm({ name: '', description: '', icon: '', board_html: '', board_css: '' });
    const startEdit = (t) => { setBuilderId(null); setEditId(t.id); form.setData({ name: t.name, description: t.description || '', icon: t.icon || '', board_html: t.board_html || '', board_css: t.board_css || '' }); };
    const startBuilder = (t) => { setEditId(null); setBuilderId(t.id); form.setData({ name: t.name, description: t.description || '', icon: t.icon || '', board_html: t.board_html || '', board_css: t.board_css || '' }); };
    const save = (id, after) => form.put(route('admin.game-templates.update', id), { preserveScroll: true, onSuccess: () => after && after() });

    // پیش‌نمایش زنده با مقادیر نمونه
    const preview = (form.data.board_html || '')
        .replaceAll('{{pos}}', '۳').replaceAll('{{total}}', '۱۰')
        .replaceAll('{{percent}}', '30').replaceAll('{{char}}', '🦁').replaceAll('{{score}}', '۴۵');

    return (
        <DashLayout title="قالب‌های بازی" roleLabel="ادمین کل" menu={adminMenu} active="game-templates">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}
            {(errors.board_html || errors.board_css) && <div className="panel" style={{ borderColor: '#e8505b', background: '#fff5f5' }}><b style={{ color: '#e8505b' }}>{errors.board_html || errors.board_css}</b></div>}

            <div className="panel">
                <h3>🎲 قالب‌ها و مکانیک‌های بازی</h3>
                <p style={{ color: 'var(--muted)', marginTop: 4 }}>
                    قالب = منطق و مکانیکِ بازی (جدا از تم/ظاهر). قالب‌های فعال برای معلمان در استودیو نمایش داده می‌شوند.
                    با دکمه‌ی «🧩 تم‌ساز» می‌توانید تخته‌ی هر قالب را با HTML/CSS بسازید — همان تخته دقیقاً در بازی دانش‌آموز رندر می‌شود.
                </p>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(260px,1fr))', gap: 12, marginTop: 12 }}>
                    {templates.map((t) => (
                        <div key={t.id} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 14, borderTop: `4px solid ${t.is_active ? '#2bb673' : '#c4ccda'}` }}>
                            {editId === t.id ? (
                                <div style={{ display: 'grid', gap: 8 }}>
                                    <input className="input" value={form.data.icon} onChange={(e) => form.setData('icon', e.target.value)} placeholder="ایموجی" />
                                    <input className="input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="نام" />
                                    <textarea className="input" rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="توضیح" />
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button onClick={() => save(t.id, () => setEditId(null))} className="btn btn-sm">💾 ذخیره</button>
                                        <button onClick={() => setEditId(null)} className="btn btn-ghost btn-sm">انصراف</button>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                        <span style={{ fontSize: 26 }}>{t.icon}</span>
                                        <b style={{ flex: 1 }}>{t.name}</b>
                                        <span className={`tag ${t.is_active ? 'tag-ok' : 'tag-info'}`} style={{ fontSize: 11 }}>{t.is_active ? 'فعال' : 'غیرفعال'}</span>
                                    </div>
                                    <div style={{ fontSize: 12.5, color: 'var(--muted)', marginTop: 6, minHeight: 34 }}>{t.description}</div>
                                    <div style={{ fontSize: 11.5, color: 'var(--muted-2)', marginTop: 4 }}>
                                        {fa(t.games)} بازی از این قالب{t.board_html ? ' · 🧩 تخته‌ی سفارشی دارد' : ''}
                                    </div>
                                    <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                                        <button onClick={() => startEdit(t)} className="btn btn-ghost btn-sm">✏️ ویرایش</button>
                                        <button onClick={() => startBuilder(t)} className="btn btn-ghost btn-sm">🧩 تم‌ساز</button>
                                        <button onClick={() => router.post(route('admin.game-templates.toggle', t.id), {}, { preserveScroll: true })} className="btn btn-ghost btn-sm">{t.is_active ? '⏸️ غیرفعال' : '▶️ فعال'}</button>
                                    </div>
                                </>
                            )}
                        </div>
                    ))}
                </div>
            </div>

            {/* 🧩 تم‌ساز HTML */}
            {builderId && (() => {
                const t = templates.find((x) => x.id === builderId);
                return (
                    <div className="panel" style={{ borderColor: 'var(--gold)' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                            <h3 style={{ margin: 0 }}>🧩 تم‌ساز تخته‌ی «{t?.name}»</h3>
                            <button onClick={() => { form.setData({ ...form.data, board_html: SAMPLE_HTML, board_css: SAMPLE_CSS }); }} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>📋 درج نمونه</button>
                            <button onClick={() => { form.setData({ ...form.data, board_html: '', board_css: '' }); }} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️ حذف تخته (بازگشت به پیش‌فرض)</button>
                        </div>
                        <p style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 6 }}>
                            جای‌گیرهای مجاز: <code dir="ltr">{'{{pos}}'}</code> (پیشرفت)، <code dir="ltr">{'{{total}}'}</code> (کل)، <code dir="ltr">{'{{percent}}'}</code> (درصد ۰–۱۰۰)،
                            <code dir="ltr">{'{{char}}'}</code> (شخصیت تیم دانش‌آموز)، <code dir="ltr">{'{{score}}'}</code> (امتیاز فعلی).
                            با هر پاسخِ درست، تخته با مقادیر جدید دوباره رندر می‌شود — انیمیشن را با <code dir="ltr">transition</code> در CSS بسازید.
                            کد <code dir="ltr">&lt;script&gt;</code> مجاز نیست.
                        </p>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))', gap: 12, marginTop: 10 }}>
                            <div>
                                <label style={{ fontWeight: 700, fontSize: 13 }}>HTML تخته</label>
                                <textarea className="input" dir="ltr" rows={10} style={{ fontFamily: 'monospace', fontSize: 12 }} value={form.data.board_html} onChange={(e) => form.setData('board_html', e.target.value)} placeholder={SAMPLE_HTML} />
                            </div>
                            <div>
                                <label style={{ fontWeight: 700, fontSize: 13 }}>CSS تخته</label>
                                <textarea className="input" dir="ltr" rows={10} style={{ fontFamily: 'monospace', fontSize: 12 }} value={form.data.board_css} onChange={(e) => form.setData('board_css', e.target.value)} placeholder={SAMPLE_CSS} />
                            </div>
                        </div>
                        {/* پیش‌نمایش زنده */}
                        <label style={{ fontWeight: 700, fontSize: 13, display: 'block', marginTop: 12 }}>👁️ پیش‌نمایش زنده (با مقادیر نمونه: خانه ۳ از ۱۰، شخصیت 🦁)</label>
                        <div style={{ marginTop: 6, borderRadius: 16, padding: 14, background: 'linear-gradient(135deg,#334155,#1e293b)', minHeight: 70 }}>
                            {form.data.board_css && <style>{form.data.board_css}</style>}
                            {form.data.board_html
                                ? <div dir="rtl" dangerouslySetInnerHTML={{ __html: preview }} />
                                : <div style={{ color: '#94a3b8', fontSize: 13, textAlign: 'center' }}>تخته‌ای تعریف نشده — از تخته‌ی انیمیشنیِ پیش‌فرضِ همین قالب استفاده می‌شود.</div>}
                        </div>
                        <div style={{ display: 'flex', gap: 8, marginTop: 14 }}>
                            <button onClick={() => save(builderId, () => setBuilderId(null))} disabled={form.processing} className="btn">💾 ذخیره‌ی تخته</button>
                            <button onClick={() => setBuilderId(null)} className="btn btn-ghost">بستن</button>
                        </div>
                    </div>
                );
            })()}
        </DashLayout>
    );
}
