import { useEffect, useRef, useState } from 'react';
import { pushOverlay, closeOverlay } from '@/lib/overlayBack';

/**
 * نمایشگرِ تمام‌صفحه‌ی یک فایل (عکس یا PDF) با دکمه‌ی ✕ — برای کاربرگِ خالی، فایلِ تکلیف و …
 * «بازگشتِ» گوشی و Esc هم می‌بندد؛ دکمه‌ی «دانلود» فایل را جدا ذخیره می‌کند.
 * (لینکِ مستقیمِ فایل در اپ صفحه‌ای بدونِ راهِ برگشت باز می‌کرد.)
 */
export default function FileViewer({ url, title = 'فایل', pdf, onClose }) {
    const me = useRef({ close: () => {} });
    me.current.close = onClose;
    useEffect(() => pushOverlay(me.current), []); // eslint-disable-line react-hooks/exhaustive-deps
    const close = () => closeOverlay(me.current);
    const [zoom, setZoom] = useState(false);
    const [failed, setFailed] = useState(false);
    const isPdf = pdf ?? /\.pdf(\?|$)/i.test(url || '');

    useEffect(() => {
        const onKey = (e) => e.key === 'Escape' && close();
        window.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => { window.removeEventListener('keydown', onKey); document.body.style.overflow = ''; };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div className="sv" dir="rtl" role="dialog" aria-modal="true">
            <header className="sv-bar">
                <button type="button" className="sv-x" onClick={close} aria-label="بستن">✕</button>
                <div className="sv-title"><b>{title}</b><span>{isPdf ? 'PDF' : 'برای بزرگ‌نمایی روی عکس بزنید'}</span></div>
                <a className="sv-dl" href={url} download>⬇️ دانلود</a>
            </header>
            <div className="sv-body">
                <div className={`sv-stage ${zoom ? 'zoom' : ''}`}>
                    {isPdf ? (
                        <div className="sv-pdf">
                            <iframe title={title} src={url} />
                            <a href={url} target="_blank" rel="noreferrer" className="btn btn-sm">📄 بازکردنِ PDF در برنامه‌ی دیگر</a>
                        </div>
                    ) : failed ? (
                        <div className="sv-fail">فایل باز نشد. <a href={url} download>دانلود کنید</a></div>
                    ) : (
                        <div className="sv-paper">
                            <img src={url} alt={title} onClick={() => setZoom((z) => !z)} onError={() => setFailed(true)} draggable={false} />
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

/** نوعِ فایل از روی نشانی: عکس یا PDF در نمایشگر باز می‌شود؛ بقیه (ویدیو، صدا، Word…) همان لینک. */
export const viewable = (url) => /\.(jpe?g|png|webp|gif|pdf)(\?|$)/i.test(url || '') || /\/worksheet-(sheet|files)\//.test(url || '');
