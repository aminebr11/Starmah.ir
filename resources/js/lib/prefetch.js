import { router } from '@inertiajs/react';

/**
 * پیش‌بارگذاریِ صفحه‌ی بعد: همین که انگشت روی پیوند می‌نشیند (touchstart) یا ماوس
 * لحظه‌ای روی آن می‌ماند، داده‌ی صفحه و کدِ آن هم‌زمان گرفته می‌شود؛ تا کلیک ثبت شود
 * (معمولاً ۱۰۰ تا ۳۰۰ میلی‌ثانیه بعد) صفحه آماده است. Inertia همان پاسخ را برای رفتن استفاده می‌کند.
 *
 * فقط پیوندهای داخلیِ سایت؛ دانلود/خروجی/فایل/خروج پیش‌بارگذاری نمی‌شوند.
 */
const SKIP = /\/(logout|login|register|password|install)\b|\/(export|download|print|pdf|csv|xlsx|excel|storage|media|file|files|asset|recording|stream|audio|image|avatar)\b|\.\w{2,5}$/i;
const HOVER_DELAY = 90;
let lastHref = '';
let lastAt = 0;
let hoverT = null;
const started = new Map();   // href → زمانِ شروعِ پیش‌بارگذاری

function linkOf(target) {
    const a = target?.closest?.('a[href]');
    if (!a || a.target === '_blank' || a.hasAttribute('download') || a.dataset.noPrefetch !== undefined) return null;
    let url;
    try { url = new URL(a.href, window.location.href); } catch { return null; }
    if (url.origin !== window.location.origin || SKIP.test(url.pathname)) return null;
    if (url.pathname === window.location.pathname && url.search === window.location.search) return null;
    return url.pathname + url.search;
}

function go(href, resolve) {
    const now = Date.now();
    if (href === lastHref && now - lastAt < 15000) return;   // همین تازگی گرفته شده
    lastHref = href; lastAt = now;
    started.set(href, now);
    try {
        router.prefetch(href, {
            method: 'get',
            // کدِ صفحه هم هم‌زمان (نه پس از رسیدنِ داده)
            onPrefetched: (response) => { try { const name = response?.data?.component; if (name) resolve(name); } catch { /* */ } },
        }, { cacheFor: '20s' });
    } catch { /* پیش‌بارگذاری اختیاری است */ }
}

export function initPrefetch(resolve) {
    if (typeof window === 'undefined') return;
    // صفحه از پیش‌بارگذاری آمد (درخواستِ تازه‌ای به سرور نرفت): در آمارِ بازدید ثبت شود
    router.on('navigate', () => {
        const href = window.location.pathname + window.location.search;
        const at = started.get(href);
        if (at && Date.now() - at < 19000) {
            started.delete(href);
            window.axios?.post('/presence/view', { path: href }).catch(() => {});
        }
    });
    // اینترنتِ «کم‌مصرف» را محترم بشمار
    if (navigator.connection?.saveData) return;
    document.addEventListener('touchstart', (e) => { const h = linkOf(e.target); if (h) go(h, resolve); }, { passive: true, capture: true });
    document.addEventListener('mouseover', (e) => {
        const h = linkOf(e.target);
        clearTimeout(hoverT);
        if (h) hoverT = setTimeout(() => go(h, resolve), HOVER_DELAY);
    }, { passive: true });
    document.addEventListener('mouseout', () => clearTimeout(hoverT), { passive: true });
}
