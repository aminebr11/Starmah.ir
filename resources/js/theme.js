/**
 * کمک‌کننده‌های تم — رنگ‌ها از prop سراسری `theme.skin` می‌آیند.
 * این فایل استایل‌های مشترک و توابع کمکی همه‌ی صفحات را فراهم می‌کند.
 */
export const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export function cssVars(skin = {}) {
    return {
        '--bg1': skin.bg1 ?? '#0e1c3d',
        '--bg2': skin.bg2 ?? '#152a55',
        '--p1': skin.p1 ?? '#f5b53f',
        '--p2': skin.p2 ?? '#e09b22',
        '--acc': skin.acc ?? '#ffd87a',
        '--acc2': skin.acc2 ?? '#4a90d9',
        '--ring': skin.ring ?? '#f5b53f',
    };
}

export const ui = {
    page: {
        minHeight: '100vh',
        background: 'linear-gradient(180deg,var(--bg1),var(--bg2))',
        color: '#fff',
        fontFamily: "'Vazirmatn', system-ui, sans-serif",
        transition: 'background .5s',
    },
    container: { maxWidth: 480, margin: '0 auto', padding: '18px 16px 96px' },
    card: { background: 'rgba(255,255,255,.06)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 22, padding: 16, backdropFilter: 'blur(8px)' },
    muted: { color: 'rgba(255,255,255,.6)', fontSize: 12, lineHeight: 1.9 },
    h: { fontWeight: 800, fontSize: 19, letterSpacing: '-.4px' },
    btn: {
        display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, width: '100%',
        border: 0, cursor: 'pointer', fontFamily: 'inherit', fontWeight: 800, fontSize: 15, color: '#0e1c3d',
        padding: 15, borderRadius: 18, background: 'linear-gradient(135deg,var(--p1),var(--p2))',
        boxShadow: '0 12px 26px -10px var(--p1)',
    },
    ghost: { background: 'rgba(255,255,255,.07)', color: '#fff', border: '1px solid rgba(255,255,255,.12)', boxShadow: 'none' },
    pill: { fontSize: 11, fontWeight: 700, padding: '5px 10px', borderRadius: 30, background: 'rgba(255,255,255,.1)', border: '1px solid rgba(255,255,255,.12)', color: '#fff' },
    pillAcc: { background: 'linear-gradient(135deg,var(--acc),#ff9d00)', color: '#1a1200', border: 0 },
    input: {
        width: '100%', padding: 13, borderRadius: 14, background: 'rgba(255,255,255,.08)',
        border: '1px solid rgba(255,255,255,.16)', color: '#fff', fontFamily: 'inherit', fontSize: 14,
    },
    bar: { height: 9, borderRadius: 10, background: 'rgba(255,255,255,.1)', overflow: 'hidden' },
    barFill: (w) => ({ width: `${w}%`, height: '100%', borderRadius: 10, background: 'linear-gradient(90deg,var(--p1),var(--acc))' }),
    row: (j = 'flex-start') => ({ display: 'flex', alignItems: 'center', gap: 10, justifyContent: j }),
    avatar: (skin = {}, size = 46) => ({
        width: size, height: size, borderRadius: 16, display: 'grid', placeItems: 'center',
        fontSize: size * 0.5, flex: 'none',
        background: `linear-gradient(135deg,${skin.p1 ?? '#f5b53f'},${skin.p2 ?? '#e09b22'})`,
    }),
};

export const rankColor = (r) => (r === 1 ? '#ffd23f' : r === 2 ? '#cdd3dc' : r === 3 ? '#e29a5b' : 'rgba(255,255,255,.6)');
