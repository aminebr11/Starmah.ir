/**
 * آواتارِ کاربر — اگر عکس داشته باشد نمایش می‌دهد، وگرنه حرفِ اولِ نام روی گرادیانِ تم.
 * props: src (آدرسِ عکس)، name، size (px)، ring (حلقه‌ی دور)، style
 */
export default function Avatar({ src, name = '', size = 36, ring = false, style = {} }) {
    const initials = String(name).trim().split(/\s+/).filter(Boolean).slice(0, 2)
        .map((w) => w[0]).join('');
    const base = {
        width: size, height: size, borderRadius: '50%', flex: 'none', objectFit: 'cover',
        boxShadow: ring ? '0 0 0 2px rgba(255,255,255,.6)' : undefined, ...style,
    };
    if (src) return <img src={src} alt={name || 'avatar'} style={base} loading="lazy" />;
    return (
        <span aria-hidden="true" style={{
            ...base, display: 'grid', placeItems: 'center', fontWeight: 800, fontSize: Math.round(size * 0.4),
            color: '#fff', background: 'linear-gradient(135deg,var(--p1,#3d7bf0),var(--p2,#2555c0))',
        }}>{initials || '🙂'}</span>
    );
}
