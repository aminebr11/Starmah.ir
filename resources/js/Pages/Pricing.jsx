import { Link, usePage } from '@inertiajs/react';
import WebLayout from '@/Layouts/WebLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const toman = (n) => fa(Number(n).toLocaleString('en-US'));

/** صفحه‌ی عمومیِ قیمت — چهار طرحِ فروش (قیمت‌ها از پنلِ ادمین). */
export default function Pricing() {
    const { plans = [] } = usePage().props;

    return (
        <WebLayout title="قیمت‌ها" active="pricing">
            <div className="container section">
                <div className="section-head" style={{ textAlign: 'center', marginBottom: 8 }}>
                    <span className="eyebrow">💎 طرح‌های اشتراک</span>
                    <h2>طرحی متناسب با مدرسه‌ی شما</h2>
                    <p style={{ maxWidth: 620, margin: '0 auto' }}>
                        از نسخه‌ی آزمایشیِ رایگان شروع کنید و هر زمان خواستید ارتقا دهید. همه‌ی طرح‌ها شاملِ
                        بازی، مأموریت و گزارش‌های تحلیلی هستند.
                    </p>
                </div>

                <div className="pricing-grid">
                    {plans.map((p) => (
                        <div key={p.key} className={`price-card ${p.highlighted ? 'featured' : ''}`}>
                            {p.highlighted && <div className="price-badge">پیشنهادِ ما ⭐</div>}
                            <h3 className="price-name">{p.name}</h3>
                            {p.description && <p className="price-desc">{p.description}</p>}
                            <div className="price-amount">
                                {p.price === 0
                                    ? <b>رایگان</b>
                                    : <><b>{toman(p.price)}</b><span className="cur"> تومان</span></>}
                                {p.period_label && p.price > 0 && <div className="price-period">/ {p.period_label}</div>}
                            </div>
                            <ul className="price-features">
                                {(p.features || []).map((f, i) => (
                                    <li key={i}><span className="tick">✓</span>{f}</li>
                                ))}
                            </ul>
                            <Link href={`/register/school?plan=${p.key}`} className={`btn ${p.highlighted ? '' : 'btn-ghost'}`} style={{ width: '100%', justifyContent: 'center' }}>
                                {p.price === 0 ? 'شروعِ رایگان' : 'انتخاب و ثبت‌نام'}
                            </Link>
                        </div>
                    ))}
                    {plans.length === 0 && <p style={{ color: 'var(--muted)', textAlign: 'center', gridColumn: '1/-1' }}>هنوز طرحی تعریف نشده است.</p>}
                </div>

                <div className="card" style={{ maxWidth: 760, margin: '34px auto 0', textAlign: 'center' }}>
                    <h3 style={{ color: 'var(--navy-800)', marginTop: 0 }}>سؤالی دارید؟</h3>
                    <p style={{ color: 'var(--muted)' }}>برای مشاوره‌ی انتخابِ طرح یا نسخه‌ی سازمانیِ ویژه، با ما در ارتباط باشید.</p>
                    <Link href="/register/school" className="btn btn-ghost">درخواستِ مشاوره</Link>
                </div>
            </div>
        </WebLayout>
    );
}
