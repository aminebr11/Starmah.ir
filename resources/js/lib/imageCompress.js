/**
 * کوچک‌کردنِ عکس در مرورگر پیش از آپلود.
 *
 * عکسِ گوشی معمولاً ۳ تا ۸ مگابایت است؛ با چند عکس سقفِ حجمِ درخواستِ هاست
 * (post_max_size) پر می‌شود. عکس‌های بزرگ به بیشینه‌ی ۱۹۲۰ پیکسل و JPEG
 * با کیفیتِ ۰٫۸۵ تبدیل می‌شوند؛ کیفیت برای نمایش در گالری کاملاً کافی است.
 * GIF (ممکن است متحرک باشد) و عکس‌های کوچک دست‌نخورده می‌مانند.
 */
const MAX_SIDE = 1920;
const MIN_BYTES = 900 * 1024;

export async function compressImage(file) {
    try {
        if (!/^image\/(jpeg|png|webp)$/i.test(file.type) || file.size < MIN_BYTES) return file;
        const bmp = await loadImage(file);
        const scale = Math.min(1, MAX_SIDE / Math.max(bmp.width, bmp.height));
        const w = Math.round(bmp.width * scale), h = Math.round(bmp.height * scale);
        const canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff'; // پس‌زمینه‌ی سفید برای PNGهای شفاف
        ctx.fillRect(0, 0, w, h);
        ctx.drawImage(bmp, 0, 0, w, h);
        const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.85));
        if (!blob || blob.size >= file.size) return file;
        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], name, { type: 'image/jpeg', lastModified: file.lastModified });
    } catch {
        return file; // هر خطایی → همان فایلِ اصلی
    }
}

function loadImage(file) {
    if (window.createImageBitmap) {
        return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(() => loadViaTag(file));
    }
    return loadViaTag(file);
}

function loadViaTag(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
        img.onerror = (e) => { URL.revokeObjectURL(url); reject(e); };
        img.src = url;
    });
}

/** تقسیمِ فایل‌ها به تکه‌هایی که از سقفِ تعداد و حجمِ هر درخواست رد نشوند. */
export function chunkFiles(files, maxCount = 10, maxBytes = 24 * 1024 * 1024) {
    const out = [];
    let cur = [], size = 0;
    for (const f of files) {
        if (cur.length && (cur.length >= maxCount || size + f.size > maxBytes)) {
            out.push(cur); cur = []; size = 0;
        }
        cur.push(f); size += f.size;
    }
    if (cur.length) out.push(cur);
    return out;
}
