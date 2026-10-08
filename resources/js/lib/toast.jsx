import { createRoot } from 'react-dom/client';
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * پیامِ کوتاهِ سراسری (پایینِ صفحه) برای خطاهایی که مالِ هیچ فرمی نیستند:
 * نشستِ منقضی، قطعیِ اینترنت، پاسخِ خرابِ سرور. پیش از این اینرشیا در این
 * حالت‌ها یک پنجره‌ی HTMLِ انگلیسی نشان می‌داد یا هیچ پیامی نمی‌آمد.
 */
let push = () => {};

export function toast(text, tone = 'error') { push({ text, tone, id: Date.now() + Math.random() }); }

function Toasts() {
    const [items, setItems] = useState([]);
    useEffect(() => {
        push = (t) => {
            setItems((l) => [...l.filter((x) => x.text !== t.text), t].slice(-3));
            setTimeout(() => setItems((l) => l.filter((x) => x.id !== t.id)), 6000);
        };
    }, []);
    if (!items.length) return null;
    return (
        <div className="sm-toasts" dir="rtl" role="status" aria-live="polite">
            {items.map((t) => (
                <div key={t.id} className={`sm-toast ${t.tone}`}>
                    <span>{t.text}</span>
                    <button type="button" aria-label="بستن" onClick={() => setItems((l) => l.filter((x) => x.id !== t.id))}>✕</button>
                </div>
            ))}
        </div>
    );
}

const STATUS = {
    403: 'اجازه‌ی این کار را ندارید.',
    404: 'این مورد پیدا نشد؛ شاید حذف شده باشد.',
    413: 'حجمِ فایل بیش از حدِ مجاز است.',
    419: 'زمانِ صفحه تمام شد. یک‌بار صفحه را تازه کنید و دوباره امتحان کنید.',
    429: 'درخواست‌ها زیاد شد؛ چند لحظه صبر کنید.',
    500: 'مشکلی در سرور پیش آمد. چند لحظه بعد دوباره امتحان کنید.',
    502: 'سرور پاسخ نداد. چند لحظه بعد دوباره امتحان کنید.',
    503: 'سایت در حالِ به‌روزرسانی است. کمی بعد دوباره امتحان کنید.',
    504: 'سرور دیر پاسخ داد. دوباره امتحان کنید.',
};

export function initToasts() {
    const el = document.createElement('div');
    document.body.appendChild(el);
    createRoot(el).render(<Toasts />);

    // پیامِ سرور (مثلاً «زمانِ صفحه تمام شد») که با session('toast') آمده
    router.on('navigate', (e) => {
        const t = e.detail?.page?.props?.toast;
        if (t) toast(t, 'info');
    });

    // پاسخِ غیرِ اینرشیایی (صفحه‌ی خطای HTML) → به‌جای پنجره‌ی انگلیسی، پیامِ فارسی
    router.on('invalid', (e) => {
        const status = e.detail?.response?.status;
        if (import.meta.env.DEV && status >= 500) return; // در توسعه صفحه‌ی خطای کامل بماند
        e.preventDefault();
        toast(STATUS[status] || 'پاسخِ سرور قابلِ خواندن نبود. صفحه را تازه کنید.');
    });

    // قطعیِ اینترنت یا خطای شبکه
    router.on('exception', (e) => {
        e.preventDefault();
        toast(navigator.onLine === false ? 'اینترنت قطع است. اتصال را بررسی کنید و دوباره امتحان کنید.' : 'ارتباط با سرور برقرار نشد. دوباره امتحان کنید.');
    });
}
