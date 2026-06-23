import { Link } from '@inertiajs/react';
import WebLayout from '@/Layouts/WebLayout';

export default function RegisterThanks() {
    return (
        <WebLayout title="درخواست ثبت شد">
            <div className="container section">
                <div className="card" style={{ maxWidth: 520, margin: '0 auto', textAlign: 'center' }}>
                    <div style={{ fontSize: 64 }}>🎉</div>
                    <h2 style={{ color: 'var(--navy-800)', marginBottom: 10 }}>درخواست شما ثبت شد!</h2>
                    <p style={{ color: 'var(--muted)' }}>
                        تیم ستاره ماه درخواست مدرسه‌ی شما را بررسی می‌کند و پس از تأیید،
                        اطلاعات ورود حساب مدیر برای شما ارسال می‌شود.
                    </p>
                    <Link href="/" className="btn" style={{ marginTop: 20 }}>بازگشت به صفحه‌ی اصلی</Link>
                </div>
            </div>
        </WebLayout>
    );
}
