/**
 * بازکردنِ برگه‌ی چاپیِ تمیز (A4) و شروعِ خودکارِ چاپ.
 *
 * - اپِ اندروید: همان‌جا باز می‌شود و پلِ StarmahApp.print پنجره‌ی چاپِ گوشی را باز می‌کند.
 * - آیفون در حالتِ «نصب‌شده روی صفحه» (PWA): window.print آنجا کار نمی‌کند؛ پس
 *   برگه در سافاری باز می‌شود که چاپ و «ذخیره به PDF» را دارد.
 * - مرورگرهای معمولی: در همان زبانه باز و چاپ می‌شود.
 */
export function openPrint(url) {
    const u = url + (url.includes('?') ? '&' : '?') + 'autoprint=1';
    const iosStandalone = window.navigator.standalone === true
        || (/iPhone|iPad|iPod/.test(navigator.userAgent) && window.matchMedia?.('(display-mode: standalone)').matches);
    if (iosStandalone) {
        window.open(u, '_blank');
        return;
    }
    window.location.href = u;
}
