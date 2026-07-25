import { Link, usePage } from '@inertiajs/react';
import WebLayout from '@/Layouts/WebLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function RegisterThanks() {
    const { ref, failed, manual } = usePage().props;

    let icon = '🎉', title = 'درخواست شما ثبت شد!', body = 'تیم ستاره ماه درخواستِ مدرسه‌ی شما را بررسی می‌کند و پس از تأیید، اطلاعاتِ ورودِ حسابِ مدیر برایتان ارسال می‌شود.';
    if (ref) { icon = '✅'; title = 'پرداخت موفق بود!'; body = 'اشتراکِ شما ثبت شد. اطلاعاتِ ورودِ حسابِ مدیر به‌زودی برایتان ارسال می‌شود.'; }
    else if (failed) { icon = '⚠️'; title = 'پرداخت ناموفق بود'; body = 'مشکلی در پرداخت پیش آمد. می‌توانید دوباره تلاش کنید یا با پشتیبانی در تماس باشید.'; }
    else if (manual) { icon = '🕐'; title = 'درخواستِ شما ثبت شد'; body = 'درگاهِ پرداختِ آنلاین فعلاً فعال نیست؛ درخواستِ شما برای بررسی و هماهنگیِ پرداخت توسطِ مدیرِ سامانه ثبت شد و به‌زودی با شما تماس گرفته می‌شود.'; }

    return (
        <WebLayout title={title}>
            <div className="container section">
                <div className="card" style={{ maxWidth: 520, margin: '0 auto', textAlign: 'center' }}>
                    <div style={{ fontSize: 64 }}>{icon}</div>
                    <h2 style={{ color: 'var(--navy-800)', marginBottom: 10 }}>{title}</h2>
                    <p style={{ color: 'var(--muted)' }}>{body}</p>
                    {ref && <div style={{ marginTop: 12, background: '#e2f6ec', color: '#177a4c', borderRadius: 12, padding: '8px 14px', display: 'inline-block', fontWeight: 700 }}>کدِ پیگیری: {fa(ref)}</div>}
                    <div style={{ marginTop: 20, display: 'flex', gap: 10, justifyContent: 'center', flexWrap: 'wrap' }}>
                        <Link href="/" className="btn">بازگشت به صفحه‌ی اصلی</Link>
                        {failed && <Link href="/pricing" className="btn btn-ghost">مشاهده‌ی طرح‌ها</Link>}
                    </div>
                </div>
            </div>
        </WebLayout>
    );
}
