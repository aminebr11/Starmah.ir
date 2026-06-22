import { Head, Link, usePage, router } from '@inertiajs/react';
import { useState } from 'react';

/** منوی اصلی — مطابق ساختار سایت اصلی ستاره ماه. */
export const MENU = [
    { key: 'home', label: '🏠 خانه', href: '/' },
    { key: 'about', label: '📖 درباره کلاس', href: '/about' },
    { key: 'homework', label: '📝 تکالیف', href: '/homework' },
    { key: 'materials', label: '📚 مطالب درسی', href: '/materials' },
    { key: 'games', label: '🎮 آزمون و بازی', href: '/games' },
    { key: 'podcast', label: '🎧 پادکست', href: '/podcast' },
    { key: 'gallery', label: '🖼️ گالری', href: '/gallery' },
    { key: 'feedback', label: '💬 بازخورد', href: '/feedback' },
];

/** چیدمان وب ریسپانسیو: نوار ناوبری بالا + فوتر. دسکتاپ‌محور، ریسپانسیو به موبایل. */
export default function WebLayout({ title, active = '', children }) {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const user = auth?.user;

    return (
        <div dir="rtl">
            <Head title={title ? `${title} — ستاره ماه` : 'ستاره ماه'} />

            <header className="nav">
                <div className="container nav-inner">
                    <Link href="/" className="nav-logo">
                        <img src="/brand/logo-mark.svg" alt="ستاره ماه" />
                        <span>ستاره<span style={{ color: 'var(--gold-2)' }}>ماه</span></span>
                    </Link>

                    <nav className={`nav-menu ${open ? 'open' : ''}`}>
                        {MENU.map((m) => (
                            <Link key={m.key} href={m.href} className={active === m.key ? 'active' : ''} onClick={() => setOpen(false)}>
                                {m.label}
                            </Link>
                        ))}
                        {user?.roles?.includes?.('admin')}
                    </nav>

                    <div className="nav-cta">
                        {user ? (
                            <>
                                <Link href="/dashboard" className="btn btn-sm">داشبورد من</Link>
                                <button onClick={() => router.post(route('logout'))} className="btn btn-ghost btn-sm">خروج</button>
                            </>
                        ) : (
                            <>
                                <Link href={route('login')} className="btn btn-navy btn-sm">ورود</Link>
                                <Link href={route('register')} className="btn btn-sm">ثبت‌نام</Link>
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
                        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                            <img src="/brand/logo-mark.svg" width="46" height="46" alt="" />
                            <div>
                                <div style={{ fontWeight: 800, color: '#fff', fontSize: 17 }}>ستاره ماه</div>
                                <div style={{ fontSize: 13 }}>هر کودک، ستاره‌ای‌ست در مسیر کشف بی‌پایان</div>
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 18, flexWrap: 'wrap' }}>
                            {MENU.slice(0, 5).map((m) => <Link key={m.key} href={m.href}>{m.label}</Link>)}
                        </div>
                        <a href="https://instagram.com/starmah.ir" target="_blank" rel="noreferrer" className="btn btn-sm">📸 starmah.ir</a>
                    </div>
                    <div className="footer-bottom">© ستاره ماه — آموزش خلاقانه با خانم نجمه محمودی</div>
                </div>
            </footer>
        </div>
    );
}
