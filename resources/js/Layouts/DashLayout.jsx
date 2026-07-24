import { Head, Link, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import AssistantWidget from '@/Components/AssistantWidget';
import Avatar from '@/Components/Avatar';

/**
 * چیدمان داشبورد مدیریتی (سوپرادمین / مدیر مدرسه / معلم) با سایدبار.
 * هماهنگ با تن صفحه‌ی اصلی (سرمه‌ای/طلایی). ریسپانسیو.
 *
 * props: title, roleLabel, menu:[{key,label,icon,href}], active, children
 */
export default function DashLayout({ title, roleLabel, menu = [], active = '', children, actions = null }) {
    const { auth, unreadNotices = 0, smartLab = false, avatarUrl = null, school = null } = usePage().props;
    const [open, setOpen] = useState(false);
    // آیتم‌هایی که flag: 'smart' دارند فقط وقتی ماژول فعال است نمایش داده می‌شوند
    menu = menu.filter((m) => !m.flag || (m.flag === 'smart' && smartLab));

    return (
        <div dir="rtl" className="dash">
            <Head title={title ? `${title} — ستاره ماه` : 'ستاره ماه'} />

            <aside className={`dash-side ${open ? 'open' : ''}`}>
                <Link href="/" className="dash-brand">
                    <img src="/brand/logo-emblem.png" alt="" />
                    <div>ستاره ماه<div className="dash-role">{roleLabel}</div></div>
                </Link>
                {school && (
                    <div className="dash-school">
                        {school.logo_url
                            ? <img src={school.logo_url} alt="" />
                            : <span className="dash-school-ph">🏫</span>}
                        <span className="dash-school-nm">{school.name}</span>
                    </div>
                )}
                <nav className="dash-nav">
                    {menu.map((m, idx) => (
                        m.divider ? (
                            <div key={`d${idx}`} className="dash-nav-section">{m.divider}</div>
                        ) : (
                            <Link key={m.key} href={m.href} className={active === m.key ? 'active' : ''} onClick={() => setOpen(false)}>
                                <span className="ic">{m.icon}</span>{m.label}
                                {m.key === 'notices' && unreadNotices > 0 && <span className="nav-badge">{unreadNotices}</span>}
                            </Link>
                        )
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
                        <Link href="/notices" className={`bell-btn ${unreadNotices > 0 ? 'ring' : ''}`} title="اعلان‌ها و پیام‌ها">
                            🔔{unreadNotices > 0 && <span className="bell-dot">{unreadNotices}</span>}
                        </Link>
                        <div className="user-chip">
                            <Link href={route('profile.edit')} className="user-chip-name" title="پروفایل من">
                                <Avatar src={avatarUrl} name={auth?.user?.name} size={26} /><span className="nm">{auth?.user?.name}</span>
                            </Link>
                            <button onClick={() => router.post(route('logout'))} className="user-chip-out" title="خروج از حساب">🚪</button>
                        </div>
                    </div>
                </div>
                {children}
            </main>
            <AssistantWidget />
        </div>
    );
}

export const adminMenu = [
    { key: 'home', label: 'پیشخوان', icon: '📊', href: '/admin' },

    { divider: 'مدرسه‌ها و اشتراک' },
    { key: 'schools', label: 'مدارس', icon: '🏫', href: '/admin/schools' },
    { key: 'plans', label: 'طرح‌های اشتراک', icon: '🎟️', href: '/admin/plans' },

    { divider: 'محتوا و بازی' },
    { key: 'curriculum', label: 'دروس و کتاب‌ها', icon: '📚', href: '/admin/curriculum' },
    { key: 'bank', label: 'بانک سؤالات', icon: '🗄️', href: '/question-bank' },
    { key: 'themes', label: 'تم‌ها (دنیاها)', icon: '🎨', href: '/admin/themes' },
    { key: 'game-templates', label: 'قالب‌های بازی', icon: '🎲', href: '/admin/game-templates' },
    { key: 'smart-lab', label: 'آزمایشگاه هوشمند', icon: '🧪', href: '/admin/smart-lab' },

    { divider: 'گزارش و تنظیمات' },
    { key: 'reports', label: 'گزارش‌ها', icon: '📈', href: '/admin/reports' },
    { key: 'settings', label: 'تنظیمات پلتفرم', icon: '⚙️', href: '/admin/settings' },
];

export const schoolMenu = [
    { key: 'home', label: 'پیشخوان مدرسه', icon: '📊', href: '/school' },

    { divider: 'افراد و کلاس‌ها' },
    { key: 'teachers', label: 'معلم‌ها و کلاس‌ها', icon: '👩‍🏫', href: '/school/teachers' },
    { key: 'students', label: 'دانش‌آموزان', icon: '🎓', href: '/school/students' },
    { key: 'schedule', label: 'برنامه‌ی کلاس‌ها', icon: '🗓️', href: '/school/schedule' },
    { key: 'attendance', label: 'حضور و غیاب', icon: '✅', href: '/school/attendance' },

    { divider: 'آموزش و آزمون' },
    { key: 'bank', label: 'بانک سؤالات', icon: '🗄️', href: '/question-bank' },
    { key: 'examreports', label: 'گزارش آزمون‌ها', icon: '📊', href: '/school/exam-reports' },

    { divider: 'ارتباط و گزارش' },
    { key: 'announcements', label: 'اطلاعیه‌ها', icon: '📢', href: '/school/announcements' },
    { key: 'messages', label: 'ارتباط با والدین/معلم', icon: '💬', href: '/messages' },
    { key: 'reports', label: 'گزارش‌ها', icon: '📈', href: '/school/reports' },
];

export const parentMenu = [
    { key: 'home', label: 'وضعیتِ فرزندِ من', icon: '📊', href: '/parent' },
    { key: 'messages', label: 'ارتباط با معلم/مدرسه', icon: '💬', href: '/messages' },
    { key: 'notices', label: 'اعلان‌ها', icon: '📢', href: '/notices' },
];

export const teacherMenu = [
    { key: 'home', label: 'پیشخوان', icon: '📊', href: '/teacher' },

    { divider: 'کلاسِ من' },
    { key: 'class', label: 'دانش‌آموزان', icon: '🎓', href: '/teacher/students' },
    { key: 'attendance', label: 'حضور و غیاب', icon: '✅', href: '/teacher/attendance' },
    { key: 'schedule', label: 'برنامه‌ی کلاسی', icon: '🗓️', href: '/teacher/schedule' },
    { key: 'gradebook', label: 'دفترِ نمره', icon: '📔', href: '/teacher/gradebook' },

    { divider: 'آموزش و بازی' },
    { key: 'studio', label: 'استودیوی بازی', icon: '🎮', href: '/teacher/studio' },
    { key: 'missions', label: 'مأموریت‌های روزانه', icon: '🎯', href: '/teacher/missions' },
    { key: 'materials', label: 'مطالب و محتوا', icon: '📚', href: '/teacher/materials' },
    { key: 'smart', label: 'آزمون هوشمند 🧪', icon: '🧠', href: '/teacher/smart-exams', flag: 'smart' },

    { divider: 'امتیاز و انضباط' },
    { key: 'points', label: 'امتیازِ دانش‌آموزان', icon: '⚡', href: '/teacher/points' },
    { key: 'activities', label: 'امتیازدهیِ گروهی', icon: '🏅', href: '/teacher/activities' },
    { key: 'groups', label: 'امتیازِ تیم‌ها', icon: '🏆', href: '/teacher/groups' },
    { key: 'discipline', label: 'دفترِ انضباط', icon: '⭐', href: '/teacher/discipline' },

    { divider: 'تنظیمات و گزارش' },
    { key: 'levels', label: 'تنظیم مرحله‌ها', icon: '🎚️', href: '/teacher/levels' },
    { key: 'reports', label: 'گزارش‌ها', icon: '📈', href: '/teacher/reports' },

    { divider: 'ارتباط' },
    { key: 'messages', label: 'ارتباط با والدین/مدیر', icon: '💬', href: '/messages' },
    { key: 'notices', label: 'اعلان‌ها', icon: '📢', href: '/notices' },
];
