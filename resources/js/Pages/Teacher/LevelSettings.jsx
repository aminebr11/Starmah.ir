import { usePage, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function LevelSettings() {
    const { config = {}, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const form = useForm({
        xp_per_level: config.xp_per_level ?? 150,
        names: config.names ?? [],
    });

    const setName = (i, v) => {
        const names = [...(form.data.names || [])];
        names[i] = v;
        form.setData('names', names);
    };
    const addName = () => form.setData('names', [...(form.data.names || []), '']);
    const removeName = (i) => form.setData('names', (form.data.names || []).filter((_, j) => j !== i));

    const submit = (e) => { e.preventDefault(); form.post(route('teacher.levels.update'), { preserveScroll: true }); };

    const xp = Number(form.data.xp_per_level) || 150;
    // پیش‌نمایش ۵ مرحله‌ی اول
    const preview = Array.from({ length: 6 }, (_, i) => ({
        level: i + 1,
        from: i * xp,
        name: (form.data.names || [])[i] || `مرحله ${fa(i + 1)}`,
    }));

    return (
        <DashLayout title="تنظیم مرحله‌ها" roleLabel="معلم" menu={teacherMenu} active="levels">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div className="panel">
                <h3>🎚️ مرحله‌بندی پیشرفت دانش‌آموزان</h3>
                <p style={{ color: 'var(--muted)', marginTop: 4 }}>
                    هر دانش‌آموز با جمع‌کردن امتیاز، مرحله به مرحله بالا می‌رود. اینجا تعیین می‌کنی هر مرحله چند امتیاز نیاز دارد و در صورت تمایل، نامِ دلخواه برای مرحله‌ها بگذاری.
                </p>

                <form onSubmit={submit} style={{ marginTop: 14 }}>
                    <div className="field" style={{ maxWidth: 320 }}>
                        <label>امتیاز لازم برای هر مرحله</label>
                        <input type="number" min={10} max={5000} className="input" value={form.data.xp_per_level}
                            onChange={(e) => form.setData('xp_per_level', e.target.value)} dir="ltr" style={{ textAlign: 'center', fontSize: 18, fontWeight: 800 }} />
                        {form.errors.xp_per_level && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{form.errors.xp_per_level}</div>}
                        <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 6 }}>پیشنهاد: بین ۱۰۰ تا ۳۰۰ امتیاز</div>
                    </div>

                    <div style={{ marginTop: 18 }}>
                        <label style={{ fontWeight: 700, display: 'block', marginBottom: 8 }}>نام مرحله‌ها (اختیاری)</label>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 8 }}>
                            {(form.data.names || []).map((n, i) => (
                                <div key={i} style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                                    <span className="tag tag-info" style={{ flex: 'none' }}>{fa(i + 1)}</span>
                                    <input className="input" value={n} onChange={(e) => setName(i, e.target.value)} placeholder={`نام مرحله ${fa(i + 1)}`} />
                                    <button type="button" onClick={() => removeName(i)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b', flex: 'none' }}>✕</button>
                                </div>
                            ))}
                        </div>
                        <button type="button" onClick={addName} className="btn btn-ghost btn-sm" style={{ marginTop: 8 }}>+ افزودن نام مرحله</button>
                    </div>

                    <button type="submit" disabled={form.processing} className="btn" style={{ marginTop: 18 }}>💾 ذخیره‌ی تنظیمات</button>
                </form>
            </div>

            <div className="panel">
                <h3 style={{ fontSize: 15 }}>👁️ پیش‌نمایش مرحله‌ها</h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10, marginTop: 10 }}>
                    {preview.map((p) => (
                        <div key={p.level} style={{ borderRadius: 14, padding: 12, color: '#fff', background: `linear-gradient(135deg, hsl(${(p.level * 40) % 360} 70% 52%), hsl(${(p.level * 40 + 30) % 360} 70% 40%))`, boxShadow: '0 6px 16px -8px rgba(0,0,0,.4)' }}>
                            <div style={{ fontSize: 12, opacity: .85 }}>مرحله {fa(p.level)}</div>
                            <div style={{ fontWeight: 800, fontSize: 15, margin: '2px 0' }}>{p.name}</div>
                            <div style={{ fontSize: 12, opacity: .9 }}>از {fa(p.from)} امتیاز</div>
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}
