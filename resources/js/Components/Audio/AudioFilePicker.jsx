import { useRef } from 'react';

/**
 * انتخابِ فایلِ صوتی که روی همه‌ی گوشی‌ها کار کند.
 * - به‌جای accept="audio/*" فهرستِ پسوند/نوع: آیفون با audio/* فایل‌های mp3ِ برنامه‌ی Files را خاکستری
 *   می‌کند (یا فقط کتابخانه‌ی موسیقی را باز می‌کند) و بعضی اندرویدها اصلاً اجازه‌ی انتخاب نمی‌دهند.
 * - input با display:none در بعضی مرورگرهای موبایل با لمسِ label باز نمی‌شود؛ پس دکمه، خودش input را باز می‌کند.
 */
export const AUDIO_ACCEPT = '.mp3,.m4a,.aac,.wav,.ogg,.oga,.opus,.webm,audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/aac,audio/wav,audio/x-wav,audio/ogg,audio/webm';

export default function AudioFilePicker({ onPick, label = '📁 انتخابِ فایلِ صوتی', className = 'btn btn-sm', busy = false, file = null, style }) {
    const ref = useRef(null);
    return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8, flexWrap: 'wrap', ...style }}>
            <button type="button" className={className} disabled={busy} onClick={() => ref.current?.click()}>{label}</button>
            {file && <small style={{ opacity: .8, maxWidth: 220, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>🎵 {file.name}</small>}
            <input ref={ref} type="file" accept={AUDIO_ACCEPT} tabIndex={-1} aria-hidden="true"
                style={{ position: 'absolute', width: 1, height: 1, opacity: 0, pointerEvents: 'none', overflow: 'hidden' }}
                onChange={(e) => { const f = e.target.files?.[0] || null; e.target.value = ''; onPick(f); }} />
        </span>
    );
}
