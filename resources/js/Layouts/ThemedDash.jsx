import { Head, Link, usePage, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { cssVars } from '@/theme';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * داشبورد دانش‌آموز — مثل صفحات معلم (سایدبار) ولی با رنگ تیم/گروه خودش.
 * لوگوی سایت بالا قرار دارد. ریسپانسیو.
 */
export default function ThemedDash({ title, active = '', children, actions = null }) {
    const { auth, theme, notifications = [], unreadNotices = 0, smartLab = false } = usePage().props;
    const unread = unreadNotices || 0;
    const skin = theme?.skin ?? {};
    const vars = useMemo(() => cssVars(skin), [theme?.id]);
    const [open, setOpen] = useState(false);
    const [bell, setBell] = useState(false);

    const menu = [
        { key: 'home', label: 'خانه', icon: '🏠', href: '/dashboard' },
        { key: 'practice', label: 'مأموریت و تمرین', icon: '🎯', href: '/practice' },
        { key: 'gameworld', label: 'دنیای بازی‌ها', icon: '🎮', href: '/game-world' },
        ...(smartLab ? [{ key: 'smart', label: 'آزمون هوشمند 🧪', icon: '🧠', href: '/student/smart-exams' }] : []),
        { key: 'board', label: 'رقابت تیم‌ها', icon: '🏆', href: '/leaderboard' },
        { key: 'reports', label: 'گزارش‌ها و نمودارها', icon: '📈', href: '/my-reports' },
        { key: 'progress', label: 'کارنامه‌ی من', icon: '🗂️', href: '/progress' },
        { key: 'grades', label: 'نمرات کلاسی', icon: '📔', href: '/my-grades' },
        { key: 'activities', label: 'فعالیت‌ها و امتیازها', icon: '🎁', href: '/my-activities' },
        { key: 'discipline', label: 'موارد انضباطی', icon: '⭐', href: '/my-discipline' },
        { key: 'content', label: 'محتوای کلاس', icon: '📚', href: '/class-content' },
        { key: 'homework', label: 'تکالیف', icon: '📝', href: '/homework' },
        { key: 'schedule', label: 'برنامه کلاسی', icon: '🗓️', href: '/schedule' },
        { key: 'notices', label: 'اعلان‌ها', icon: '📢', href: '/notices' },
        { key: 'messages', label: 'پیام‌ها', icon: '💌', href: '/messages' },
        { key: 'profile', label: 'پروفایل من', icon: '👤', href: '/profile' },
    ];

    // نوار پایین موبایل — ۵ مقصد اصلی بچه‌ها (انگشت‌پسند)
    const bottomNav = [
        { key: 'home', label: 'خانه', icon: '🏠', href: '/dashboard' },
        { key: 'gameworld', label: 'بازی‌ها', icon: '🎮', href: '/game-world' },
        ...(smartLab ? [{ key: 'smart', label: 'آزمون', icon: '🧠', href: '/student/smart-exams' }] : []),
        { key: 'board', label: 'رقابت', icon: '🏆', href: '/leaderboard' },
        { key: 'notices', label: 'اعلان‌ها', icon: '📢', href: '/notices' },
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

            <main className="dash-main kids" style={{ color: '#fff' }}>
                <div className="dash-topbar">
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <button className="dash-mobilebtn" onClick={() => setOpen(true)} style={{ background: 'rgba(255,255,255,.1)', border: '1px solid rgba(255,255,255,.2)', color: '#fff' }}>☰</button>
                        <h1 style={{ color: '#fff' }}>{title}</h1>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        {actions}
                        {/* زنگوله‌ی اعلان یکپارچه (اطلاعیه + پیام + انضباط) */}
                        <div style={{ position: 'relative' }}>
                            <button onClick={() => setBell(!bell)} className={unread > 0 ? 'bell-live' : ''}
                                style={{ position: 'relative', background: 'rgba(255,255,255,.1)', border: '1px solid rgba(255,255,255,.2)', color: '#fff', width: 40, height: 40, borderRadius: 12, cursor: 'pointer', fontSize: 18 }}>
                                🔔
                                {unread > 0 && <span style={{ position: 'absolute', top: -4, insetInlineEnd: -4, background: '#e8505b', color: '#fff', borderRadius: 20, minWidth: 18, height: 18, fontSize: 11, fontWeight: 800, display: 'grid', placeItems: 'center', padding: '0 4px', boxShadow: '0 0 0 2px rgba(255,255,255,.3)' }}>{fa(unread)}</span>}
                            </button>
                            {bell && (
                                <div style={{ position: 'absolute', top: 48, insetInlineEnd: 0, width: 300, maxWidth: '86vw', background: '#fff', color: 'var(--ink)', borderRadius: 16, boxShadow: '0 20px 50px -20px rgba(0,0,0,.5)', zIndex: 80, overflow: 'hidden' }}>
                                    <div style={{ padding: '12px 14px', fontWeight: 800, borderBottom: '1px solid #eef2f8', display: 'flex', alignItems: 'center', gap: 8 }}>
                                        🔔 اعلان‌ها
                                        {unread > 0 && <span style={{ background: '#e8505b', color: '#fff', borderRadius: 20, padding: '1px 8px', fontSize: 11 }}>{fa(unread)} جدید</span>}
                                    </div>
                                    {notifications.length === 0 && <div style={{ padding: 16, color: '#6b7794', fontSize: 13, textAlign: 'center' }}>اعلان جدیدی نداری 🎉</div>}
                                    <div style={{ maxHeight: 340, overflowY: 'auto' }}>
                                        {notifications.map((n) => (
                                            <Link key={n.id} href={n.href} onClick={() => setBell(false)}
                                                style={{ padding: '11px 14px', borderBottom: '1px solid #f3f6fb', display: 'flex', alignItems: 'flex-start', gap: 10, color: 'inherit', background: n.read ? '#fff' : '#f4f8ff', borderInlineStart: `4px solid ${n.color}` }}>
                                                <span style={{ fontSize: 18, flex: 'none' }}>{n.icon}</span>
                                                <div style={{ flex: 1, minWidth: 0 }}>
                                                    <div style={{ fontSize: 13, fontWeight: 700, display: 'flex', gap: 6 }}>
                                                        <span style={{ flex: 1 }}>{n.title}</span>
                                                        {!n.read && <span style={{ width: 8, height: 8, borderRadius: 8, background: '#e8505b', flex: 'none', marginTop: 4 }} />}
                                                    </div>
                                                    {n.body && <div style={{ fontSize: 12, color: '#6b7794', marginTop: 2, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{n.body}</div>}
                                                    <div style={{ fontSize: 10.5, color: '#9aa6bd', marginTop: 2 }}>{n.date}</div>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                    <Link href="/notices" onClick={() => setBell(false)} style={{ display: 'block', padding: '11px 14px', textAlign: 'center', color: 'var(--gold-2)', fontWeight: 700, fontSize: 13 }}>مشاهده‌ی همه ←</Link>
                                </div>
                            )}
                        </div>
                        <Link href="/profile" style={{ color: 'rgba(255,255,255,.85)', fontSize: 14, display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span style={{ width: 34, height: 34, borderRadius: '50%', background: 'linear-gradient(135deg,var(--p1),var(--p2))', display: 'grid', placeItems: 'center' }}>{skin.mascot ?? '🙂'}</span>
                            <span className="hide-mobile">{auth?.user?.name}</span>
                        </Link>
                        <button onClick={() => router.post(route('logout'))} title="خروج از حساب کاربری"
                            style={{ display: 'flex', alignItems: 'center', gap: 6, background: 'rgba(232,80,91,.22)', border: '1px solid rgba(232,80,91,.4)', color: '#ffd9dc', height: 40, padding: '0 12px', borderRadius: 12, cursor: 'pointer', fontFamily: 'inherit', fontWeight: 700, fontSize: 13 }}>
                            🚪 <span className="hide-mobile">خروج از حساب کاربری</span>
                        </button>
                    </div>
                </div>
                {children}
            </main>

            {/* نوار بازی‌گونه‌ی پایین — فقط موبایل */}
            <nav className="bottom-nav">
                {bottomNav.map((m) => (
                    <Link key={m.key} href={m.href} className={active === m.key ? 'on' : ''}>
                        <span className="bic">{m.icon}</span>{m.label}
                        {m.key === 'notices' && unreadNotices > 0 && <span className="bdot">{fa(unreadNotices)}</span>}
                    </Link>
                ))}
            </nav>
        </div>
    );
}
