import { useState, useRef, useEffect, useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import ThemedDash from '@/Layouts/ThemedDash';
import ListSearch, { normalizeFa } from '@/Components/ListSearch';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const TYPE = {
    material: { icon: '📄', label: 'جزوه و فایل', c1: '#3d7bf0', c2: '#2555c0' },
    podcast:  { icon: '🎧', label: 'پادکست', c1: '#a24cf0', c2: '#6f2fb0' },
    video:    { icon: '🎬', label: 'ویدیوی درسی', c1: '#e8505b', c2: '#b3303c' },
    gallery:  { icon: '🖼️', label: 'گالری', c1: '#f0952e', c2: '#c06712' },
};

const TABS = [
    { v: 'material', ic: '📄', t: 'جزوه و فایل' },
    { v: 'podcast', ic: '🎧', t: 'پادکست' },
    { v: 'video', ic: '🎬', t: 'ویدیو' },
    { v: 'gallery', ic: '🖼️', t: 'گالری' },
];

const isImageUrl = (u) => /\.(png|jpe?g|gif|webp|svg|bmp)$/i.test(u || '');
const isAudioUrl = (u) => /\.(mp3|m4a|aac|ogg|wav|opus)$/i.test(u || '');
const isVideoUrl = (u) => /\.(mp4|webm|ogv|mov|m4v)$/i.test(u || '');

const secFmt = (s) => {
    const n = Math.max(0, Math.round(s || 0));
    const m = Math.floor(n / 60);
    return `${fa(m)}:${fa(String(n % 60).padStart(2, '0'))}`;
};

export default function ClassContent() {
    const { items = [] } = usePage().props;
    const firstWith = TABS.find((t) => items.some((i) => i.type === t.v))?.v || 'material';
    const [tab, setTab] = useState(firstWith);
    const [q, setQ] = useState('');

    const list = useMemo(() => {
        const nq = normalizeFa(q);
        return items.filter((i) => i.type === tab
            && (!nq || normalizeFa(i.title).includes(nq) || normalizeFa(i.description).includes(nq)));
    }, [items, tab, q]);

    const active = TABS.find((t) => t.v === tab) || TABS[0];

    // خلاصه‌ی پیشرفت — انگیزه‌بخش و شفاف
    const playable = items.filter((i) => i.type === 'podcast' || i.type === 'video');
    const doneCount = playable.filter((i) => i.completed).length;
    const earned = items.reduce((a, i) => a + (i.my_xp || 0), 0);
    const available = items.reduce((a, i) => a + (i.completed ? 0 : (i.xp_value || 0)), 0);

    return (
        <ThemedDash title="محتوای کلاس" active="content">
            <div className="k3-card">
                <div style={{ fontWeight: 900, fontSize: 18, marginBottom: 4 }}>📚 محتوای کلاس تو</div>
                <div style={{ opacity: .82, fontSize: 13, lineHeight: 1.9 }}>
                    پادکست و ویدیو را که <b>کامل و بدون جلو زدن</b> ببینی، امتیازش
                    <b> یک‌بار </b> به تو داده می‌شود. جزوه‌ها و عکس‌ها هم با دیدن، امتیاز دارند.
                </div>
            </div>

            {/* نوارِ پیشرفتِ کلی */}
            <div className="cc-summary">
                <div><b>{fa(doneCount)}/{fa(playable.length)}</b><span>کامل دیده‌شده</span></div>
                <div><b>⭐ {fa(earned)}</b><span>امتیازِ گرفته</span></div>
                <div><b>🎯 {fa(available)}</b><span>امتیازِ باقی‌مانده</span></div>
            </div>

            <div className="cc-tabs">
                {TABS.map((t) => {
                    const count = items.filter((i) => i.type === t.v).length;
                    const on = tab === t.v;
                    return (
                        <button key={t.v} onClick={() => setTab(t.v)} className={`cc-tab ${on ? 'on' : ''}`}>
                            <div className="ic">{t.ic}</div>
                            <div className="t">{t.t}</div>
                            <div className="n">{fa(count)} مورد</div>
                        </button>
                    );
                })}
            </div>

            {items.filter((i) => i.type === tab).length > 3 && (
                <div style={{ marginTop: 12 }}>
                    <ListSearch value={q} onChange={setQ} placeholder={`جست‌وجو در ${active.t}…`} />
                </div>
            )}

            {list.length === 0 && (
                <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', opacity: .85 }}>
                    {q ? 'با این جست‌وجو چیزی پیدا نشد 🔍' : `هنوز ${active.t} اضافه نشده 📭`}
                </div>
            )}

            <div className="cc-grid">
                {list.map((it) => <ContentCard key={it.id} it={it} />)}
            </div>
        </ThemedDash>
    );
}

/* ═══════════════════════ کارتِ یک محتوا ═══════════════════════ */
function ContentCard({ it }) {
    const t = TYPE[it.type] ?? TYPE.material;

    const [percent, setPercent] = useState(it.percent || 0);
    const [completed, setCompleted] = useState(!!it.completed);
    const [xp, setXp] = useState(it.my_xp || 0);
    const [toast, setToast] = useState(null);
    const [seeking, setSeeking] = useState(false);

    const mediaRef = useRef(null);
    const durationRef = useRef(it.duration || 0);
    const lastPosRef = useRef(0);

    const isVideo = it.type === 'video' || (it.is_file && isVideoUrl(it.url));
    const isAudio = it.type === 'podcast' && !isVideo && (it.is_file ? isAudioUrl(it.url) : true);
    const isImage = it.type === 'gallery' || (it.is_file && isImageUrl(it.url));
    const isPlayable = isVideo || isAudio;

    /** گزارشِ نقطه‌ی جاری به سرور. سرور خودش تصمیم می‌گیرد چه چیزی معتبر است. */
    const report = async (position, ended = false) => {
        try {
            const { data } = await axios.post(route('my.content.progress', it.id), {
                position: Math.round(position || 0),
                duration: durationRef.current ? Math.round(durationRef.current) : undefined,
                ended,
            });
            if (!data.ok) return;
            setPercent(data.percent);
            if (data.gained > 0) setToast(`+${fa(data.gained)} امتیاز 🎉`);
            if (data.xp !== xp) setXp(data.xp);
            if (data.completed) setCompleted(true);
        } catch { /* بی‌صدا؛ پخش نباید متوقف شود */ }
    };

    // جزوه/عکس: با اولین دیدن، یک‌بار امتیاز
    const markViewed = () => { if (!completed) report(0, true); };

    useEffect(() => {
        const el = mediaRef.current;
        if (!el || !isPlayable) return;

        let iv = null;

        const onMeta = () => {
            if (Number.isFinite(el.duration)) durationRef.current = el.duration;
            // ادامه از جایی که رها کرده بود
            if (it.my_position > 0 && it.my_position < el.duration - 2) {
                try { el.currentTime = it.my_position; } catch { /* نادیده */ }
            }
        };
        const ping = () => report(el.currentTime, false);
        const onPlay = () => { iv = setInterval(ping, 5000); };
        const onStop = () => { if (iv) { clearInterval(iv); iv = null; } ping(); };
        const onEnd = () => { if (iv) { clearInterval(iv); iv = null; } report(el.currentTime, true); };

        // هشدارِ دوستانه هنگامِ جلو زدن — کاربر باید بداند چرا امتیاز نگرفت
        const onSeeked = () => {
            const jump = Math.abs((el.currentTime || 0) - lastPosRef.current);
            if (jump > 25 && !completed) {
                setSeeking(true);
                setTimeout(() => setSeeking(false), 3200);
            }
            lastPosRef.current = el.currentTime || 0;
        };
        const onTime = () => { lastPosRef.current = el.currentTime || 0; };

        el.addEventListener('loadedmetadata', onMeta);
        el.addEventListener('play', onPlay);
        el.addEventListener('pause', onStop);
        el.addEventListener('ended', onEnd);
        el.addEventListener('seeked', onSeeked);
        el.addEventListener('timeupdate', onTime);

        return () => {
            if (iv) clearInterval(iv);
            el.removeEventListener('loadedmetadata', onMeta);
            el.removeEventListener('play', onPlay);
            el.removeEventListener('pause', onStop);
            el.removeEventListener('ended', onEnd);
            el.removeEventListener('seeked', onSeeked);
            el.removeEventListener('timeupdate', onTime);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isPlayable]);

    useEffect(() => {
        if (!toast) return;
        const h = setTimeout(() => setToast(null), 2600);
        return () => clearTimeout(h);
    }, [toast]);

    return (
        <article className="k3-card cc-card" style={{ borderTop: `4px solid ${t.c1}` }}>
            {toast && <div className="cc-toast">{toast}</div>}

            <header className="cc-head">
                <span className="cc-ic" style={{ background: `linear-gradient(135deg,${t.c1},${t.c2})` }}>{t.icon}</span>
                <div className="cc-title">
                    <b>{it.title}</b>
                    <small>
                        {fa(it.date)}
                        {it.duration ? ` · ⏱️ ${secFmt(it.duration)}` : ''}
                    </small>
                </div>
                {completed
                    ? <span className="cc-badge done">✅ کامل</span>
                    : <span className="cc-badge">⭐ {fa(it.xp_value)}</span>}
            </header>

            {it.description && <p className="cc-desc">{it.description}</p>}

            {/* ── پخش‌کننده ── */}
            {isVideo && it.url && (
                <video ref={mediaRef} src={it.url} controls playsInline preload="metadata" className="cc-video" />
            )}
            {isAudio && it.url && (
                <audio ref={mediaRef} src={it.url} controls preload="metadata" className="cc-audio" />
            )}
            {isImage && it.url && (
                <img src={it.url} alt={it.title} className="cc-image" loading="lazy" onLoad={markViewed} />
            )}

            {/* ── پیشرفت ── */}
            {isPlayable && (
                <div className="cc-progress">
                    <div className="cc-bar" title={`${fa(percent)} درصد دیده‌شده`}>
                        <span style={{ width: `${Math.min(100, percent)}%`, background: completed ? '#2bb673' : t.c1 }} />
                    </div>
                    <div className="cc-progress-txt">
                        {completed
                            ? <span className="ok">✅ کامل دیدی — {fa(xp)} امتیاز گرفتی</span>
                            : <span>{fa(percent)}٪ دیده‌شده · با دیدنِ کامل {fa(it.xp_value)} امتیاز می‌گیری</span>}
                    </div>
                </div>
            )}

            {seeking && (
                <div className="cc-warn">
                    ⏭️ جلو زدی! بخشی که رد شد حساب نمی‌شود. برای گرفتنِ امتیاز باید همه‌اش را ببینی.
                </div>
            )}

            {/* ── دانلود / لینک ── */}
            {it.url && !isImage && (
                <div className="cc-actions">
                    <a href={it.url} target="_blank" rel="noreferrer" className="cc-link" onClick={markViewed}>
                        {isPlayable ? '⬇️ دریافتِ فایل' : '📂 بازکردن'}
                    </a>
                </div>
            )}
        </article>
    );
}
