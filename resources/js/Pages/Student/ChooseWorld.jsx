import { Head, usePage, router } from '@inertiajs/react';
import { cssVars, ui } from '@/theme';

/** انتخاب دنیای علاقه (onboarding) — برند ستاره ماه. */
export default function ChooseWorld() {
    const { themes } = usePage().props;

    const pick = (id) => router.post(route('world.update'), { theme_id: id });

    return (
        <div dir="rtl" style={{ ...cssVars({}), ...ui.page }}>
            <Head title="انتخاب دنیا" />
            <div style={{ ...ui.container, paddingTop: 30 }}>
                <div style={{ textAlign: 'center' }}>
                    <img src="/brand/logo-mark.svg" width="78" height="78" alt="ستاره ماه" />
                    <div style={{ ...ui.h, marginTop: 10 }}>دنیای خودت رو انتخاب کن! ✨</div>
                    <p style={ui.muted}>همه‌ی درس‌ها، بازی‌ها و جایزه‌ها بر اساس علاقه‌ی تو شکل می‌گیرن.</p>
                </div>

                <div style={{ display: 'grid', gap: 12, marginTop: 18 }}>
                    {themes.map((t) => (
                        <button key={t.id} onClick={() => pick(t.id)} style={{
                            position: 'relative', borderRadius: 24, padding: 22, overflow: 'hidden', cursor: 'pointer',
                            border: '1px solid rgba(255,255,255,.14)', minHeight: 120, textAlign: 'right', color: '#fff',
                            fontFamily: 'inherit',
                            background: `linear-gradient(135deg,${t.skin?.p2 ?? '#16264f'},${t.skin?.bg1 ?? '#0e1c3d'})`,
                        }}>
                            <span style={{ position: 'absolute', top: -12, left: -6, fontSize: 96, opacity: .28, transform: 'rotate(-12deg)' }}>{t.emoji}</span>
                            <div style={{ fontSize: 19, fontWeight: 800 }}>{t.name} {t.emoji}</div>
                            <div style={{ fontSize: 12, color: 'rgba(255,255,255,.8)', marginTop: 4 }}>برای شروع ضربه بزن</div>
                        </button>
                    ))}

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                        {[['🚀', 'فضا'], ['🦁', 'حیوانات']].map(([e, n]) => (
                            <div key={n} style={{ borderRadius: 20, padding: 18, opacity: .45, filter: 'grayscale(.4)',
                                background: 'linear-gradient(135deg,#16264f,#0e1c3d)', border: '1px solid rgba(255,255,255,.1)' }}>
                                <div style={{ fontSize: 30 }}>{e}</div>
                                <div style={{ fontWeight: 800 }}>{n}</div>
                                <div style={ui.muted}>به‌زودی</div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
