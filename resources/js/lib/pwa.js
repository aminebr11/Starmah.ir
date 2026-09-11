/**
 * راه‌اندازیِ اپِ نصب‌شدنی (PWA).
 *
 * دو کار می‌کند:
 *   ۱) Service Worker را ثبت می‌کند تا اپ روی اندروید و آیفون نصب‌شدنی شود.
 *   ۲) وقتی نسخه‌ی تازه‌ای از سایت منتشر شد، نواری پایینِ صفحه نشان می‌دهد
 *      تا کاربر با یک لمس به‌روز شود — بدون نیاز به نصبِ دوباره‌ی اپ.
 */

let deferredPrompt = null;

/** آیا مرورگر امکانِ نصبِ خودکار دارد؟ (اندروید/کروم) */
export function canInstall() {
    return deferredPrompt !== null;
}

/** نمایشِ پنجره‌ی نصبِ سیستمی. @returns 'accepted' | 'dismissed' | 'unavailable' */
export async function promptInstall() {
    if (!deferredPrompt) return 'unavailable';
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    deferredPrompt = null;
    window.dispatchEvent(new CustomEvent('starmah:installability'));
    return outcome;
}

/** آیا اپ همین حالا به‌صورتِ نصب‌شده اجرا می‌شود؟ */
export function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

/** iOS نصبِ خودکار ندارد و کاربر باید از «افزودن به صفحه‌ی اصلی» استفاده کند. */
export function isIos() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

/** نوارِ «نسخه‌ی تازه آماده است». */
function showUpdateBar(registration) {
    if (document.getElementById('sw-update-bar')) return;

    const bar = document.createElement('div');
    bar.id = 'sw-update-bar';
    bar.setAttribute('role', 'status');
    bar.innerHTML = `
        <span>نسخه‌ی تازه‌ی ستاره ماه آماده است ✨</span>
        <button type="button" id="sw-update-btn">به‌روزرسانی</button>
        <button type="button" id="sw-update-x" aria-label="بستن">✕</button>`;
    document.body.appendChild(bar);

    document.getElementById('sw-update-btn').onclick = () => {
        registration.waiting?.postMessage('SKIP_WAITING');
        // وقتی SW تازه کنترل را گرفت، صفحه یک‌بار تازه می‌شود
        navigator.serviceWorker.addEventListener('controllerchange', () => window.location.reload(), { once: true });
    };
    document.getElementById('sw-update-x').onclick = () => bar.remove();
}

export function initPwa() {
    // پنجره‌ی نصب (اندروید) — مرورگر این رویداد را پیش از نمایش می‌دهد
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        window.dispatchEvent(new CustomEvent('starmah:installability'));
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        window.dispatchEvent(new CustomEvent('starmah:installability'));
    });

    if (!('serviceWorker' in navigator)) return;

    window.addEventListener('load', async () => {
        try {
            const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });

            // اگر همین حالا نسخه‌ی تازه‌ای منتظرِ فعال‌شدن است
            if (reg.waiting) showUpdateBar(reg);

            reg.addEventListener('updatefound', () => {
                const sw = reg.installing;
                if (!sw) return;
                sw.addEventListener('statechange', () => {
                    // «installed» + وجودِ controller یعنی این یک به‌روزرسانی است، نه نصبِ اول
                    if (sw.state === 'installed' && navigator.serviceWorker.controller) {
                        showUpdateBar(reg);
                    }
                });
            });

            // هر بار که اپ به پیش‌زمینه می‌آید، وجودِ نسخه‌ی تازه را چک کن
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') reg.update().catch(() => {});
            });
        } catch {
            // نبودِ Service Worker نباید مانعِ کارِ سایت شود
        }
    });
}
