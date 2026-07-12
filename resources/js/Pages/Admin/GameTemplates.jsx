import { usePage, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/* ── محیط‌های نمونه‌ی آماده (زیبا، یکتا، فقط HTML/CSS با انیمیشن) ── */
const PRESETS = {
    neon: {
        name: 'نوار نئونی', icon: '💠',
        html: `<div class="nz">
  <div class="nz-track"><div class="nz-fill" style="width:{{percent}}%"></div><span class="nz-char">{{char}}</span></div>
  <div class="nz-info"><span>پیشرفت {{pos}}/{{total}}</span><span class="nz-score">⚡ {{score}}</span></div>
</div>`,
        css: `.nz{padding:6px 4px}
.nz-track{position:relative;height:30px;border-radius:20px;background:#0b1024;box-shadow:inset 0 0 12px #000;overflow:hidden}
.nz-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,#22d3ee,#a855f7,#ec4899);box-shadow:0 0 16px #a855f7;transition:width .7s cubic-bezier(.2,.9,.3,1.2)}
.nz-char{position:absolute;top:50%;inset-inline-end:8px;transform:translateY(-50%);font-size:20px;filter:drop-shadow(0 0 6px #fff)}
.nz-info{display:flex;justify-content:space-between;margin-top:8px;font-weight:800;font-size:13px;color:#e5e7ff}
.nz-score{color:#fbbf24}`,
    },
    mountain: {
        name: 'صعود به قله', icon: '🏔️',
        html: `<div class="mt">
  <div class="mt-scene">
    <div class="mt-peak">🚩</div>
    <div class="mt-climber" style="bottom:calc({{percent}}% - 6px);inset-inline-start:calc({{percent}}% - 12px)">{{char}}</div>
  </div>
  <p class="mt-t">⛰️ ارتفاع {{pos}} از {{total}} — امتیاز {{score}}</p>
</div>`,
        css: `.mt-scene{position:relative;height:120px;border-radius:14px;overflow:hidden;
  background:linear-gradient(#bae6fd,#e0f2fe);
  clip-path:polygon(0 100%,50% 8%,100% 100%);}
.mt-scene::after{content:"";position:absolute;inset:0;background:linear-gradient(135deg,#94a3b8,#475569);opacity:.25;clip-path:polygon(0 100%,50% 8%,100% 100%)}
.mt-peak{position:absolute;top:2px;inset-inline-start:50%;transform:translateX(-50%);font-size:22px}
.mt-climber{position:absolute;font-size:22px;transition:all .8s cubic-bezier(.3,1,.4,1);filter:drop-shadow(0 3px 4px rgba(0,0,0,.4))}
.mt-t{text-align:center;font-weight:800;font-size:13px;margin-top:8px;color:#fff}`,
    },
    rocket: {
        name: 'موشک تا ماه', icon: '🚀',
        html: `<div class="rk">
  <div class="rk-sky">
    <span class="rk-moon">🌙</span>
    <span class="rk-star s1">✦</span><span class="rk-star s2">✦</span><span class="rk-star s3">✦</span>
    <span class="rk-ship" style="bottom:calc({{percent}}% - 10px)">{{char}}🚀</span>
  </div>
  <p class="rk-t">🌌 {{pos}}/{{total}} — سوخت امتیاز: {{score}}</p>
</div>`,
        css: `.rk-sky{position:relative;height:130px;border-radius:14px;overflow:hidden;background:radial-gradient(120% 90% at 50% 100%,#1e293b,#020617)}
.rk-moon{position:absolute;top:8px;inset-inline-start:50%;transform:translateX(-50%);font-size:26px;filter:drop-shadow(0 0 10px #fde68a)}
.rk-star{position:absolute;color:#e2e8f0;font-size:11px;animation:rk-tw 1.6s ease-in-out infinite}
.rk-star.s1{top:24px;inset-inline-start:22%}.rk-star.s2{top:50px;inset-inline-end:26%;animation-delay:.5s}.rk-star.s3{top:74px;inset-inline-start:36%;animation-delay:1s}
@keyframes rk-tw{0%,100%{opacity:.3}50%{opacity:1}}
.rk-ship{position:absolute;inset-inline-start:50%;transform:translateX(-50%);font-size:22px;transition:bottom .8s cubic-bezier(.3,1,.4,1)}
.rk-t{text-align:center;font-weight:800;font-size:13px;margin-top:8px;color:#fff}`,
    },
    stars: {
        name: 'مسیر ستاره‌ای', icon: '⭐',
        html: `<div class="sp">
  <div class="sp-row">
    <i class="sp-dot" style="--w:{{percent}}"></i>
    <span class="sp-char" style="inset-inline-start:calc({{percent}}% - 12px)">{{char}}</span>
  </div>
  <p class="sp-t">⭐ {{pos}} از {{total}} ستاره — {{score}} امتیاز</p>
</div>`,
        css: `.sp-row{position:relative;height:26px;border-radius:20px;background:#1e1b4b url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20'%3E%3Ctext y='15' font-size='12'%3E✦%3C/text%3E%3C/svg%3E");overflow:hidden}
.sp-dot{position:absolute;inset-inline-start:0;top:0;height:100%;width:calc(var(--w)*1%);background:linear-gradient(90deg,#facc15,#f59e0b);border-radius:20px;transition:width .7s}
.sp-char{position:absolute;top:50%;transform:translateY(-50%);font-size:20px;transition:inset-inline-start .7s}
.sp-t{text-align:center;font-weight:800;font-size:13px;margin-top:8px;color:#fff}`,
    },
};

export default function GameTemplates() {
    const { templates = [], flash, errors = {} } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [editId, setEditId] = useState(null);
    const [builderId, setBuilderId] = useState(null);
    const [creating, setCreating] = useState(false);
    const [showGuide, setShowGuide] = useState(false);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const form = useForm({ name: '', description: '', icon: '', board_html: '', board_css: '' });
    const startEdit = (t) => { setCreating(false); setBuilderId(null); setEditId(t.id); form.setData({ name: t.name, description: t.description || '', icon: t.icon || '', board_html: t.board_html || '', board_css: t.board_css || '' }); };
    const startBuilder = (t) => { setCreating(false); setEditId(null); setBuilderId(t.id); form.setData({ name: t.name, description: t.description || '', icon: t.icon || '', board_html: t.board_html || '', board_css: t.board_css || '' }); };
    const startCreate = () => { setEditId(null); setBuilderId(null); setCreating(true); form.reset(); window.scrollTo({ top: 0, behavior: 'smooth' }); };
    const applyPreset = (k) => { const p = PRESETS[k]; form.setData({ ...form.data, name: form.data.name || p.name, icon: form.data.icon || p.icon, board_html: p.html, board_css: p.css }); };
    const saveUpdate = (id) => form.put(route('admin.game-templates.update', id), { preserveScroll: true, onSuccess: () => setBuilderId(null) });
    const saveNew = () => form.post(route('admin.game-templates.store'), { preserveScroll: true, onSuccess: () => setCreating(false) });

    const preview = (form.data.board_html || '')
        .replaceAll('{{pos}}', '۳').replaceAll('{{total}}', '۱۰')
        .replaceAll('{{percent}}', '35').replaceAll('{{char}}', '🦁').replaceAll('{{score}}', '۴۵');

    const BuilderFields = (
        <>
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 10 }}>
                <span style={{ fontSize: 13, fontWeight: 700, alignSelf: 'center' }}>محیط‌های آماده:</span>
                {Object.entries(PRESETS).map(([k, p]) => <button key={k} type="button" onClick={() => applyPreset(k)} className="btn btn-ghost btn-sm">{p.icon} {p.name}</button>)}
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))', gap: 12 }}>
                <div>
                    <label style={{ fontWeight: 700, fontSize: 13 }}>HTML تخته</label>
                    <textarea className="input" dir="ltr" rows={11} style={{ fontFamily: 'monospace', fontSize: 12 }} value={form.data.board_html} onChange={(e) => form.setData('board_html', e.target.value)} />
                </div>
                <div>
                    <label style={{ fontWeight: 700, fontSize: 13 }}>CSS تخته</label>
                    <textarea className="input" dir="ltr" rows={11} style={{ fontFamily: 'monospace', fontSize: 12 }} value={form.data.board_css} onChange={(e) => form.setData('board_css', e.target.value)} />
                </div>
            </div>
            <label style={{ fontWeight: 700, fontSize: 13, display: 'block', marginTop: 12 }}>👁️ پیش‌نمایش زنده (خانه ۳ از ۱۰، شخصیت 🦁)</label>
            <div style={{ marginTop: 6, borderRadius: 16, padding: 14, background: 'linear-gradient(135deg,#334155,#1e293b)', minHeight: 70 }}>
                {form.data.board_css && <style>{form.data.board_css}</style>}
                {form.data.board_html
                    ? <div dir="rtl" dangerouslySetInnerHTML={{ __html: preview }} />
                    : <div style={{ color: '#94a3b8', fontSize: 13, textAlign: 'center' }}>یک محیط آماده انتخاب کنید یا کد بنویسید.</div>}
            </div>
        </>
    );

    return (
        <DashLayout title="محیط‌های بازی" roleLabel="ادمین کل" menu={adminMenu} active="game-templates">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}
            {(errors.board_html || errors.board_css || errors.delete) && <div className="panel" style={{ borderColor: '#e8505b', background: '#fff5f5' }}><b style={{ color: '#e8505b' }}>{errors.board_html || errors.board_css || errors.delete}</b></div>}

            <div className="panel">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <h3 style={{ margin: 0 }}>🎲 محیط‌ها و مکانیک‌های بازی</h3>
                    <button onClick={() => setShowGuide(!showGuide)} className="btn btn-ghost btn-sm">📘 راهنمای کد HTML</button>
                    <button onClick={startCreate} className="btn btn-sm" style={{ marginInlineStart: 'auto' }}>+ ساخت محیط جدید</button>
                </div>
                <p style={{ color: 'var(--muted)', marginTop: 6 }}>
                    «محیط/قالب» = مکانیک و ظاهرِ تختهٔ بازی (جدا از تم/رنگِ تیم). محیط‌های فعال برای معلمان در استودیو نمایش داده می‌شوند
                    و همان تخته دقیقاً در بازی دانش‌آموز رندر می‌شود.
                </p>

                {showGuide && (
                    <div style={{ background: 'var(--cream)', borderRadius: 14, padding: 16, marginTop: 12, fontSize: 13.5, lineHeight: 2 }}>
                        <b>📘 راهنمای ساخت تختهٔ بازی با HTML/CSS</b>
                        <ol style={{ marginTop: 8, paddingInlineStart: 20 }}>
                            <li>تخته با <b>هر بار پاسخِ درستِ دانش‌آموز</b> دوباره رندر می‌شود؛ برای حرکتِ نرم از <code dir="ltr">transition</code> در CSS استفاده کنید.</li>
                            <li>این جای‌گیرها خودکار جایگزین می‌شوند:
                                <div style={{ marginTop: 4 }}>
                                    <code dir="ltr">{'{{pos}}'}</code> شمارهٔ پیشرفت · <code dir="ltr">{'{{total}}'}</code> کل ·
                                    <code dir="ltr"> {'{{percent}}'}</code> درصد (۰ تا ۱۰۰، برای <code dir="ltr">width/inset</code>) ·
                                    <code dir="ltr"> {'{{char}}'}</code> شخصیتِ تیمِ دانش‌آموز · <code dir="ltr">{'{{score}}'}</code> امتیازِ فعلی
                                </div>
                            </li>
                            <li>برای موقعیتِ متحرک از <code dir="ltr">{'style="width:{{percent}}%"'}</code> یا <code dir="ltr">{'inset-inline-start:calc({{percent}}% - 12px)'}</code> استفاده کنید.</li>
                            <li><b>محدودیت امنیتی:</b> فقط HTML و CSS نمایشی مجاز است؛ <code dir="ltr">&lt;script&gt;</code>، <code dir="ltr">on*</code> و <code dir="ltr">javascript:</code> پذیرفته نمی‌شوند.</li>
                            <li>اگر تخته خالی بماند، تختهٔ انیمیشنیِ پیش‌فرضِ همان مکانیک استفاده می‌شود.</li>
                            <li>ساده‌ترین شروع: دکمهٔ «محیط‌های آماده» را بزنید و همان را ویرایش کنید.</li>
                        </ol>
                    </div>
                )}

                {creating && (
                    <div style={{ border: '2px solid var(--gold)', borderRadius: 14, padding: 14, marginTop: 14 }}>
                        <h3 style={{ fontSize: 15, marginTop: 0 }}>✨ محیطِ بازیِ جدید</h3>
                        <div style={{ display: 'grid', gridTemplateColumns: '80px 1fr', gap: 10, marginBottom: 10 }}>
                            <div className="field" style={{ margin: 0 }}><label>ایموجی</label><input className="input" value={form.data.icon} onChange={(e) => form.setData('icon', e.target.value)} placeholder="🎲" /></div>
                            <div className="field" style={{ margin: 0 }}><label>نام محیط</label><input className="input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="مثلاً: صعود به قله" /></div>
                        </div>
                        <div className="field" style={{ margin: '0 0 10px' }}><label>توضیح</label><input className="input" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></div>
                        {BuilderFields}
                        <div style={{ display: 'flex', gap: 8, marginTop: 14 }}>
                            <button onClick={saveNew} disabled={form.processing || !form.data.name} className="btn">💾 ساخت محیط</button>
                            <button onClick={() => setCreating(false)} className="btn btn-ghost">انصراف</button>
                        </div>
                    </div>
                )}

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(260px,1fr))', gap: 12, marginTop: 14 }}>
                    {templates.map((t) => (
                        <div key={t.id} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 14, borderTop: `4px solid ${t.is_active ? '#2bb673' : '#c4ccda'}` }}>
                            {editId === t.id ? (
                                <div style={{ display: 'grid', gap: 8 }}>
                                    <input className="input" value={form.data.icon} onChange={(e) => form.setData('icon', e.target.value)} placeholder="ایموجی" />
                                    <input className="input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="نام" />
                                    <textarea className="input" rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="توضیح" />
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button onClick={() => form.put(route('admin.game-templates.update', t.id), { preserveScroll: true, onSuccess: () => setEditId(null) })} className="btn btn-sm">💾 ذخیره</button>
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
                                    <div style={{ fontSize: 11.5, color: 'var(--muted-2)', marginTop: 4 }}>{fa(t.games)} بازی{t.board_html ? ' · 🧩 تختهٔ سفارشی' : ' · تختهٔ پیش‌فرض'}</div>
                                    <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                                        <button onClick={() => startEdit(t)} className="btn btn-ghost btn-sm">✏️ ویرایش</button>
                                        <button onClick={() => startBuilder(t)} className="btn btn-ghost btn-sm">🧩 تختهٔ HTML</button>
                                        <button onClick={() => router.post(route('admin.game-templates.toggle', t.id), {}, { preserveScroll: true })} className="btn btn-ghost btn-sm">{t.is_active ? '⏸️' : '▶️'}</button>
                                        <button onClick={() => confirm(`محیطِ «${t.name}» حذف شود؟`) && router.delete(route('admin.game-templates.destroy', t.id), { preserveScroll: true })} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
                                    </div>
                                </>
                            )}
                        </div>
                    ))}
                </div>
            </div>

            {builderId && (() => {
                const t = templates.find((x) => x.id === builderId);
                return (
                    <div className="panel" style={{ borderColor: 'var(--gold)' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                            <h3 style={{ margin: 0 }}>🧩 تختهٔ «{t?.name}»</h3>
                            <button onClick={() => { form.setData({ ...form.data, board_html: '', board_css: '' }); }} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto', color: '#e8505b' }}>🗑️ حذف تخته (بازگشت به پیش‌فرض)</button>
                        </div>
                        <div style={{ marginTop: 10 }}>{BuilderFields}</div>
                        <div style={{ display: 'flex', gap: 8, marginTop: 14 }}>
                            <button onClick={() => saveUpdate(builderId)} disabled={form.processing} className="btn">💾 ذخیرهٔ تخته</button>
                            <button onClick={() => setBuilderId(null)} className="btn btn-ghost">بستن</button>
                        </div>
                    </div>
                );
            })()}
        </DashLayout>
    );
}
