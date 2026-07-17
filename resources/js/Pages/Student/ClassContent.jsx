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

const TABS = [
    { v: 'material', ic: '📄', t: 'جزوه و فایل' },
    { v: 'podcast', ic: '🎧', t: 'پادکست' },
    { v: 'gallery', ic: '🖼️', t: 'گالری تصاویر' },
];

export default function ClassContent() {
    const { items = [] } = usePage().props;
    // نخستین بخشی که محتوا دارد را پیش‌فرض انتخاب کن
    const firstWith = TABS.find((t) => items.some((i) => i.type === t.v))?.v || 'material';
    const [tab, setTab] = useState(firstWith);
    const list = items.filter((i) => i.type === tab);
    const active = TABS.find((t) => t.v === tab) || TABS[0];

    return (
        <ThemedDash title="محتوای کلاس" active="content">
            <div className="k3-card">
                <div style={{ fontWeight: 900, fontSize: 18, marginBottom: 4 }}>📚 محتوای کلاس تو</div>
                <div style={{ opacity: .8, fontSize: 13 }}>هر بخش را جداگانه ببین: جزوه‌ها، پادکست‌ها و گالری. گوش دادن به پادکست امتیاز (XP) دارد!</div>
            </div>

            {/* تب‌های نوعِ محتوا — مطابقِ بخش‌های بارگذاریِ معلم */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 10, marginTop: 14 }}>
                {TABS.map((t) => {
                    const count = items.filter((i) => i.type === t.v).length;
                    const on = tab === t.v;
                    return (
                        <button key={t.v} onClick={() => setTab(t.v)}
                            style={{ cursor: 'pointer', fontFamily: 'inherit', textAlign: 'center', borderRadius: 16, padding: '12px 8px', color: '#fff', border: on ? '2px solid var(--acc,#f5b53f)' : '1px solid rgba(255,255,255,.14)', background: on ? 'linear-gradient(135deg,var(--p1),var(--p2))' : 'rgba(255,255,255,.06)' }}>
                            <div style={{ fontSize: 24 }}>{t.ic}</div>
                            <div style={{ fontWeight: 800, fontSize: 13, marginTop: 3 }}>{t.t}</div>
                            <div style={{ fontSize: 11, opacity: .8 }}>{fa(count)} مورد</div>
                        </button>
                    );
                })}
            </div>

            {list.length === 0 && (
                <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', opacity: .8 }}>
                    هنوز {active.t} اضافه نشده 📭
                </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(280px,1fr))', gap: 14, marginTop: 14 }}>
                {list.map((it) => <ContentCard key={it.id} it={it} />)}
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
    const listenedRef = useRef(it.my_seconds || 0); // ثانیه‌ی «واقعاً گوش‌داده‌شده» (بدون احتساب پرش/جلوزدن)
    const lastTimeRef = useRef(0);

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
        // فقط زمانِ «واقعاً گوش‌داده‌شده» شمرده می‌شود: با هر timeupdate اختلافِ زمان
        // بررسی می‌شود و اگر پرشِ بزرگ (جلوزدن/عقب‌زدن) باشد، آن بازه شمرده نمی‌شود.
        const flush = () => {
            const cur = Math.floor(listenedRef.current);
            if (cur > lastSentRef.current) { lastSentRef.current = cur; sendProgress(cur); }
        };
        const onTime = () => {
            const now = el.currentTime || 0;
            const delta = now - lastTimeRef.current;
            if (delta > 0 && delta < 1.5) listenedRef.current += delta; // پخشِ عادی
            lastTimeRef.current = now; // پرش‌ها نادیده گرفته می‌شوند
        };
        const onSeek = () => { lastTimeRef.current = el.currentTime || 0; }; // پس از پرش، مبدأ به‌روز می‌شود
        const onPlay = () => { lastTimeRef.current = el.currentTime || 0; iv = setInterval(flush, 10000); };
        const onStop = () => { if (iv) { clearInterval(iv); iv = null; } flush(); };
        el.addEventListener('play', onPlay);
        el.addEventListener('timeupdate', onTime);
        el.addEventListener('seeking', onSeek);
        el.addEventListener('seeked', onSeek);
        el.addEventListener('pause', onStop);
        el.addEventListener('ended', onStop);
        return () => { if (iv) clearInterval(iv); el.removeEventListener('play', onPlay); el.removeEventListener('timeupdate', onTime); el.removeEventListener('seeking', onSeek); el.removeEventListener('seeked', onSeek); el.removeEventListener('pause', onStop); el.removeEventListener('ended', onStop); };
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
                    <div style={{ fontSize: 11.5, opacity: .7, marginTop: 4 }}>🎧 فقط با گوش‌دادنِ واقعی امتیاز می‌گیری (هر ۱۵ ثانیه ۱ XP، تا سقف ۲۰). جلوزدن/پرش‌کردن امتیاز نمی‌آورد.</div>
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
