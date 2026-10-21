const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * صفحه‌های کاربرگِ «بارگذاریِ فایل»: عکس‌ها زیرِ هم دیده و چاپ می‌شوند؛ PDF/Word دکمه‌ی بازکردن و دانلود دارد.
 * کاربرگِ یک‌فایلیِ قدیمی هم همین‌جا (یک صفحه) نشان داده می‌شود.
 */
export default function WorksheetPages({ pages = [], title, onOpen, dark = false }) {
    const many = pages.length > 1;
    return (
        <div className={`wsg ${dark ? 'dark' : ''}`}>
            {many && <div className="wsg-h no-print">📑 این کاربرگ {fa(pages.length)} صفحه دارد — همه را ببین، چاپ کن و پر کن.</div>}
            {pages.map((p) => (p.image ? (
                <figure key={p.n} className="wsg-page ws-sheet">
                    {many && <figcaption className="no-print">صفحه‌ی {fa(p.n)} از {fa(pages.length)}</figcaption>}
                    <img src={p.url} alt={`${title} — صفحه ${p.n}`} onClick={() => onOpen?.(p)} loading="lazy" />
                </figure>
            ) : (
                <div key={p.n} className="wsg-file no-print">
                    <span className="wsg-ic">{p.pdf ? '📄' : '📝'}</span>
                    <div>
                        <b>{many ? `صفحه‌ی ${fa(p.n)} — ` : ''}{p.pdf ? 'فایلِ PDF' : 'فایلِ Word'}</b>
                        <small>بازش کن، چاپ کن و پر کن.</small>
                    </div>
                    {(p.pdf || p.image) && <button type="button" className="btn btn-sm" onClick={() => onOpen?.(p)}>📂 بازکردن</button>}
                    <a href={p.url} download className="btn btn-sm btn-ghost">⬇️ دانلود</a>
                </div>
            )))}
        </div>
    );
}
