import { Head, Link, usePage, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { cssVars } from '@/theme';
import AssistantWidget from '@/Components/AssistantWidget';
import BellMenu from '@/Components/BellMenu';
import Avatar from '@/Components/Avatar';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** نوارِ ثابتِ بالای صفحه در حالتِ «پیش‌نمایشِ معلم». */
function PreviewRibbon({ preview }) {
    const st = { draft: 'پیش‌نویس', published: 'منتشرشده', closed: 'بسته‌شده', archived: 'بایگانی' }[preview.status] || preview.status;
    return (
        <div style={{
            position: 'fixed', insetInlineStart: 0, insetInlineEnd: 0, top: 0, zIndex: 120,
            background: 'linear-gradient(90deg,#f5b53f,#e8862e)', color: '#231603',
            display: 'flex', alignItems: 'center', gap: 10, padding: '9px 14px', flexWrap: 'wrap',
            fontWeight: 800, fontSize: 13, boxShadow: '0 4px 18px -8px rgba(0,0,0,.6)',
        }}>
            <span>👁️ پیش‌نمایشِ معلم — این دقیقاً همان چیزی است که دانش‌آموز می‌بیند</span>
            <span style={{ background: 'rgba(0,0,0,.18)', borderRadius: 20, padding: '2px 10px', fontSize: 11.5 }}>وضعیت: {st}</span>
            <span style={{ fontWeight: 600, fontSize: 11.5, opacity: .85 }}>هیچ امتیاز و نمره‌ای ثبت نمی‌شود</span>
            <a href={preview.back} style={{ marginInlineStart: 'auto', background: '#231603', color: '#ffe9b8', borderRadius: 10, padding: '6px 14px', textDecoration: 'none', fontSize: 12.5 }}>
                ← بازگشت و ویرایش
            </a>
        </div>
    );
}

/**
 * داشبورد دانش‌آموز — مثل صفحات معلم (سایدبار) ولی با رنگ تیم/گروه خودش.
 * لوگوی سایت بالا قرار دارد. ریسپانسیو.
 */
export default function ThemedDash({ title, active = '', children, actions = null }) {
    const { auth, theme, unreadNotices = 0, smartLab = false, avatarUrl = null, familyNew = 0, preview = null } = usePage().props;
    const skin = theme?.skin ?? {};
    const vars = useMemo(() => cssVars(skin), [theme?.id]);
    const [open, setOpen] = useState(false);

    const menu = [
        { key: 'home', label: 'خانه', icon: '🏠', href: '/dashboard' },
        { divider: 'یادگیری و بازی' },
        { key: 'practice', label: 'مأموریت‌های من', icon: '🎯', href: '/missions' },
        { key: 'gameworld', label: 'دنیای بازی‌ها', icon: '🎮', href: '/game-world' },
        ...(smartLab ? [{ key: 'smart', label: 'آزمون هوشمند 🧪', icon: '🧠', href: '/student/smart-exams' }] : []),
        { divider: 'عملکردِ من' },
        { key: 'report', label: 'کارنامه', icon: '📊', href: '/report' },
        { key: 'team', label: 'تیمِ ما', icon: '🏆', href: '/my-team' },
        { key: 'board', label: 'رقابت تیم‌ها', icon: '🏅', href: '/leaderboard' },
        { key: 'activities', label: 'فعالیت‌ها و امتیازها', icon: '🎁', href: '/my-activities' },
        { key: 'discipline', label: 'موارد انضباطی', icon: '⭐', href: '/my-discipline' },
        { divider: 'کلاس' },
        // تکالیف و کاربرگ‌ها تبی از «محتوای کلاس» هستند، نه منویی جدا
        { key: 'content', label: 'محتوای کلاس', icon: '📚', href: '/class-content' },
        { key: 'homework', label: 'تکالیف و کاربرگ', icon: '📝', href: '/class-content?tab=homework' },
        { key: 'schedule', label: 'برنامه کلاسی', icon: '🗓️', href: '/schedule' },
        { divider: 'ارتباط' },
        { key: 'messages', label: 'ارتباط با معلم', icon: '💬', href: '/messages' },
        { key: 'notices', label: 'اعلان‌ها', icon: '📢', href: '/notices' },
        { key: 'family', label: 'بخشِ والدین', icon: '🔐', href: '/family' },
        { divider: null },
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
        <div dir="rtl" className="dash kids-dash" style={{ ...vars, background: 'linear-gradient(180deg,var(--bg1),var(--bg2))', paddingTop: preview ? 46 : 0 }}>
            <Head title={title ? `${title} — ستاره ماه` : 'ستاره ماه'} />

            {/* نوارِ پیش‌نمایشِ معلم — تا لحظه‌ای هم گمان نرود این صفحه واقعی است */}
            {preview && <PreviewRibbon preview={preview} />}

            {/* در پیش‌نمایش، منویِ دانش‌آموز فقط تزئینی است تا معلم با کلیک به خطای دسترسی نخورد */}
            <aside className={`dash-side ${open ? 'open' : ''}`}
                style={{ background: 'rgba(0,0,0,.25)', ...(preview ? { pointerEvents: 'none', opacity: .55 } : {}) }}>
                <Link href="/" className="dash-brand" style={{ color: '#fff' }}>
                    <img src="/brand/logo-emblem.png" alt="" />
                    <div>ستاره ماه<div className="dash-role" style={{ background: 'rgba(255,255,255,.14)', borderColor: 'rgba(255,255,255,.25)', color: 'var(--acc)' }}>{theme?.emoji} {theme?.name}</div></div>
                </Link>
                <nav className="dash-nav">
                    {menu.map((m, i) => (
                        m.divider !== undefined ? (
                            m.divider ? <div key={`d${i}`} className="dash-nav-section" style={{ color: 'rgba(255,255,255,.5)', borderTopColor: 'rgba(255,255,255,.1)' }}>{m.divider}</div>
                                : <div key={`d${i}`} style={{ height: 1, background: 'rgba(255,255,255,.1)', margin: '8px 6px' }} />
                        ) : (
                            <Link key={m.key} href={m.href} className={active === m.key ? 'active' : ''} onClick={() => setOpen(false)}
                                style={active === m.key ? { background: 'linear-gradient(135deg,var(--p1),var(--p2))', color: '#fff' } : {}}>
                                <span className="ic">{m.icon}</span>{m.label}
                                {m.key === 'notices' && unreadNotices > 0 && <span className="nav-badge">{fa(unreadNotices)}</span>}
                                {m.key === 'family' && familyNew > 0 && <span className="nav-badge">{fa(familyNew)}</span>}
                            </Link>
                        )
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
                        {/* زنگوله‌ی اعلان یکپارچه — مؤلفه‌ی مشترکِ همه‌ی نقش‌ها */}
                        <BellMenu tone="dark" />
                        <Link href="/profile" className="td-profile-link" style={{ color: 'rgba(255,255,255,.85)', fontSize: 14, display: 'flex', alignItems: 'center', gap: 8 }}>
                            {avatarUrl
                                ? <Avatar src={avatarUrl} name={auth?.user?.name} size={34} ring />
                                : <span style={{ width: 34, height: 34, borderRadius: '50%', background: 'linear-gradient(135deg,var(--p1),var(--p2))', display: 'grid', placeItems: 'center' }}>{skin.mascot ?? '🙂'}</span>}
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
            <nav className="bottom-nav" style={preview ? { pointerEvents: 'none', opacity: .55 } : undefined}>
                {bottomNav.map((m) => (
                    <Link key={m.key} href={m.href} className={active === m.key ? 'on' : ''}>
                        <span className="bic">{m.icon}</span>{m.label}
                        {m.key === 'notices' && unreadNotices > 0 && <span className="bdot">{fa(unreadNotices)}</span>}
                    </Link>
                ))}
            </nav>

            <AssistantWidget />
        </div>
    );
}
