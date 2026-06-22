import { Head, Link, usePage } from '@inertiajs/react';
import { cssVars, ui } from '@/theme';

/** صفحه‌ی فرود برند ستاره ماه. */
export default function Welcome() {
    const { auth } = usePage().props;
    const loggedIn = !!auth?.user;

    return (
        <div dir="rtl" style={{ ...cssVars({}), ...ui.page, display: 'flex', flexDirection: 'column' }}>
            <Head title="ستاره ماه — آموزش خلاقانه برای آینده‌ای روشن" />

            <div style={{ position: 'fixed', inset: 0, pointerEvents: 'none', opacity: .5,
                background: 'radial-gradient(circle at 20% 20%, rgba(245,181,63,.15), transparent 35%), radial-gradient(circle at 80% 70%, rgba(74,144,217,.15), transparent 35%)' }} />

            <div style={{ ...ui.container, position: 'relative', flex: 1, display: 'flex', flexDirection: 'column' }}>
                <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', paddingTop: 8 }}>
                    <img src="/brand/logo-horizontal.svg" alt="ستاره ماه" style={{ height: 44 }} />
                    <Link href={loggedIn ? '/dashboard' : route('login')} style={{ ...ui.pill, textDecoration: 'none' }}>
                        {loggedIn ? 'داشبورد' : 'ورود'}
                    </Link>
                </header>

                <section style={{ textAlign: 'center', marginTop: 40 }}>
                    <img src="/brand/icon-512.png" width="150" height="150" alt="" style={{ filter: 'drop-shadow(0 16px 40px rgba(0,0,0,.5))' }} />
                    <h1 style={{ fontSize: 30, fontWeight: 800, marginTop: 18, lineHeight: 1.5 }}>
                        هر کودک، <span style={{ color: 'var(--p1)' }}>ستاره‌ای‌ست</span><br />در مسیر کشف بی‌پایان
                    </h1>
                    <p style={{ ...ui.muted, fontSize: 14, marginTop: 12, maxWidth: 360, marginInline: 'auto' }}>
                        پلتفرم آموزشی گیمیفای‌شده‌ای که هر درس را بر اساس علاقه‌ی کودک — فوتبال، ماشین و بیشتر — شخصی‌سازی می‌کند.
                    </p>

                    <div style={{ display: 'flex', gap: 10, justifyContent: 'center', marginTop: 24, flexWrap: 'wrap' }}>
                        <Link href={loggedIn ? '/dashboard' : route('login')} style={{ ...ui.btn, width: 'auto', padding: '14px 28px', textDecoration: 'none' }}>
                            {loggedIn ? 'ادامه' : 'شروع کن ✨'}
                        </Link>
                        <a href="https://instagram.com/starmah.ir" target="_blank" rel="noreferrer" style={{ ...ui.btn, ...ui.ghost, width: 'auto', padding: '14px 22px', textDecoration: 'none' }}>
                            📸 starmah.ir
                        </a>
                    </div>
                </section>

                <section style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 10, marginTop: 'auto', paddingTop: 40 }}>
                    {[['🎮', 'بازی و آزمون'], ['📈', 'پیشرفت و کارنامه'], ['💌', 'ارتباط با والدین']].map(([e, t]) => (
                        <div key={t} style={{ ...ui.card, textAlign: 'center', padding: 14 }}>
                            <div style={{ fontSize: 26 }}>{e}</div>
                            <div style={{ fontSize: 12, fontWeight: 700, marginTop: 6 }}>{t}</div>
                        </div>
                    ))}
                </section>

                <footer style={{ textAlign: 'center', ...ui.muted, marginTop: 24, paddingBottom: 10 }}>
                    🌟 ساخته شده با ❤️ — نجمه محمودی
                </footer>
            </div>
        </div>
    );
}
