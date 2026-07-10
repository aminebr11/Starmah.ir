import { Head, Link, usePage, router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * چیدمان داشبورد مدیریتی (سوپرادمین / مدیر مدرسه / معلم) با سایدبار.
 * هماهنگ با تن صفحه‌ی اصلی (سرمه‌ای/طلایی). ریسپانسیو.
 *
 * props: title, roleLabel, menu:[{key,label,icon,href}], active, children
 */
export default function DashLayout({ title, roleLabel, menu = [], active = '', children, actions = null }) {
    const { auth, unreadNotices = 0 } = usePage().props;
    const [open, setOpen] = useState(false);

    return (
        <div dir="rtl" className="dash">
            <Head title={title ? `${title} — ستاره ماه` : 'ستاره ماه'} />

            <aside className={`dash-side ${open ? 'open' : ''}`}>
                <Link href="/" className="dash-brand">
                    <img src="/brand/logo-emblem.png" alt="" />
                    <div>ستاره ماه<div className="dash-role">{roleLabel}</div></div>
                </Link>
                <nav className="dash-nav">
                    {menu.map((m) => (
                        <Link key={m.key} href={m.href} className={active === m.key ? 'active' : ''} onClick={() => setOpen(false)}>
                            <span className="ic">{m.icon}</span>{m.label}
                            {m.key === 'notices' && unreadNotices > 0 && <span className="nav-badge">{unreadNotices}</span>}
                        </Link>
                    ))}
                    <Link href={route('profile.edit')} className={active === 'profile' ? 'active' : ''} onClick={() => setOpen(false)}>
                        <span className="ic">👤</span>پروفایل من
                    </Link>
                    <button onClick={() => router.post(route('logout'))}
                        style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '11px 13px', borderRadius: 13, color: '#ff9d9d', background: 'transparent', border: 0, fontFamily: 'inherit', fontWeight: 600, fontSize: 14, cursor: 'pointer', marginTop: 8 }}>
                        <span className="ic">🚪</span> خروج
                    </button>
                </nav>
            </aside>

            {open && <div onClick={() => setOpen(false)} style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,.4)', zIndex: 90 }} />}

            <main className="dash-main">
                <div className="dash-topbar">
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <button className="dash-mobilebtn" onClick={() => setOpen(true)}>☰</button>
                        <h1>{title}</h1>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        {actions}
                        <Link href={route('profile.edit')} className="btn btn-ghost btn-sm" title="پروفایل من">
                            👤 {auth?.user?.name}
                        </Link>
                    </div>
                </div>
                {children}
            </main>
        </div>
    );
}

export const adminMenu = [
    { key: 'home', label: 'پیشخوان', icon: '📊', href: '/admin' },
    { key: 'schools', label: 'مدارس', icon: '🏫', href: '/admin/schools' },
    { key: 'plans', label: 'طرح‌های اشتراک', icon: '🎟️', href: '/admin/plans' },
    { key: 'curriculum', label: 'دروس و کتاب‌ها', icon: '📚', href: '/admin/curriculum' },
    { key: 'themes', label: 'تم‌ها (دنیاها)', icon: '🎨', href: '/admin/themes' },
    { key: 'reports', label: 'گزارش‌ها', icon: '📈', href: '/admin/reports' },
    { key: 'settings', label: 'تنظیمات پلتفرم', icon: '⚙️', href: '/admin/settings' },
];

export const schoolMenu = [
    { key: 'home', label: 'پیشخوان مدرسه', icon: '📊', href: '/school' },
    { key: 'teachers', label: 'معلم‌ها و کلاس‌ها', icon: '👩‍🏫', href: '/school/teachers' },
    { key: 'students', label: 'دانش‌آموزان', icon: '🎓', href: '/school/students' },
    { key: 'schedule', label: 'برنامه‌ی کلاس‌ها', icon: '🗓️', href: '/school/schedule' },
    { key: 'announcements', label: 'اطلاعیه‌ها', icon: '📢', href: '/school/announcements' },
    { key: 'reports', label: 'گزارش‌ها', icon: '📈', href: '/school/reports' },
];

export const teacherMenu = [
    { key: 'home', label: 'پیشخوان', icon: '📊', href: '/teacher' },
    { key: 'attendance', label: 'حضور و غیاب', icon: '✅', href: '/teacher/attendance' },
    { key: 'activities', label: 'فعالیت‌ها و امتیاز', icon: '🎯', href: '/teacher/activities' },
    { key: 'exams', label: 'آزمون‌ساز (AI)', icon: '📝', href: '/teacher/exams' },
    { key: 'schedule', label: 'برنامه کلاسی', icon: '🗓️', href: '/teacher/schedule' },
    { key: 'gradebook', label: 'دفتر نمره', icon: '📔', href: '/teacher/gradebook' },
    { key: 'discipline', label: 'انضباط', icon: '⭐', href: '/teacher/discipline' },
    { key: 'notices', label: 'اعلان‌ها', icon: '📢', href: '/notices' },
    { key: 'reports', label: 'گزارش‌ها', icon: '📈', href: '/teacher/reports' },
    { key: 'materials', label: 'مطالب و محتوا', icon: '📚', href: '/teacher/materials' },
];
