import { useForm, router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import axios from 'axios';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const card = { background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 16, color: '#fff' };

/** کاربرگِ دانش‌آموز — چاپ/دانلود (امتیازِ یک‌بار) + ارسالِ کاربرگِ پرشده به معلم. */
export default function WorksheetView() {
    const { worksheet, submitted, downloadXp = 5, submitXp = 15, downloaded = false } = usePage().props;
    const fileRef = useRef(null);
    const [dlToast, setDlToast] = useState(downloaded ? null : `این کاربرگ را دریافت کن تا +${fa(downloadXp)} امتیاز بگیری`);
    const [gotDl, setGotDl] = useState(downloaded);

    // ثبتِ دریافت (یک‌بار امتیاز) هنگام چاپ یا دانلودِ فایل
    const markDownloaded = async () => {
        try {
            const { data } = await axios.post(route('my.worksheet.download', worksheet.id));
            if (data.ok && data.gained > 0) { setGotDl(true); setDlToast(`+${fa(data.gained)} امتیاز برای دریافتِ کاربرگ گرفتی 🎉`); }
        } catch (e) { /* بی‌صدا */ }
    };
    const print = () => { markDownloaded(); window.print(); };

    const form = useForm({ file: null, note: '' });
    const send = (e) => {
        e.preventDefault();
        form.post(route('my.worksheet.submit', worksheet.id), {
            forceFormData: true, preserveScroll: true,
            onSuccess: () => { form.reset(); if (fileRef.current) fileRef.current.value = ''; },
        });
    };

    return (
        <ThemedDash title={worksheet.title} active="homework">
            <style>{`@media print { .dash-topbar, .dash-side, .bottom-nav, .no-print { display:none !important; } .dash-main { padding:0 !important; } .ws-sheet { box-shadow:none !important; background:#fff !important; } }`}</style>

            <div className="no-print" style={{ ...card, display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                <b style={{ fontSize: 16 }}>🎨 {worksheet.title}</b>
                <span style={{ marginInlineStart: 'auto', display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {worksheet.file && <a href={worksheet.file} target="_blank" rel="noreferrer" onClick={markDownloaded} style={{ background: 'linear-gradient(135deg,#3d7bf0,#2555c0)', color: '#fff', padding: '9px 18px', borderRadius: 12, fontWeight: 800, textDecoration: 'none' }}>⬇️ دانلود فایلِ کاربرگ</a>}
                    <button onClick={print} style={{ background: 'linear-gradient(135deg,var(--p1),var(--p2))', color: '#fff', border: 0, padding: '9px 18px', borderRadius: 12, fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit' }}>🖨️ چاپ کاربرگ</button>
                </span>
            </div>

            {dlToast && <div className="no-print" style={{ ...card, marginTop: 10, background: gotDl ? 'rgba(43,182,115,.18)' : 'rgba(240,149,46,.16)', border: `1px solid ${gotDl ? 'rgba(43,182,115,.5)' : 'rgba(240,149,46,.5)'}`, fontSize: 13.5, fontWeight: 700 }}>{gotDl ? '✅' : '⚡'} {dlToast}</div>}

            {/* تصویر سرلوحه است و جایِ سؤال‌ها را نمی‌گیرد */}
            {worksheet.image && (
                <div className="ws-sheet" style={{ marginTop: 12, textAlign: 'center' }}>
                    <img src={worksheet.image} alt={worksheet.title} style={{ maxWidth: '100%', borderRadius: 16 }} />
                </div>
            )}
            {worksheet.html
                ? <div className="ws-sheet" style={{ marginTop: 12 }} dangerouslySetInnerHTML={{ __html: worksheet.html }} />
                : worksheet.file
                    ? <div className="ws-sheet no-print" style={{ marginTop: 12, textAlign: 'center', padding: 30 }}><div style={{ fontSize: 40 }}>📄</div><p style={{ opacity: .85 }}>این کاربرگ یک فایلِ آماده است. با دکمه‌ی «دانلود فایلِ کاربرگ» آن را بگیر، چاپ کن و پر کن.</p></div>
                    : null}

            {/* ارسالِ کاربرگِ پرشده */}
            <div className="no-print" style={{ ...card, marginTop: 16 }}>
                <b style={{ fontSize: 15 }}>📤 ارسالِ کاربرگِ پرشده برای معلم</b>
                <p style={{ opacity: .8, fontSize: 13, marginTop: 4 }}>کاربرگ را چاپ/دانلود کن، با دست پرش کن، سپس عکس یا فایلش را این‌جا برای معلم بفرست. با اولین ارسال +{fa(submitXp)} امتیاز می‌گیری؛ اگر پاسخت درست باشد، معلم در امتیازِ گروهی برایت امتیازِ بیشتر ثبت می‌کند.</p>
                {submitted && (
                    <div style={{ background: 'rgba(43,182,115,.18)', border: '1px solid rgba(43,182,115,.5)', borderRadius: 12, padding: '10px 14px', marginTop: 8 }}>
                        ✅ قبلاً فرستادی ({submitted.date}) — <a href={submitted.url} target="_blank" rel="noreferrer" style={{ color: '#9be7bd', fontWeight: 800 }}>مشاهده</a>. می‌توانی نسخه‌ی جدید بفرستی.
                    </div>
                )}
                <form onSubmit={send} style={{ marginTop: 10 }}>
                    <input ref={fileRef} type="file" accept="image/*,application/pdf" onChange={(e) => form.setData('file', e.target.files[0] || null)}
                        style={{ width: '100%', background: '#fff', color: '#1b2742', borderRadius: 12, padding: 10, border: 0 }} />
                    {form.errors.file && <div style={{ color: '#ff8f9a', fontSize: 12.5, marginTop: 4 }}>{form.errors.file}</div>}
                    <input value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} placeholder="توضیح (اختیاری)"
                        style={{ width: '100%', marginTop: 8, background: '#fff', color: '#1b2742', borderRadius: 12, padding: 10, border: 0, fontFamily: 'inherit' }} />
                    <button type="submit" disabled={form.processing || !form.data.file} style={{ marginTop: 10, background: 'linear-gradient(135deg,#2bb673,#0f9d58)', color: '#fff', border: 0, padding: '11px 20px', borderRadius: 12, fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit' }}>{form.processing ? 'در حال ارسال…' : '📨 ارسال برای معلم'}</button>
                </form>
            </div>
        </ThemedDash>
    );
}
