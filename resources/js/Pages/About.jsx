import { Link } from '@inertiajs/react';
import { useEffect } from 'react';
import WebLayout from '@/Layouts/WebLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

function useReveal() {
    useEffect(() => {
        const els = document.querySelectorAll('.sm-reveal');
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => e.isIntersecting && e.target.classList.add('in'));
        }, { threshold: 0.12 });
        els.forEach((el) => io.observe(el));
        const fb = setTimeout(() => els.forEach((el) => el.classList.add('in')), 1600);
        return () => { io.disconnect(); clearTimeout(fb); };
    }, []);
}

const PHILOSOPHY = [
    { ic: '🌱', cls: 'ic-teal', t: 'رشد همه‌جانبه', d: 'ما به رشد تحصیلی، اجتماعی، عاطفی و اخلاقی دانش‌آموزان توجه داریم.' },
    { ic: '🎨', cls: 'ic-pink', t: 'خلاقیت و نوآوری', d: 'تشویق دانش‌آموزان به تفکر خلاق و یافتن راه‌حل‌های نوآورانه برای مسائل.' },
    { ic: '🤝', cls: 'ic-blue', t: 'یادگیری مشارکتی', d: 'ایجاد محیطی که دانش‌آموزان با همکاری یکدیگر یاد بگیرند و رشد کنند.' },
    { ic: '💡', cls: 'ic-gold', t: 'یادگیری با لذت', d: 'استفاده از بازی، داستان و فناوری برای جذاب‌تر کردن فرآیند یادگیری.' },
    { ic: '⭐', cls: 'ic-purple', t: 'کشف استعدادها', d: 'شناخت و پرورش استعدادهای منحصربه‌فرد هر دانش‌آموز و هدایت آن‌ها به سمت موفقیت.' },
    { ic: '🌍', cls: 'ic-teal', t: 'آموزش مسئولیت‌پذیری', d: 'تربیت شهروندان آگاه و مسئول که برای جامعه و محیط زیست احساس مسئولیت کنند.' },
];

const GOALS = [
    { n: 1, t: 'تقویت مهارت‌های پایه', d: 'تسلط بر خواندن، نوشتن و محاسبات ریاضی به‌عنوان پایه‌های اصلی یادگیری' },
    { n: 2, t: 'پرورش تفکر انتقادی', d: 'آموزش مهارت‌های حل مسئله و تحلیل اطلاعات به شیوه‌های خلاقانه' },
    { n: 3, t: 'توسعه مهارت‌های اجتماعی', d: 'یادگیری کار گروهی، احترام به دیگران و مسئولیت‌پذیری' },
    { n: 4, t: 'افزایش اعتماد به نفس', d: 'کمک به دانش‌آموزان برای باور به توانایی‌های خود و بیان آزادانه نظرات' },
];

const FEATURES = [
    { ic: '📱', t: 'فناوری آموزشی', d: 'استفاده از تبلت‌های هوشمند و نرم‌افزارهای آموزشی تعاملی' },
    { ic: '🌱', t: 'فعالیت‌های محیط زیستی', d: 'انجام پروژه‌های سبز و آموزش حفاظت از محیط زیست' },
    { ic: '📖', t: 'کتابخانه کلاسی', d: 'مجموعه‌ای از کتاب‌های داستان و آموزشی مناسب سن دانش‌آموزان' },
    { ic: '🎯', t: 'آموزش هدفمند', d: 'برنامه‌ریزی شخصی برای هر دانش‌آموز بر اساس نیازها و استعدادهایش' },
    { ic: '🎭', t: 'فعالیت‌های هنری', d: 'نقاشی، کاردستی و نمایش برای پرورش خلاقیت' },
    { ic: '🏃', t: 'ورزش و تحرک', d: 'فعالیت‌های بدنی منظم برای سلامت جسم و روح' },
];

const PARENT_ROLES = [
    'شرکت در جلسات والدین',
    'پیگیری تکالیف و فعالیت‌های درسی',
    'ارائه بازخورد سازنده',
    'مشارکت در برنامه‌های کلاسی',
];

const CONTACT = [
    { ic: '📍', t: 'آدرس', d: 'تهران، نیاوران، دبستان فرزانه ۳' },
    { ic: '📞', t: 'تلفن', d: fa('02126452784') },
    { ic: '📧', t: 'ایمیل', d: 'info@starmah.ir' },
    { ic: '🕐', t: 'ساعات پاسخگویی', d: 'شنبه تا چهارشنبه، ساعت ۸ تا ۱۳' },
];

export default function About() {
    useReveal();

    return (
        <WebLayout title="درباره من" active="about" variant="cosmic">
            <div className="sm">
                <div className="sm-stars" />

                {/* ===== HERO ===== */}
                <header className="sm-hero about-hero">
                    <div className="sm-aurora g" /><div className="sm-aurora b" /><div className="sm-aurora p" />
                    <span className="sm-spark" style={{ top: '18%', insetInlineStart: '10%', fontSize: 30 }}>🌟</span>
                    <span className="sm-spark" style={{ top: '26%', insetInlineEnd: '12%', fontSize: 34, animationDelay: '-2s' }}>🌙</span>
                    <span className="sm-spark" style={{ bottom: '18%', insetInlineStart: '16%', fontSize: 22, animationDelay: '-4s' }}>✨</span>
                    <span className="sm-spark" style={{ bottom: '24%', insetInlineEnd: '20%', fontSize: 26, animationDelay: '-6s' }}>⭐</span>

                    <div className="sm-wrap" style={{ position: 'relative', zIndex: 3, textAlign: 'center' }}>
                        <span className="sm-eyebrow"><span className="d" /> درباره‌ی من و کلاس</span>
                        <h1 className="sm-h1" style={{ maxWidth: 900, margin: '18px auto 14px' }}>
                            درباره‌ی کلاس <span className="sm-grad">ستاره‌های درخشان</span>
                        </h1>
                        <p className="sm-lead" style={{ margin: '0 auto 26px' }}>
                            جایی که یادگیری با <b>عشق</b> و <b>خلاقیت</b> آمیخته می‌شود 🌙
                        </p>
                        <div className="sm-cta" style={{ justifyContent: 'center' }}>
                            <Link href="/#worlds" className="sm-btn">🌟 چطور امتیاز بگیریم؟</Link>
                            <Link href="/#how" className="sm-btn ghost">🚀 سفر به دنیای ستاره ماه</Link>
                        </div>
                    </div>
                </header>

                {/* ===== TEACHER PROFILE ===== */}
                <section className="sm-section" style={{ paddingTop: 40 }}>
                    <div className="sm-wrap">
                        <div className="about-profile sm-reveal">
                            <div className="about-avatar">
                                {/* عکس معلم را می‌توانید جایگزین کنید: /brand/teacher.jpg */}
                                <img src="/brand/logo-emblem.png" alt="نجمه محمودی" />
                                <span className="about-avatar-glow" />
                            </div>
                            <div className="about-profile-body">
                                <span className="sm-kicker">بنیان‌گذار و معلم کلاس</span>
                                <h2>نجمه محمودی</h2>
                                <p>
                                    با عشق به کودکان و باور به اینکه «هر کودک، ستاره‌ای‌ست در مسیر کشف بی‌پایان»،
                                    کلاس ستاره‌های درخشان را ساختم؛ محیطی گرم و شاد که در آن هر دانش‌آموز
                                    استعدادهای منحصربه‌فرد خودش را کشف می‌کند و با لذت یاد می‌گیرد.
                                </p>
                                <div className="about-badges">
                                    <span>🎓 بیش از یک دهه سابقه تدریس</span>
                                    <span>🏆 برنده‌ی جایزه‌ی معلم نمونه</span>
                                    <span>📚 آموزش نوین و بازی‌محور</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ===== PHILOSOPHY ===== */}
                <section className="sm-section" style={{ paddingTop: 20 }}>
                    <div className="sm-wrap">
                        <div className="sm-head sm-reveal">
                            <span className="sm-kicker">✨ ارزش‌های ما</span>
                            <h2>فلسفه‌ی آموزشی ما</h2>
                            <p>آنچه هر روز در کلاس ستاره‌های درخشان دنبال می‌کنیم</p>
                        </div>
                        <div className="about-grid3 sm-reveal">
                            {PHILOSOPHY.map((p) => (
                                <div key={p.t} className="sm-b" style={{ cursor: 'default' }}>
                                    <div className={`ic ${p.cls}`}>{p.ic}</div>
                                    <h3>{p.t}</h3><p>{p.d}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ===== GOALS ===== */}
                <section className="sm-section" style={{ paddingTop: 20 }}>
                    <div className="sm-wrap">
                        <div className="sm-head sm-reveal">
                            <span className="sm-kicker">🎯 مسیر امسال</span>
                            <h2>اهداف آموزشی سال تحصیلی</h2>
                            <p>چهار هدف کلیدی که با هم به آن‌ها می‌رسیم</p>
                        </div>
                        <div className="about-goals sm-reveal">
                            {GOALS.map((g) => (
                                <div key={g.n} className="about-goal">
                                    <div className="about-goal-num">{fa(String(g.n).padStart(2, '0'))}</div>
                                    <div>
                                        <h3>{g.t}</h3>
                                        <p>{g.d}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ===== CLASS FEATURES ===== */}
                <section className="sm-section" style={{ paddingTop: 20 }}>
                    <div className="sm-wrap">
                        <div className="sm-head sm-reveal">
                            <span className="sm-kicker">🌟 ویژگی‌ها</span>
                            <h2>ویژگی‌های کلاس ما</h2>
                            <p>ابزارها و فعالیت‌هایی که یادگیری را متفاوت می‌کنند</p>
                        </div>
                        <div className="about-grid3 sm-reveal">
                            {FEATURES.map((f) => (
                                <div key={f.t} className="sm-b" style={{ cursor: 'default' }}>
                                    <div className="ic ic-gold">{f.ic}</div>
                                    <h3>{f.t}</h3><p>{f.d}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ===== PARENTS ===== */}
                <section className="sm-section" style={{ paddingTop: 20 }}>
                    <div className="sm-wrap">
                        <div className="about-parents sm-reveal">
                            <div className="sm-cta-glow" />
                            <div style={{ position: 'relative', zIndex: 2 }}>
                                <span className="sm-kicker">🤝 دست در دست</span>
                                <h2>نقش والدین</h2>
                                <p className="about-parents-lead">
                                    <b>همراهی شما، کلید موفقیت فرزندتان.</b> ما معتقدیم والدین شریک اصلی ما در فرآیند
                                    آموزش هستند. با همکاری و هماهنگی میان خانه و مدرسه، بهترین نتایج را برای فرزندان
                                    عزیزمان رقم می‌زنیم.
                                </p>
                                <div className="about-parent-list">
                                    {PARENT_ROLES.map((r) => (
                                        <div key={r} className="about-parent-item"><span>✅</span>{r}</div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ===== CONTACT ===== */}
                <section className="sm-section" style={{ paddingTop: 20 }}>
                    <div className="sm-wrap">
                        <div className="sm-head sm-reveal">
                            <span className="sm-kicker">📮 در تماس باشیم</span>
                            <h2>ارتباط با ما</h2>
                            <p>خوشحال می‌شویم صدای شما را بشنویم</p>
                        </div>
                        <div className="about-contact sm-reveal">
                            {CONTACT.map((c) => (
                                <div key={c.t} className="about-contact-card">
                                    <div className="about-contact-ic">{c.ic}</div>
                                    <div className="about-contact-t">{c.t}</div>
                                    <div className="about-contact-d">{c.d}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ===== CTA ===== */}
                <section className="sm-section" style={{ paddingTop: 0 }}>
                    <div className="sm-wrap">
                        <div className="sm-cta-band sm-reveal">
                            <div className="sm-cta-glow" />
                            <h2>دوست داری به ستاره‌های درخشان بپیوندی؟ 🌟</h2>
                            <p>همین حالا ثبت‌نام کن و سفرت را در دنیای ستاره ماه شروع کن.</p>
                            <Link href="/register/student" className="sm-btn">🎓 ثبت‌نام دانش‌آموز</Link>
                        </div>
                    </div>
                </section>
            </div>
        </WebLayout>
    );
}
