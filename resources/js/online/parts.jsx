import { Children, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import '@/../css/online.css';

export const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const initials = (name) => String(name || '؟').trim().split(/\s+/).map((w) => w[0]).slice(0, 2).join('‌');
const hue = (id) => (Number(id) * 47) % 360;

export function Avatar({ id, name, size = 36 }) {
    return (
        <span className="oc-av" style={{ width: size, height: size, fontSize: size * 0.38, background: `hsl(${hue(id)} 70% 92%)`, color: `hsl(${hue(id)} 55% 32%)` }}>
            {initials(name)}
        </span>
    );
}

/** نمایشِ یک ترکِ ویدیو (با جای‌خالی وقتی تصویری نمی‌آید). */
export function Vid({ track, mirror = false, className = '', label, fit = 'cover', onEl, onClick, cam }) {
    const ref = useRef(null);
    const [live, setLive] = useState(false);
    useEffect(() => {
        const el = ref.current;
        if (!el) return undefined;
        el.srcObject = track ? new MediaStream([track]) : null;
        onEl?.(track ? el : null);
        if (!track) { setLive(false); return undefined; }
        const on = () => setLive(!track.muted && track.readyState === 'live');
        on();
        track.addEventListener('mute', on); track.addEventListener('unmute', on); track.addEventListener('ended', on);
        el.play().catch(() => {});
        return () => { track.removeEventListener('mute', on); track.removeEventListener('unmute', on); track.removeEventListener('ended', on); };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [track]);
    return (
        <div className={`oc-vid ${live ? 'live' : ''} ${onClick || cam ? 'tap' : ''} ${className}`} data-cam={cam || undefined} onClick={onClick}
            role={onClick ? 'button' : undefined} title={onClick || cam ? 'برای بزرگ دیدن بزن' : undefined}>
            <video ref={ref} autoPlay playsInline muted style={{ transform: mirror ? 'scaleX(-1)' : undefined, objectFit: fit }} />
            {label && <span className="oc-vid-l">{label}</span>}
        </div>
    );
}

/**
 * جعبه‌ی جابه‌جاشدنی برای تصویرها: با انگشت/ماوس بکشید؛ جای آن روی همین دستگاه می‌ماند.
 * جا به نسبتِ قاب ذخیره می‌شود تا با چرخاندنِ گوشی بیرون نزند.
 */
export function DragBox({ id, className = '', children, onTap, extra = null }) {
    const ref = useRef(null);
    const drag = useRef(null);
    const key = `oc-pos-${id}`;
    const read = (k, d) => { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch { return d; } };
    const save = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* حالتِ خصوصی */ } };
    const [pos, setPos] = useState(() => read(key, null));               // { fx, fy } بین ۰ و ۱
    const [mini, setMini] = useState(() => read(`${key}-mini`, false));
    const [big, setBig] = useState(() => read(`${key}-big`, false));
    const items = Children.toArray(children).filter(Boolean);
    if (!items.length) return null;

    const down = (e) => {
        if (e.button > 0 || e.target.closest('button')) return;
        const el = ref.current; const par = el?.offsetParent;
        if (!el || !par) return;
        drag.current = { sx: e.clientX, sy: e.clientY, x: el.offsetLeft, y: el.offsetTop, w: par.clientWidth - el.offsetWidth, h: par.clientHeight - el.offsetHeight, moved: false };
        el.setPointerCapture?.(e.pointerId);
    };
    const move = (e) => {
        const d = drag.current;
        if (!d) return;
        const dx = e.clientX - d.sx; const dy = e.clientY - d.sy;
        if (!d.moved && Math.hypot(dx, dy) < 6) return;
        d.moved = true;
        const clamp = (v) => Math.max(0, Math.min(1, v));
        setPos({ fx: d.w > 0 ? clamp((d.x + dx) / d.w) : 0, fy: d.h > 0 ? clamp((d.y + dy) / d.h) : 0 });
    };
    const up = (e) => {
        const d = drag.current;
        drag.current = null;
        if (d?.moved) setPos((p) => { save(key, p); return p; });
        // لمسِ کوتاه (بدونِ کشیدن) روی یک تصویر → بزرگ‌نمایی
        else if (d && onTap && e?.type === 'pointerup') {
            const hit = document.elementFromPoint(e.clientX, e.clientY)?.closest?.('[data-cam]');
            if (hit && ref.current?.contains(hit)) onTap(Number(hit.dataset.cam));
        }
    };
    const toggle = (k, v, set) => { set(v); save(`${key}-${k}`, v); };
    const style = pos ? { left: `${pos.fx * 100}%`, top: `${pos.fy * 100}%`, right: 'auto', bottom: 'auto', transform: `translate(${-pos.fx * 100}%, ${-pos.fy * 100}%)` } : undefined;

    return (
        <div ref={ref} className={`oc-drag ${className} ${mini ? 'mini' : ''} ${big ? 'big' : ''}`} style={style}
            onPointerDown={down} onPointerMove={move} onPointerUp={up} onPointerCancel={up}>
            <div className="oc-drag-h">
                <span className="oc-drag-grip" aria-hidden="true">⠿</span>
                {!mini && extra}
                {!mini && <button type="button" onClick={() => toggle('big', !big, setBig)} title={big ? 'کوچک‌تر' : 'بزرگ‌تر'} aria-label={big ? 'کوچک‌تر' : 'بزرگ‌تر'}>{big ? '➖' : '➕'}</button>}
                <button type="button" onClick={() => toggle('mini', !mini, setMini)} title={mini ? 'نمایشِ تصویرها' : 'جمع کردن'} aria-label={mini ? 'نمایشِ تصویرها' : 'جمع کردن'}>{mini ? `🎥 ${fa(items.length)}` : '▁'}</button>
                {pos && !mini && <button type="button" onClick={() => { setPos(null); save(key, null); }} title="برگشت به جای اول" aria-label="برگشت به جای اول">↺</button>}
            </div>
            <div className="oc-drag-b" hidden={mini}>{items}</div>
        </div>
    );
}

/** پخشِ یک ترکِ صدا. */
export function AudioSink({ track, muted, onBlocked }) {
    const ref = useRef(null);
    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        el.srcObject = track ? new MediaStream([track]) : null;
        if (track) el.play().catch(() => onBlocked?.());
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [track]);
    useEffect(() => { if (ref.current) ref.current.muted = !!muted; }, [muted]);
    return <audio ref={ref} autoPlay playsInline data-oc-audio="1" />;
}

/** زمانِ سپری‌شده از شروعِ کلاس. */
export function Clock({ from, skew = 0 }) {
    const [, tick] = useState(0);
    useEffect(() => { const t = setInterval(() => tick((x) => x + 1), 1000); return () => clearInterval(t); }, []);
    if (!from) return null;
    const s = Math.max(0, Math.floor((Date.now() + skew - from) / 1000));
    const h = Math.floor(s / 3600); const m = Math.floor((s % 3600) / 60); const sec = s % 60;
    return <span className="oc-clock">{fa(`${h ? `${h}:` : ''}${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`)}</span>;
}

/** شمارش معکوسِ تایمرِ معلم. */
export function Countdown({ end, skew = 0 }) {
    const [, tick] = useState(0);
    useEffect(() => { const t = setInterval(() => tick((x) => x + 1), 500); return () => clearInterval(t); }, []);
    if (!end) return null;
    const left = Math.ceil((end - (Date.now() + skew)) / 1000);
    if (left < -6) return null;
    const done = left <= 0;
    const m = Math.floor(Math.max(0, left) / 60); const s = Math.max(0, left) % 60;
    return <span className={`oc-timer ${done ? 'done' : left <= 10 ? 'soon' : ''}`}>{done ? '⏰ وقت تمام!' : `⏱️ ${fa(m)}:${fa(String(s).padStart(2, '0'))}`}</span>;
}

const QUICK = ['👋', '👍', '😀', '❤️', '🙏', '❓', '✅', '🌟'];

export function Chat({ msgs, me, chatOn, isMod, onSend, onHide }) {
    const [text, setText] = useState('');
    const box = useRef(null);
    useEffect(() => { if (box.current) box.current.scrollTop = box.current.scrollHeight; }, [msgs.length]);
    const send = (t) => { const v = (t ?? text).trim(); if (!v) return; onSend(v); setText(''); };
    const can = chatOn || isMod;
    return (
        <div className="oc-chat">
            <div className="oc-msgs" ref={box}>
                {!msgs.length && <div className="oc-empty">💬 هنوز پیامی نیامده. سلام کن!</div>}
                {msgs.map((m) => (
                    <div key={m.id} className={`oc-msg ${m.uid === me ? 'mine' : ''} ${m.kind === 'system' ? 'sys' : ''}`}>
                        {m.uid !== me && <b style={{ color: `hsl(${hue(m.uid)} 55% 38%)` }}>{m.name}</b>}
                        <span>{m.body}</span>
                        {(isMod || m.uid === me) && <button type="button" className="oc-x" onClick={() => onHide(m.id)} aria-label="حذفِ پیام">×</button>}
                    </div>
                ))}
            </div>
            {can ? (
                <>
                    <div className="oc-quick">{QUICK.map((e) => <button key={e} type="button" onClick={() => send(e)}>{e}</button>)}</div>
                    <form className="oc-send" onSubmit={(e) => { e.preventDefault(); send(); }}>
                        <input value={text} maxLength={500} onChange={(e) => setText(e.target.value)} placeholder="پیام بنویس…" aria-label="پیام" />
                        <button type="submit" disabled={!text.trim()} aria-label="ارسال">➤</button>
                    </form>
                </>
            ) : <div className="oc-closed">🔒 معلم فعلاً گفت‌وگو را بسته است.</div>}
        </div>
    );
}

/** نام بدونِ نیم‌فاصله/فاصله و با حروفِ یکسان (ی/ک عربی) — برای جستجو. */
const norm = (v) => String(v || '').replace(/[‌‏\s]+/g, '').replace(/ي/g, 'ی').replace(/ك/g, 'ک').toLowerCase();
const clock = (ms) => { try { return ms ? new Date(ms).toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' }) : ''; } catch { return ''; } };

/**
 * فهرستِ نفرات: جستجو؛ «دسترسی‌دارها» بالای فهرست (تا برای قطعِ دسترسی دنبالِ اسم نگردی)؛
 * بقیه به ترتیبِ زمانِ ورود. کارتِ «دستِ بالا» همان‌جا دکمه‌ی صحبت/دوربین/تخته دارد.
 */
export function People({ people, me, isMod, isHost, tiles, onPerm, onStar, onKick, onSpot, spotlight, onAll, speaking = [], msOf = () => '', openMics = false, onCam, onLower }) {
    const [q, setQ] = useState('');
    const [newest, setNewest] = useState(false);
    const online = people.filter((p) => p.online);
    const hands = online.filter((p) => p.hand).sort((a, b) => a.hand - b.hand);
    const students = people.filter((p) => p.role === 'student');
    // «میکِ همه باز» که باشد، صحبت دسترسیِ ویژه حساب نمی‌شود
    const hasAccess = (p) => p.role === 'student' && ((p.can_speak && !openMics) || p.can_video || p.can_draw);
    const nq = norm(q);
    const match = (p) => !nq || norm(p.name).includes(nq);
    const byJoin = (a, b) => ((newest ? -1 : 1) * ((a.joined || 0) - (b.joined || 0))) || String(a.name).localeCompare(String(b.name), 'fa');
    const staff = people.filter((p) => p.role !== 'student' && match(p));
    const access = isMod ? students.filter((p) => hasAccess(p) && match(p)).sort(byJoin) : [];
    const rest = students.filter((p) => !(isMod && hasAccess(p)) && match(p)).sort((a, b) => (Number(b.online) - Number(a.online)) || byJoin(a, b));
    const found = staff.length + access.length + rest.length;

    const row = (p, pinned = false) => {
        const ms = msOf(p.id);
        const cam = (p.can_video || p.role !== 'student') && ms.includes('+cam') ? tiles[p.id]?.cam : null;
        return (
            <div key={p.id} className={`oc-person ${p.online ? '' : 'off'} ${pinned ? 'pin' : ''}`}>
                <div className="oc-p-main">
                    <span className={`oc-p-av ${speaking.includes(p.id) ? 'spk' : ''}`}><Avatar id={p.id} name={p.name} size={34} /><i className={p.online ? 'on' : ''} /></span>
                    <span className="oc-p-name">
                        {p.name}{p.id === me ? ' (من)' : ''}
                        <small>
                            {p.role === 'host' ? '👩‍🏫 معلم' : p.role === 'observer' ? '🛡️ ناظر' : p.stars ? `⭐ ${fa(p.stars)}` : ''}{p.hand ? ' ✋' : ''}
                            {isMod && p.role === 'student' && p.joined ? <em>{p.stars || p.hand ? ' · ' : ''}ورود {clock(p.joined)}</em> : null}
                        </small>
                    </span>
                    {p.role === 'student' && !isMod && (
                        <span className="oc-p-flags">
                            {p.can_speak && <i title="اجازه‌ی صحبت">🎤</i>}
                            {p.can_video && <i title="اجازه‌ی دوربین">🎥</i>}
                            {p.can_draw && <i title="اجازه‌ی نوشتن روی تخته">✏️</i>}
                        </span>
                    )}
                    {p.online && (ms.includes('+mic') || ms.includes('+cam') || ms.includes('+scr')) && (
                        <span className="oc-p-live" title="الان روشن است">
                            {ms.includes('+mic') && <i>🎙️</i>}{ms.includes('+cam') && <i>📹</i>}{ms.includes('+scr') && <i>🖥️</i>}
                        </span>
                    )}
                </div>
                {isMod && p.role === 'student' && (
                    <div className="oc-p-acts">
                        {pinned && <button type="button" className="cut" onClick={() => onPerm(p.id, 'all', false)} title="قطعِ همه‌ی دسترسی‌های این دانش‌آموز">🚫 قطع</button>}
                        <button type="button" className={p.can_speak ? 'on' : ''} onClick={() => onPerm(p.id, 'can_speak', !p.can_speak)} title="صحبت" aria-label="اجازه‌ی صحبت" aria-pressed={!!p.can_speak}>🎤</button>
                        <button type="button" className={p.can_video ? 'on' : ''} onClick={() => onPerm(p.id, 'can_video', !p.can_video)} title="دوربین" aria-label="اجازه‌ی دوربین" aria-pressed={!!p.can_video}>🎥</button>
                        <button type="button" className={p.can_draw ? 'on' : ''} onClick={() => onPerm(p.id, 'can_draw', !p.can_draw)} title="تخته" aria-label="اجازه‌ی تخته" aria-pressed={!!p.can_draw}>✏️</button>
                        <button type="button" onClick={() => onStar(p.id)} title="ستاره و امتیاز">⭐</button>
                        {isHost && cam && <button type="button" className={spotlight === p.id ? 'on' : ''} onClick={() => onSpot(spotlight === p.id ? null : p.id)} title="نمایشِ تصویرش برای همه">👁️</button>}
                        <button type="button" onClick={() => onKick(p)} title="بیرون گذاشتن">🚪</button>
                    </div>
                )}
                {isMod && cam && <Vid track={cam} className="oc-p-cam" onClick={onCam ? () => onCam(p.id) : undefined} />}
            </div>
        );
    };

    return (
        <div className="oc-people">
            <div className="oc-pp-search">
                <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔎 جستجوی نام…" aria-label="جستجو در فهرستِ نفرات" enterKeyHint="search" />
                {q && <button type="button" onClick={() => setQ('')} aria-label="پاک کردنِ جستجو">✕</button>}
            </div>
            {isMod && (
                <div className="oc-pp-acts">
                    <button type="button" onClick={() => onAll('lower_hands')}>✋ پایین آوردنِ دست‌ها</button>
                    <button type="button" onClick={() => onAll('mute_all')}>🔇 بی‌صدا کردنِ همه</button>
                    <button type="button" onClick={() => onAll('open_mics', true)}>🎤 باز کردنِ میکِ همه</button>
                </div>
            )}
            {hands.length > 0 && !nq && (
                <div className="oc-hands">
                    <b>✋ دستِ بالا ({fa(hands.length)})</b>
                    {hands.map((p, i) => (
                        <div key={p.id} className="oc-hand">
                            <span>{fa(i + 1)}. {p.name}</span>
                            {isMod && p.role === 'student' && <HandActs p={p} onPerm={onPerm} onLower={onLower} />}
                        </div>
                    ))}
                </div>
            )}
            <div className="oc-pp-count">{fa(online.length)} نفر آنلاین · {fa(students.length)} دانش‌آموز</div>
            {staff.map((p) => row(p))}
            {access.length > 0 && (
                <div className="oc-pp-sec access">
                    <b>🔓 دسترسی دارند ({fa(access.length)})</b>
                    <button type="button" onClick={() => { if (window.confirm('دسترسیِ صحبت، دوربین و تخته‌ی همه‌ی دانش‌آموزان قطع شود؟')) onAll('revoke_all'); }}>🚫 قطعِ همه</button>
                </div>
            )}
            {access.map((p) => row(p, true))}
            {rest.length > 0 && (
                <div className="oc-pp-sec">
                    <b>👥 {access.length ? 'بقیه‌ی دانش‌آموزان' : 'دانش‌آموزان'} — به ترتیبِ ورود</b>
                    <button type="button" onClick={() => setNewest((v) => !v)} title="عوض کردنِ ترتیب">{newest ? '⬆️ تازه‌واردها بالا' : '⬇️ تازه‌واردها پایین'}</button>
                </div>
            )}
            {rest.map((p) => row(p))}
            {!found && <div className="oc-pp-none">کسی با این نام پیدا نشد.</div>}
        </div>
    );
}

/** دکمه‌های دسترسی برای کسی که دست بالا کرده: صحبت، دوربین، تخته + «رسیدگی شد». */
export function HandActs({ p, onPerm, onLower }) {
    return (
        <span className="oc-hc-acts">
            <button type="button" className={p.can_speak ? 'on' : ''} onClick={() => onPerm(p.id, 'can_speak', !p.can_speak, true)} aria-pressed={!!p.can_speak} title="اجازه‌ی صحبت">🎤<small>صحبت</small></button>
            <button type="button" className={p.can_video ? 'on' : ''} onClick={() => onPerm(p.id, 'can_video', !p.can_video, true)} aria-pressed={!!p.can_video} title="اجازه‌ی دوربین">🎥<small>دوربین</small></button>
            <button type="button" className={p.can_draw ? 'on' : ''} onClick={() => onPerm(p.id, 'can_draw', !p.can_draw, true)} aria-pressed={!!p.can_draw} title="اجازه‌ی نوشتن روی تخته">✏️<small>تخته</small></button>
            <button type="button" className="ok" onClick={() => onLower(p.id)} title="رسیدگی شد؛ دستش پایین بیاید" aria-label="پایین آوردنِ دست">✓</button>
        </span>
    );
}

/** کارتِ شناورِ «دستِ بالا» برای معلم: همان‌جا روی صفحه‌ی کلاس، بدونِ باز کردنِ فهرستِ نفرات. */
export function HandCards({ people, onPerm, onLower, onMore }) {
    const hands = people.filter((p) => p.online && p.hand && p.role === 'student').sort((a, b) => a.hand - b.hand);
    if (!hands.length) return null;
    return (
        <div className="oc-handbar" role="region" aria-label="دست‌های بالا">
            {hands.slice(0, 3).map((p) => (
                <div key={p.id} className="oc-handcard">
                    <span className="oc-hc-name"><i aria-hidden="true">✋</i><b>{p.name}</b></span>
                    <HandActs p={p} onPerm={onPerm} onLower={onLower} />
                </div>
            ))}
            {hands.length > 3 && <button type="button" className="oc-hc-more" onClick={onMore}>+{fa(hands.length - 3)} دستِ دیگر</button>}
        </div>
    );
}

/**
 * «سینمای دوربین»: معلم روی هر تصویر بزند، تمام‌صفحه می‌بیند — با نوارِ همه‌ی دوربین‌ها، ورق زدن با
 * کشیدنِ انگشت/فلش، و دکمه‌های دسترسیِ همان دانش‌آموز.
 */
export function CamViewer({ cams, id, onChange, onClose, people = [], isMod, isHost, onPerm, onStar, onSpot, spotlight, speaking = [], msOf = () => '' }) {
    const idx = cams.findIndex((c) => c.id === id);
    const cur = idx >= 0 ? cams[idx] : null;
    const person = people.find((p) => p.id === id);
    const [fill, setFill] = useState(false);
    const touch = useRef(null);
    const lastTap = useRef(0);
    const go = (d) => { if (cams.length > 1) onChange(cams[(Math.max(idx, 0) + d + cams.length) % cams.length].id); };
    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') onClose();
            else if (e.key === 'ArrowLeft') go(1);
            else if (e.key === 'ArrowRight') go(-1);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });
    const name = cur?.name || person?.name || '';
    const ms = msOf(id);
    const student = person?.role === 'student';
    return createPortal(
        <div className="oc-viewer" dir="rtl" role="dialog" aria-modal="true" aria-label={`تصویرِ ${name}`}>
            {cur && <Vid key={`amb-${cur.id}`} track={cur.track} mirror={cur.mirror} className="oc-vw-amb" />}
            <header className="oc-vw-top">
                <span className={`oc-vw-who ${speaking.includes(id) ? 'spk' : ''}`}>
                    <Avatar id={id} name={name} size={38} />
                    <span><b>{name}</b><small>{person?.role === 'host' ? '👩‍🏫 معلم' : person?.role === 'observer' ? '🛡️ ناظر' : student ? `دانش‌آموز${person?.stars ? ` · ⭐ ${fa(person.stars)}` : ''}` : ''}{ms.includes('+mic') ? ' · 🎙️ میکروفون روشن' : ''}</small></span>
                </span>
                {cams.length > 1 && <span className="oc-vw-count">{fa(Math.max(idx, 0) + 1)} از {fa(cams.length)}</span>}
                <button type="button" className="oc-vw-x" onClick={onClose} aria-label="بستن">✕</button>
            </header>
            <div className="oc-vw-stage"
                onTouchStart={(e) => { touch.current = { x: e.touches[0].clientX, y: e.touches[0].clientY }; }}
                onTouchEnd={(e) => {
                    const t = touch.current; touch.current = null;
                    if (!t) return;
                    const dx = e.changedTouches[0].clientX - t.x; const dy = e.changedTouches[0].clientY - t.y;
                    if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) go(dx > 0 ? 1 : -1);
                    else if (dy > 110 && Math.abs(dy) > Math.abs(dx) * 1.5) onClose();
                }}
                onClick={() => { const now = Date.now(); if (now - lastTap.current < 320) setFill((v) => !v); lastTap.current = now; }}>
                {cur
                    ? <Vid key={cur.id} track={cur.track} mirror={cur.mirror} fit={fill ? 'cover' : 'contain'} className={`oc-vw-main ${speaking.includes(id) ? 'spk' : ''}`} />
                    : <div className="oc-vw-off"><Avatar id={id} name={name} size={96} /><b>{name}</b><span>دوربین خاموش شد.</span></div>}
                {cams.length > 1 && <button type="button" className="oc-vw-nav prev" onClick={(e) => { e.stopPropagation(); go(-1); }} aria-label="تصویرِ قبلی">›</button>}
                {cams.length > 1 && <button type="button" className="oc-vw-nav next" onClick={(e) => { e.stopPropagation(); go(1); }} aria-label="تصویرِ بعدی">‹</button>}
            </div>
            <footer className="oc-vw-foot">
                {isMod && student && person && (
                    <div className="oc-vw-acts">
                        <button type="button" className={person.can_speak ? 'on' : ''} onClick={() => onPerm(id, 'can_speak', !person.can_speak, true)} aria-pressed={!!person.can_speak}>🎤<small>صحبت</small></button>
                        <button type="button" className={person.can_video ? 'on' : ''} onClick={() => onPerm(id, 'can_video', !person.can_video)} aria-pressed={!!person.can_video}>🎥<small>دوربین</small></button>
                        <button type="button" className={person.can_draw ? 'on' : ''} onClick={() => onPerm(id, 'can_draw', !person.can_draw)} aria-pressed={!!person.can_draw}>✏️<small>تخته</small></button>
                        <button type="button" onClick={() => onStar(id)}>⭐<small>ستاره</small></button>
                        {isHost && cur && <button type="button" className={spotlight === id ? 'on' : ''} onClick={() => onSpot(spotlight === id ? null : id)}>👁️<small>{spotlight === id ? 'پایانِ نمایش' : 'برای همه'}</small></button>}
                    </div>
                )}
                {cams.length > 1 && (
                    <div className="oc-vw-strip" role="tablist" aria-label="همه‌ی دوربین‌ها">
                        {cams.map((c) => (
                            <button key={c.id} type="button" role="tab" aria-selected={c.id === id} className={`${c.id === id ? 'on' : ''} ${speaking.includes(c.id) ? 'spk' : ''}`} onClick={() => onChange(c.id)}>
                                <Vid track={c.track} mirror={c.mirror} />
                                <span>{c.name}</span>
                            </button>
                        ))}
                    </div>
                )}
                <p className="oc-vw-tip">دو بار بزن: پُر/کامل · به چپ و راست بکش: دوربینِ بعدی · پایین بکش: بستن</p>
            </footer>
        </div>,
        document.body,
    );
}

export function PollPanel({ poll, isMod, onCreate, onClose, onDelete }) {
    const [q, setQ] = useState('');
    const [opts, setOpts] = useState(['', '']);
    const [correct, setCorrect] = useState(null);
    const [xp, setXp] = useState(5);
    const total = poll?.counts?.reduce((a, b) => a + b, 0) || 0;
    const create = () => {
        const options = opts.map((o) => o.trim()).filter(Boolean);
        if (!q.trim() || options.length < 2) return;
        onCreate({ question: q.trim(), options, correct: correct !== null && correct < options.length ? correct : null, xp });
        setQ(''); setOpts(['', '']); setCorrect(null);
    };
    return (
        <div className="oc-poll">
            {poll && (
                <div className="oc-poll-cur">
                    <div className="oc-poll-q">📊 {poll.question}</div>
                    {poll.options.map((o, i) => {
                        const n = poll.counts?.[i] ?? 0; const pct = total ? Math.round((n * 100) / total) : 0;
                        return (
                            <div key={i} className={`oc-bar ${poll.correct === i ? 'ok' : ''}`}>
                                <span className="oc-bar-f" style={{ width: `${pct}%` }} />
                                <span className="oc-bar-t">{poll.correct === i ? '✅ ' : ''}{o}</span>
                                {poll.counts && <b>{fa(n)} · {fa(pct)}٪</b>}
                            </div>
                        );
                    })}
                    <div className="oc-poll-meta">{fa(poll.answered)} پاسخ · {poll.status === 'open' ? 'در حالِ پاسخ‌دادن' : 'نتیجه نمایش داده شد'}</div>
                    {isMod && poll.status === 'open' && <button type="button" className="oc-btn" onClick={() => onClose(poll.id)}>🏁 پایان و نمایشِ نتیجه</button>}
                    {isMod && poll.who && <PollWho poll={poll} />}
                    {isMod && poll.status !== 'open' && (
                        <button type="button" className="oc-btn oc-btn-del" onClick={() => { if (confirm('این سؤال و نتیجه‌اش از کلاس پاک شود؟ (امتیازِ داده‌شده می‌ماند)')) onDelete?.(poll.id); }}>🗑️ پاک کردنِ سؤال</button>
                    )}
                </div>
            )}
            {isMod && (
                <div className="oc-poll-new">
                    <b>➕ سؤالِ سریعِ تازه</b>
                    <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="سؤال… مثلاً «۷ × ۸ چند می‌شود؟»" maxLength={300} />
                    {opts.map((o, i) => (
                        <div key={i} className="oc-opt">
                            <button type="button" className={correct === i ? 'on' : ''} onClick={() => setCorrect(correct === i ? null : i)} title="پاسخِ درست">✓</button>
                            <input value={o} onChange={(e) => setOpts(opts.map((x, k) => (k === i ? e.target.value : x)))} placeholder={`گزینه‌ی ${fa(i + 1)}`} maxLength={120} />
                            {opts.length > 2 && <button type="button" onClick={() => { setOpts(opts.filter((_, k) => k !== i)); if (correct === i) setCorrect(null); }} aria-label="حذف">×</button>}
                        </div>
                    ))}
                    {opts.length < 6 && <button type="button" className="oc-link" onClick={() => setOpts([...opts, ''])}>+ گزینه</button>}
                    {correct !== null && (
                        <label className="oc-xp">امتیازِ پاسخِ درست: <select value={xp} onChange={(e) => setXp(Number(e.target.value))}>{[0, 3, 5, 10, 15, 20].map((v) => <option key={v} value={v}>{fa(v)}</option>)}</select></label>
                    )}
                    <button type="button" className="oc-btn" onClick={create}>🚀 فرستادن برای بچه‌ها</button>
                    <small>با زدنِ ✓ کنارِ یک گزینه، آن را پاسخِ درست کنید تا بچه‌ها امتیاز بگیرند.</small>
                </div>
            )}
            {!poll && !isMod && <div className="oc-empty">📊 فعلاً سؤالی نیست.</div>}
        </div>
    );
}

/** نتیجه‌ی کامل برای معلم: چه کسی درست، چه کسی نادرست (با گزینه‌اش) و چه کسی پاسخ نداد. */
function PollWho({ poll }) {
    const who = poll.who || [];
    const right = who.filter((w) => w.ok === true);
    const wrong = who.filter((w) => w.ok === false);
    const answered = who.filter((w) => w.ok === null && w.choice !== null);   // سؤالِ بی‌پاسخِ درست
    const none = who.filter((w) => w.choice === null);
    const opt = (i) => poll.options?.[i] ?? '';
    return (
        <div className="oc-who">
            {poll.correct !== null && (
                <>
                    <div className="oc-who-g ok"><b>✅ درست ({fa(right.length)})</b><div>{right.map((w) => <span key={w.id}>{w.name}</span>)}{!right.length && <small>هیچ‌کس</small>}</div></div>
                    <div className="oc-who-g bad"><b>❌ نادرست ({fa(wrong.length)})</b><div>{wrong.map((w) => <span key={w.id}>{w.name}<small> ← {opt(w.choice)}</small></span>)}{!wrong.length && <small>هیچ‌کس</small>}</div></div>
                </>
            )}
            {answered.length > 0 && <div className="oc-who-g"><b>🗳️ پاسخ‌ها ({fa(answered.length)})</b><div>{answered.map((w) => <span key={w.id}>{w.name}<small> ← {opt(w.choice)}</small></span>)}</div></div>}
            <div className="oc-who-g none"><b>⏳ بی‌پاسخ ({fa(none.length)})</b><div>{none.map((w) => <span key={w.id}>{w.name}</span>)}{!none.length && <small>همه پاسخ دادند 👏</small>}</div></div>
        </div>
    );
}

/** کارتِ بزرگِ سؤال برای بچه‌ها (روی صحنه). */
export function PollCard({ poll, onAnswer, onDismiss }) {
    if (!poll) return null;
    const answered = poll.mine !== null && poll.mine !== undefined;
    const shown = poll.status === 'shown';
    const right = shown && poll.correct !== null && poll.mine === poll.correct;
    const total = poll.counts?.reduce((a, b) => a + b, 0) || 0;
    return (
        <div className={`oc-pcard ${right ? 'right' : ''}`} role="dialog" aria-label="سؤالِ معلم">
            <button type="button" className="oc-pcard-x" onClick={onDismiss} aria-label="بستن">×</button>
            <div className="oc-pcard-q">{poll.question}</div>
            <div className="oc-pcard-opts">
                {poll.options.map((o, i) => {
                    const n = poll.counts?.[i] ?? 0;
                    return (
                        <button key={i} type="button" disabled={answered || shown}
                            className={`${poll.mine === i ? 'mine' : ''} ${shown && poll.correct === i ? 'ok' : ''} c${i}`}
                            onClick={() => onAnswer(poll.id, i)}>
                            <span>{o}</span>
                            {shown && total > 0 && <small>{fa(Math.round((n * 100) / total))}٪</small>}
                        </button>
                    );
                })}
            </div>
            <div className="oc-pcard-foot">
                {shown ? (poll.correct === null ? '📊 نتیجه‌ی نظرسنجی' : right ? `🎉 آفرین! درست بود${poll.xp ? ` · +${fa(poll.xp)} امتیاز` : ''}` : answered ? '💪 این بار نشد؛ دفعه‌ی بعد!' : '⏰ وقتِ پاسخ تمام شد')
                    : answered ? '✓ جوابت ثبت شد؛ منتظرِ نتیجه باش…' : poll.xp ? `جوابِ درست = ${fa(poll.xp)} امتیاز ⚡` : 'گزینه‌ات را بزن'}
            </div>
        </div>
    );
}

/**
 * پنجره‌ی روی صفحه — مستقیم روی <body> (portal) تا زیرِ سربرگ و منوی پایینِ پیشخوان نرود؛
 * دکمه‌ی ✕ همیشه دیده می‌شود (سربرگِ پنجره چسبان است)، با Esc یا لمسِ بیرونِ پنجره هم بسته می‌شود.
 */
export function Modal({ label, onClose, boxClass = '', children }) {
    useEffect(() => {
        const key = (e) => { if (e.key === 'Escape') onClose?.(); };
        window.addEventListener('keydown', key);
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { window.removeEventListener('keydown', key); document.body.style.overflow = prev; };
    }, [onClose]);
    if (typeof document === 'undefined') return null;
    return createPortal(
        <div className="oc-modal" role="dialog" aria-modal="true" aria-label={label} onClick={(e) => { if (e.target === e.currentTarget) onClose?.(); }}>
            <div className={`oc-modal-box ${boxClass}`}>{children}</div>
        </div>,
        document.body,
    );
}

/** گزارشِ کلاس (برای معلم، مدیر و ناظر): حضور + سابقه‌ی کاملِ گفت‌وگو. */
export function ReportModal({ url, onClose, tab: tab0 = 'att' }) {
    const [data, setData] = useState(null);
    const [tab, setTab] = useState(tab0);
    const [q, setQ] = useState('');
    const [who, setWho] = useState('');
    useEffect(() => { axios.get(url).then(({ data: d }) => setData(d)).catch(() => setData({ error: true })); }, [url]);
    const chat = (data?.chat || []).filter((m) => m.kind !== 'system');
    const nq = q.trim();
    const shown = chat.filter((m) => (!who || String(m.uid) === who) && (!nq || m.body.includes(nq) || m.name.includes(nq)));
    const writers = [...new Map(chat.map((m) => [m.uid, m.name])).entries()];
    const counts = chat.reduce((o, m) => ({ ...o, [m.uid]: (o[m.uid] || 0) + 1 }), {});
    const download = () => {
        const lines = [`گفت‌وگوی کلاس: ${data.title}${data.date ? ` — ${data.date}` : ''}`, ''];
        chat.forEach((m) => lines.push(`[${m.at}] ${m.name}${m.hidden ? ' (پنهان‌شده)' : ''}: ${m.body}`));
        const blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/plain;charset=utf-8' });
        const el = document.createElement('a');
        el.href = URL.createObjectURL(blob);
        el.download = `گفت‌وگو-${String(data.title).replace(/[\\/:*?"<>|]+/g, ' ').trim()}.txt`;
        document.body.appendChild(el); el.click(); el.remove();
        setTimeout(() => URL.revokeObjectURL(el.href), 4000);
    };
    return (
        <Modal label="گزارشِ کلاس" onClose={onClose}>
                <div className="oc-modal-h"><b>📋 گزارشِ کلاس{data?.title ? ` — ${data.title}` : ''}</b><button type="button" onClick={onClose} aria-label="بستن">×</button></div>
                {!data && <div className="oc-empty">در حالِ بارگذاری…</div>}
                {data?.error && <div className="oc-empty">گزارش بارگذاری نشد.</div>}
                {data?.rows && (
                    <>
                        <div className="oc-rep-sum">
                            {data.date && <span>🗓️ <b>{data.date}</b></span>}
                            <span>🎓 حاضر: <b>{fa(data.present)}</b> از {fa(data.total)}</span>
                            <span>⏱️ مدت: <b>{fa(data.length)}</b> دقیقه</span>
                            <span>💬 پیام: <b>{fa(data.messages)}</b></span>
                        </div>
                        <div className="oc-rep-tabs" role="tablist">
                            <button type="button" role="tab" aria-selected={tab === 'att'} className={tab === 'att' ? 'on' : ''} onClick={() => setTab('att')}>📋 حضور</button>
                            <button type="button" role="tab" aria-selected={tab === 'chat'} className={tab === 'chat' ? 'on' : ''} onClick={() => setTab('chat')}>💬 گفت‌وگوها ({fa(chat.length)})</button>
                        </div>
                        {tab === 'att' && (
                            <table className="oc-rep">
                                <thead><tr><th>نام</th><th>حضور</th><th>دقیقه</th><th>ورود</th><th>⭐</th><th>امتیاز</th><th>💬</th></tr></thead>
                                <tbody>
                                    {data.rows.map((r) => (
                                        <tr key={r.id} className={r.present ? '' : 'abs'}>
                                            <td>{r.name}</td><td>{r.present ? '✅' : '❌ غایب'}</td><td>{fa(r.minutes)}</td>
                                            <td>{r.first ? `${r.first}${r.joins > 1 ? ` (${fa(r.joins)} بار)` : ''}` : '—'}</td><td>{fa(r.stars)}</td><td>{fa(r.xp)}</td>
                                            <td>{counts[r.id] ? <button type="button" className="oc-rep-link" onClick={() => { setWho(String(r.id)); setTab('chat'); }}>{fa(counts[r.id])}</button> : '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                        {tab === 'chat' && (
                            <div className="oc-rep-chat">
                                {chat.length === 0 ? <div className="oc-empty">در این کلاس پیامی نوشته نشده است.</div> : (
                                    <>
                                        <div className="oc-rep-f">
                                            <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="🔎 جستجو در پیام‌ها…" aria-label="جستجو در پیام‌ها" />
                                            <select value={who} onChange={(e) => setWho(e.target.value)} aria-label="فقط پیام‌های یک نفر">
                                                <option value="">همه ({fa(chat.length)})</option>
                                                {writers.map(([id, name]) => <option key={id} value={String(id)}>{name} ({fa(counts[id])})</option>)}
                                            </select>
                                            <button type="button" onClick={download} title="ذخیره‌ی متنِ گفت‌وگو">⬇️ متن</button>
                                        </div>
                                        <ol className="oc-rep-msgs">
                                            {shown.map((m) => (
                                                <li key={m.id} className={`${m.teacher ? 't' : ''} ${m.hidden ? 'hid' : ''}`}>
                                                    <span className="oc-rep-mh"><b>{m.teacher ? '👩‍🏫 ' : ''}{m.name}</b><time>{m.at}</time>{m.hidden && <em>پنهان‌شده</em>}</span>
                                                    <p>{m.body}</p>
                                                </li>
                                            ))}
                                            {shown.length === 0 && <li className="oc-empty">پیامی با این جستجو پیدا نشد.</li>}
                                        </ol>
                                    </>
                                )}
                            </div>
                        )}
                    </>
                )}
        </Modal>
    );
}

/** «🖼️ تصویرِ خوش‌آمد» کلاس: عکس + یک جمله؛ بچه‌ها پیش از شروع و در فهرستِ کلاس‌ها می‌بینند. */
export function CoverEditor({ url, cover, welcome, onDone, onClose }) {
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(cover || null);
    const [text, setText] = useState(welcome || '');
    const [busy, setBusy] = useState(false);
    const [err, setErr] = useState(null);
    const pick = async (f) => {
        if (!f) return;
        if (!/^image\//.test(f.type)) { setErr('یک عکس انتخاب کنید (JPG، PNG یا WEBP).'); return; }
        setErr(null); setBusy(true);
        try {
            const { default: shrink } = await import('@/lib/shrinkImage');
            const small = await shrink(f, { max: 1800, quality: 0.85, minBytes: 400 * 1024 });
            setFile(small);
            setPreview(URL.createObjectURL(small));
        } catch { setErr('این عکس خوانده نشد؛ عکسِ دیگری انتخاب کنید.'); } finally { setBusy(false); }
    };
    const save = async (remove = false) => {
        setBusy(true); setErr(null);
        try {
            const fd = new FormData();
            if (remove) fd.append('remove', '1');
            else { if (file) fd.append('file', file instanceof File ? file : new File([file], 'cover.jpg', { type: file.type || 'image/jpeg' })); fd.append('welcome', text); }
            const { data } = await axios.post(url, fd, { headers: { Accept: 'application/json' } });
            onDone?.(data);
            onClose();
        } catch (e) {
            setErr(e?.response?.data?.message || 'ذخیره نشد؛ دوباره امتحان کنید.');
        } finally { setBusy(false); }
    };
    return (
        <Modal label="تصویرِ خوش‌آمد" onClose={onClose} boxClass="small oc-cover-box">
            <div className="oc-modal-h"><b>🖼️ تصویرِ خوش‌آمد</b><button type="button" onClick={onClose} aria-label="بستن">×</button></div>
            <p>بچه‌ها پیش از شروعِ کلاس (در اتاقِ انتظار) و در فهرستِ کلاس‌ها این تصویر و جمله را می‌بینند.</p>
            <label className={`oc-cover-pick ${preview ? 'has' : ''}`}>
                {preview ? <img src={preview} alt="پیش‌نمایشِ تصویرِ خوش‌آمد" /> : <span>📷 انتخابِ عکس</span>}
                <input type="file" accept="image/*" onChange={(e) => { pick(e.target.files?.[0]); e.target.value = ''; }} />
            </label>
            <input className="oc-cover-t" value={text} maxLength={200} onChange={(e) => setText(e.target.value)} placeholder="مثلاً: سلام بچه‌ها! امروز با کسرها بازی می‌کنیم 🍕" aria-label="جمله‌ی خوش‌آمد" />
            {err && <div className="oc-err">{err}</div>}
            <div className="oc-cover-a">
                <button type="button" className="oc-go" disabled={busy || (!file && text === (welcome || ''))} onClick={() => save(false)}>{busy ? 'در حالِ ذخیره…' : '💾 ذخیره'}</button>
                {(cover || welcome) && <button type="button" className="oc-back" disabled={busy} onClick={() => save(true)}>🗑️ برداشتن</button>}
            </div>
        </Modal>
    );
}

/**
 * «🎬 فیلم‌های کلاس»: فهرستِ ضبط‌ها با پخش در همین صفحه و دانلود.
 * معلم: «برای بچه‌ها» روشن/خاموش و حذف.
 */
export function RecordingsList({ recordings = [], manage = false, onShare, onDelete, title = '🎬 فیلم‌های کلاس' }) {
    const [play, setPlay] = useState(null);
    const [all, setAll] = useState(false);
    if (!recordings.length) return null;
    const list = all ? recordings : recordings.slice(0, 6);
    return (
        <div className="panel oc-recs">
            <h3>{title} <span className="oc-st">{fa(recordings.length)} فیلم</span></h3>
            <div className="oc-recs-g">
                {list.map((r) => (
                    <div key={r.id} className="oc-vrec">
                        <button type="button" className="oc-vrec-play" onClick={() => setPlay(r)} aria-label={`پخشِ ${r.title}`}>▶</button>
                        <div className="oc-vrec-b">
                            <b>{r.title}</b>
                            <small>👥 {r.classroom}{r.teacher && !manage ? ` · 👩‍🏫 ${r.teacher}` : ''}</small>
                            <small>🗓️ {r.at}{r.minutes ? ` · ⏱️ ${fa(r.minutes)} دقیقه` : ''} · {r.size_mb >= 1 ? `${fa(r.size_mb)} مگ` : 'کمتر از ۱ مگ'}</small>
                            <div className="oc-vrec-a">
                                <a href={`${r.url}&download=1`} data-no-prefetch>⬇️ دانلود</a>
                                {manage && <label><input type="checkbox" checked={r.shared} onChange={(e) => onShare?.(r, e.target.checked)} /> بچه‌ها ببینند</label>}
                                {manage && <button type="button" onClick={() => onDelete?.(r)}>🗑️ حذف</button>}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
            {recordings.length > 6 && <button type="button" className="oc-link" onClick={() => setAll((a) => !a)}>{all ? 'کمتر' : `همه‌ی ${fa(recordings.length)} فیلم`}</button>}
            {play && (
                <Modal label={play.title} boxClass="oc-player" onClose={() => setPlay(null)}>
                    <div className="oc-player-h"><b>🎬 {play.title}</b><small>{play.at}</small><button type="button" onClick={() => setPlay(null)} aria-label="بستن">×</button></div>
                    <video src={play.url} controls autoPlay playsInline preload="metadata" />
                </Modal>
            )}
        </div>
    );
}
