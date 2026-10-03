/**
 * بستنِ پنجره‌های تمام‌صفحه (آلبوم، نمایشگرِ عکس، …) با دکمه‌ی «بازگشت» گوشی.
 *
 * این ماژول باید پیش از راه‌اندازیِ Inertia در app.jsx import شود تا شنونده‌ی
 * popstate آن *قبل* از روترِ Inertia اجرا شود. اگر پنجره‌ای باز باشد فقط همان
 * بسته می‌شود و رویداد به روتر نمی‌رسد؛ وگرنه روتر کلِ صفحه را از نو می‌سازد
 * و همه‌ی پنجره‌ها یک‌جا بسته می‌شوند.
 */
const overlays = [];

if (typeof window !== 'undefined') {
    window.addEventListener('popstate', (e) => {
        const top = overlays.pop();
        if (!top) return;
        e.stopImmediatePropagation();
        top.close();
    });
}

/** ثبتِ یک پنجره؛ خروجی: تابعِ لغوِ ثبت (هنگامِ unmount). */
export function pushOverlay(entry) {
    overlays.push(entry);
    try { window.history.pushState(window.history.state, ''); } catch { /* نادیده */ }
    return () => { const i = overlays.indexOf(entry); if (i >= 0) overlays.splice(i, 1); };
}

/** بستن با دکمه: ورودیِ تاریخچه‌ی همان پنجره برداشته می‌شود. */
export function closeOverlay(entry) {
    if (overlays[overlays.length - 1] === entry) window.history.back();
    else { const i = overlays.indexOf(entry); if (i >= 0) overlays.splice(i, 1); entry.close(); }
}
