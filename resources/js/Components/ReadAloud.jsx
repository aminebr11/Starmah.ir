import { useEffect } from 'react';
import useSpeech from '@/hooks/useSpeech';

/** دکمه‌ی «🔊 بخوان برایم» — متنِ سؤال را با صدای فارسیِ دستگاه می‌خواند. */
export default function ReadAloud({ text, style = {} }) {
    const { supported, ready, speaking, speak, stop } = useSpeech();

    // با عوض‌شدنِ سؤال، خواندنِ قبلی قطع شود
    useEffect(() => stop, [text, stop]);

    const disabled = ready && !supported;
    return (
        <button type="button"
            onClick={() => (speaking ? stop() : speak(text))}
            disabled={!ready || disabled}
            title={disabled ? 'صدای فارسی روی این دستگاه نصب نیست' : speaking ? 'توقف' : 'سؤال را برایم بخوان'}
            aria-label={speaking ? 'توقفِ خواندن' : 'سؤال را بلند بخوان'}
            style={{
                display: 'inline-flex', alignItems: 'center', gap: 6, border: 0, cursor: disabled ? 'not-allowed' : 'pointer',
                fontFamily: 'inherit', fontWeight: 800, fontSize: 13, borderRadius: 30, padding: '6px 12px',
                background: speaking ? 'var(--acc,#4fd2ff)' : '#fff', color: '#0e2a5e',
                boxShadow: '0 4px 0 rgba(0,0,0,.3)', opacity: disabled ? 0.55 : 1, ...style,
            }}>
            <span aria-hidden>{speaking ? '⏹️' : '🔊'}</span>
            {disabled ? 'صدای فارسی نیست' : speaking ? 'در حالِ خواندن…' : 'بخوان برایم'}
        </button>
    );
}
