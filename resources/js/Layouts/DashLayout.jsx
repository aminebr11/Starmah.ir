import { Head, Link, usePage, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import AssistantWidget from '@/Components/AssistantWidget';
import BellMenu from '@/Components/BellMenu';
import Avatar from '@/Components/Avatar';
import Icon, { MENU_ICON } from '@/Components/Icon';
import UiSwitch from '@/Components/UiSwitch';

/**
 * چیدمان داشبورد مدیریتی (سوپرادمین / مدیر مدرسه / معلم) با سایدبار.
 * هماهنگ با تن صفحه‌ی اصلی (سرمه‌ای/طلایی). ریسپانسیو.
 *
 * props: title, roleLabel, menu:[{key,label,icon,href}], active, children
 */
export default function DashLayout({ title, roleLabel, menu = [], active = '', children, actions = null }) {
    const { auth, unreadNotices = 0, smartLab = false, avatarUrl = null, school = null, ui = 'classic' } = usePage().props;
    const [open, setOpen] = useState(false);
    // آیتم‌هایی که flag: 'smart' دارند فقط وقتی ماژول فعال است نمایش داده می‌شوند
    menu = menu.filter((m) => !m.flag || (m.flag === 'smart' && smartLab));

    if (ui === 'clay') {
        return <ClayDash {...{ title, roleLabel, menu, active, children, actions }} />;
    }

    return (
        <div dir="rtl" className={`dash ${open ? 'drawer-open' : ''}`}>
            <Head title={title ? `${title} — ستاره ماه` : 'ستاره ماه'} />

            <aside className={`dash-side ${open ? 'open' : ''}`}>
                <Link href="/" className="dash-brand">
                    <img src="/brand/logo-mark-240.webp" alt="" />
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
                {/* نوارِ بالا در دو ردیف: ردیفِ اول همیشه (منو، عنوان، زنگوله،
                    کاربر) و ردیفِ دوم دکمه‌های همان صفحه. روی موبایل ردیفِ
                    دوم افقی اسکرول می‌شود تا دکمه‌ها دوخطی و درهم نشوند. */}
                <div className="dash-topbar">
                    <div className="dash-bar-main">
                        <button className="dash-mobilebtn" onClick={() => setOpen(true)} aria-label="منو">☰</button>
                        <h1>{title}</h1>
                        <div className="dash-bar-tools">
                            <UiSwitch compact />
                            <BellMenu tone="light" />
                            <div className="user-chip">
                                <Link href={route('profile.edit')} className="user-chip-name" title="پروفایل من">
                                    <Avatar src={avatarUrl} name={auth?.user?.name} size={26} /><span className="nm">{auth?.user?.name}</span>
                                </Link>
                                <button onClick={() => router.post(route('logout'))} className="user-chip-out" title="خروج از حساب">🚪</button>
                            </div>
                        </div>
                    </div>
                    {actions && <div className="dash-actions">{actions}</div>}
                </div>
                {children}
            </main>
            <AssistantWidget />
        </div>
    );
}

/** منو را به گروه‌ها (همان «جداکننده‌ها») تقسیم می‌کند. */
function groupMenu(menu) {
    const groups = [];
    let cur = { label: 'پیشخوان', items: [] };
    menu.forEach((m) => {
        if (m.divider !== undefined) {
            if (cur.items.length) groups.push(cur);
            cur = { label: m.divider || '', items: [] };
        } else {
            cur.items.push(m);
        }
    });
    if (cur.items.length) groups.push(cur);
    return groups;
}

// مقصدهای نوارِ پایینِ موبایل، به ترتیبِ اولویت (هر نقش هرکدام را داشت)
const BOTTOM_PRIORITY = ['home', 'class', 'students', 'teachers', 'schools', 'studio', 'missions', 'messages', 'notices', 'reports'];
const BOTTOM_LABEL = { gameworld: 'بازی‌ها', smart: 'آزمون', board: 'رقابت', practice: 'مأموریت', home: 'خانه', class: 'کلاس', students: 'دانش‌آموزان', teachers: 'معلم‌ها', schools: 'مدارس', studio: 'بازی‌ها', missions: 'مأموریت', messages: 'پیام‌ها', notices: 'اعلان‌ها', reports: 'گزارش' };

/**
 * چیدمانِ «خمیرماه»: به‌جای ستونِ کناری، گروه‌های منو تب‌های بزرگِ بالا
 * هستند و صفحه‌های همان گروه برچسب‌های ردیفِ دوم. روی موبایل نوارِ پایینِ
 * خمیری با دکمه‌ی «همه» که کلِ منو را در یک ورقه باز می‌کند.
 *
 * برای دانش‌آموز هم همین چیدمان به کار می‌رود (kids)، با رنگِ دنیا و تیمِ
 * خودش در زمینه‌ی صفحه. دکمه‌ی خروج روی هر اندازه‌ای همیشه دیده می‌شود.
 */
export function ClayDash({
    title, roleLabel, menu, active, children, actions,
    kids = false, kidsStyle = null, bottomKeys = null, badges = {}, ribbon = null, locked = false,
}) {
    const { auth, unreadNotices = 0, avatarUrl = null, school = null } = usePage().props;
    const [sheet, setSheet] = useState(false);
    const groups = groupMenu(menu);
    const current = groups.find((g) => g.items.some((m) => m.key === active)) || groups[0];
    const icon = (m) => MENU_ICON[m.key] || 'star';
    const count = (m) => (m.key === 'notices' ? unreadNotices : (badges[m.key] || 0));
    const badge = (m) => (count(m) > 0 ? <span className="cd-badge">{count(m)}</span> : null);
    const flat = groups.flatMap((g) => g.items);
    const bottom = (bottomKeys || BOTTOM_PRIORITY).map((k) => flat.find((m) => m.key === k)).filter(Boolean).slice(0, 4);
    const logout = () => router.post(route('logout'));
    const subsRef = useRef(null);
    // روی موبایل ردیفِ برچسب‌ها افقی اسکرول می‌شود؛ برچسبِ صفحه‌ی فعلی دیده شود
    useEffect(() => {
        const on = subsRef.current?.querySelector('a.on');
        if (on && subsRef.current.scrollWidth > subsRef.current.clientWidth) {
            on.scrollIntoView({ inline: 'center', block: 'nearest' });
        }
    }, [active]);
    // ورقه‌ی باز با دکمه‌ی Esc بسته شود و صفحه‌ی پشتش اسکرول نخورد
    useEffect(() => {
        if (!sheet) return undefined;
        const onKey = (e) => { if (e.key === 'Escape') setSheet(false); };
        window.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => { window.removeEventListener('keydown', onKey); document.body.style.overflow = ''; };
    }, [sheet]);
    const label = (m) => BOTTOM_LABEL[m.key] || m.label;
    const navOff = locked ? { style: { pointerEvents: 'none', opacity: 0.55 } } : {};

    return (
        <div dir="rtl" className={`cd ${kids ? 'cd-kids' : ''}`} style={kidsStyle || undefined}>
            <Head title={title ? `${title} — ستاره ماه` : 'ستاره ماه'} />
            {ribbon}

            <header className="cd-bar">
                <Link href="/" className="cd-brand">
                    <img src="/brand/logo-mark-120.webp" alt="" width="120" height="120" />
                    <span className="cd-brand-t">ستاره <em>ماه</em></span>
                    {roleLabel && <span className="cd-role">{roleLabel}</span>}
                </Link>
                <nav className="cd-tabs" aria-label="بخش‌ها" {...navOff}>
                    {groups.map((g) => (
                        <Link key={g.label || g.items[0].key} href={g.items[0].href} className={g === current ? 'on' : ''}>
                            <Icon name={icon(g.items[0])} />{g.label || g.items[0].label}
                        </Link>
                    ))}
                </nav>
                <div className="cd-tools">
                    <UiSwitch compact className="cd-hide-m" />
                    <BellMenu tone="light" />
                    <Link href={route('profile.edit')} className="cd-me" title="پروفایل من">
                        <Avatar src={avatarUrl} name={auth?.user?.name} size={34} />
                        <span className="cd-me-nm">{auth?.user?.name}</span>
                    </Link>
                    <button type="button" onClick={logout} className="cd-out" title="خروج از حساب" aria-label="خروج از حساب">
                        <Icon name="logout" /><span className="cd-out-t">خروج</span>
                    </button>
                </div>
            </header>

            {current && current.items.length > 1 && (
                <nav className="cd-subs" ref={subsRef} aria-label={current.label} {...navOff}>
                    {current.items.map((m) => (
                        <Link key={m.key} href={m.href} className={active === m.key ? 'on' : ''}>
                            <Icon name={icon(m)} size={17} />{m.label}{badge(m)}
                        </Link>
                    ))}
                    {school && !kids && <span className="cd-school">{school.logo_url ? <img src={school.logo_url} alt="" /> : '🏫'} {school.name}</span>}
                </nav>
            )}

            <main className={`cd-main ${kids ? 'kids' : ''}`}>
                <div className="cd-head">
                    <h1>{title}</h1>
                    {actions && <div className="cd-actions">{actions}</div>}
                </div>
                {children}
            </main>

            <nav className="cd-bnav" aria-label="ناوبری" {...navOff}>
                {bottom.slice(0, 2).map((m) => (
                    <Link key={m.key} href={m.href} className={active === m.key ? 'on' : ''}><Icon name={icon(m)} size={22} />{label(m)}{badge(m)}</Link>
                ))}
                <button type="button" className="cd-bnav-all" onClick={() => setSheet(true)} aria-label="همه‌ی بخش‌ها"><Icon name="grid" size={26} /></button>
                {bottom.slice(2, 4).map((m) => (
                    <Link key={m.key} href={m.href} className={active === m.key ? 'on' : ''}><Icon name={icon(m)} size={22} />{label(m)}{badge(m)}</Link>
                ))}
            </nav>

            {sheet && (
                <div className="cd-sheet-bg" onClick={() => setSheet(false)}>
                    <div className="cd-sheet" role="dialog" aria-modal="true" aria-label="همه‌ی بخش‌ها" onClick={(e) => e.stopPropagation()}>
                        <div className="cd-sheet-h">
                            <b>همه‌ی بخش‌ها</b>
                            <button type="button" onClick={() => setSheet(false)} aria-label="بستن"><Icon name="x" /></button>
                        </div>
                        {school && <div className="cd-school m">{school.logo_url ? <img src={school.logo_url} alt="" /> : '🏫'} {school.name}</div>}
                        {groups.map((g) => (
                            <div key={g.label || g.items[0].key} className="cd-sheet-g">
                                {g.label && <div className="cd-sheet-gl">{g.label}</div>}
                                <div className="cd-sheet-grid">
                                    {g.items.map((m) => (
                                        <Link key={m.key} href={m.href} className={active === m.key ? 'on' : ''} onClick={() => setSheet(false)}>
                                            <Icon name={icon(m)} size={22} /><span>{m.label}</span>{badge(m)}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        ))}
                        <div className="cd-sheet-foot">
                            <Link href={route('profile.edit')} className="btn btn-ghost btn-sm"><Icon name="user" size={17} /> پروفایل من</Link>
                            <UiSwitch />
                            <button type="button" onClick={logout} className="btn btn-danger btn-sm"><Icon name="logout" size={17} /> خروج از حساب</button>
                        </div>
                    </div>
                </div>
            )}

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
    { key: 'sms', label: 'سامانه‌ی پیامک', icon: '📩', href: '/admin/sms' },
    { key: 'integrations', label: 'درگاه‌ها (پیامک/پرداخت)', icon: '🔌', href: '/admin/integrations' },
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
    { key: 'familynotes', label: 'پیامِ محرمانه به والدین', icon: '🔐', href: '/family-notes' },
    { key: 'announcements', label: 'اطلاعیه‌ها', icon: '📢', href: '/school/announcements' },
    { key: 'messages', label: 'ارتباط با والدین/معلم', icon: '💬', href: '/messages' },
    { key: 'sms', label: 'سامانه‌ی پیامک', icon: '📩', href: '/school/sms' },
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
    { key: 'mybank', label: 'بانکِ سؤالاتِ من', icon: '🗄️', href: '/teacher/my-bank' },
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
    { key: 'familynotes', label: 'پیامِ محرمانه به والدین', icon: '🔐', href: '/family-notes' },
    { key: 'messages', label: 'ارتباط با والدین/مدیر', icon: '💬', href: '/messages' },
    { key: 'sms', label: 'پیامک به اولیا', icon: '📩', href: '/teacher/sms' },
    { key: 'notices', label: 'اعلان‌ها', icon: '📢', href: '/notices' },
];
