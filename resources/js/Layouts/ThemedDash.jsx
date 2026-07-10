import { Head, Link, usePage, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { cssVars } from '@/theme';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * داشبورد دانش‌آموز — مثل صفحات معلم (سایدبار) ولی با رنگ تیم/گروه خودش.
 * لوگوی سایت بالا قرار دارد. ریسپانسیو.
 */
export default function ThemedDash({ title, active = '', children, actions = null }) {
    const { auth, theme, notifications = [], unreadNotices = 0 } = usePage().props;
    const skin = theme?.skin ?? {};
    const vars = useMemo(() => cssVars(skin), [theme?.id]);
    const [open, setOpen] = useState(false);
    const [bell, setBell] = useState(false);

    const menu = [
        { key: 'home', label: 'خانه', icon: '🏠', href: '/dashboard' },
        { key: 'practice', label: 'مأموریت و بازی', icon: '🎮', href: '/practice' },
        { key: 'exams', label: 'آزمون‌های من', icon: '💻', href: '/exams' },
        { key: 'board', label: 'رقابت تیم‌ها', icon: '🏆', href: '/leaderboard' },
        { key: 'progress', label: 'کارنامه‌ی من', icon: '📈', href: '/progress' },
        { key: 'discipline', label: 'موارد انضباطی', icon: '⭐', href: '/my-discipline' },
        { key: 'schedule', label: 'برنامه کلاسی', icon: '🗓️', href: '/schedule' },
        { key: 'notices', label: 'اعلان‌ها', icon: '📢', href: '/notices' },
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
                            {m.key === 'notices' && unreadNotices > 0 && <span className="nav-badge">{fa(unreadNotices)}</span>}
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
                        {/* زنگوله‌ی اعلان (موارد انضباطی ۲۴ ساعت اخیر) */}
                        <div style={{ position: 'relative' }}>
                            <button onClick={() => setBell(!bell)} style={{ position: 'relative', background: 'rgba(255,255,255,.1)', border: '1px solid rgba(255,255,255,.2)', color: '#fff', width: 40, height: 40, borderRadius: 12, cursor: 'pointer', fontSize: 18 }}>
                                🔔
                                {notifications.length > 0 && <span style={{ position: 'absolute', top: -4, insetInlineEnd: -4, background: '#e8505b', color: '#fff', borderRadius: 20, minWidth: 18, height: 18, fontSize: 11, fontWeight: 800, display: 'grid', placeItems: 'center', padding: '0 4px' }}>{fa(notifications.length)}</span>}
                            </button>
                            {bell && (
                                <div style={{ position: 'absolute', top: 48, insetInlineEnd: 0, width: 280, background: '#fff', color: 'var(--ink)', borderRadius: 16, boxShadow: '0 20px 50px -20px rgba(0,0,0,.5)', zIndex: 80, overflow: 'hidden' }}>
                                    <div style={{ padding: '12px 14px', fontWeight: 800, borderBottom: '1px solid #eef2f8' }}>🔔 اعلان‌های جدید</div>
                                    {notifications.length === 0 && <div style={{ padding: 16, color: '#6b7794', fontSize: 13, textAlign: 'center' }}>اعلان جدیدی نداری 🎉</div>}
                                    {notifications.map((n, i) => (
                                        <div key={i} style={{ padding: '11px 14px', borderBottom: '1px solid #f3f6fb', display: 'flex', alignItems: 'center', gap: 10 }}>
                                            <span style={{ fontSize: 18 }}>{n.kind === 'positive' ? '🌟' : '⚠️'}</span>
                                            <span style={{ flex: 1, fontSize: 13, fontWeight: 600 }}>{n.title}</span>
                                            <b style={{ color: n.points >= 0 ? '#2bb673' : '#e8505b' }}>{n.points >= 0 ? '+' : ''}{fa(n.points)}</b>
                                        </div>
                                    ))}
                                    <Link href="/my-discipline" onClick={() => setBell(false)} style={{ display: 'block', padding: '11px 14px', textAlign: 'center', color: 'var(--gold-2)', fontWeight: 700, fontSize: 13 }}>مشاهده‌ی همه‌ی موارد ←</Link>
                                </div>
                            )}
                        </div>
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
