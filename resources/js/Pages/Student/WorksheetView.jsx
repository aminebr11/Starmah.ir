import { useForm, router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import axios from 'axios';
import ThemedDash from '@/Layouts/ThemedDash';
import SubmissionViewer from '@/Components/SubmissionViewer';
import FileViewer from '@/Components/FileViewer';
import shrinkImage from '@/lib/shrinkImage';
import stitchPages from '@/lib/stitchPages';
import WorksheetPages from '@/Components/WorksheetPages';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const card = { background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 16, color: '#fff' };

/** کاربرگِ دانش‌آموز — چاپ/دانلود (امتیازِ یک‌بار) + ارسالِ کاربرگِ پرشده به معلم. */
export default function WorksheetView() {
    const { worksheet, submitted, downloadXp = 5, submitXp = 15, downloaded = false } = usePage().props;
    const fileRef = useRef(null);
    const [dlToast, setDlToast] = useState(downloaded || !(downloadXp > 0) ? null : `این کاربرگ را دریافت کن تا +${fa(downloadXp)} امتیاز بگیری`);
    const [gotDl, setGotDl] = useState(downloaded);
    const [viewing, setViewing] = useState(false);
    const [showFile, setShowFile] = useState(null);

    // ثبتِ دریافت (یک‌بار امتیاز) هنگام چاپ یا دانلودِ فایل
    const markDownloaded = async () => {
        try {
            const { data } = await axios.post(route('my.worksheet.download', worksheet.id));
            if (data.ok && data.gained > 0) { setGotDl(true); setDlToast(`+${fa(data.gained)} امتیاز برای دریافتِ کاربرگ گرفتی 🎉`); }
        } catch (e) { /* بی‌صدا */ }
    };
    const print = () => { markDownloaded(); window.print(); };

    const form = useForm({ file: null, note: '' });
    // صفحه‌های انتخاب‌شده (عکس‌ها به ترتیب، یا یک PDF)
    const [pages, setPages] = useState([]);
    const [prep, setPrep] = useState(false);
    const [pickErr, setPickErr] = useState(null);
    const addPages = (list) => {
        const picked = Array.from(list || []);
        setPickErr(null);
        setPages((cur) => {
            const pdf = picked.find((f) => f.type === 'application/pdf' || /\.pdf$/i.test(f.name));
            if (pdf) { if (picked.length > 1 || cur.length) setPickErr('PDF را تنها بفرست؛ برای چند صفحه، از هر صفحه عکس بگیر.'); return [{ file: pdf, url: null, pdf: true }]; }
            const base = cur.some((p) => p.pdf) ? [] : cur;
            const imgs = picked.filter((f) => f.type.startsWith('image/')).map((file) => ({ file, url: URL.createObjectURL(file), pdf: false }));
            if (base.length + imgs.length > 8) setPickErr('حداکثر ۸ صفحه.');
            return [...base, ...imgs].slice(0, 8);
        });
    };
    const movePage = (i, d) => setPages((cur) => { const a = [...cur]; const j = i + d; if (j < 0 || j >= a.length) return a; [a[i], a[j]] = [a[j], a[i]]; return a; });
    const send = async (e) => {
        e.preventDefault();
        if (!pages.length) return;
        setPrep(true);
        let file;
        try {
            file = pages.length > 1 ? await stitchPages(pages.map((p) => p.file), 'worksheet') : await shrinkImage(pages[0].file);
        } catch {
            setPrep(false); setPickErr('آماده‌کردنِ صفحه‌ها نشد؛ صفحه‌ها را یکی‌یکی کم کن و دوباره امتحان کن.'); return;
        }
        setPrep(false);
        form.transform((d) => ({ ...d, file }));
        form.post(route('my.worksheet.submit', worksheet.id), {
            forceFormData: true, preserveScroll: true,
            onSuccess: () => { form.reset(); setPages([]); if (fileRef.current) fileRef.current.value = ''; },
        });
    };

    return (
        <ThemedDash title={worksheet.title} active="homework">
            <style>{`@media print { .dash-topbar, .dash-side, .bottom-nav, .no-print { display:none !important; } .dash-main { padding:0 !important; } .ws-sheet { box-shadow:none !important; background:#fff !important; } }`}</style>

            <div className="no-print" style={{ ...card, display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                <b style={{ fontSize: 16 }}>🎨 {worksheet.title}</b>
                <span style={{ marginInlineStart: 'auto', display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {worksheet.file && (worksheet.pages?.length || 0) <= 1 && <button type="button" onClick={() => { markDownloaded(); setShowFile({ url: worksheet.file, pdf: worksheet.file_pdf }); }} style={{ background: 'linear-gradient(135deg,#3d7bf0,#2555c0)', color: '#fff', padding: '9px 18px', borderRadius: 12, fontWeight: 800, border: 0, cursor: 'pointer', fontFamily: 'inherit' }}>📂 بازکردن / دانلودِ کاربرگ</button>}
                    <button onClick={print} style={{ background: 'linear-gradient(135deg,var(--p1),var(--p2))', color: '#fff', border: 0, padding: '9px 18px', borderRadius: 12, fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit' }}>🖨️ چاپ کاربرگ</button>
                </span>
            </div>

            {dlToast && <div className="no-print" style={{ ...card, marginTop: 10, background: gotDl ? 'rgba(43,182,115,.18)' : 'rgba(240,149,46,.16)', border: `1px solid ${gotDl ? 'rgba(43,182,115,.5)' : 'rgba(240,149,46,.5)'}`, fontSize: 13.5, fontWeight: 700 }}>{gotDl ? '✅' : '⚡'} {dlToast}</div>}

            {/* یک برگه‌ی یکپارچه: تصویر سرلوحه‌ی خودِ کاربرگ است و سؤال‌ها داخلش */}
            {worksheet.html
                ? <div className="ws-sheet" style={{ marginTop: 12 }} dangerouslySetInnerHTML={{ __html: worksheet.html }} />
                : worksheet.image
                ? <div className="ws-sheet" style={{ marginTop: 12, textAlign: 'center' }}><img src={worksheet.image} alt={worksheet.title} onClick={() => setShowFile({ url: worksheet.image, pdf: false })} style={{ maxWidth: '100%', borderRadius: 16, cursor: 'zoom-in' }} /></div>
                : worksheet.pages?.length
                    ? <WorksheetPages pages={worksheet.pages} title={worksheet.title} onOpen={(p) => { markDownloaded(); setShowFile({ url: p.url, pdf: p.pdf }); }} dark />
                    : null}

            {/* ارسالِ کاربرگِ پرشده */}
            <div className="no-print" style={{ ...card, marginTop: 16 }}>
                <b style={{ fontSize: 15 }}>📤 ارسالِ کاربرگِ پرشده برای معلم</b>
                <p style={{ opacity: .8, fontSize: 13, marginTop: 4 }}>کاربرگ را چاپ/دانلود کن، با دست پرش کن، سپس عکس یا فایلش را این‌جا برای معلم بفرست.{submitXp > 0 ? ` با اولین ارسال +${fa(submitXp)} امتیاز می‌گیری؛` : ''} اگر پاسخت درست باشد، معلم در امتیازِ گروهی برایت امتیازِ بیشتر ثبت می‌کند.</p>
                {submitted && (
                    <div style={{ background: 'rgba(43,182,115,.18)', border: '1px solid rgba(43,182,115,.5)', borderRadius: 12, padding: '10px 14px', marginTop: 8 }}>
                        ✅ قبلاً فرستادی ({submitted.date}) — <button type="button" onClick={() => setViewing(true)} style={{ background: 'none', border: 0, padding: 0, color: '#9be7bd', fontWeight: 800, font: 'inherit', cursor: 'pointer', textDecoration: 'underline' }}>مشاهده</button>. می‌توانی نسخه‌ی جدید بفرستی.
                        {submitted.graded ? (
                            <div style={{ marginTop: 8, background: 'rgba(0,0,0,.2)', borderRadius: 10, padding: '8px 12px', lineHeight: 1.9 }}>
                                ✍️ <b>معلم تصحیح کرد</b>{submitted.grade ? <> — نمره: <b>{submitted.grade}</b></> : null}{submitted.xp > 0 ? <> — ⚡ +{fa(submitted.xp)} امتیاز</> : null}
                                {submitted.feedback && <div>💬 {submitted.feedback}</div>}
                                {submitted.marked_url && <button type="button" onClick={() => setViewing(true)} className="k3-btn" style={{ marginTop: 6, fontSize: 13 }}>🖍️ دیدنِ برگه‌ی تصحیح‌شده</button>}
                            </div>
                        ) : <div style={{ marginTop: 4, fontSize: 12.5, opacity: .85 }}>⏳ منتظرِ تصحیحِ معلم</div>}
                    </div>
                )}
                <form onSubmit={send} style={{ marginTop: 10 }}>
                    <input ref={fileRef} type="file" multiple accept="image/*,application/pdf" hidden onChange={(e) => { addPages(e.target.files); e.target.value = ''; }} />
                    {pages.length === 0 ? (
                        <button type="button" className="wsp-pick" onClick={() => fileRef.current?.click()}>
                            <span>📷</span><b>عکسِ صفحه‌ها را انتخاب کن</b>
                            <small>کاربرگت چند صفحه است؟ همه‌ی صفحه‌ها را با هم انتخاب کن (یا یکی‌یکی اضافه کن)؛ همه در یک فایل برای معلم می‌رود.</small>
                        </button>
                    ) : (
                        <div className="wsp-list">
                            {pages.map((p, i) => (
                                <div key={i} className="wsp-item">
                                    {p.pdf ? <div className="wsp-pdf">📄<small>{p.file.name}</small></div> : <img src={p.url} alt={`صفحه ${i + 1}`} />}
                                    <span className="wsp-n">صفحه‌ی {fa(i + 1)}</span>
                                    <div className="wsp-tools">
                                        {pages.length > 1 && <button type="button" onClick={() => movePage(i, -1)} disabled={i === 0} aria-label="قبل">→</button>}
                                        {pages.length > 1 && <button type="button" onClick={() => movePage(i, 1)} disabled={i === pages.length - 1} aria-label="بعد">←</button>}
                                        <button type="button" className="x" onClick={() => setPages((cur) => cur.filter((_, k) => k !== i))} aria-label="برداشتن">✕</button>
                                    </div>
                                </div>
                            ))}
                            {!pages.some((p) => p.pdf) && pages.length < 8 && (
                                <button type="button" className="wsp-add" onClick={() => fileRef.current?.click()}><span>➕</span>صفحه‌ی بعد</button>
                            )}
                        </div>
                    )}
                    {pickErr && <div style={{ color: '#ffcf70', fontSize: 12.5, marginTop: 6 }}>{pickErr}</div>}
                    {form.errors.file && <div style={{ color: '#ff8f9a', fontSize: 12.5, marginTop: 4 }}>{form.errors.file}</div>}
                    <input value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} placeholder="توضیح (اختیاری)"
                        style={{ width: '100%', marginTop: 8, background: '#fff', color: '#1b2742', borderRadius: 12, padding: 10, border: 0, fontFamily: 'inherit' }} />
                    <button type="submit" disabled={form.processing || prep || !pages.length} style={{ marginTop: 10, background: 'linear-gradient(135deg,#2bb673,#0f9d58)', color: '#fff', border: 0, padding: '11px 20px', borderRadius: 12, fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit' }}>
                        {prep ? '⏳ آماده‌کردنِ صفحه‌ها…' : form.processing ? `در حال ارسال…${form.progress?.percentage != null ? ` ${fa(form.progress.percentage)}٪` : ''}` : pages.length > 1 ? `📨 ارسالِ ${fa(pages.length)} صفحه برای معلم` : '📨 ارسال برای معلم'}
                    </button>
                </form>
            </div>
            {viewing && submitted && <SubmissionViewer items={[submitted]} onClose={() => setViewing(false)} />}
            {showFile && <FileViewer url={showFile.url} pdf={showFile.pdf} title={worksheet.title} onClose={() => setShowFile(null)} />}
        </ThemedDash>
    );
}
