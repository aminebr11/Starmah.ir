import { Link, useForm } from '@inertiajs/react';
import AuthCard from '@/Components/AuthCard';

/** تأییدِ ایمیل. */
export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});
    const submit = (e) => { e.preventDefault(); post(route('verification.send')); };

    return (
        <AuthCard icon="📧" title="ایمیلت را تأیید کن" subtitle="لینکِ تأیید را به ایمیلت فرستادیم. اگر نرسیده، پوشه‌ی هرزنامه را هم ببین یا دوباره بفرست."
            footer={<Link href={route('logout')} method="post" as="button" className="link-gold" style={{ fontWeight: 700, background: 'none', border: 0, cursor: 'pointer', fontFamily: 'inherit' }}>خروج از حساب</Link>}>
            {status === 'verification-link-sent' && (
                <div className="auth-ok">✅ لینکِ تازه به ایمیلت فرستاده شد.</div>
            )}
            <form onSubmit={submit}>
                <button type="submit" disabled={processing} className="btn" style={{ width: '100%' }}>
                    {processing ? 'در حالِ ارسال…' : '📩 ارسالِ دوباره‌ی لینک'}
                </button>
            </form>
        </AuthCard>
    );
}
