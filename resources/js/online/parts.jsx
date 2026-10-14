import { Children, useEffect, useRef, useState } from 'react';
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
export function Vid({ track, mirror = false, className = '', label, fit = 'cover', onEl }) {
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
        <div className={`oc-vid ${live ? 'live' : ''} ${className}`}>
            <video ref={ref} autoPlay playsInline muted style={{ transform: mirror ? 'scaleX(-1)' : undefined, objectFit: fit }} />
            {label && <span className="oc-vid-l">{label}</span>}
        </div>
    );
}

/**
 * جعبه‌ی جابه‌جاشدنی برای تصویرها: با انگشت/ماوس بکشید؛ جای آن روی همین دستگاه می‌ماند.
 * جا به نسبتِ قاب ذخیره می‌شود تا با چرخاندنِ گوشی بیرون نزند.
 */
export function DragBox({ id, className = '', children }) {
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
    const up = () => {
        const d = drag.current;
        drag.current = null;
        if (d?.moved) setPos((p) => { save(key, p); return p; });
    };
    const toggle = (k, v, set) => { set(v); save(`${key}-${k}`, v); };
    const style = pos ? { left: `${pos.fx * 100}%`, top: `${pos.fy * 100}%`, right: 'auto', bottom: 'auto', transform: `translate(${-pos.fx * 100}%, ${-pos.fy * 100}%)` } : undefined;

    return (
        <div ref={ref} className={`oc-drag ${className} ${mini ? 'mini' : ''} ${big ? 'big' : ''}`} style={style}
            onPointerDown={down} onPointerMove={move} onPointerUp={up} onPointerCancel={up}>
            <div className="oc-drag-h">
                <span className="oc-drag-grip" aria-hidden="true">⠿</span>
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

export function People({ people, me, isMod, isHost, tiles, onPerm, onStar, onKick, onSpot, spotlight, onAll, speaking = [], msOf = () => '' }) {
    const online = people.filter((p) => p.online);
    const hands = online.filter((p) => p.hand).sort((a, b) => a.hand - b.hand);
    const students = people.filter((p) => p.role === 'student');
    return (
        <div className="oc-people">
            {isMod && (
                <div className="oc-pp-acts">
                    <button type="button" onClick={() => onAll('lower_hands')}>✋ پایین آوردنِ دست‌ها</button>
                    <button type="button" onClick={() => onAll('mute_all')}>🔇 بی‌صدا کردنِ همه</button>
                    <button type="button" onClick={() => onAll('open_mics', true)}>🎤 باز کردنِ میکِ همه</button>
                </div>
            )}
            {hands.length > 0 && (
                <div className="oc-hands">
                    <b>✋ دستِ بالا ({fa(hands.length)})</b>
                    {hands.map((p, i) => (
                        <div key={p.id} className="oc-hand">
                            <span>{fa(i + 1)}. {p.name}</span>
                            {isMod && p.role === 'student' && <button type="button" onClick={() => onPerm(p.id, 'can_speak', true)}>🎤 اجازه‌ی صحبت</button>}
                        </div>
                    ))}
                </div>
            )}
            <div className="oc-pp-count">{fa(online.length)} نفر آنلاین · {fa(students.length)} دانش‌آموز</div>
            {people.map((p) => {
                const ms = msOf(p.id);
                const cam = (p.can_video || p.role !== 'student') && ms.includes('+cam') ? tiles[p.id]?.cam : null;
                return (
                    <div key={p.id} className={`oc-person ${p.online ? '' : 'off'}`}>
                        <div className="oc-p-main">
                            <span className={`oc-p-av ${speaking.includes(p.id) ? 'spk' : ''}`}><Avatar id={p.id} name={p.name} size={34} /><i className={p.online ? 'on' : ''} /></span>
                            <span className="oc-p-name">
                                {p.name}{p.id === me ? ' (من)' : ''}
                                <small>{p.role === 'host' ? '👩‍🏫 معلم' : p.role === 'observer' ? '🛡️ ناظر' : p.stars ? `⭐ ${fa(p.stars)}` : ''}{p.hand ? ' ✋' : ''}</small>
                            </span>
                            {p.role === 'student' && (
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
                                <button type="button" className={p.can_speak ? 'on' : ''} onClick={() => onPerm(p.id, 'can_speak', !p.can_speak)} title="صحبت">🎤</button>
                                <button type="button" className={p.can_video ? 'on' : ''} onClick={() => onPerm(p.id, 'can_video', !p.can_video)} title="دوربین">🎥</button>
                                <button type="button" className={p.can_draw ? 'on' : ''} onClick={() => onPerm(p.id, 'can_draw', !p.can_draw)} title="تخته">✏️</button>
                                <button type="button" onClick={() => onStar(p.id)} title="ستاره و امتیاز">⭐</button>
                                {isHost && cam && <button type="button" className={spotlight === p.id ? 'on' : ''} onClick={() => onSpot(spotlight === p.id ? null : p.id)} title="نمایشِ تصویرش برای همه">👁️</button>}
                                <button type="button" onClick={() => onKick(p)} title="بیرون گذاشتن">🚪</button>
                            </div>
                        )}
                        {isMod && cam && <Vid track={cam} className="oc-p-cam" />}
                    </div>
                );
            })}
        </div>
    );
}

export function PollPanel({ poll, isMod, onCreate, onClose }) {
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

/** گزارشِ حضورِ کلاس (برای معلم و مدیر). */
export function ReportModal({ url, onClose }) {
    const [data, setData] = useState(null);
    useEffect(() => { axios.get(url).then(({ data: d }) => setData(d)).catch(() => setData({ error: true })); }, [url]);
    return (
        <div className="oc-modal" role="dialog" aria-label="گزارشِ حضور" onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
            <div className="oc-modal-box">
                <div className="oc-modal-h"><b>📋 گزارشِ حضور</b><button type="button" onClick={onClose} aria-label="بستن">×</button></div>
                {!data && <div className="oc-empty">در حالِ بارگذاری…</div>}
                {data?.error && <div className="oc-empty">گزارش بارگذاری نشد.</div>}
                {data?.rows && (
                    <>
                        <div className="oc-rep-sum">
                            <span>🎓 حاضر: <b>{fa(data.present)}</b> از {fa(data.total)}</span>
                            <span>⏱️ مدت: <b>{fa(data.length)}</b> دقیقه</span>
                            <span>💬 پیام: <b>{fa(data.messages)}</b></span>
                        </div>
                        <table className="oc-rep">
                            <thead><tr><th>نام</th><th>حضور</th><th>دقیقه</th><th>ورود</th><th>⭐</th><th>امتیاز</th></tr></thead>
                            <tbody>
                                {data.rows.map((r) => (
                                    <tr key={r.id} className={r.present ? '' : 'abs'}>
                                        <td>{r.name}</td><td>{r.present ? '✅' : '❌ غایب'}</td><td>{fa(r.minutes)}</td>
                                        <td>{r.first ? `${r.first}${r.joins > 1 ? ` (${fa(r.joins)} بار)` : ''}` : '—'}</td><td>{fa(r.stars)}</td><td>{fa(r.xp)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </>
                )}
            </div>
        </div>
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
                <div className="oc-modal" role="dialog" aria-label={play.title} onClick={(e) => { if (e.target === e.currentTarget) setPlay(null); }}>
                    <div className="oc-modal-box oc-player">
                        <div className="oc-player-h"><b>🎬 {play.title}</b><small>{play.at}</small><button type="button" onClick={() => setPlay(null)} aria-label="بستن">×</button></div>
                        <video src={play.url} controls autoPlay playsInline preload="metadata" />
                    </div>
                </div>
            )}
        </div>
    );
}
