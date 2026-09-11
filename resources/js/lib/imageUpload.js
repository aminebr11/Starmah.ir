/**
 * نرمال‌سازیِ عکس در مرورگر، پیش از ارسال به سرور.
 *
 * چرا لازم است:
 *  ۱) عکسِ دوربینِ گوشی‌های امروزی ۳ تا ۸ مگابایت است، اما اعتبارسنجیِ سرور
 *     سقفِ ۲ مگابایت دارد ⇒ آپلود رد می‌شد.
 *  ۲) آیفون عکس را با فرمتِ HEIC می‌دهد؛ این پسوند در فهرستِ مجازِ سرور نبود و
 *     فایل **بدونِ هیچ پیامی** دور انداخته می‌شد (فرم ذخیره می‌شد، عکس نه).
 *  ۳) مرورگرها HEIC را می‌توانند روی canvas رسم کنند، پس خروجی همیشه JPEG می‌شود
 *     و هر دو مشکل هم‌زمان حل می‌شود.
 */

/** بیشینه‌ی ضلع و کیفیتِ پیش‌فرض — خروجی معمولاً ۱۵۰ تا ۳۰۰ کیلوبایت می‌شود. */
export const DEFAULTS = { maxSize: 1024, quality: 0.85, mime: 'image/jpeg' };

/** خواندنِ فایل به‌صورتِ تصویرِ قابلِ رسم. */
function loadImage(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('decode-failed')); };
        img.src = url;
    });
}

/**
 * عکس را به JPEGِ کوچک تبدیل می‌کند.
 * اگر مرورگر نتواند فایل را رمزگشایی کند (فرمتِ ناشناخته)، همان فایلِ اصلی
 * برگردانده می‌شود تا دستِ‌کم تلاشِ آپلود انجام شود و سرور پیامِ روشن بدهد.
 */
export async function normalizeImage(file, opts = {}) {
    const { maxSize, quality, mime } = { ...DEFAULTS, ...opts };
    if (!file) return null;

    let img;
    try {
        img = await loadImage(file);
    } catch {
        return file;
    }

    const scale = Math.min(1, maxSize / Math.max(img.naturalWidth, img.naturalHeight));
    const w = Math.max(1, Math.round(img.naturalWidth * scale));
    const h = Math.max(1, Math.round(img.naturalHeight * scale));

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';           // JPEG شفافیت ندارد؛ پس‌زمینه‌ی سفید
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(img, 0, 0, w, h);

    const blob = await new Promise((res) => canvas.toBlob(res, mime, quality));
    if (!blob) return file;

    const base = (file.name || 'photo').replace(/\.[^.]+$/, '') || 'photo';
    return new File([blob], `${base}.jpg`, { type: mime, lastModified: Date.now() });
}

/** برشِ مربعیِ وسط‌چین — برای آواتار و لوگو که دایره‌ای نمایش داده می‌شوند. */
export async function normalizeSquare(file, opts = {}) {
    const { maxSize, quality, mime } = { ...DEFAULTS, maxSize: 512, ...opts };
    if (!file) return null;

    let img;
    try {
        img = await loadImage(file);
    } catch {
        return file;
    }

    const side = Math.min(img.naturalWidth, img.naturalHeight);
    const sx = (img.naturalWidth - side) / 2;
    const sy = (img.naturalHeight - side) / 2;
    const out = Math.min(maxSize, side);

    const canvas = document.createElement('canvas');
    canvas.width = out;
    canvas.height = out;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, out, out);
    ctx.drawImage(img, sx, sy, side, side, 0, 0, out, out);

    const blob = await new Promise((res) => canvas.toBlob(res, mime, quality));
    if (!blob) return file;

    const base = (file.name || 'photo').replace(/\.[^.]+$/, '') || 'photo';
    return new File([blob], `${base}.jpg`, { type: mime, lastModified: Date.now() });
}

/** اندازه‌ی خوانا برای نمایش به کاربر. */
export function humanSize(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return `${bytes} بایت`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} کیلوبایت`;
    return `${(bytes / 1024 / 1024).toFixed(1)} مگابایت`;
}
