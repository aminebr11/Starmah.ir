import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const TYPE = {
    material: { icon: '📄', label: 'جزوه و فایل', c1: '#3d7bf0', c2: '#2555c0' },
    podcast:  { icon: '🎧', label: 'پادکست', c1: '#a24cf0', c2: '#6f2fb0' },
    gallery:  { icon: '🖼️', label: 'گالری', c1: '#f0952e', c2: '#c06712' },
};

export default function ClassContent() {
    const { items = [] } = usePage().props;

    return (
        <ThemedDash title="محتوای کلاس" active="content">
            <div className="k3-card">
                <div style={{ fontWeight: 900, fontSize: 18, marginBottom: 4 }}>📚 محتوای کلاس تو</div>
                <div style={{ opacity: .8, fontSize: 13 }}>جزوه‌ها، پادکست‌ها و گالریِ کلاس که معلم برایت گذاشته.</div>
            </div>

            {items.length === 0 && (
                <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', opacity: .8 }}>
                    هنوز محتوایی اضافه نشده 📭
                </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(240px,1fr))', gap: 14, marginTop: 14 }}>
                {items.map((it) => {
                    const t = TYPE[it.type] ?? TYPE.material;
                    return (
                        <a key={it.id} href={it.url || '#'} target={it.url ? '_blank' : undefined} rel="noreferrer"
                            className="k3-card" style={{ display: 'block', color: 'inherit', textDecoration: 'none', borderTop: `4px solid ${t.c1}` }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                <span style={{ width: 44, height: 44, borderRadius: 12, display: 'grid', placeItems: 'center', fontSize: 22, background: `linear-gradient(135deg,${t.c1},${t.c2})` }}>{t.icon}</span>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontWeight: 800, fontSize: 15 }}>{it.title}</div>
                                    <div style={{ fontSize: 11.5, opacity: .7 }}>{t.label} · {it.date}</div>
                                </div>
                            </div>
                            {it.description && <div style={{ fontSize: 13, opacity: .85, marginTop: 8, lineHeight: 1.9 }}>{it.description}</div>}
                            {it.url && <div style={{ marginTop: 10, fontWeight: 800, fontSize: 13, color: t.c1 }}>{it.is_file ? '⬇️ دانلود' : '🔗 مشاهده'} ←</div>}
                        </a>
                    );
                })}
            </div>
        </ThemedDash>
    );
}
