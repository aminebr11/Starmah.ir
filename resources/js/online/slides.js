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

let pdfjsP = null;
/** pdf.js فقط همین‌جا و فقط وقتی لازم است بارگذاری می‌شود. */
function pdfjs() {
    if (!pdfjsP) {
        pdfjsP = (async () => {
            const lib = await import('pdfjs-dist');
            // کارگرِ pdf.js به‌صورتِ فایلِ .js ساخته می‌شود (نه .mjs) تا روی هاستِ cPanel با نوعِ درست سرو شود
            const PdfWorker = (await import('pdfjs-dist/build/pdf.worker.min.mjs?worker')).default;
            lib.GlobalWorkerOptions.workerPort = new PdfWorker();
            return lib;
        })();
    }
    return pdfjsP;
}

/**
 * باز کردنِ PDF: از فایلِ محلی، یا از نشانیِ سرور. از سرور فقط تکه‌های لازم گرفته می‌شود
 * (کتابِ ۸۰ مگابایتی لازم نیست کامل دانلود شود تا صفحه‌ی ۳۵ نمایش داده شود).
 */
export async function openPdf(src) {
    const lib = await pdfjs();
    const opts = typeof src === 'string'
        ? { url: src, rangeChunkSize: 262144, disableAutoFetch: true, disableStream: true, withCredentials: true }
        : { data: src };
    return lib.getDocument(opts).promise;
}

/** یک صفحه → تصویرِ JPEG (حداکثر ۱۶۰۰ پیکسل؛ برای گوشیِ بچه‌ها سبک و برای خواندن واضح). */
export async function renderPdfPage(doc, i, max = 1600) {
    const page = await doc.getPage(i);
    const base = page.getViewport({ scale: 1 });
    const scale = Math.min(2.5, max / base.width, max / base.height);
    const vp = page.getViewport({ scale });
    const c = document.createElement('canvas');
    c.width = Math.round(vp.width); c.height = Math.round(vp.height);
    const g = c.getContext('2d');
    g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height);
    await page.render({ canvasContext: g, viewport: vp }).promise;
    const blob = await new Promise((res) => c.toBlob(res, 'image/jpeg', 0.85));
    page.cleanup();
    return blob;
}

async function importPdf(file, uploadUrl, onProgress) {
    const doc = await openPdf(await file.arrayBuffer());
    const n = Math.min(doc.numPages, 60);
    const urls = [];
    for (let i = 1; i <= n; i += 1) {
        onProgress?.(i - 1, n);
        urls.push(await upload(await renderPdfPage(doc, i), uploadUrl, `page-${i}.jpg`));
    }
    onProgress?.(n, n);
    return urls;
}
