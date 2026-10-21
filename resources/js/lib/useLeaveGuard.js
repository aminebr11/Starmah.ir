import { useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import { pushOverlay } from './overlayBack';

/**
 * وسطِ بازی/آزمون: پیش از بیرون رفتن (منو، پیوند، دکمه‌ی بازگشتِ گوشی، بستنِ برگه) می‌پرسد،
 * چون کارِ نیمه‌تمام با امتیازِ صفر ثبت می‌شود. وقتی active=false شد (کار تمام شد)، دیگر نمی‌پرسد.
 */
export default function useLeaveGuard(active, message) {
    const on = useRef(active);
    on.current = active;
    useEffect(() => {
        if (!active) return undefined;
        const unload = (e) => { if (on.current) { e.preventDefault(); e.returnValue = message; return message; } return undefined; };
        window.addEventListener('beforeunload', unload);
        // رفتن با پیوندها و منوها (درخواست‌های پس‌زمینه و ارسالِ پاسخ‌ها مزاحم نمی‌شوند)
        const offBefore = router.on('before', (ev) => {
            const v = ev.detail.visit;
            if (!on.current || v.method !== 'get' || v.async || v.prefetch || v.only?.length || v.except?.length) return;
            if (!window.confirm(message)) ev.preventDefault();
            else on.current = false;
        });
        // دکمه‌ی «بازگشت» گوشی
        let offBack = null;
        const entry = {
            close: () => {
                if (!on.current) { window.history.back(); return; }
                if (window.confirm(message)) { on.current = false; window.history.back(); } else offBack = pushOverlay(entry);
            },
        };
        offBack = pushOverlay(entry);
        return () => { window.removeEventListener('beforeunload', unload); offBefore(); offBack?.(); };
    }, [active]); // eslint-disable-line react-hooks/exhaustive-deps
}
