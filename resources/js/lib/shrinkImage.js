/**
 * کوچک‌کردنِ عکس پیش از آپلود (روی خودِ گوشی).
 *
 * عکسِ دوربینِ گوشی معمولاً ۳ تا ۸ مگابایت است؛ روی هاست با سقفِ آپلودِ ۲ مگابایت اصلاً نمی‌رسید
 * و روی اینترنتِ موبایل هم کند بود. اینجا تا ۲۰۰۰ پیکسل و JPEG با کیفیتِ خوب کوچک می‌شود
 * (برای خواندنِ دست‌خطِ کاربرگ کاملاً کافی است). PDF و فایل‌های غیرِعکس دست نمی‌خورند.
 */
export default async function shrinkImage(file, { max = 2000, quality = 0.85, minBytes = 900 * 1024 } = {}) {
    if (!file || !/^image\/(jpeg|png|webp)$/i.test(file.type) || file.size < minBytes) return file;
    try {
        const url = URL.createObjectURL(file);
        const img = await new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = url; });
        const k = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
        const c = document.createElement('canvas');
        c.width = Math.round(img.naturalWidth * k); c.height = Math.round(img.naturalHeight * k);
        const g = c.getContext('2d');
        g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height);
        g.drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        const blob = await new Promise((res) => c.toBlob(res, 'image/jpeg', quality));
        if (!blob || blob.size >= file.size) return file;
        return new File([blob], (file.name || 'photo').replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
    } catch {
        return file; // اگر مرورگر نتوانست، همان فایلِ اصلی
    }
}
