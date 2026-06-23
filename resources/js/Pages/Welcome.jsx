import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import WebLayout from '@/Layouts/WebLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/* اسکرول‌ریویل ساده با IntersectionObserver */
function useReveal() {
    useEffect(() => {
        const els = document.querySelectorAll('.reveal');
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => e.isIntersecting && e.target.classList.add('in'));
        }, { threshold: 0.12 });
        els.forEach((el) => io.observe(el));
        // ایمنی: اگر به هر دلیلی observer اجرا نشد، بعد از کمی همه را نمایش بده تا هرگز خالی نماند
        const fallback = setTimeout(() => els.forEach((el) => el.classList.add('in')), 1500);
        return () => { io.disconnect(); clearTimeout(fallback); };
    }, []);
}

/* شمارنده‌ی انیمیشنی */
function Counter({ to = 0, suffix = '' }) {
    const [n, setN] = useState(0);
    const ref = useRef(null);
    useEffect(() => {
        let done = false;
        const io = new IntersectionObserver((es) => {
            if (es[0].isIntersecting && !done) {
                done = true;
                const dur = 1100, t0 = performance.now();
                const tick = (t) => {
                    const p = Math.min((t - t0) / dur, 1);
                    setN(Math.round(to * (1 - Math.pow(1 - p, 3))));
                    if (p < 1) requestAnimationFrame(tick);
                };
                requestAnimationFrame(tick);
            }
        }, { threshold: 0.5 });
        if (ref.current) io.observe(ref.current);
        return () => io.disconnect();
    }, [to]);
    return <b ref={ref}>{fa(n)}{suffix}</b>;
}

const SUBJECTS = ['🧮 ریاضی', '📖 فارسی', '🔬 علوم', '🌍 اجتماعی', '✍️ املا', '🎨 هنر', '🧠 هوش', '📐 هندسه'];

const BENTO = [
    { ic: '🎮', cls: 'ic-gold', t: 'آزمون و بازی', d: 'یادگیری با بازی‌های جذاب', href: '/games', wide: true },
    { ic: '🏆', cls: 'ic-pink', t: 'امتیاز و ستاره', d: 'رقابت و پیشرفت', href: '/leaderboard' },
    { ic: '📝', cls: 'ic-blue', t: 'تکالیف روزانه', d: 'تمرین هر روز', href: '/homework' },
    { ic: '📚', cls: 'ic-teal', t: 'مطالب درسی', d: 'جزوه و فایل آموزشی', href: '/materials' },
    { ic: '🎧', cls: 'ic-purple', t: 'پادکست', d: 'فایل‌های صوتی', href: '/podcast' },
    { ic: '🖼️', cls: 'ic-gold', t: 'گالری', d: 'لحظه‌های کلاس', href: '/gallery' },
];

export default function Welcome() {
    const { auth, weeklyTop = [], stats = {} } = usePage().props;
    const user = auth?.user;
    useReveal();

    return (
        <WebLayout active="home">
            {/* ===== HERO ===== */}
            <div className="container">
                <section className="hero">
                    <div className="aurora a1" /><div className="aurora a2" /><div className="aurora a3" />
                    <div className="stars-bg" />
                    <span className="float" style={{ top: 28, insetInlineStart: '14%', fontSize: 26, animationDelay: '-1s' }}>✨</span>
                    <span className="float" style={{ bottom: 40, insetInlineEnd: '12%', fontSize: 22, animationDelay: '-3s' }}>⭐</span>
                    <span className="float" style={{ top: '52%', insetInlineStart: '6%', fontSize: 20, animationDelay: '-5s' }}>💫</span>

                    <div className="hero-grid">
                        <div className="reveal in">
                            <span className="hero-eyebrow"><span className="dot" /> پلتفرم آموزش هوشمند مدارس · با هوش مصنوعی 🤖</span>
                            <h1>کلاست را با <span className="gradient-text">ستاره ماه</span> و هوش مصنوعی جذاب‌تر کن <span className="moon">🌙</span></h1>
                            <p className="lead">
                                <b style={{ color: '#fff' }}>دنیای خودت را بساز و از آن استفاده کن.</b> هر دانش‌آموز بر اساس علاقه‌اش
                                (فوتبال، ماشین و…) درس می‌خواند، بازی می‌کند و رشد می‌کند — یادگیری شخصی‌سازی‌شده برای
                                مدارس، معلم‌ها و دانش‌آموزان.
                            </p>
                            <div className="hero-cta">
                                {user ? (
                                    <Link href="/dashboard" className="btn btn-lg">📊 ورود به داشبورد من</Link>
                                ) : (
                                    <>
                                        <Link href="/register/school" className="btn btn-lg">🏫 ثبت‌نام مدرسه</Link>
                                        <Link href={route('login')} className="btn btn-glass btn-lg">🔑 ورود</Link>
                                    </>
                                )}
                            </div>
                            {!user && (
                                <div style={{ marginTop: 14, color: '#9fb1d8', fontSize: 13 }}>
                                    دانش‌آموز هستی؟ <Link href="/register/student" style={{ color: 'var(--gold-light)', fontWeight: 700 }}>از اینجا ثبت‌نام کن ←</Link>
                                </div>
                            )}
                            <div className="hero-trust">
                                <div className="avatars"><span>🦁</span><span>🐯</span><span>🦊</span><span>🐼</span></div>
                                <span>+{fa(stats.students ?? 0)} دانش‌آموز در حال یادگیری و رقابت</span>
                            </div>
                        </div>

                        <div className="hero-art">
                            <div className="glow" />
                            <img src="/brand/logo-illustration.png" alt="ستاره ماه" />
                            <div className="chip c1">⭐ <span>امتیاز امروز: <b>۱۲۴۰</b></span></div>
                            <div className="chip c2">🏆 <span>رتبه‌ی کلاس: <b>۲</b></span></div>
                            <div className="chip c3">🔥 <span>زنجیره: <b>۷ روز</b></span></div>
                        </div>
                    </div>
                </section>

                {/* subjects marquee */}
                <div className="marquee">
                    <div className="marquee-track">
                        {[...SUBJECTS, ...SUBJECTS].map((s, i) => <span className="subj" key={i}>{s}</span>)}
                    </div>
                </div>
            </div>

            {/* ===== THEMED WORLDS (USP) ===== */}
            <section className="section" id="worlds">
                <div className="container">
                    <div className="section-head reveal">
                        <span className="eyebrow">✨ منحصر‌به‌فرد</span>
                        <h2>هر کودک، دنیای خودش را انتخاب می‌کند</h2>
                        <p>درس‌ها، جایزه‌ها و حتی ظاهر برنامه بر اساس علاقه‌ی دانش‌آموز شکل می‌گیرد</p>
                    </div>
                    <div className="worlds reveal">
                        <div className="world football">
                            <span className="em">⚽</span>
                            <h3>دنیای فوتبال</h3>
                            <p>درس‌ها در قالب لیگ، گل و قهرمانی</p>
                            <div className="tags"><span>🥅 گل</span><span>🏆 لیگ برتر</span><span>🔥 قهرمانی</span></div>
                        </div>
                        <div className="world cars">
                            <span className="em">🏎️</span>
                            <h3>دنیای ماشین و مسابقه</h3>
                            <p>یادگیری در قالب گرنپری و نیترو</p>
                            <div className="tags"><span>⚡ نیترو</span><span>🏁 گرنپری</span><span>🏆 سکوی قهرمانی</span></div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ===== BENTO FEATURES ===== */}
            <section className="section" id="features" style={{ background: '#fff', borderBlock: '1px solid var(--line)' }}>
                <div className="container">
                    <div className="section-head reveal">
                        <span className="eyebrow">امکانات</span>
                        <h2>هر چیزی که کلاس نیاز دارد</h2>
                        <p>همه‌ی ابزارهای آموزش، بازی و ارتباط در یک‌جا</p>
                    </div>
                    <div className="bento reveal">
                        {BENTO.map((b) => (
                            <Link key={b.t} href={b.href} className={`b ${b.wide ? 'wide' : ''}`}>
                                <div className={`ic ${b.cls}`}>{b.ic}</div>
                                <h3>{b.t}</h3><p>{b.d}</p>
                            </Link>
                        ))}
                        <div className="b feature-dark">
                            <div className="ic ic-gold">💌</div>
                            <h3>ارتباط با والدین</h3><p>گفت‌وگوی دوسویه‌ی خانه و مدرسه</p>
                        </div>
                    </div>
                </div>
            </section>

            {/* ===== STATS ===== */}
            <section className="section">
                <div className="container">
                    <div className="stats reveal">
                        <div className="stat"><Counter to={stats.students ?? 0} /><span>دانش‌آموز فعال</span></div>
                        <div className="stat"><Counter to={stats.activities ?? 0} /><span>تمرین و آزمون</span></div>
                        <div className="stat"><Counter to={stats.classrooms ?? 0} /><span>کلاس</span></div>
                        <div className="stat"><Counter to={2} /><span>دنیای علاقه 🎮</span></div>
                    </div>
                </div>
            </section>

            {/* ===== WEEKLY STARS ===== */}
            <section className="section" style={{ background: '#fff', borderBlock: '1px solid var(--line)' }}>
                <div className="container">
                    <div className="section-head reveal">
                        <span className="eyebrow">🏆 افتخارات</span>
                        <h2>ستاره‌های درخشان این هفته</h2>
                        <p>دانش‌آموزانی که بیشترین تلاش را داشتند</p>
                    </div>
                    <div className="reveal" style={{ maxWidth: 580, margin: '0 auto' }}>
                        <div className="lb-card">
                            {weeklyTop.length ? weeklyTop.map((s) => (
                                <div key={s.rank} className="lb-row">
                                    <div className={`lb-rank ${s.rank === 1 ? 'g1' : s.rank === 2 ? 'g2' : s.rank === 3 ? 'g3' : ''}`}>{fa(s.rank)}</div>
                                    <div style={{ fontWeight: 700 }}>{s.rank === 1 && '👑 '}{s.name}</div>
                                    <div className="lb-xp">⭐ {fa(s.xp)}</div>
                                </div>
                            )) : <div className="lb-row" style={{ color: 'var(--muted)' }}>هنوز امتیازی ثبت نشده</div>}
                        </div>
                    </div>
                </div>
            </section>

            {/* ===== HOW IT WORKS ===== */}
            <section className="section" id="how">
                <div className="container">
                    <div className="section-head reveal">
                        <span className="eyebrow">چطور کار می‌کند؟</span>
                        <h2>راه‌اندازی برای مدرسه، تنها در چند قدم</h2>
                        <p>از ثبت‌نام مدرسه تا ورود دانش‌آموز به دنیای دلخواهش</p>
                    </div>
                    <div className="steps reveal">
                        <div className="step"><div className="num">۱</div><h3>ثبت‌نام مدرسه</h3><p>مدیر مدرسه درخواست می‌دهد و پس از تأیید، حساب مدرسه ساخته می‌شود</p></div>
                        <div className="step"><div className="num">۲</div><h3>ساخت معلم‌ها و کلاس‌ها</h3><p>مدیر برای هر کلاس یک معلم و کد کلاس ایجاد می‌کند</p></div>
                        <div className="step"><div className="num">۳</div><h3>ورود دانش‌آموز</h3><p>دانش‌آموز مدرسه، معلم و دنیای دلخواهش را انتخاب می‌کند و شروع می‌کند</p></div>
                    </div>
                </div>
            </section>

            {/* ===== FINAL CTA ===== */}
            <section className="section" style={{ paddingTop: 0 }}>
                <div className="container">
                    <div className="cta-band reveal">
                        <div className="stars-bg" />
                        <h2>آماده‌ای ستاره‌ی کلاس شوی؟ 🌟</h2>
                        <p>همین حالا شروع کن و دنیای یادگیری خودت را بساز.</p>
                        <Link href={user ? '/dashboard' : route('login')} className="btn btn-lg">{user ? 'ادامه بده' : 'شروع کن'}</Link>
                    </div>
                </div>
            </section>
        </WebLayout>
    );
}
