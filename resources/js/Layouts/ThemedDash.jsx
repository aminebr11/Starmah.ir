import { Head, Link, usePage, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { cssVars } from '@/theme';

/**
 * داشبورد دانش‌آموز — مثل صفحات معلم (سایدبار) ولی با رنگ تیم/گروه خودش.
 * لوگوی سایت بالا قرار دارد. ریسپانسیو.
 */
export default function ThemedDash({ title, active = '', children, actions = null }) {
    const { auth, theme } = usePage().props;
    const skin = theme?.skin ?? {};
    const vars = useMemo(() => cssVars(skin), [theme?.id]);
    const [open, setOpen] = useState(false);

    const menu = [
        { key: 'home', label: 'خانه', icon: '🏠', href: '/dashboard' },
        { key: 'practice', label: 'مأموریت و بازی', icon: '🎮', href: '/practice' },
        { key: 'board', label: 'رقابت تیم‌ها', icon: '🏆', href: '/leaderboard' },
        { key: 'progress', label: 'کارنامه‌ی من', icon: '📈', href: '/progress' },
        { key: 'messages', label: 'پیام‌ها', icon: '💌', href: '/messages' },
        { key: 'profile', label: 'پروفایل من', icon: '👤', href: '/profile' },
    ];

    return (
        <div dir="rtl" className="dash" style={{ ...vars, background: 'linear-gradient(180deg,var(--bg1),var(--bg2))' }}>
            <Head title={title ? `${title} — ستاره ماه` : 'ستاره ماه'} />

            <aside className={`dash-side ${open ? 'open' : ''}`} style={{ background: 'rgba(0,0,0,.25)' }}>
                <Link href="/" className="dash-brand" style={{ color: '#fff' }}>
                    <img src="/brand/logo-emblem.png" alt="" />
                    <div>ستاره ماه<div className="dash-role" style={{ background: 'rgba(255,255,255,.14)', borderColor: 'rgba(255,255,255,.25)', color: 'var(--acc)' }}>{theme?.emoji} {theme?.name}</div></div>
                </Link>
                <nav className="dash-nav">
                    {menu.map((m) => (
                        <Link key={m.key} href={m.href} className={active === m.key ? 'active' : ''} onClick={() => setOpen(false)}
                            style={active === m.key ? { background: 'linear-gradient(135deg,var(--p1),var(--p2))', color: '#fff' } : {}}>
                            <span className="ic">{m.icon}</span>{m.label}
                        </Link>
                    ))}
                    <button onClick={() => router.post(route('logout'))}
                        style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '11px 13px', borderRadius: 13, color: '#ffb3b3', background: 'transparent', border: 0, fontFamily: 'inherit', fontWeight: 600, fontSize: 14, cursor: 'pointer', marginTop: 8 }}>
                        <span className="ic">🚪</span> خروج
                    </button>
                </nav>
            </aside>

            {open && <div onClick={() => setOpen(false)} style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,.5)', zIndex: 90 }} />}

            <main className="dash-main" style={{ color: '#fff' }}>
                <div className="dash-topbar">
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <button className="dash-mobilebtn" onClick={() => setOpen(true)} style={{ background: 'rgba(255,255,255,.1)', border: '1px solid rgba(255,255,255,.2)', color: '#fff' }}>☰</button>
                        <h1 style={{ color: '#fff' }}>{title}</h1>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        {actions}
                        <Link href="/profile" style={{ color: 'rgba(255,255,255,.85)', fontSize: 14, display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span style={{ width: 34, height: 34, borderRadius: '50%', background: 'linear-gradient(135deg,var(--p1),var(--p2))', display: 'grid', placeItems: 'center' }}>{skin.mascot ?? '🙂'}</span>
                            {auth?.user?.name}
                        </Link>
                    </div>
                </div>
                {children}
            </main>
        </div>
    );
}
