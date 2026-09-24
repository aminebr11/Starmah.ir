import { Head, Link, usePage, router } from '@inertiajs/react';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** صفحه‌ی خطای فارسی (۴۰۳، ۴۰۴، ۵۰۰، …) با راهِ برگشت. */
export default function Error({ status, title, text }) {
    const { auth } = usePage().props;
    return (
        <div dir="rtl" className="err-page">
            <Head title={title} />
            <div className="err-card">
                <img src="/brand/logo-mark.webp" alt="" width="120" height="120" />
                <div className="err-code">{fa(status)}</div>
                <h1>{title}</h1>
                <p>{text}</p>
                <div className="err-actions">
                    <button type="button" className="btn btn-ghost" onClick={() => window.history.length > 1 ? window.history.back() : router.visit('/')}>بازگشت</button>
                    <Link href={auth?.user ? '/dashboard' : '/'} className="btn">{auth?.user ? 'داشبوردِ من' : 'صفحه‌ی اصلی'}</Link>
                </div>
            </div>
        </div>
    );
}
