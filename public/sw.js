/* =====================================================================
   ستاره ماه — Service Worker
   ---------------------------------------------------------------------
   هدف: نصبِ اپ روی اندروید و آیفون + به‌روزرسانیِ خودکار با هر تغییرِ سایت.

   راهبردِ کش، عمداً «شبکه‌اول» برای صفحه‌هاست:
     • هر بار که کاربر صفحه‌ای باز می‌کند، نسخه‌ی تازه از سرور گرفته می‌شود؛
       پس هر تغییری که روی سایت می‌دهید، بی‌درنگ در اپ هم دیده می‌شود.
     • اگر اینترنت قطع باشد، آخرین نسخه‌ی کش‌شده نمایش داده می‌شود.
     • فایل‌های build نامِ هَش‌دار دارند (app-XxYy.js)، پس با هر انتشارِ
       تازه نشانیِ تازه می‌گیرند و کشِ قدیمی خودبه‌خود بی‌اثر می‌شود.
   ===================================================================== */

// با هر تغییرِ راهبردِ کش این عدد بالا می‌رود تا کشِ قدیمی پاک شود.
// v2: فونت‌ها محلی شدند و باید مثلِ دارایی‌های build کش‌اول باشند.
const VERSION = 'v2';
const SHELL_CACHE = `starmah-shell-${VERSION}`;
const ASSET_CACHE = `starmah-assets-${VERSION}`;
const PAGE_CACHE = `starmah-pages-${VERSION}`;

/** حداقلِ چیزی که برای نمایشِ صفحه‌ی آفلاین لازم است. */
const SHELL = [
    '/offline.html',
    '/brand/icon-192.png',
    '/brand/icon-512.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((c) => c.addAll(SHELL))
            // نصب نباید به‌خاطرِ یک فایلِ گمشده شکست بخورد
            .catch(() => undefined)
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        // پاک‌کردنِ کشِ نسخه‌های قبلی
        const keys = await caches.keys();
        await Promise.all(
            keys.filter((k) => k.startsWith('starmah-') && !k.endsWith(VERSION))
                .map((k) => caches.delete(k))
        );
        await self.clients.claim();
    })());
});

/** آیا این درخواست باید اصلاً از کش عبور کند؟ */
function isCacheable(request, url) {
    if (request.method !== 'GET') return false;
    if (url.origin !== self.location.origin) return false;
    // مسیرهای پویا و حساس هرگز کش نمی‌شوند
    if (/^\/(login|logout|register|password|pay|api)\b/.test(url.pathname)) return false;
    return true;
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (!isCacheable(request, url)) return;

    /* ---- ۱) دارایی‌های build و برند: کش‌اول (نامشان هَش‌دار است) ---- */
    // fonts هم مثلِ build نامِ ثابت و محتوای تغییرناپذیر دارد
    if (/^\/(build|brand|fonts)\//.test(url.pathname)) {
        event.respondWith((async () => {
            const cached = await caches.match(request);
            if (cached) return cached;
            try {
                const res = await fetch(request);
                if (res && res.ok) {
                    const c = await caches.open(ASSET_CACHE);
                    c.put(request, res.clone());
                }
                return res;
            } catch {
                return cached || Response.error();
            }
        })());
        return;
    }

    /* ---- ۲) عکس‌های آپلودی: کش‌اول با به‌روزرسانیِ پس‌زمینه ---- */
    if (url.pathname.startsWith('/storage/')) {
        event.respondWith((async () => {
            const cached = await caches.match(request);
            const fetching = fetch(request).then((res) => {
                if (res && res.ok) {
                    caches.open(ASSET_CACHE).then((c) => c.put(request, res.clone()));
                }
                return res;
            }).catch(() => cached);
            return cached || fetching;
        })());
        return;
    }

    /* ---- ۳) صفحه‌ها: شبکه‌اول، تا تغییراتِ سایت بی‌درنگ دیده شود ---- */
    if (request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html')) {
        event.respondWith((async () => {
            try {
                const res = await fetch(request);
                if (res && res.ok) {
                    const c = await caches.open(PAGE_CACHE);
                    c.put(request, res.clone());
                }
                return res;
            } catch {
                const cached = await caches.match(request);
                return cached || caches.match('/offline.html');
            }
        })());
    }
});

/** پیام از صفحه: «همین حالا فعال شو» (برای دکمه‌ی به‌روزرسانی). */
self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') self.skipWaiting();
});
