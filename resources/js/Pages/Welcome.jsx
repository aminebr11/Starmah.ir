import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import WebLayout from '@/Layouts/WebLayout';
import CosmicScene from '@/Components/CosmicScene';
import Icon from '@/Components/Icon';

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

/* تیلت سبک برای کارت‌ها (worlds / bento) — فقط با ماوس.
   روی لمس، pointermove هنگامِ اسکرول هم شلیک می‌شد و کارت زیرِ انگشت
   جابه‌جا می‌ماند (pointerleave روی تاچ همیشه نمی‌آید)، پس هم ظاهر
   به‌هم می‌ریخت و هم هدفِ لمس از جای خودش می‌رفت. */
function tiltHandlers(strength = 10) {
    const onMove = (e) => {
        if (e.pointerType !== 'mouse') return;
        const el = e.currentTarget;
        const r = el.getBoundingClientRect();
        const px = (e.clientX - r.left) / r.width - 0.5;
        const py = (e.clientY - r.top) / r.height - 0.5;
        el.style.transform = `perspective(700px) rotateY(${px * strength}deg) rotateX(${-py * strength}deg) translateY(-4px)`;
    };
    const reset = (e) => { e.currentTarget.style.transform = ''; };
    // pointercancel هم لازم است: روی تاچ، اسکرول باعثِ cancel می‌شود
    return { onPointerMove: onMove, onPointerLeave: reset, onPointerCancel: reset, onPointerUp: reset };
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

/* کمترین تعداد دانش‌آموز برای نمایشِ آمار در هیرو.
   زیر این حد، عدد نشان داده نمی‌شود تا ادعای ضعیف مطرح نشود. */
const TRUST_MIN_STUDENTS = 50;

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
    const { ui = 'classic' } = usePage().props;
    return ui === 'clay' ? <WelcomeClay /> : <WelcomeClassic />;
}

const CLAY_FEATURES = [
    { ic: 'game', c: 'gold', t: 'آزمون و بازی', d: 'یادگیری با بازی‌های جذاب و هیجان‌انگیز', href: '/games' },
    { ic: 'trophy', c: 'pink', t: 'امتیاز و ستاره', d: 'رقابت و پیشرفتِ روزانه', href: '/leaderboard' },
    { ic: 'target', c: 'sky', t: 'مأموریتِ روزانه', d: 'هر روز یک چالشِ تازه', href: '/missions' },
    { ic: 'spark', c: 'lilac', t: 'آزمونِ هوشمند', d: 'سؤال بر اساسِ کلاس و فصل، با هوش مصنوعی', href: '/student/smart-exams' },
    { ic: 'book', c: 'mint', t: 'مطالبِ درسی', d: 'جزوه، پادکست و گالریِ کلاس', href: '/class-content' },
    { ic: 'heart', c: 'orange', t: 'ارتباط با والدین', d: 'گفت‌وگوی دوسویه‌ی خانه و مدرسه', href: '/messages' },
];

/** صفحه‌ی اصلی در طرحِ «خمیرماه». همان محتوا و همان داده‌ی واقعی. */
function WelcomeClay() {
    const { auth, weeklyTop = [], stats = {} } = usePage().props;
    const user = auth?.user;
    // صفحه‌های امکانات مالِ دانش‌آموزند؛ بقیه به داشبوردِ خودشان و مهمان به ثبت‌نام می‌رود
    const isStudent = (auth?.roles ?? []).includes('student');
    const featHref = (h) => (isStudent ? h : user ? '/dashboard' : '/register');
    useReveal();

    return (
        <WebLayout active="home">
            <div className="cw">
                <header className="cw-hero cw-wrap">
                    <div className="cw-hero-txt">
                        <span className="cw-tag"><Icon name="spark" size={17} /> پلتفرمِ آموزشِ هوشمندِ مدارس · با هوش مصنوعی</span>
                        <h1>درس خواندن این‌بار <span className="cw-hl">بازی است!</span></h1>
                        <p>
                            هر دانش‌آموز بر اساسِ علاقه‌اش (فوتبال، ماشین و…) دنیای خودش را می‌سازد؛ مأموریت می‌گیرد، بازی می‌کند،
                            آزمونِ هوشمند می‌دهد و مثلِ یک ستاره رشد می‌کند. معلم هم با هوش مصنوعی در چند دقیقه سؤال و بازی می‌سازد.
                        </p>
                        <div className="cw-cta">
                            {user ? (
                                <Link href="/dashboard" className="btn btn-lg"><Icon name="home" /> ورود به داشبورد من</Link>
                            ) : (
                                <>
                                    <Link href="/register/student" prefetch="mount" cacheFor="5m" className="btn btn-lg"><Icon name="rocket" /> ثبت‌نام دانش‌آموز</Link>
                                    <Link href="/register/school" prefetch="mount" cacheFor="5m" className="btn btn-lg btn-sky"><Icon name="school" /> ثبت‌نام مدرسه</Link>
                                </>
                            )}
                        </div>
                        {!user && <Link href={route('login')} prefetch="mount" cacheFor="5m" className="cw-login">قبلاً ثبت‌نام کرده‌ای؟ ورود ←</Link>}
                    </div>
                    <div className="cw-stage" aria-hidden="true">
                        <span className="cw-blob b1" /><span className="cw-blob b2" /><span className="cw-blob b3" />
                        <img src="/brand/logo-main-640.webp" srcSet="/brand/logo-main-320.webp 320w, /brand/logo-main-640.webp 640w"
                            sizes="(max-width: 900px) 70vw, 440px" width="880" height="880" fetchPriority="high" decoding="async" alt="" />
                        <span className="cw-sticker s1"><i className="mint"><Icon name="trophy" size={17} /></i> سطحِ بعدی نزدیک است!</span>
                        <span className="cw-sticker s2"><i className="pink"><Icon name="star" size={17} /></i> +۲۰ ستاره</span>
                    </div>
                </header>

                <div className="cw-wrap cw-subjects">
                    {SUBJECTS.map((s) => <span key={s}>{s}</span>)}
                </div>

                <section className="cw-sec cw-wrap" id="worlds">
                    <div className="cw-head sm-reveal"><h2>دنیای خودت را انتخاب کن</h2><p>درس‌ها، جایزه‌ها و حتی ظاهرِ برنامه بر اساسِ علاقه‌ی دانش‌آموز شکل می‌گیرد</p></div>
                    <div className="cw-worlds sm-reveal">
                        <div className="cw-world mint"><Icon name="ball" size={40} /><h3>دنیای فوتبال</h3><p>درس‌ها در قالبِ لیگ، گل و قهرمانی</p><div className="cw-tags"><span>گل</span><span>لیگِ برتر</span><span>قهرمانی</span></div></div>
                        <div className="cw-world pink"><Icon name="car" size={40} /><h3>دنیای ماشین و مسابقه</h3><p>یادگیری در قالبِ گرنپری و نیترو</p><div className="cw-tags"><span>نیترو</span><span>گرنپری</span><span>سکوی قهرمانی</span></div></div>
                    </div>
                </section>

                <section className="cw-sec cw-wrap" id="features">
                    <div className="cw-head sm-reveal"><h2>هر چیزی که کلاس نیاز دارد</h2><p>همه‌ی ابزارهای آموزش، بازی و ارتباط در یک‌جا</p></div>
                    <div className="cw-feats sm-reveal">
                        {CLAY_FEATURES.map((f) => (
                            <Link key={f.t} href={featHref(f.href)} className="cw-feat">
                                <i className={f.c}><Icon name={f.ic} size={26} /></i>
                                <h3>{f.t}</h3><p>{f.d}</p>
                            </Link>
                        ))}
                    </div>
                </section>

                <section className="cw-sec cw-wrap">
                    <div className="cw-stats sm-reveal">
                        <div><Counter to={stats.students ?? 0} /><span>دانش‌آموزِ فعال</span></div>
                        <div><Counter to={stats.activities ?? 0} /><span>تمرین و آزمون</span></div>
                        <div><Counter to={stats.classrooms ?? 0} /><span>کلاس</span></div>
                        <div><Counter to={2} /><span>دنیای علاقه</span></div>
                    </div>
                </section>

                <section className="cw-sec cw-wrap cw-two" id="stars">
                    <div className="sm-reveal">
                        <div className="cw-head start"><h2>ستاره‌های این هفته</h2><p>دانش‌آموزانی که بیشترین تلاش را داشتند</p></div>
                        <div className="cw-lb">
                            {weeklyTop.length ? weeklyTop.map((s) => (
                                <div key={s.rank} className="cw-lb-row">
                                    <span className={`cw-rank r${Math.min(s.rank, 4)}`}>{fa(s.rank)}</span>
                                    <b>{s.name}</b>
                                    <em>⭐ {fa(s.xp)}</em>
                                </div>
                            )) : <div className="cw-lb-row"><b>هنوز امتیازی ثبت نشده</b></div>}
                        </div>
                    </div>
                    <div className="sm-reveal" id="how">
                        <div className="cw-head start"><h2>راه‌اندازی در سه قدم</h2><p>از ثبت‌نامِ مدرسه تا ورودِ دانش‌آموز به دنیای دلخواهش</p></div>
                        <ol className="cw-steps">
                            <li><b>ثبت‌نامِ مدرسه</b><span>مدیر درخواست می‌دهد و پس از تأیید، حسابِ مدرسه ساخته می‌شود</span></li>
                            <li><b>ساختِ معلم‌ها و کلاس‌ها</b><span>برای هر کلاس یک معلم و کدِ کلاس ایجاد می‌شود</span></li>
                            <li><b>ورودِ دانش‌آموز</b><span>مدرسه، معلم و دنیای دلخواهش را انتخاب می‌کند و شروع می‌کند</span></li>
                        </ol>
                    </div>
                </section>

                <section className="cw-sec cw-wrap">
                    <div className="cw-band sm-reveal">
                        <img src="/brand/logo-mark-120.webp" alt="" width="120" height="120" />
                        <div><h2>آماده‌ای ستاره‌ی کلاس شوی؟</h2><p>همین حالا شروع کن و دنیای یادگیریِ خودت را بساز.</p></div>
                        <Link href={user ? '/dashboard' : '/register'} className="btn btn-lg">{user ? 'ادامه بده' : 'شروع کن'} <Icon name="arrow" /></Link>
                    </div>
                </section>
            </div>
        </WebLayout>
    );
}

function WelcomeClassic() {
    const { auth, weeklyTop = [], stats = {} } = usePage().props;
    const user = auth?.user;
    const isStudent = (auth?.roles ?? []).includes('student');
    const featHref = (h) => (isStudent ? h : user ? '/dashboard' : '/register');
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
                                        <Link href="/register/student" prefetch="mount" cacheFor="5m" className="sm-btn">🎓 ثبت‌نام دانش‌آموز</Link>
                                        <Link href="/register/school" prefetch="mount" cacheFor="5m" className="sm-btn ghost">🏫 ثبت‌نام مدرسه</Link>
                                    </>
                                )}
                            </div>
                            {/* راهنمای «کدام دکمه را بزن» حذف شد؛ خودِ برچسبِ دکمه‌ها گویاست.
                                فقط مسیرِ کاربرِ بازگشتی باقی مانده. */}
                            {!user && (
                                <div className="sm-hint">
                                    <Link href={route('login')} prefetch="mount" cacheFor="5m">قبلاً ثبت‌نام کرده‌ای؟ ورود ←</Link>
                                </div>
                            )}
                            {/* شمارِ دانش‌آموزان تنها پس از رسیدن به یک حدِ باورپذیر نشان داده
                                می‌شود؛ اعلامِ عددِ کوچک به اعتبار آسیب می‌زند. */}
                            {(stats.students ?? 0) >= TRUST_MIN_STUDENTS && (
                                <div className="sm-trust">
                                    <div className="sm-ava"><span>🦁</span><span>🐯</span><span>🦊</span><span>🐼</span></div>
                                    <span>+{fa(stats.students)} دانش‌آموز در حال یادگیری و رقابت</span>
                                </div>
                            )}
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

                            {/* نشانِ تصویری بدون متن؛ نسخه‌ی logo-illustration متنِ برند و شعار را
                                داخل خود داشت و border-radius:50% آن را می‌برید.
                                width/height صریح ⇒ جلوگیری از پرشِ چیدمان (CLS). */}
                            <img
                                className="sm-hero-img"
                                src="/brand/hero-emblem-880.webp"
                                srcSet="/brand/hero-emblem-560.webp 560w, /brand/hero-emblem-880.webp 880w"
                                sizes="(max-width: 980px) 60vw, 440px"
                                width="880"
                                height="880"
                                fetchPriority="high"
                                decoding="async"
                                alt="کودکی با کتاب، در حال قدم زدن روی جاده‌ای به‌سوی آسمانِ پرستاره"
                            />
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
                                <Link key={b.t} href={featHref(b.href)} className={`sm-b sm-tilt ${b.wide ? 'wide' : ''}`} {...tiltHandlers(8)}>
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
