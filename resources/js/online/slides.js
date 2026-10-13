import axios from 'axios';
import shrinkImage from '@/lib/shrinkImage';

/**
 * آوردنِ اسلاید روی تخته: عکس یا PDF.
 * PDF روی رایانه‌ی معلم به تصویر تبدیل می‌شود (pdf.js فقط همین‌جا بارگذاری
 * می‌شود)، پس گوشیِ بچه‌ها فقط تصویرِ سبک می‌گیرد و چیزی نصب یا اجرا نمی‌کند.
 * خروجی: فهرستِ نشانیِ تصویرها (هر کدام یک صفحه‌ی تخته).
 */
export async function importSlides(file, uploadUrl, onProgress) {
    if (/pdf$/i.test(file.type) || /\.pdf$/i.test(file.name)) return importPdf(file, uploadUrl, onProgress);
    const small = await shrinkImage(file, { max: 1800, quality: 0.85, minBytes: 300 * 1024 });
    onProgress?.(0, 1);
    const url = await upload(small, uploadUrl);
    onProgress?.(1, 1);
    return [url];
}

async function upload(blob, uploadUrl, name = 'slide.jpg') {
    const fd = new FormData();
    fd.append('file', blob instanceof File ? blob : new File([blob], name, { type: blob.type || 'image/jpeg' }));
    const { data } = await axios.post(uploadUrl, fd);
    return data.url;
}

async function importPdf(file, uploadUrl, onProgress) {
    const pdfjs = await import('pdfjs-dist');
    // کارگرِ pdf.js به‌صورتِ فایلِ .js ساخته می‌شود (نه .mjs) تا روی هاستِ cPanel با نوعِ درست سرو شود
    const PdfWorker = (await import('pdfjs-dist/build/pdf.worker.min.mjs?worker')).default;
    pdfjs.GlobalWorkerOptions.workerPort = new PdfWorker();
    const doc = await pdfjs.getDocument({ data: await file.arrayBuffer() }).promise;
    const n = Math.min(doc.numPages, 60);
    const urls = [];
    for (let i = 1; i <= n; i += 1) {
        onProgress?.(i - 1, n);
        const page = await doc.getPage(i);
        const base = page.getViewport({ scale: 1 });
        const scale = Math.min(2.5, 1600 / base.width, 1600 / base.height);
        const vp = page.getViewport({ scale });
        const c = document.createElement('canvas');
        c.width = Math.round(vp.width); c.height = Math.round(vp.height);
        const g = c.getContext('2d');
        g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height);
        await page.render({ canvasContext: g, viewport: vp }).promise;
        const blob = await new Promise((res) => c.toBlob(res, 'image/jpeg', 0.84));
        urls.push(await upload(blob, uploadUrl, `page-${i}.jpg`));
    }
    onProgress?.(n, n);
    return urls;
}
