/**
 * چند عکسِ صفحه (کاربرگِ چندصفحه‌ای) → یک فایلِ JPEG، صفحه‌ها زیرِ هم با شماره‌ی صفحه.
 *
 * چرا یک فایل: معلم همان یک برگه را می‌بیند، روی همه‌ی صفحه‌ها پشتِ هم تیک/ضربدر می‌زند و یک
 * نمره می‌دهد؛ تصحیح، امتیاز و پاسخ به دانش‌آموز مثلِ کاربرگِ یک‌صفحه‌ای کار می‌کند.
 * اندازه طوری انتخاب می‌شود که از سقفِ بومِ (canvas) گوشی‌ها — حدودِ ۱۶ میلیون پیکسل — بیشتر نشود.
 */
const MAX_W = 1400;
const MAX_AREA = 14_000_000;
const gapFor = (w) => Math.max(34, Math.round(w * 0.05));

async function load(file) {
    if (typeof createImageBitmap === 'function') {
        try { return await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch { /* پشتیبان */ }
    }
    const url = URL.createObjectURL(file);
    try {
        return await new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = url; });
    } finally {
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
}

export default async function stitchPages(files, name = 'pages') {
    const imgs = [];
    for (const f of files) imgs.push(await load(f));
    const dims = imgs.map((im) => ({ w: im.width || im.naturalWidth, h: im.height || im.naturalHeight }));
    let W = Math.min(MAX_W, Math.max(...dims.map((d) => d.w)));
    const heightAt = (w) => dims.reduce((t, d) => t + Math.round(d.h * (w / d.w)), 0) + gapFor(w) * imgs.length;
    while (W > 500 && W * heightAt(W) > MAX_AREA) W = Math.floor(W * 0.9);
    const H = heightAt(W);
    const GAP = gapFor(W);
    const c = document.createElement('canvas');
    c.width = W; c.height = H;
    const g = c.getContext('2d');
    g.fillStyle = '#fff'; g.fillRect(0, 0, W, H);
    let y = 0;
    imgs.forEach((im, k) => {
        // نوارِ «صفحه‌ی n» بالای هر صفحه
        g.fillStyle = '#eef1f8'; g.fillRect(0, y, W, GAP);
        g.fillStyle = '#4a5578'; g.font = `bold ${Math.round(GAP * 0.55)}px Vazirmatn, Tahoma, sans-serif`;
        g.textAlign = 'center'; g.textBaseline = 'middle';
        g.fillText(`صفحه‌ی ${String(k + 1).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d])} از ${String(imgs.length).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d])}`, W / 2, y + GAP / 2);
        y += GAP;
        const h = Math.round(dims[k].h * (W / dims[k].w));
        g.drawImage(im, 0, y, W, h);
        y += h;
        im.close?.();
    });
    const blob = await new Promise((res) => c.toBlob(res, 'image/jpeg', 0.82));
    if (!blob) throw new Error('stitch failed');
    return new File([blob], `${name}-${imgs.length}p.jpg`, { type: 'image/jpeg' });
}
