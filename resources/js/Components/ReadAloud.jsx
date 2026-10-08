import { useEffect } from 'react';
import useSpeech from '@/hooks/useSpeech';

/**
 * دکمه‌ی «🔊 بخوان برایم» — متنِ سؤال را به فارسی می‌خواند (صدای دستگاه یا صدای فارسیِ سرور).
 * اگر هیچ صدای فارسی‌ای در دسترس نباشد، دکمه نمایش داده نمی‌شود.
 */
export default function ReadAloud({ text, style = {} }) {
    const { supported, ready, speaking, loading, speak, stop } = useSpeech();

    // با عوض‌شدنِ سؤال، خواندنِ قبلی قطع شود
    useEffect(() => stop, [text, stop]);

    if (!ready || !supported) return null;
    return (
        <button type="button"
            onClick={() => (speaking ? stop() : speak(text))}
            disabled={loading}
            title={speaking ? 'توقف' : 'سؤال را برایم بخوان'}
            aria-label={speaking ? 'توقفِ خواندن' : 'سؤال را بلند بخوان'}
            style={{
                display: 'inline-flex', alignItems: 'center', gap: 6, border: 0, cursor: 'pointer',
                fontFamily: 'inherit', fontWeight: 800, fontSize: 13, borderRadius: 30, padding: '6px 12px',
                background: speaking ? 'var(--acc,#4fd2ff)' : '#fff', color: '#0e2a5e',
                boxShadow: '0 4px 0 rgba(0,0,0,.3)', opacity: loading ? 0.7 : 1, ...style,
            }}>
            <span aria-hidden>{loading ? '⏳' : speaking ? '⏹️' : '🔊'}</span>
            {loading ? 'آماده می‌شود…' : speaking ? 'در حالِ خواندن…' : 'بخوان برایم'}
        </button>
    );
}
