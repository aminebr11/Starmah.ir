import { Head, usePage, router } from '@inertiajs/react';

/** انتخاب تیم/گروه — «تو کدوم تیمی؟» (مطابق طرح ۴ تیم). */
export default function ChooseWorld() {
    const { themes = [] } = usePage().props;
    const pick = (id) => router.post(route('world.update'), { theme_id: id });

    return (
        <div dir="rtl" style={{ minHeight: '100vh', position: 'relative', overflow: 'hidden',
            background: 'radial-gradient(circle at 50% -10%, #1e3a6e, #070f24 60%)', color: '#fff',
            fontFamily: "'Vazirmatn',system-ui,sans-serif" }}>
            <Head title="انتخاب تیم" />
            {/* نورهای استادیوم */}
            <div style={{ position: 'absolute', inset: 0, background: 'radial-gradient(circle at 15% 0,rgba(255,255,255,.12),transparent 25%),radial-gradient(circle at 85% 0,rgba(255,255,255,.12),transparent 25%)', pointerEvents: 'none' }} />

            <div style={{ maxWidth: 760, margin: '0 auto', padding: '30px 18px 50px', position: 'relative' }}>
                <div style={{ textAlign: 'center', marginBottom: 8 }}>
                    <img src="/brand/logo-emblem.png" width="64" height="64" alt="ستاره ماه" style={{ margin: '0 auto', borderRadius: 16 }} />
                </div>
                <div style={{ textAlign: 'center', marginBottom: 22 }}>
                    <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: 'rgba(245,181,63,.16)', border: '1px solid rgba(245,181,63,.4)', color: '#ffd87a', padding: '7px 18px', borderRadius: 30, fontWeight: 700, fontSize: 14 }}>
                        🏆 رقابت این هفته
                    </div>
                    <h1 style={{ fontSize: 30, fontWeight: 800, margin: '16px 0 6px' }}>۴ تیم قدرتمند — <span style={{ color: '#ffd23f' }}>تو کدوم تیمی؟</span></h1>
                    <p style={{ color: '#c4d2f0', fontSize: 14 }}>تیمت را انتخاب کن و با بقیه‌ی تیم‌های کلاس رقابت کن!</p>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2,1fr)', gap: 16 }} className="teams-grid">
                    {themes.map((t) => (
                        <button key={t.id} onClick={() => pick(t.id)} style={{
                            position: 'relative', overflow: 'hidden', borderRadius: 22, padding: '22px 20px', cursor: 'pointer',
                            textAlign: 'center', color: '#fff', fontFamily: 'inherit',
                            border: `2px solid ${t.skin?.p1 ?? '#fff'}55`,
                            background: `linear-gradient(160deg, ${t.skin?.p2 ?? '#16264f'}, ${t.skin?.bg1 ?? '#0e1c3d'})`,
                            boxShadow: `0 18px 40px -18px ${t.skin?.p1 ?? '#000'}`, transition: '.2s',
                        }}
                            onMouseEnter={(e) => { e.currentTarget.style.transform = 'translateY(-4px) scale(1.02)'; }}
                            onMouseLeave={(e) => { e.currentTarget.style.transform = 'none'; }}>
                            <div style={{ position: 'absolute', top: -10, insetInlineEnd: -6, fontSize: 90, opacity: .18 }}>{t.skin?.hero}</div>
                            <div style={{ fontSize: 56, lineHeight: 1, filter: 'drop-shadow(0 6px 14px rgba(0,0,0,.5))' }}>{t.character}</div>
                            <div style={{ fontWeight: 800, fontSize: 18, marginTop: 12 }}>{t.name}</div>
                            <div style={{ fontWeight: 800, fontSize: 13, letterSpacing: 1, color: t.skin?.acc ?? '#ffd87a', marginTop: 2 }}>{t.subtitle}</div>
                            <div style={{ fontSize: 12, color: 'rgba(255,255,255,.8)', marginTop: 8 }}>{t.tagline}</div>
                            <div style={{ marginTop: 14, display: 'inline-block', background: t.skin?.p1, color: '#fff', borderRadius: 20, padding: '7px 18px', fontWeight: 800, fontSize: 13 }}>
                                {t.emoji} انتخاب این تیم
                            </div>
                        </button>
                    ))}
                </div>

                <div style={{ textAlign: 'center', marginTop: 26, color: '#9fb1d8', fontSize: 13, lineHeight: 2 }}>
                    🔥 هر روز مسابقه! هر روز امتیاز! آخر هفته تیم قهرمان معرفی می‌شود.<br />
                    منتظر حضور تو در تیم قهرمانان هستیم! 🏆
                </div>
            </div>
        </div>
    );
}
