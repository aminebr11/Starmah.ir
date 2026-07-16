import { useState, useRef, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const TYPE = {
    material: { icon: '📄', label: 'جزوه و فایل', c1: '#3d7bf0', c2: '#2555c0' },
    podcast:  { icon: '🎧', label: 'پادکست', c1: '#a24cf0', c2: '#6f2fb0' },
    gallery:  { icon: '🖼️', label: 'گالری', c1: '#f0952e', c2: '#c06712' },
};

const isImageUrl = (u) => /\.(png|jpe?g|gif|webp|svg|bmp)$/i.test(u || '');
const isAudioUrl = (u) => /\.(mp3|m4a|aac|ogg|wav|opus)$/i.test(u || '');

export default function ClassContent() {
    const { items = [] } = usePage().props;

    return (
        <ThemedDash title="محتوای کلاس" active="content">
            <div className="k3-card">
                <div style={{ fontWeight: 900, fontSize: 18, marginBottom: 4 }}>📚 محتوای کلاس تو</div>
                <div style={{ opacity: .8, fontSize: 13 }}>جزوه‌ها، پادکست‌ها و گالریِ کلاس که معلم برایت گذاشته. گوش دادن به پادکست امتیاز (XP) دارد!</div>
            </div>

            {items.length === 0 && (
                <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', opacity: .8 }}>
                    هنوز محتوایی اضافه نشده 📭
                </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(280px,1fr))', gap: 14, marginTop: 14 }}>
                {items.map((it) => <ContentCard key={it.id} it={it} />)}
            </div>
        </ThemedDash>
    );
}

function ContentCard({ it }) {
    const t = TYPE[it.type] ?? TYPE.material;
    const [viewed, setViewed] = useState(it.viewed);
    const [xp, setXp] = useState(it.my_xp || 0);
    const [toast, setToast] = useState(null);
    const audioRef = useRef(null);
    const lastSentRef = useRef(it.my_seconds || 0);

    const sendProgress = async (seconds, finished = false) => {
        try {
            const { data } = await axios.post(route('my.content.progress', it.id), { seconds: Math.round(seconds), finished });
            if (data.ok) {
                setViewed(true);
                if (data.xp > xp) {
                    if (data.gained > 0) setToast(`+${fa(data.gained)} XP`);
                    setXp(data.xp);
                }
            }
        } catch (e) { /* بی‌صدا */ }
    };

    // عکس/جزوه: با اولین دیدن، بازدید ثبت شود (یک‌بار XP)
    const markViewed = () => { if (!viewed) sendProgress(0, true); };

    // پادکست: ردیابی ثانیه‌ی گوش‌داده‌شده
    const isPodcast = it.type === 'podcast' || (it.is_file && isAudioUrl(it.url));
    const isImage = it.type === 'gallery' || (it.is_file && isImageUrl(it.url));

    useEffect(() => {
        const el = audioRef.current;
        if (!el) return;
        let iv = null;
        const flush = () => {
            const cur = Math.max(lastSentRef.current, Math.floor(el.currentTime || 0));
            if (cur > lastSentRef.current) { lastSentRef.current = cur; sendProgress(cur); }
        };
        const onPlay = () => { iv = setInterval(flush, 10000); };
        const onStop = () => { if (iv) { clearInterval(iv); iv = null; } flush(); };
        el.addEventListener('play', onPlay);
        el.addEventListener('pause', onStop);
        el.addEventListener('ended', () => sendProgress(Math.floor(el.duration || el.currentTime || 0), true));
        return () => { if (iv) clearInterval(iv); el.removeEventListener('play', onPlay); el.removeEventListener('pause', onStop); };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => { if (toast) { const h = setTimeout(() => setToast(null), 2200); return () => clearTimeout(h); } }, [toast]);

    return (
        <div className="k3-card" style={{ borderTop: `4px solid ${t.c1}`, position: 'relative' }}>
            {toast && <div style={{ position: 'absolute', top: 8, insetInlineEnd: 8, background: '#2bb673', color: '#fff', fontWeight: 900, fontSize: 12, borderRadius: 20, padding: '4px 10px', boxShadow: '0 4px 12px -4px rgba(0,0,0,.4)' }}>{toast}</div>}
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <span style={{ width: 44, height: 44, borderRadius: 12, display: 'grid', placeItems: 'center', fontSize: 22, background: `linear-gradient(135deg,${t.c1},${t.c2})` }}>{t.icon}</span>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 800, fontSize: 15 }}>{it.title}</div>
                    <div style={{ fontSize: 11.5, opacity: .7 }}>{t.label} · {it.date}</div>
                </div>
                {viewed && <span title="دیده‌شده" style={{ fontSize: 12, fontWeight: 800, color: '#2bb673' }}>✓{xp > 0 ? ` ⚡${fa(xp)}` : ''}</span>}
            </div>

            {it.description && <div style={{ fontSize: 13, opacity: .85, marginTop: 8, lineHeight: 1.9 }}>{it.description}</div>}

            {/* پادکست: پخش‌کننده‌ی درون‌برنامه با ردیابی */}
            {isPodcast && it.url && (
                <div style={{ marginTop: 10 }}>
                    <audio ref={audioRef} src={it.url} controls preload="metadata" style={{ width: '100%' }} onPlay={markViewed} />
                    <div style={{ fontSize: 11.5, opacity: .7, marginTop: 4 }}>🎧 گوش دادن به این پادکست امتیاز دارد (هر ۱۵ ثانیه ۱ XP، تا سقف ۲۰).</div>
                </div>
            )}

            {/* عکس/گالری: نمایش درون‌برنامه */}
            {isImage && it.url && (
                <a href={it.url} target="_blank" rel="noreferrer" onClick={markViewed}>
                    <img src={it.url} alt={it.title} loading="lazy" onLoad={markViewed}
                        style={{ width: '100%', borderRadius: 12, marginTop: 10, maxHeight: 260, objectFit: 'cover' }} />
                </a>
            )}

            {/* جزوه/فایل دیگر: دانلود/مشاهده */}
            {!isPodcast && !isImage && it.url && (
                <a href={it.url} target={it.is_file ? '_blank' : undefined} rel="noreferrer" onClick={markViewed}
                    style={{ display: 'inline-block', marginTop: 10, fontWeight: 800, fontSize: 13, color: t.c1, textDecoration: 'none' }}>
                    {it.is_file ? '⬇️ دانلود / مشاهده' : '🔗 مشاهده'} ←
                </a>
            )}
        </div>
    );
}
