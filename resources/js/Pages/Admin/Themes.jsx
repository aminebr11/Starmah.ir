import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

export default function Themes() {
    const { themes = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(flash.flash); }, [flash]);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '', emoji: '🪐', bg1: '#0e1c3d', bg2: '#152a55', p1: '#7c5cff', p2: '#5b3fd6', acc: '#ffd87a',
        xp_unit: 'امتیاز', league: 'لیگ',
    });
    const submit = (e) => { e.preventDefault(); post(route('admin.themes.store'), { preserveScroll: true, onSuccess: () => reset() }); };

    return (
        <DashLayout title="تم‌ها (دنیاها)" roleLabel="ادمین کل" menu={adminMenu} active="themes">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner.message}</b></div>}

            <div style={{ display: 'grid', gridTemplateColumns: '1.2fr 1fr', gap: 20, alignItems: 'start' }} className="themes-grid">
                {/* لیست تم‌ها */}
                <div className="panel">
                    <h3>🎨 دنیاهای موجود</h3>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2,1fr)', gap: 12 }}>
                        {themes.map((t) => (
                            <div key={t.id} style={{ borderRadius: 16, padding: 16, color: '#fff', position: 'relative',
                                background: `linear-gradient(135deg,${t.skin?.p2 ?? '#16264f'},${t.skin?.bg1 ?? '#0e1c3d'})`, opacity: t.is_active ? 1 : .5 }}>
                                <div style={{ fontSize: 28 }}>{t.emoji}</div>
                                <div style={{ fontWeight: 800, marginTop: 4 }}>{t.name}</div>
                                <div style={{ fontSize: 12, opacity: .8 }}>{t.is_active ? 'فعال' : 'غیرفعال'}{t.is_premium ? ' · ویژه' : ''}</div>
                                {t.key !== 'brand' && (
                                    <button onClick={() => router.post(route('admin.themes.toggle', t.id), {}, { preserveScroll: true })}
                                        style={{ marginTop: 8, fontSize: 11, padding: '4px 10px', borderRadius: 20, border: '1px solid rgba(255,255,255,.3)', background: 'rgba(255,255,255,.15)', color: '#fff', cursor: 'pointer', fontFamily: 'inherit' }}>
                                        {t.is_active ? 'غیرفعال کن' : 'فعال کن'}
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                {/* ساخت تم جدید */}
                <form onSubmit={submit} className="panel">
                    <h3>➕ ساخت دنیای جدید</h3>
                    <Field label="نام دنیا" err={errors.name}><input className="input" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="مثلاً: فضا" /></Field>
                    <Field label="ایموجی / شخصیت" err={errors.emoji}><input className="input" value={data.emoji} onChange={(e) => setData('emoji', e.target.value)} /></Field>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                        <Color label="رنگ پس‌زمینه" v={data.bg1} on={(v) => setData('bg1', v)} />
                        <Color label="پس‌زمینه ۲" v={data.bg2} on={(v) => setData('bg2', v)} />
                        <Color label="رنگ اصلی" v={data.p1} on={(v) => setData('p1', v)} />
                        <Color label="رنگ دوم" v={data.p2} on={(v) => setData('p2', v)} />
                        <Color label="رنگ تأکید" v={data.acc} on={(v) => setData('acc', v)} />
                    </div>
                    <Field label="واحد امتیاز (مثلاً گل/نیترو)" err={errors.xp_unit}><input className="input" value={data.xp_unit} onChange={(e) => setData('xp_unit', e.target.value)} /></Field>
                    <Field label="نام لیگ/مرحله" err={errors.league}><input className="input" value={data.league} onChange={(e) => setData('league', e.target.value)} /></Field>

                    {/* پیش‌نمایش */}
                    <div style={{ borderRadius: 16, padding: 16, color: '#fff', marginBottom: 12,
                        background: `linear-gradient(135deg,${data.p2},${data.bg1})` }}>
                        <div style={{ fontSize: 26 }}>{data.emoji}</div>
                        <b>{data.name || 'پیش‌نمایش دنیا'}</b>
                        <div style={{ fontSize: 12, opacity: .85 }}>۱۲۰ {data.xp_unit} · {data.league}</div>
                    </div>
                    <button type="submit" disabled={processing} className="btn" style={{ width: '100%' }}>ساخت دنیا</button>
                </form>
            </div>
        </DashLayout>
    );
}

function Field({ label, err, children }) {
    return <div className="field"><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
function Color({ label, v, on }) {
    return (
        <div className="field">
            <label style={{ fontSize: 12 }}>{label}</label>
            <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                <input type="color" value={v} onChange={(e) => on(e.target.value)} style={{ width: 38, height: 38, border: 'none', background: 'none', cursor: 'pointer' }} />
                <input className="input" value={v} onChange={(e) => on(e.target.value)} style={{ flex: 1 }} />
            </div>
        </div>
    );
}
