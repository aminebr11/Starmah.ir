import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { pushOverlay, closeOverlay } from '@/lib/overlayBack';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * دکمه‌ی «شروعِ بازی/آزمون» با هشدارِ پیش از شروع.
 *
 * چرا: بچه‌ها دکمه‌ی شروع را می‌زدند، وسطِ کار بیرون می‌رفتند و کارِ نیمه‌تمام با امتیازِ صفر
 * برای معلم ثبت می‌شد. حالا پیش از ورود می‌بینند که باید تا آخر بمانند و فقط با «آره، شروع می‌کنم»
 * وارد می‌شوند؛ «بعداً» هیچ چیزی ثبت نمی‌کند. (پیش‌بارگذاری هم ندارد تا لمسِ اتفاقی کاری شروع نکند.)
 */
export default function StartLink({ href, kind = 'game', title, count, duration, resume = false, practice = false, className, style, children, ...rest }) {
    const [open, setOpen] = useState(false);
    return (
        <>
            <a {...rest} href={href} data-no-prefetch="" className={className} style={style}
                onClick={(e) => { e.preventDefault(); e.stopPropagation(); setOpen(true); }}>{children}</a>
            {open && <StartModal href={href} kind={kind} title={title} count={count} duration={duration} resume={resume} practice={practice} onClose={() => setOpen(false)} />}
        </>
    );
}

function StartModal({ href, kind, title, count, duration, resume, practice, onClose }) {
    const exam = kind === 'exam';
    const what = exam ? 'آزمون' : 'بازی';
    const ref = useRef({ close: onClose });
    useEffect(() => {
        const entry = ref.current;
        const off = pushOverlay(entry);
        const key = (e) => { if (e.key === 'Escape') closeOverlay(entry); };
        document.addEventListener('keydown', key);
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { off(); document.removeEventListener('keydown', key); document.body.style.overflow = prev; };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps
    const later = () => closeOverlay(ref.current);
    const go = () => {
        const entry = ref.current;
        // ورودیِ تاریخچه‌ی پنجره را دور بزن تا «بازگشت» از صفحه‌ی بازی دوباره این‌جا نیاید
        entry.close = () => {};
        closeOverlay(entry);
        setTimeout(() => router.visit(href), 60);
    };

    return createPortal(
        <div className="sg-back" role="dialog" aria-modal="true" aria-label={`شروعِ ${what}`} onClick={(e) => { if (e.target === e.currentTarget) later(); }}>
            <div className={`sg-box ${exam ? 'exam' : 'game'}`}>
                <div className="sg-ic">{exam ? '📝' : '🎮'}</div>
                <h3>{resume ? `ادامه‌ی ${what}؟` : `آماده‌ای ${what} را شروع کنی؟`}</h3>
                {title && <div className="sg-title">«{title}»</div>}
                {(count || duration) && (
                    <div className="sg-chips">
                        {count ? <span>❓ {fa(count)} سؤال</span> : null}
                        {duration ? <span>⏱️ {fa(duration)} دقیقه</span> : null}
                    </div>
                )}
                <ul className="sg-rules">
                    <li><b>🏁</b><span>وقتی وارد شدی باید {what} را <b>تا آخر</b> انجام بدهی.</span></li>
                    <li className="warn"><b>⚠️</b><span>{practice
                        ? `اگر نیمه‌کاره بیرون بروی، این دورِ ${what} حساب نمی‌شود.`
                        : <>اگر وسطِ کار بیرون بروی یا رهایش کنی، <b>امتیازت صفر ثبت می‌شود</b>.</>}</span></li>
                    <li><b>📶</b><span>اینترنت و شارژِ گوشی‌ات را نگاه کن؛ اگر الان وقت نداری «بعداً» را بزن.</span></li>
                </ul>
                <div className="sg-act">
                    <button type="button" className="sg-go" onClick={go}>✅ آره، مطمئنم — شروع کن</button>
                    <button type="button" className="sg-later" onClick={later}>⏳ بعداً</button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
