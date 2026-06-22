import { Link, usePage } from '@inertiajs/react';
import WebLayout, { MENU } from '@/Layouts/WebLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const FEATURES = [
    { ic: '📝', t: 'تکالیف روزانه', d: 'تکالیف و تمرین‌های هر روز کلاس', href: '/homework' },
    { ic: '📚', t: 'مطالب درسی', d: 'جزوه‌ها و فایل‌های آموزشی', href: '/materials' },
    { ic: '🎮', t: 'آزمون و بازی', d: 'یادگیری با بازی‌های جذاب و آزمون', href: '/games' },
    { ic: '🎧', t: 'پادکست', d: 'فایل‌های صوتی آموزشی', href: '/podcast' },
    { ic: '🖼️', t: 'گالری', d: 'تصاویر و لحظه‌های کلاس', href: '/gallery' },
    { ic: '🏆', t: 'امتیازات و ستاره‌ها', d: 'پیشرفت و رقابت دانش‌آموزان', href: '/leaderboard' },
];

export default function Welcome() {
    const { auth, weeklyTop = [], stats = {} } = usePage().props;
    const user = auth?.user;

    return (
        <WebLayout active="home">
            {/* HERO */}
            <div className="container">
                <section className="hero">
                    <div className="stars" aria-hidden="true">
                        <span style={{ top: 20, insetInlineEnd: 60, fontSize: 34 }}>🌟</span>
                        <span style={{ bottom: 26, insetInlineStart: 70, fontSize: 28, animationDelay: '1s' }}>✨</span>
                        <span style={{ top: 40, insetInlineStart: 150, fontSize: 24, animationDelay: '2s' }}>🌙</span>
                        <span style={{ top: '60%', insetInlineEnd: 24, fontSize: 22, animationDelay: '3s' }}>⭐</span>
                    </div>
                    <div className="hero-grid">
                        <div>
                            <span className="eyebrow" style={{ background: 'rgba(245,181,63,.18)', color: 'var(--gold-light)' }}>کلاس چهارم — خانم نجمه محمودی</span>
                            <h1>کلاس <span className="g">ستاره‌های درخشان</span> ✨</h1>
                            <p>پر از شگفتی و یادگیری! 🚀 هر کودک، ستاره‌ای‌ست در مسیر کشف بی‌پایان — با بازی، آزمون، امتیاز و دنیای دلخواه خودش.</p>
                            <div className="hero-cta">
                                {user ? (
                                    <Link href="/dashboard" className="btn">📊 ورود به داشبورد من</Link>
                                ) : (
                                    <>
                                        <Link href={route('register')} className="btn">🎓 ثبت‌نام دانش‌آموز</Link>
                                        <Link href={route('login')} className="btn btn-ghost">🔑 ورود به دنیای یادگیری</Link>
                                    </>
                                )}
                            </div>
                        </div>
                        <div className="hero-art">
                            <img src="/brand/logo-illustration.png" alt="ستاره ماه" />
                        </div>
                    </div>
                </section>
            </div>

            {/* STATS */}
            <div className="container" style={{ marginTop: 28 }}>
                <div className="stats">
                    <div className="stat"><b>{fa(stats.students ?? 0)}</b><span>دانش‌آموز</span></div>
                    <div className="stat"><b>{fa(stats.classrooms ?? 0)}</b><span>کلاس</span></div>
                    <div className="stat"><b>{fa(stats.activities ?? 0)}</b><span>تمرین و آزمون</span></div>
                    <div className="stat"><b>۲</b><span>دنیای علاقه 🎮</span></div>
                </div>
            </div>

            {/* FEATURES */}
            <section className="section">
                <div className="container">
                    <div className="section-head">
                        <span className="eyebrow">امکانات</span>
                        <h2>هر چیزی که کلاس نیاز دارد</h2>
                        <p>همه‌ی ابزارهای آموزش، بازی و ارتباط در یک‌جا</p>
                    </div>
                    <div className="grid grid-3">
                        {FEATURES.map((f) => (
                            <Link key={f.t} href={f.href} className="card feature">
                                <div className="ic">{f.ic}</div>
                                <h3>{f.t}</h3>
                                <p>{f.d}</p>
                            </Link>
                        ))}
                    </div>
                </div>
            </section>

            {/* WEEKLY TOP */}
            <section className="section" style={{ background: '#fff', borderBlock: '1px solid var(--line)' }}>
                <div className="container">
                    <div className="section-head">
                        <span className="eyebrow">🏆 افتخارات</span>
                        <h2>ستاره‌های درخشان این هفته</h2>
                        <p>دانش‌آموزانی که بیشترین تلاش را داشتند</p>
                    </div>
                    <div style={{ maxWidth: 560, margin: '0 auto' }}>
                        <div className="lb-card">
                            {weeklyTop.length ? weeklyTop.map((s) => (
                                <div key={s.rank} className="lb-row">
                                    <div className={`lb-rank ${s.rank === 1 ? 'g1' : s.rank === 2 ? 'g2' : s.rank === 3 ? 'g3' : ''}`}>{fa(s.rank)}</div>
                                    <div style={{ fontWeight: 700 }}>{s.name}</div>
                                    <div className="lb-xp">⭐ {fa(s.xp)}</div>
                                </div>
                            )) : <div className="lb-row" style={{ color: 'var(--muted)' }}>هنوز امتیازی ثبت نشده</div>}
                        </div>
                    </div>
                </div>
            </section>

            {/* CTA */}
            <section className="section">
                <div className="container">
                    <div className="card" style={{ textAlign: 'center', background: 'radial-gradient(circle at 80% 20%,var(--navy-700),var(--navy-900))', color: '#fff', border: 0 }}>
                        <h2 style={{ color: '#fff', fontSize: 26, marginBottom: 10 }}>آماده‌ای ستاره‌ی کلاس شوی؟ 🌟</h2>
                        <p style={{ color: '#cdd8ef', marginBottom: 20 }}>دنیای دلخواهت را انتخاب کن و یادگیری را شروع کن.</p>
                        <Link href={user ? '/dashboard' : route('login')} className="btn">{user ? 'ادامه بده' : 'شروع کن'}</Link>
                    </div>
                </div>
            </section>
        </WebLayout>
    );
}
