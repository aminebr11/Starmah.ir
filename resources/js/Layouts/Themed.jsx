import { Head, Link, usePage, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { cssVars, ui } from '@/theme';

/**
 * چیدمان مشترک تم‌دار + برند.
 * - رنگ‌ها از theme.skin (موتور تم) تزریق می‌شوند
 * - هدر با لوگوی برند ستاره ماه
 * - ناوبری پایین بر اساس نقش (nav prop)
 */
export default function Themed({ title, children, nav = null, active = '' }) {
    const { theme, auth } = usePage().props;
    const skin = theme?.skin ?? {};
    const vars = useMemo(() => cssVars(skin), [theme?.id]);

    return (
        <div dir="rtl" style={{ ...vars, ...ui.page }}>
            <Head title={title} />

            <header style={{ position: 'sticky', top: 0, zIndex: 30,
                background: 'rgba(14,28,61,.55)', backdropFilter: 'blur(14px)',
                borderBottom: '1px solid rgba(255,255,255,.08)' }}>
                <div style={{ ...ui.container, padding: '10px 16px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Link href="/dashboard" style={{ display: 'flex', alignItems: 'center', gap: 8, textDecoration: 'none' }}>
                        <img src="/brand/logo-mark.svg" alt="ستاره ماه" width="34" height="34" />
                        <span style={{ fontWeight: 800, color: '#fff', fontSize: 16 }}>
                            ستاره<span style={{ color: 'var(--p1)' }}>ماه</span>
                        </span>
                    </Link>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                        {theme?.emoji && <span style={{ ...ui.pill }}>{theme.emoji} {theme.name}</span>}
                        <button onClick={() => router.post(route('logout'))} style={{ ...ui.pill, cursor: 'pointer', fontFamily: 'inherit' }}>خروج</button>
                    </div>
                </div>
            </header>

            <main style={ui.container}>{children}</main>

            {nav && (
                <nav style={{ position: 'fixed', bottom: 0, left: 0, right: 0, height: 70, zIndex: 40,
                    background: 'rgba(14,28,61,.92)', backdropFilter: 'blur(14px)',
                    borderTop: '1px solid rgba(255,255,255,.1)', display: 'flex', maxWidth: 480, margin: '0 auto' }}>
                    {nav.map((n) => (
                        <Link key={n.key} href={n.href} style={{
                            flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3,
                            paddingTop: 11, textDecoration: 'none', fontSize: 10, fontWeight: 700,
                            color: active === n.key ? 'var(--acc)' : 'rgba(255,255,255,.6)',
                        }}>
                            <span style={{ fontSize: 20, transform: active === n.key ? 'translateY(-2px) scale(1.12)' : 'none', transition: '.2s' }}>{n.icon}</span>
                            {n.label}
                        </Link>
                    ))}
                </nav>
            )}
        </div>
    );
}

export const studentNav = (active) => ([
    { key: 'home', label: 'خانه', icon: '🏠', href: '/dashboard' },
    { key: 'practice', label: 'تمرین', icon: '🎮', href: '/practice' },
    { key: 'board', label: 'جدول', icon: '🏆', href: '/leaderboard' },
    { key: 'progress', label: 'کارنامه', icon: '📈', href: '/progress' },
    { key: 'messages', label: 'پیام‌ها', icon: '💌', href: '/messages' },
]);
