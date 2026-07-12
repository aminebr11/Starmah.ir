import { usePage, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const FLAG_LABELS = {
    smart_lab_enabled: 'فعال‌سازی آزمایشگاه هوشمند (کلید اصلی)',
    smart_ai_enabled: 'تولید سؤال با هوش مصنوعی',
    smart_adaptive_enabled: 'آزمون تطبیقی',
    smart_analysis_enabled: 'تحلیل هوشمند نتیجه',
    smart_games_enabled: 'اتصال به بازی‌های جبرانی',
};

export default function SmartLab() {
    const { flags = {}, scope = 'off', pilotSchools = [], schools = [], ai = {}, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const form = useForm({ flags: { ...flags }, scope, pilotSchools: [...pilotSchools] });
    const setFlag = (k, v) => form.setData('flags', { ...form.data.flags, [k]: v });
    const togglePilot = (id) => form.setData('pilotSchools', form.data.pilotSchools.includes(id) ? form.data.pilotSchools.filter((x) => x !== id) : [...form.data.pilotSchools, id]);
    const submit = (e) => { e.preventDefault(); form.post(route('admin.smart-lab.update'), { preserveScroll: true }); };

    return (
        <DashLayout title="آزمایشگاه هوشمند" roleLabel="ادمین کل" menu={adminMenu} active="smart-lab">
            <div className="smart-scope">
                {banner && <div className="smart-panel" style={{ borderColor: 'var(--sm-acc)', background: '#f5f2ff' }}><b>{banner}</b></div>}

                <div className="smart-panel">
                    <div className="smart-h">🧪 آزمایشگاه هوشمند آزمون <span className="smart-badge">ماژول آزمایشی</span></div>
                    <p className="smart-muted" style={{ marginTop: 6 }}>
                        این ماژول آزمایشی در کنار آزمون‌ساز فعلی اجرا می‌شود و آن را تغییر نمی‌دهد. با خاموش‌کردنِ کلید اصلی،
                        همه‌ی منوها و مسیرهای آن غیرفعال می‌شوند و آزمون‌های قدیمی بدون هیچ تغییری کار می‌کنند.
                    </p>

                    <form onSubmit={submit} style={{ marginTop: 14 }}>
                        <div style={{ display: 'grid', gap: 8 }}>
                            {Object.keys(FLAG_LABELS).map((k) => (
                                <label key={k} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 12px', borderRadius: 12, background: '#faf9ff', border: '1px solid var(--sm-line)', cursor: 'pointer', opacity: k !== 'smart_lab_enabled' && !form.data.flags.smart_lab_enabled ? .5 : 1 }}>
                                    <input type="checkbox" checked={!!form.data.flags[k]} disabled={k !== 'smart_lab_enabled' && !form.data.flags.smart_lab_enabled}
                                        onChange={(e) => setFlag(k, e.target.checked)} />
                                    <span style={{ fontWeight: 700, fontSize: 14 }}>{FLAG_LABELS[k]}</span>
                                    {k === 'smart_lab_enabled' && <span className="smart-badge" style={{ marginInlineStart: 'auto' }}>اصلی</span>}
                                </label>
                            ))}
                        </div>

                        <div className="smart-field" style={{ marginTop: 16 }}>
                            <label>دامنه‌ی دسترسی (توسط ادمین کل)</label>
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                {[['off', 'هیچ مدرسه‌ای'], ['pilot', 'فقط مدارسِ منتخب'], ['all', 'همه‌ی مدارس']].map(([v, t]) => (
                                    <button type="button" key={v} onClick={() => form.setData('scope', v)} className={`smart-chip ${form.data.scope === v ? 'on' : ''}`}>{t}</button>
                                ))}
                            </div>
                            <div className="smart-muted" style={{ marginTop: 6 }}>دسترسی به این ماژول را ادمین کل به‌صورتِ مدرسه‌ای تعیین می‌کند؛ نه معلم.</div>
                        </div>

                        {form.data.scope === 'pilot' && (
                            <div className="smart-field" style={{ marginTop: 14 }}>
                                <label>مدارسِ مجاز</label>
                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                    {schools.length === 0 && <span className="smart-muted">مدرسه‌ای ثبت نشده.</span>}
                                    {schools.map((s) => (
                                        <button type="button" key={s.id} onClick={() => togglePilot(s.id)} className={`smart-chip ${form.data.pilotSchools.includes(s.id) ? 'on' : ''}`}>{s.name}</button>
                                    ))}
                                </div>
                            </div>
                        )}

                        <button type="submit" disabled={form.processing} className="smart-btn" style={{ marginTop: 18 }}>💾 ذخیره‌ی تنظیمات</button>
                    </form>
                </div>

                <div className="smart-panel">
                    <div className="smart-h">🤖 آمار هوش مصنوعی</div>
                    <div className="smart-kpis" style={{ marginTop: 12 }}>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#6d28d9,#4c1d95)' }}><b>{ai.provider === 'off' ? 'خاموش' : ai.provider}</b><span>Provider فعال</span></div>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#0ea5b7,#0a7d8a)' }}><b>{fa(ai.requests)}</b><span>درخواست‌ها</span></div>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#2bb673,#1a8a52)' }}><b>{fa(ai.produced)}</b><span>سؤال تولیدشده</span></div>
                        <div className="smart-kpi" style={{ background: 'linear-gradient(135deg,#e8505b,#b0333f)' }}><b>{fa(ai.errors)}</b><span>خطاها</span></div>
                    </div>
                    <p className="smart-muted" style={{ marginTop: 10 }}>Provider و کلید API از «تنظیمات پلتفرم» مدیریت می‌شوند. کلید فقط سمت سرور و در فایل .env/دیتابیس ذخیره می‌شود.</p>
                </div>
            </div>
        </DashLayout>
    );
}
