/**
 * 📎 آماده‌کردنِ «فایلِ نمونه‌سؤال / صفحه‌ی کتاب» برای هوش مصنوعی.
 *
 * هر عکس به JPEGِ حداکثر ۱۶۰۰ پیکسل تبدیل می‌شود (خوانا برای هوش مصنوعی و سبک برای هاست)
 * و هر صفحه‌ی PDF در خودِ مرورگر به عکس تبدیل می‌شود؛ پس همه‌ی سرویس‌های تصویرخوان
 * (ChatGPT، Gemini، Claude و واسطه‌های سازگار) آن را می‌فهمند، حتی آن‌هایی که PDF نمی‌پذیرند.
 */
export const MAX_PAGES = 6;
const MAX_SIDE = 1600;

function canvasToDataUrl(c) {
    return c.toDataURL('image/jpeg', 0.82);
}

async function loadBitmap(file) {
    if (window.createImageBitmap) {
        try { return await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch { /* ادامه */ }
    }
    return new Promise((res, rej) => {
        const img = new Image();
        img.onload = () => res(img);
        img.onerror = rej;
        img.src = URL.createObjectURL(file);
    });
}

async function imageToPage(file) {
    const bmp = await loadBitmap(file);
    const s = Math.min(1, MAX_SIDE / Math.max(bmp.width, bmp.height));
    const c = document.createElement('canvas');
    c.width = Math.round(bmp.width * s); c.height = Math.round(bmp.height * s);
    const g = c.getContext('2d');
    g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height);
    g.drawImage(bmp, 0, 0, c.width, c.height);
    return { name: file.name, url: canvasToDataUrl(c) };
}

function blobToDataUrl(blob) {
    return new Promise((res, rej) => {
        const r = new FileReader();
        r.onload = () => res(r.result);
        r.onerror = rej;
        r.readAsDataURL(blob);
    });
}

/**
 * فایل‌های انتخاب‌شده → صفحه‌ها [{name, url}] (حداکثر `room` صفحه).
 * @returns {Promise<{pages: Array<{name:string,url:string}>, skipped: number}>}
 */
export async function filesToPages(files, room = MAX_PAGES, onProgress) {
    const pages = [];
    let skipped = 0;
    for (const f of Array.from(files || [])) {
        if (pages.length >= room) { skipped += 1; continue; }
        if (f.type === 'application/pdf' || /\.pdf$/i.test(f.name)) {
            const { openPdf, renderPdfPage } = await import('@/online/slides');
            const doc = await openPdf(await f.arrayBuffer());
            const n = doc.numPages;
            for (let i = 1; i <= n; i += 1) {
                if (pages.length >= room) { skipped += n - i + 1; break; }
                onProgress?.(`صفحه‌ی ${i} از ${n}`);
                const blob = await renderPdfPage(doc, i, MAX_SIDE);
                pages.push({ name: `${f.name} — ص ${i}`, url: await blobToDataUrl(blob) });
            }
        } else if (/^image\//.test(f.type)) {
            onProgress?.(f.name);
            pages.push(await imageToPage(f));
        } else {
            skipped += 1;
        }
    }
    return { pages, skipped };
}
