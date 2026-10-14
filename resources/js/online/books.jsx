import { useEffect, useState } from 'react';
import axios from 'axios';
import { fa } from '@/online/parts';

const CHUNK = 4 * 1024 * 1024;   // هاست‌ها معمولاً فایلِ بزرگ را یک‌جا نمی‌پذیرند
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** بارگذاریِ تکه‌تکه‌ی PDF؛ اگر اینترنت لحظه‌ای قطع شد، همان تکه دوباره فرستاده می‌شود. */
async function uploadBook(file, title, url, onProgress) {
    const id = (Math.random().toString(36).slice(2) + Date.now().toString(36)).replace(/[^a-z0-9]/gi, '').slice(0, 30);
    const total = Math.max(1, Math.ceil(file.size / CHUNK));
    let out = null;
    for (let i = 0; i < total; i += 1) {
        const fd = new FormData();
        fd.append('upload', id); fd.append('seq', String(i)); fd.append('total', String(total)); fd.append('title', title);
        fd.append('chunk', file.slice(i * CHUNK, (i + 1) * CHUNK), 'part.bin');
        for (let t = 0; ; t += 1) {
            try {
                out = (await axios.post(url, fd, { timeout: 180000, onUploadProgress: (e) => onProgress(Math.min(1, (i * CHUNK + (e.loaded || 0)) / file.size)) })).data;
                break;
            } catch (e) {
                const st = e?.response?.status;
                if (t >= 2 || st === 422 || st === 413 || st === 403) throw e;
                await sleep(1500 * (t + 1));
            }
        }
    }
    return out?.book;
}

/**
 * «📚 کتاب‌های درسیِ من»: کتاب‌ها روی سرور می‌مانند؛ هر بار از همان صفحه‌ای که رسیده بودید باز می‌شوند
 * و نوشته‌هایتان روی همان صفحه‌ها می‌ماند.
 */
export default function BookPanel({ listUrl, uploadUrl, deleteUrl, current, onOpen, onClose }) {
    const [books, setBooks] = useState(null);
    const [maxMb, setMaxMb] = useState(300);
    const [file, setFile] = useState(null);
    const [title, setTitle] = useState('');
    const [prog, setProg] = useState(null);
    const [err, setErr] = useState(null);

    const load = () => axios.get(listUrl).then(({ data }) => { setBooks(data.books); setMaxMb(data.max_mb || 300); }).catch(() => setBooks([]));
    useEffect(() => { load(); /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, []);

    const pick = (f) => {
        setErr(null);
        if (!f) return;
        if (!/pdf$/i.test(f.type) && !/\.pdf$/i.test(f.name)) { setErr('فقط فایلِ PDF (کتابِ درسی را از سایتِ کتاب‌های درسی به‌صورتِ PDF بگیرید).'); return; }
        if (f.size > maxMb * 1048576) { setErr(`حجمِ کتاب بیش از ${fa(maxMb)} مگابایت است.`); return; }
        setFile(f);
        setTitle(f.name.replace(/\.pdf$/i, '').replace(/[_-]+/g, ' ').trim().slice(0, 150));
    };
    const send = async () => {
        if (!file || !title.trim()) return;
        setErr(null); setProg(0);
        try {
            const b = await uploadBook(file, title.trim(), uploadUrl, setProg);
            setFile(null); setTitle(''); setProg(null);
            await load();
            if (b) onOpen(b);
        } catch (e) {
            setProg(null);
            setErr(e?.response?.data?.message || 'بارگذاری انجام نشد؛ اینترنت را بررسی کنید و دوباره بفرستید.');
        }
    };
    const del = async (b) => {
        if (!confirm(`کتابِ «${b.title}» و همه‌ی نوشته‌های روی آن حذف شود؟`)) return;
        await axios.delete(deleteUrl(b.id)).catch(() => {});
        load();
    };

    return (
        <div className="oc-modal" role="dialog" aria-label="کتاب‌های درسی" onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
            <div className="oc-modal-box oc-books">
                <div className="oc-books-h"><b>📚 کتاب‌های درسیِ من</b><button type="button" onClick={onClose} aria-label="بستن">×</button></div>
                <p className="oc-books-note">کتاب روی سرور می‌ماند؛ هر بار از همان صفحه‌ای که رسیده بودید باز می‌شود و نوشته‌هایتان روی صفحه‌ها می‌ماند.</p>

                <div className="oc-books-up">
                    <label className="oc-file">📄 انتخابِ PDFِ کتاب<input type="file" accept="application/pdf,.pdf" onChange={(e) => { pick(e.target.files?.[0]); e.target.value = ''; }} /></label>
                    {file && (
                        <>
                            <input className="input" value={title} onChange={(e) => setTitle(e.target.value)} maxLength={150} placeholder="نامِ کتاب، مثلاً ریاضیِ سوم" />
                            <small>{file.name} · {fa((file.size / 1048576).toFixed(1))} مگ</small>
                            {prog === null
                                ? <button type="button" className="oc-go" onClick={send} disabled={!title.trim()}>⬆️ بارگذاری و باز کردن</button>
                                : <div className="oc-books-bar"><i style={{ width: `${Math.round(prog * 100)}%` }} /><span>{fa(Math.round(prog * 100))}٪</span></div>}
                        </>
                    )}
                    {err && <div className="oc-err">{err}</div>}
                </div>

                {books === null && <div className="oc-empty">…</div>}
                {books && books.length === 0 && <div className="oc-empty">هنوز کتابی نگذاشته‌اید.</div>}
                <div className="oc-books-l">
                    {(books || []).map((b) => (
                        <div key={b.id} className={`oc-book ${current === b.id ? 'on' : ''}`}>
                            <span className="oc-book-i">📘</span>
                            <div className="oc-book-b">
                                <b>{b.title}</b>
                                <small>{b.pages ? `${fa(b.pages)} صفحه · ` : ''}{b.size_mb >= 1 ? `${fa(b.size_mb)} مگ` : 'کمتر از ۱ مگ'}{b.notes ? ` · ✏️ ${fa(b.notes)} صفحه نوشته` : ''}</small>
                            </div>
                            <button type="button" className="oc-book-go" onClick={() => onOpen(b)}>{b.page > 1 ? `▶️ ادامه از صفحه‌ی ${fa(b.page)}` : '▶️ باز کردن'}</button>
                            <button type="button" className="oc-book-x" onClick={() => del(b)} aria-label={`حذفِ ${b.title}`}>🗑️</button>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
