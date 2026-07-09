import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import WebLayout from '@/Layouts/WebLayout';
import CosmicScene from '@/Components/CosmicScene';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/* اسکرول‌ریویل با IntersectionObserver */
function useReveal() {
    useEffect(() => {
        const els = document.querySelectorAll('.sm-reveal');
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => e.isIntersecting && e.target.classList.add('in'));
        }, { threshold: 0.12 });
        els.forEach((el) => io.observe(el));
        const fallback = setTimeout(() => els.forEach((el) => el.classList.add('in')), 1600);
        return () => { io.disconnect(); clearTimeout(fallback); };
    }, []);
}

/* تیلت سه‌بعدیِ صحنه‌ی شخصیت با حرکت ماوس (فقط دسکتاپ) */
function useTilt() {
    const ref = useRef(null);
    useEffect(() => {
        const el = ref.current;
        if (!el || window.matchMedia('(hover: none)').matches) return;
        let raf = 0;
        const onMove = (e) => {
            const r = el.getBoundingClientRect();
            const px = (e.clientX - r.left) / r.width - 0.5;
            const py = (e.clientY - r.top) / r.height - 0.5;
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => {
                el.style.setProperty('--rx', `${px * 14}deg`);
                el.style.setProperty('--ry', `${-py * 12}deg`);
            });
        };
        const reset = () => {
            el.style.setProperty('--rx', '0deg');
            el.style.setProperty('--ry', '0deg');
        };
        el.addEventListener('pointermove', onMove);
        el.addEventListener('pointerleave', reset);
        return () => { el.removeEventListener('pointermove', onMove); el.removeEventListener('pointerleave', reset); cancelAnimationFrame(raf); };
    }, []);
    return ref;
}

/* تیلت سبک برای کارت‌ها (worlds / bento) */
function tiltHandlers(strength = 10) {
    const onMove = (e) => {
        const el = e.currentTarget;
        const r = el.getBoundingClientRect();
        const px = (e.clientX - r.left) / r.width - 0.5;
        const py = (e.clientY - r.top) / r.height - 0.5;
        el.style.transform = `perspective(700px) rotateY(${px * strength}deg) rotateX(${-py * strength}deg) translateY(-4px)`;
    };
    const onLeave = (e) => { e.currentTarget.style.transform = ''; };
    return { onPointerMove: onMove, onPointerLeave: onLeave };
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
                const dur = 1200, t0 = performance.now();
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

const SUBJECTS = ['🧮 ریاضی', '📖 فارسی', '🔬 علوم', '🌍 اجتماعی', '✍️ املا', '🎨 هنر', '🧠 هوش', '📐 هندسه', '🚀 کاوش', '🏆 مسابقه'];

const BENTO = [
    { ic: '🎮', cls: 'ic-gold', t: 'آزمون و بازی', d: 'یادگیری با بازی‌های جذاب و هیجان‌انگیز', href: '/games', wide: true },
    { ic: '🏆', cls: 'ic-pink', t: 'امتیاز و ستاره', d: 'رقابت و پیشرفت روزانه', href: '/leaderboard' },
    { ic: '📝', cls: 'ic-blue', t: 'تکالیف روزانه', d: 'تمرین هر روز', href: '/homework' },
    { ic: '📚', cls: 'ic-teal', t: 'مطالب درسی', d: 'جزوه و فایل آموزشی', href: '/materials' },
    { ic: '🎧', cls: 'ic-purple', t: 'پادکست', d: 'فایل‌های صوتی آموزشی', href: '/podcast' },
    { ic: '🖼️', cls: 'ic-gold', t: 'گالری', d: 'لحظه‌های کلاس', href: '/gallery' },
];

export default function Welcome() {
    const { auth, weeklyTop = [], stats = {} } = usePage().props;
    const user = auth?.user;
    useReveal();
    const stageRef = useTilt();

    return (
        <WebLayout active="home" variant="cosmic">
            <div className="sm">
                <div className="sm-stars" />

                {/* ===== HERO ===== */}
                <header className="sm-hero">
                    <CosmicScene />
                    <div className="sm-aurora g" /><div className="sm-aurora b" /><div className="sm-aurora p" />

                    <div className="sm-wrap sm-hero-grid">
                        <div className="sm-reveal in">
                            <span className="sm-eyebrow"><span className="d" /> پلتفرم آموزش هوشمند مدارس · با هوش مصنوعی 🤖</span>
                            <h1 className="sm-h1">
                                کلاست را با <span className="sm-grad">ستاره ماه</span> و هوش مصنوعی جذاب‌تر کن
                                <span className="sm-moon"> 🌙</span>
                            </h1>
                            <p className="sm-lead">
                                <b>دنیای خودت را بساز و در آن یاد بگیر.</b> هر دانش‌آموز بر اساس علاقه‌اش
                                (فوتبال، ماشین و…) درس می‌خواند، بازی می‌کند و مثل یک ستاره رشد می‌کند —
                                یادگیری شخصی‌سازی‌شده برای مدارس، معلم‌ها و بچه‌ها.
                            </p>
                            <div className="sm-cta">
                                {user ? (
                                    <Link href="/dashboard" className="sm-btn">📊 ورود به داشبورد من</Link>
                                ) : (
                                    <>
                                        <Link href="/register/student" className="sm-btn">🎓 ثبت‌نام دانش‌آموز</Link>
                                        <Link href="/register/school" className="sm-btn ghost">🏫 ثبت‌نام مدرسه</Link>
                                    </>
                                )}
                            </div>
                            {!user && (
                                <div className="sm-hint">
                                    <span>👦👧 دانش‌آموزی؟ روی دکمه‌ی طلایی «ثبت‌نام دانش‌آموز» بزن.</span>
                                    <span style={{ opacity: .5 }}>|</span>
                                    <Link href={route('login')}>قبلاً ثبت‌نام کرده‌ای؟ ورود ←</Link>
                                </div>
                            )}
                            <div className="sm-trust">
                                <div className="sm-ava"><span>🦁</span><span>🐯</span><span>🦊</span><span>🐼</span></div>
                                <span>+{fa(stats.students ?? 0)} دانش‌آموز در حال یادگیری و رقابت</span>
                            </div>
                        </div>

                        {/* صحنه‌ی سه‌بعدیِ شخصیت */}
                        <div className="sm-stage" ref={stageRef}>
                            <div className="sm-orbit-ring r2" />
                            <div className="sm-orbit-ring" />
                            <div className="sm-halo" />
                            <div className="sm-planet p1"><span className="ring" /></div>
                            <div className="sm-planet p2" />
                            <span className="sm-rocket">🚀</span>
                            <span className="sm-spark" style={{ top: '8%', insetInlineStart: '10%', fontSize: 24, transform: 'translateZ(100px)' }}>✨</span>
                            <span className="sm-spark" style={{ bottom: '14%', insetInlineEnd: '8%', fontSize: 20, transform: 'translateZ(95px)', animationDelay: '-2s' }}>⭐</span>

                            <img className="sm-hero-img" src="/brand/logo-illustration.png" alt="ستاره ماه — کودک در مسیر کشف" />
                        </div>
                    </div>
                </header>

                {/* subjects marquee */}
                <div className="sm-wrap">
                    <div className="sm-marquee">
                        <div className="sm-marquee-track">
                            {[...SUBJECTS, ...SUBJECTS].map((s, i) => <span className="sm-subj" key={i}>{s}</span>)}
                        </div>
                    </div>
                </div>

                {/* ===== THEMED WORLDS ===== */}
                <section className="sm-section" id="worlds">
                    <div className="sm-wrap">
                        <div className="sm-head sm-reveal">
                            <span className="sm-kicker">✨ منحصر‌به‌فرد</span>
                            <h2>هر کودک، دنیای خودش را انتخاب می‌کند</h2>
                            <p>درس‌ها، جایزه‌ها و حتی ظاهر برنامه بر اساس علاقه‌ی دانش‌آموز شکل می‌گیرد</p>
                        </div>
                        <div className="sm-worlds sm-reveal">
                            <div className="sm-world sm-tilt football" {...tiltHandlers(9)}>
                                <span className="em">⚽</span>
                                <h3>دنیای فوتبال</h3>
                                <p>درس‌ها در قالب لیگ، گل و قهرمانی</p>
                                <div className="tags"><span>🥅 گل</span><span>🏆 لیگ برتر</span><span>🔥 قهرمانی</span></div>
                            </div>
                            <div className="sm-world sm-tilt cars" {...tiltHandlers(9)}>
                                <span className="em">🏎️</span>
                                <h3>دنیای ماشین و مسابقه</h3>
                                <p>یادگیری در قالب گرنپری و نیترو</p>
                                <div className="tags"><span>⚡ نیترو</span><span>🏁 گرنپری</span><span>🏆 سکوی قهرمانی</span></div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ===== BENTO FEATURES ===== */}
                <section className="sm-section" id="features">
                    <div className="sm-wrap">
                        <div className="sm-head sm-reveal">
                            <span className="sm-kicker">امکانات</span>
                            <h2>هر چیزی که کلاس نیاز دارد</h2>
                            <p>همه‌ی ابزارهای آموزش، بازی و ارتباط در یک‌جا</p>
                        </div>
                        <div className="sm-bento sm-reveal">
                            {BENTO.map((b) => (
                                <Link key={b.t} href={b.href} className={`sm-b sm-tilt ${b.wide ? 'wide' : ''}`} {...tiltHandlers(8)}>
                                    <div className={`ic ${b.cls}`}>{b.ic}</div>
                                    <h3>{b.t}</h3><p>{b.d}</p>
                                </Link>
                            ))}
                            <div className="sm-b dark sm-tilt" {...tiltHandlers(8)}>
                                <div className="ic ic-gold">💌</div>
                                <h3>ارتباط با والدین</h3><p>گفت‌وگوی دوسویه‌ی خانه و مدرسه</p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ===== STATS ===== */}
                <section className="sm-section" style={{ paddingBlock: '40px' }}>
                    <div className="sm-wrap">
                        <div className="sm-stats sm-reveal">
                            <div className="sm-stat"><Counter to={stats.students ?? 0} /><span>دانش‌آموز فعال</span></div>
                            <div className="sm-stat"><Counter to={stats.activities ?? 0} /><span>تمرین و آزمون</span></div>
                            <div className="sm-stat"><Counter to={stats.classrooms ?? 0} /><span>کلاس</span></div>
                            <div className="sm-stat"><Counter to={2} /><span>دنیای علاقه 🎮</span></div>
                        </div>
                    </div>
                </section>

                {/* ===== WEEKLY STARS ===== */}
                <section className="sm-section" id="stars">
                    <div className="sm-wrap">
                        <div className="sm-head sm-reveal">
                            <span className="sm-kicker">🏆 افتخارات</span>
                            <h2>ستاره‌های درخشان این هفته</h2>
                            <p>دانش‌آموزانی که بیشترین تلاش را داشتند</p>
                        </div>
                        <div className="sm-reveal">
                            <div className="sm-lb">
                                {weeklyTop.length ? weeklyTop.map((s) => (
                                    <div key={s.rank} className="sm-lb-row">
                                        <div className={`sm-rank ${s.rank === 1 ? 'g1' : s.rank === 2 ? 'g2' : s.rank === 3 ? 'g3' : ''}`}>{fa(s.rank)}</div>
                                        <div className="sm-lb-name">{s.rank === 1 && '👑 '}{s.name}</div>
                                        <div className="sm-xp">⭐ {fa(s.xp)}</div>
                                    </div>
                                )) : <div className="sm-lb-row" style={{ color: '#a9bade' }}>هنوز امتیازی ثبت نشده</div>}
                            </div>
                        </div>
                    </div>
                </section>

                {/* ===== HOW IT WORKS ===== */}
                <section className="sm-section" id="how">
                    <div className="sm-wrap">
                        <div className="sm-head sm-reveal">
                            <span className="sm-kicker">چطور کار می‌کند؟</span>
                            <h2>راه‌اندازی برای مدرسه، تنها در چند قدم</h2>
                            <p>از ثبت‌نام مدرسه تا ورود دانش‌آموز به دنیای دلخواهش</p>
                        </div>
                        <div className="sm-steps sm-reveal">
                            <div className="sm-step"><div className="sm-num">۱</div><h3>ثبت‌نام مدرسه</h3><p>مدیر مدرسه درخواست می‌دهد و پس از تأیید، حساب مدرسه ساخته می‌شود</p></div>
                            <div className="sm-step"><div className="sm-num">۲</div><h3>ساخت معلم‌ها و کلاس‌ها</h3><p>مدیر برای هر کلاس یک معلم و کد کلاس ایجاد می‌کند</p></div>
                            <div className="sm-step"><div className="sm-num">۳</div><h3>ورود دانش‌آموز</h3><p>دانش‌آموز مدرسه، معلم و دنیای دلخواهش را انتخاب می‌کند و شروع می‌کند</p></div>
                        </div>
                    </div>
                </section>

                {/* ===== FINAL CTA ===== */}
                <section className="sm-section" style={{ paddingTop: 0 }}>
                    <div className="sm-wrap">
                        <div className="sm-cta-band sm-reveal">
                            <div className="sm-cta-glow" />
                            <h2>آماده‌ای ستاره‌ی کلاس شوی؟ 🌟</h2>
                            <p>همین حالا شروع کن و دنیای یادگیری خودت را بساز.</p>
                            <Link href={user ? '/dashboard' : route('login')} className="sm-btn">{user ? 'ادامه بده 🚀' : 'شروع کن 🚀'}</Link>
                        </div>
                    </div>
                </section>
            </div>
        </WebLayout>
    );
}
