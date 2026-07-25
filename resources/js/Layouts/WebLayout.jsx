import { Head, Link, usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

/** ناوبری عمومی محصول (صفحه‌ی فرود). لینک‌ها به بخش‌های همان صفحه + ورود/ثبت‌نام. */
export const NAV = [
    { key: 'features', label: 'امکانات', href: '/#features' },
    { key: 'worlds', label: 'دنیاها', href: '/#worlds' },
    { key: 'how', label: 'چطور کار می‌کند', href: '/#how' },
    { key: 'pricing', label: 'قیمت‌ها', href: '/pricing' },
    { key: 'about', label: 'درباره من', href: '/about' },
    { key: 'schools', label: 'برای مدارس', href: '/register/school' },
];

/** چیدمان وب عمومی: نوار ناوبری بالا (لوگوی برند) + فوتر. ریسپانسیو کامل.
 *  variant="cosmic" → هدر شیشه‌ای تیره برای صفحه‌ی اولِ سه‌بعدی. */
export default function WebLayout({ title, active = '', variant = '', children }) {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const user = auth?.user;
    const cosmic = variant === 'cosmic';

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);
        window.addEventListener('scroll', onScroll);
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    return (
        <div dir="rtl" className={cosmic ? 'web-cosmic' : ''}>
            <Head title={title ? `${title} — ستاره ماه` : 'ستاره ماه — آموزش هوشمند بر اساس علاقه'} />

            <header className={`nav ${cosmic ? 'nav-cosmic' : ''} ${scrolled ? 'scrolled' : ''}`}>
                <div className="container nav-inner">
                    <Link href="/" className="nav-logo">
                        <span className="emblem"><img src="/brand/logo-emblem.png" alt="ستاره ماه" /></span>
                        <span>ستاره<span style={{ color: 'var(--gold-2)' }}> ماه</span></span>
                    </Link>

                    <nav className={`nav-menu ${open ? 'open' : ''}`}>
                        {NAV.map((m) => (
                            <Link key={m.key} href={m.href} className={active === m.key ? 'active' : ''} onClick={() => setOpen(false)}>
                                {m.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="nav-cta">
                        {user ? (
                            <>
                                <Link href={route('profile.edit')} className="btn btn-ghost btn-sm">👤 پروفایل</Link>
                                <Link href="/dashboard" className="btn btn-sm">داشبورد من</Link>
                                <button onClick={() => router.post(route('logout'))} className="btn btn-ghost btn-sm">خروج</button>
                            </>
                        ) : (
                            <>
                                <Link href={route('login')} className="btn btn-ghost btn-sm">ورود</Link>
                                <Link href="/register" className="btn btn-sm">ثبت‌نام</Link>
                            </>
                        )}
                        <button className="hamburger" onClick={() => setOpen(!open)} aria-label="منو">☰</button>
                    </div>
                </div>
            </header>

            <main>{children}</main>

            <footer className="footer">
                <div className="container">
                    <div className="footer-grid">
                        <div style={{ display: 'flex', alignItems: 'center', gap: 12, maxWidth: 360 }}>
                            <img src="/brand/logo-emblem.png" width="50" height="50" alt="" style={{ borderRadius: 12 }} />
                            <div>
                                <div style={{ fontWeight: 800, color: '#fff', fontSize: 17 }}>ستاره ماه</div>
                                <div style={{ fontSize: 13 }}>پلتفرم آموزش هوشمند و شخصی‌سازی‌شده برای مدارس</div>
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 18, flexWrap: 'wrap' }}>
                            {NAV.map((m) => <Link key={m.key} href={m.href}>{m.label}</Link>)}
                            <Link href={route('login')}>ورود</Link>
                        </div>
                        <a href="https://instagram.com/starmah.ir" target="_blank" rel="noreferrer" className="btn btn-sm">📸 starmah.ir</a>
                    </div>
                    <div className="footer-bottom">© ستاره ماه — طراحی و توسعه توسط گروه طراحی ستاره ماه</div>
                </div>
            </footer>
        </div>
    );
}
