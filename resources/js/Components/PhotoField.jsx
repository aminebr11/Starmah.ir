import { useEffect, useRef, useState } from 'react';
import { normalizeSquare, humanSize } from '@/lib/imageUpload';

/**
 * انتخاب یا گرفتنِ عکس، با پیش‌نمایشِ دایره‌ای.
 *
 * دو راه در اختیارِ کاربر است:
 *   • «گرفتن عکس»  → دوربینِ دستگاه (getUserMedia) با پیش‌نمایشِ زنده
 *   • «انتخاب از گالری» → ورودیِ فایلِ معمولی
 *
 * هر دو مسیر خروجی را از راهِ normalizeSquare عبور می‌دهند، پس فایلِ نهایی
 * همیشه JPEGِ مربعیِ کوچک است — مستقل از اینکه گوشی HEIC بدهد یا عکسِ ۸ مگابایتی.
 *
 * props:
 *   value    File|null        فایلِ فعلی
 *   onChange (File|null)=>{}  با فایلِ نرمال‌شده صدا زده می‌شود
 *   initial  string|null      نشانیِ عکسِ ذخیره‌شده‌ی قبلی (حالتِ ویرایش)
 *   label, hint, error, size
 */
export default function PhotoField({
    value = null,
    onChange,
    initial = null,
    label = 'عکس',
    hint = 'عکس در حسابِ کاربری ذخیره می‌شود.',
    error = null,
    size = 104,
}) {
    const [preview, setPreview] = useState(initial);
    const [busy, setBusy] = useState(false);
    const [camOpen, setCamOpen] = useState(false);
    const [camError, setCamError] = useState('');
    const [outSize, setOutSize] = useState(null);

    const fileRef = useRef(null);   // گالری (بدونِ capture)
    const camRef = useRef(null);    // دوربینِ خودِ گوشی (capture)
    const videoRef = useRef(null);
    const streamRef = useRef(null);

    // پیش‌نمایشِ فایلِ انتخابی
    useEffect(() => {
        if (!value) { setPreview(initial); setOutSize(null); return; }
        const url = URL.createObjectURL(value);
        setPreview(url);
        setOutSize(value.size);
        return () => URL.revokeObjectURL(url);
    }, [value, initial]);

    // بستنِ دوربین هنگامِ خروج
    useEffect(() => () => stopCam(), []);

    function stopCam() {
        if (streamRef.current) {
            streamRef.current.getTracks().forEach((t) => t.stop());
            streamRef.current = null;
        }
    }

    async function openCam() {
        setCamError('');
        // روی گوشی و تبلت (و داخلِ اپِ اندروید) دوربینِ خودِ دستگاه باز می‌شود:
        // کیفیتِ بهتر، بدونِ درخواستِ مجوزِ جدا، و در WebView هم کار می‌کند.
        const touch = typeof window !== 'undefined' && window.matchMedia?.('(pointer: coarse)').matches;
        if (touch || !navigator.mediaDevices?.getUserMedia) {
            camRef.current?.click();
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 1280 } },
                audio: false,
            });
            streamRef.current = stream;
            setCamOpen(true);
            // انتسابِ استریم پس از رندرِ ویدیو
            setTimeout(() => { if (videoRef.current) videoRef.current.srcObject = stream; }, 40);
        } catch (e) {
            const denied = e?.name === 'NotAllowedError';
            setCamError(denied
                ? 'اجازه‌ی دسترسی به دوربین داده نشد. می‌توانی از «انتخاب از گالری» استفاده کنی.'
                : 'دوربین در دسترس نیست. از «انتخاب از گالری» استفاده کن.');
            if (!denied) camRef.current?.click();
        }
    }

    function closeCam() { stopCam(); setCamOpen(false); }

    async function shoot() {
        const v = videoRef.current;
        if (!v) return;
        setBusy(true);
        try {
            const side = Math.min(v.videoWidth, v.videoHeight) || 480;
            const c = document.createElement('canvas');
            c.width = side; c.height = side;
            const ctx = c.getContext('2d');
            ctx.drawImage(v, (v.videoWidth - side) / 2, (v.videoHeight - side) / 2, side, side, 0, 0, side, side);
            const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.88));
            closeCam();
            if (blob) {
                const file = new File([blob], 'photo.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                onChange?.(await normalizeSquare(file));
            }
        } finally { setBusy(false); }
    }

    async function pickFile(e) {
        const f = e.target.files?.[0];
        e.target.value = '';
        if (!f) return;
        setBusy(true);
        if (f.type && !f.type.startsWith('image/')) {
            setCamError('فقط فایلِ تصویری (عکس) قابلِ انتخاب است.');
            setBusy(false);
            return;
        }
        setCamError('');
        try { onChange?.(await normalizeSquare(f)); }
        catch { setCamError('این عکس خوانده نشد. عکسِ دیگری انتخاب کن یا دوباره عکس بگیر.'); }
        finally { setBusy(false); }
    }

    return (
        <div className="photo-field">
            {label && <label className="photo-field-label">{label}</label>}

            <div className="photo-field-row">
                <div className="photo-avatar" style={{ width: size, height: size }}>
                    {preview
                        ? <img src={preview} alt="پیش‌نمایشِ عکس" />
                        : <span className="photo-avatar-ph" aria-hidden="true">📷</span>}
                    {busy && <span className="photo-busy">…</span>}
                </div>

                <div className="photo-field-actions">
                    <button type="button" className="btn btn-sm" onClick={openCam} disabled={busy}>
                        📸 گرفتن عکس
                    </button>
                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => fileRef.current?.click()} disabled={busy}>
                        🖼️ انتخاب از گالری
                    </button>
                    {(value || preview) && (
                        <button type="button" className="btn btn-ghost btn-sm photo-remove"
                            onClick={() => { onChange?.(null); setPreview(initial && !value ? null : initial); }}>
                            حذفِ عکس
                        </button>
                    )}
                    {/* گالری بدونِ capture — پیش از این هر دو دکمه ورودیِ capture داشتند و
                        «انتخاب از گالری» روی گوشی مستقیم دوربین را باز می‌کرد */}
                    <input ref={fileRef} type="file" accept="image/*"
                        onChange={pickFile} style={{ display: 'none' }} />
                    <input ref={camRef} type="file" accept="image/*" capture="user"
                        onChange={pickFile} style={{ display: 'none' }} />
                </div>
            </div>

            {outSize != null && (
                <div className="photo-field-hint">✅ آماده‌ی ارسال — {humanSize(outSize)} (به JPEG فشرده شد)</div>
            )}
            {hint && outSize == null && <div className="photo-field-hint">{hint}</div>}
            {camError && <div className="photo-field-err">{camError}</div>}
            {error && <div className="photo-field-err">{error}</div>}

            {camOpen && (
                <div className="cam-modal" role="dialog" aria-label="گرفتنِ عکس">
                    <div className="cam-box">
                        <video ref={videoRef} autoPlay playsInline muted />
                        <div className="cam-actions">
                            <button type="button" className="btn" onClick={shoot} disabled={busy}>📸 بگیر</button>
                            <button type="button" className="btn btn-ghost" onClick={closeCam}>انصراف</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
