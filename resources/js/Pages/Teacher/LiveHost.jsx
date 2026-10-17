import { useEffect, useRef, useState, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import '@/../css/live-contest.css';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const SHAPES = ['▲', '◆', '●', '■'];
// فقط حرف‌ها (نه رقم‌های فارسی) جهت را راست‌به‌چپ می‌کنند؛ «۷ × ۸ = ?» چپ‌به‌راست می‌ماند
const dirOf = (t) => (/[\u0621-\u064A\u0671-\u06D3\u06FA-\u06FF]/.test(String(t ?? '')) ? 'rtl' : 'ltr');
const mmss = (s) => fa(`${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`);

/** صدای کوتاه (بدونِ فایل) — تیک‌تاکِ ثانیه‌های آخر و جشنِ پایان. */
function beep(on, freq = 660, ms = 90, type = 'sine') {
    if (!on) return;
    try {
        const AC = window.AudioContext || window.webkitAudioContext;
        beep.ctx ||= new AC();
        const ctx = beep.ctx;
        const o = ctx.createOscillator(); const g = ctx.createGain();
        o.type = type; o.frequency.value = freq;
        g.gain.setValueAtTime(0.12, ctx.currentTime);
        g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + ms / 1000);
        o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + ms / 1000);
    } catch { /* بی‌صدا */ }
}

/** تخته‌ی هوشمند / ویدئوپروژکتور: اجرای «مسابقه‌ی زنده». */
export default function LiveHost({ contest }) {
    const [s, setS] = useState(null);
    const [left, setLeft] = useState(0);
    const [sound, setSound] = useState(true);
    const [err, setErr] = useState(null);
    // 🎵 آهنگِ مسابقه: خودکار، تکرارشونده، در همه‌ی مرحله‌ها؛ با دکمه‌ی قطع (انتخاب در مرورگر می‌ماند)
    const music = useRef(null);
    const [muted, setMuted] = useState(() => { try { return localStorage.getItem('live-music-muted') === '1'; } catch { return false; } });
    const [blocked, setBlocked] = useState(false);
    const got = useRef({ at: 0, remaining: 0, lead: 0 });
    const [lead, setLead] = useState(0);
    const [endLeft, setEndLeft] = useState(0);
    const busy = useRef(false);
    const autoRevealed = useRef(-1);
    const lastTick = useRef(-1);

    const now = useRef(null);          // آخرین وضعیتِ سرور (برای فرمان‌ها؛ نه وضعیتِ کهنه‌ی رندر)
    const cool = useRef(0);            // بعد از هر فرمان، چند لحظه فرمانِ دوم پذیرفته نشود (دو بار زدن)
    const autoTimer = useRef(null);

    const apply = useCallback((data) => {
        now.current = data;
        got.current = { at: performance.now(), remaining: data.remaining, lead: data.lead || 0, ends: data.ends_in };
        setS((old) => {
            if (data.phase === 'end' && old?.phase !== 'end') [523, 659, 784, 1046].forEach((f, i) => setTimeout(() => beep(sound, f, 220, 'triangle'), i * 160));
            if (data.phase === 'reveal' && old?.phase === 'question') beep(sound, 880, 200, 'triangle');
            return data;
        });
        setErr(null);
    }, [sound]);

    const load = useCallback(async () => {
        try { apply((await axios.get(route('teacher.live.state', contest.id), { timeout: 8000 })).data); } catch { setErr('اتصال قطع است؛ دوباره تلاش می‌شود…'); }
    }, [contest.id, apply]);

    const go = async (action, expect = null) => {
        if (busy.current || (action !== 'end' && action !== 'reset' && Date.now() < cool.current)) return;
        busy.current = true;
        const st = expect ?? now.current;
        // سؤال و مرحله‌ای که الان روی تخته است همراهِ فرمان می‌رود؛ سرور فرمانِ کهنه را اجرا نمی‌کند
        const body = { action, ...(st && ['reveal', 'next'].includes(action) ? { q: st.current, from: st.phase } : {}) };
        try {
            apply((await axios.post(route('teacher.live.go', contest.id), body, { timeout: 10000 })).data);
            cool.current = Date.now() + 700;
        } catch { setErr('فرمان نرسید؛ دوباره بزنید.'); } finally { busy.current = false; }
    };

    const playMusic = useCallback(() => {
        const a = music.current;
        if (!a || muted) return;
        a.play().then(() => setBlocked(false)).catch(() => setBlocked(true));
    }, [muted]);
    useEffect(() => {
        const a = music.current;
        if (!a) return;
        if (muted) { a.pause(); setBlocked(false); } else playMusic();
        try { localStorage.setItem('live-music-muted', muted ? '1' : '0'); } catch { /* */ }
    }, [muted, playMusic]);
    // مرورگر اجازه‌ی پخشِ خودکار نداد؟ با اولین لمس/کلیک روی تخته شروع شود
    useEffect(() => {
        if (!blocked) return undefined;
        const go = () => playMusic();
        window.addEventListener('pointerdown', go, { once: true });
        window.addEventListener('keydown', go, { once: true });
        return () => { window.removeEventListener('pointerdown', go); window.removeEventListener('keydown', go); };
    }, [blocked, playMusic]);
    // صدای آهنگ: هنگامِ سؤال آرام‌تر (تمرکز)، در سکوی قهرمانی بلندتر
    useEffect(() => {
        const a = music.current;
        if (!a) return;
        a.volume = s?.phase === 'question' ? 0.3 : s?.phase === 'end' ? 0.85 : 0.6;
    }, [s?.phase]);

    // نظرسنجیِ هر ثانیه
    useEffect(() => {
        let alive = true; let t;
        const loop = async () => { await load(); if (alive) t = setTimeout(loop, 1000); };
        loop();
        return () => { alive = false; clearTimeout(t); };
    }, [load]);

    // شمارش معکوسِ نرم
    useEffect(() => {
        const id = setInterval(() => {
            const since = performance.now() - got.current.at;
            const ld = Math.max(0, got.current.lead - since);
            const r = Math.max(0, got.current.remaining - Math.max(0, since - got.current.lead));
            const sec = Math.ceil(r / 1000);
            setLead(Math.ceil(ld / 1000));
            if (got.current.ends != null) setEndLeft(Math.max(0, Math.ceil(got.current.ends - since / 1000)));
            setLeft(sec);
            if (s?.phase === 'question' && sec <= 5 && sec > 0 && lastTick.current !== sec) { lastTick.current = sec; beep(sound, 520, 70); }
        }, 200);
        return () => clearInterval(id);
    }, [s?.phase, sound]);

    // همه جواب دادند → جواب را نشان بده
    useEffect(() => {
        if (s?.phase === 'question' && !s.lead && s.online > 0 && s.answered >= s.online && autoRevealed.current !== s.current) {
            autoRevealed.current = s.current;
            const q = s.current;
            clearTimeout(autoTimer.current);
            // فقط اگر هنوز همان سؤال روی تخته است (معلم در این فاصله خودش جلو نرفته باشد)
            autoTimer.current = setTimeout(() => {
                const cur = now.current;
                if (cur?.phase === 'question' && cur.current === q) go('reveal', { current: q, phase: 'question' });
            }, 900);
        }
    }, [s?.phase, s?.answered, s?.online, s?.current]); // eslint-disable-line react-hooks/exhaustive-deps
    useEffect(() => () => clearTimeout(autoTimer.current), []);

    const primary = !s ? null : s.phase === 'lobby' || s.phase === 'draft' ? ['start', '▶ شروعِ مسابقه'] : s.phase === 'question' ? ['reveal', '👀 نمایشِ جواب']
        : s.phase === 'reveal' ? ['next', s.current + 1 >= s.total ? '🏆 پایان و سکوی قهرمانی' : 'سؤالِ بعد ←'] : null;

    useEffect(() => {
        const onKey = (e) => { if ((e.key === ' ' || e.key === 'Enter' || e.key === 'ArrowLeft') && primary && !e.repeat && !e.target?.closest?.('button,input,textarea,select')) { e.preventDefault(); go(primary[0]); } };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }); // eslint-disable-line react-hooks/exhaustive-deps

    const full = () => (document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen?.()).catch?.(() => {});
    const q = s?.question;
    const maxDist = Math.max(1, ...(s?.dist || [0]));
    const topScore = Math.max(1, ...((s?.top || []).map((r) => r.score)));

    return (
        <div className="lvh">
            <Head title={`🏆 ${contest.title}`} />
            <header className="lvh-top">
                <a href={route('teacher.live')} className="lvh-x" aria-label="بستن">✕</a>
                <b className="lvh-title">🏆 {contest.title}</b>
                {s && s.phase !== 'lobby' && s.phase !== 'end' && <span className="lvh-pill">سؤالِ {fa(s.current + 1)} از {fa(s.total)}</span>}
                {s && <span className="lvh-pill">👥 {fa(s.online)} آنلاین</span>}
                {s?.ends_in != null && <span className={`lvh-pill lvh-ends ${endLeft <= 60 ? 'low' : ''}`}>🏁 {endLeft > 0 ? `${mmss(endLeft)} تا پایان` : 'پایان!'}</span>}
                {contest.music && (
                    <button type="button" className={`lvh-pill lvh-music ${blocked ? 'blocked' : ''}`} title={contest.music.title}
                        onClick={() => (blocked ? playMusic() : setMuted(!muted))}>
                        {blocked ? '▶ پخشِ آهنگ' : muted ? '🔇 آهنگ' : '🎵 آهنگ'}
                    </button>
                )}
                <button type="button" className="lvh-pill" onClick={() => setSound(!sound)} title="صدای تیک‌تاک و جشن">{sound ? '🔔' : '🔕'}</button>
                <button type="button" className="lvh-pill" onClick={full}>⛶ تمام‌صفحه</button>
            </header>
            {err && <div className="lvh-err">{err}</div>}
            {contest.music && <audio ref={music} src={contest.music.url} loop preload="auto" playsInline autoPlay={!muted} onPlay={() => setBlocked(false)} />}

            {!s ? <div className="lvh-center"><div className="lvh-spin" /></div> : s.phase === 'lobby' || s.phase === 'draft' ? (
                <main className="lvh-lobby">
                    <div className="lvh-trophy">🏆</div>
                    <h1>{contest.title}</h1>
                    <p className="lvh-how">بچه‌ها! با گوشی یا تبلت وارد <b>استارماه</b> شوید و از منو <b>«🏆 مسابقه‌ی زنده»</b> را بزنید.</p>
                    <div className="lvh-count"><b>{fa(s.players)}</b><span>نفر آماده‌اند{s.audience ? ` از ${fa(s.audience)}` : ''}</span></div>
                    {s.starts_in != null && <div className="lvh-starts">⏰ {s.starts_in > 0 ? <>شروعِ خودکار تا <b className="lvh-clock">{mmss(s.starts_in)}</b> دیگر</> : 'الان شروع می‌شود…'}</div>}
                    {s.starts_in != null && s.mode !== 'auto' && <p className="lvh-how" style={{ fontSize: 'clamp(14px,1.6vw,20px)' }}>سرِ ساعت خودش شروع می‌شود؛ رفتن به سؤالِ بعد با شماست (اگر تخته را ببندید، خودش جلو می‌رود).</p>}
                    <div className="lvh-names">{(s.names || []).map((n, i) => <span key={n + i} style={{ animationDelay: `${(i % 10) * 0.08}s` }}>{n}</span>)}</div>
                </main>
            ) : s.phase === 'end' ? (
                <main className="lvh-end">
                    <h1>🎉 قهرمان‌های «{contest.title}»</h1>
                    <div className="lvh-podium">
                        {[1, 0, 2].map((k) => s.top?.[k] && (
                            <div key={k} className={`lvh-step p${k + 1}`}>
                                <div className="lvh-medal">{['🥇', '🥈', '🥉'][k]}</div>
                                <b>{s.top[k].name}</b>
                                <span>{fa(s.top[k].score)} امتیاز</span>
                                <div className="lvh-block">{fa(k + 1)}</div>
                            </div>
                        ))}
                    </div>
                    <ol className="lvh-rest">
                        {(s.top || []).slice(3, 20).map((r) => <li key={r.id}><span>{fa(r.rank)}. {r.name}</span><b>{fa(r.score)}</b></li>)}
                    </ol>
                    <div className="lvh-confetti" aria-hidden>{Array.from({ length: 40 }, (_, i) => <i key={i} style={{ left: `${(i * 97) % 100}%`, animationDelay: `${(i % 12) * 0.25}s`, background: ['#ffd23f', '#e8505b', '#2e8bff', '#2bb673'][i % 4] }} />)}</div>
                    <button type="button" className="lvh-btn ghost" onClick={() => confirm('مسابقه از نو اجرا شود؟ (امتیازهای این دور پاک می‌شود)') && go('reset')}>🔁 اجرای دوباره</button>
                </main>
            ) : (
                <main className={`lvh-play ${s.golden ? 'golden' : ''}`}>
                    {s.golden && <div className="lvh-golden">🌟 سؤالِ طلایی — امتیازِ دو برابر!</div>}
                    {s.phase === 'question' && lead > 0 && <div className="lvh-ready"><span>آماده باشید…</span><b key={lead}>{fa(lead)}</b></div>}
                    <div className="lvh-qrow">
                        {s.phase === 'question' ? (
                            <div className="lvh-timer" style={{ '--p': `${Math.min(100, (left / s.seconds) * 100)}%` }} data-low={left <= 5}>
                                <span>{fa(left)}</span>
                            </div>
                        ) : <div className="lvh-timer done"><span>✓</span></div>}
                        <h2 className="lvh-q" dir={dirOf(q?.prompt)}>{q?.prompt}</h2>
                        <div className="lvh-ans"><b>{fa(s.answered)}</b><span>جواب</span></div>
                    </div>
                    <div className={`lvh-tiles n${q?.choices.length} ${s.phase === 'question' && lead > 0 ? 'hidden' : ''}`}>
                        {q?.choices.map((c, k) => {
                            const isOk = s.phase === 'reveal' && s.answer === k;
                            const faded = s.phase === 'reveal' && s.answer !== k;
                            return (
                                <div key={k} className={`lvh-tile c${k} ${isOk ? 'ok' : ''} ${faded ? 'faded' : ''}`}>
                                    <i>{SHAPES[k]}</i><span dir={dirOf(c)}>{c}</span>
                                    {s.phase === 'reveal' && (
                                        <div className="lvh-bar"><b>👤 {fa(s.dist[k])}</b><span><em style={{ width: `${(s.dist[k] / maxDist) * 100}%` }} /></span></div>
                                    )}
                                    {isOk && <div className="lvh-check">✔</div>}
                                </div>
                            );
                        })}
                    </div>
                    {s.phase === 'question' && <div className="lvh-progress"><i style={{ width: `${s.online ? Math.min(100, (s.answered / s.online) * 100) : 0}%` }} /></div>}
                    {s.phase === 'reveal' && (
                        <div className="lvh-board">
                            <h3>📊 جدولِ امتیاز</h3>
                            {(s.top || []).map((r, i) => (
                                <div key={r.id} className="lvh-row" style={{ animationDelay: `${i * 0.08}s` }}>
                                    <span className="lvh-rank">{['🥇', '🥈', '🥉'][i] || fa(r.rank)}</span>
                                    <span className="lvh-name">{r.name}{r.streak >= 2 ? ` 🔥${fa(r.streak)}` : ''}
                                        {r.delta > 0 && <em className="lvh-up">▲{fa(r.delta)}</em>}{r.delta < 0 && <em className="lvh-down">▼{fa(-r.delta)}</em>}
                                        {r.gained > 0 && <small className="lvh-gain">+{fa(r.gained)}</small>}</span>
                                    <span className="lvh-sbar"><i style={{ width: `${(r.score / topScore) * 100}%` }} /></span>
                                    <b>{fa(r.score)}</b>
                                </div>
                            ))}
                        </div>
                    )}
                </main>
            )}

            {primary && (
                <footer className="lvh-foot">
                    {s.phase !== 'lobby' && <button type="button" className="lvh-btn ghost" onClick={() => confirm('مسابقه همین‌جا تمام شود؟') && go('end')}>⏹ پایان</button>}
                    <button type="button" className="lvh-btn" onClick={() => go(primary[0])} disabled={(s.phase === 'lobby' && !s.total)}>{primary[1]}</button>
                    <small>کلیدِ فاصله (Space) هم کار می‌کند</small>
                </footer>
            )}
        </div>
    );
}
