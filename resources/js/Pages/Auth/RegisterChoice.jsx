import { Link } from '@inertiajs/react';
import WebLayout from '@/Layouts/WebLayout';

export default function RegisterChoice() {
    return (
        <WebLayout title="ثبت‌نام">
            <div className="container section">
                <div className="section-head">
                    <span className="eyebrow">به ستاره ماه خوش آمدید 🌟</span>
                    <h2>چطور می‌خواهی شروع کنی؟</h2>
                    <p>اگر مدیر مدرسه هستی، مدرسه‌ات را ثبت کن. اگر دانش‌آموز هستی، به کلاست بپیوند.</p>
                </div>

                <div className="worlds" style={{ maxWidth: 860, margin: '0 auto' }}>
                    <Link href="/register/school" className="world" style={{ background: 'linear-gradient(135deg,#16264f,#070f24)' }}>
                        <span className="em">🏫</span>
                        <h3>ثبت‌نام مدرسه</h3>
                        <p>مدیر مدرسه: درخواست ساخت حساب مدرسه و کلاس‌ها را ثبت کن</p>
                        <div className="tags"><span>پنل مدیریت</span><span>ساخت معلم‌ها</span><span>گزارش‌ها</span></div>
                    </Link>

                    <Link href="/register/student" className="world" style={{ background: 'linear-gradient(135deg,#0f9d4f,#0a2a18)' }}>
                        <span className="em">🎓</span>
                        <h3>ثبت‌نام دانش‌آموز</h3>
                        <p>مدرسه و معلمت را انتخاب کن، دنیای دلخواهت را بساز و شروع کن</p>
                        <div className="tags"><span>انتخاب دنیا</span><span>بازی و آزمون</span><span>امتیاز</span></div>
                    </Link>
                </div>

                <p style={{ textAlign: 'center', marginTop: 28, color: 'var(--muted)' }}>
                    قبلاً حساب داری؟ <Link href={route('login')} style={{ color: 'var(--gold-2)', fontWeight: 700 }}>وارد شو</Link>
                </p>
            </div>
        </WebLayout>
    );
}
