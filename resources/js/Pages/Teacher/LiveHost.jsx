import { useEffect, useRef, useState, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';

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
    const got = useRef({ at: 0, remaining: 0 });
    const busy = useRef(false);
    const autoRevealed = useRef(-1);
    const lastTick = useRef(-1);

    const apply = useCallback((data) => {
        got.current = { at: performance.now(), remaining: data.remaining };
        setS((old) => {
            if (data.phase === 'end' && old?.phase !== 'end') [523, 659, 784, 1046].forEach((f, i) => setTimeout(() => beep(sound, f, 220, 'triangle'), i * 160));
            if (data.phase === 'reveal' && old?.phase === 'question') beep(sound, 880, 200, 'triangle');
            return data;
        });
        setErr(null);
    }, [sound]);

    const load = useCallback(async () => {
        try { apply((await axios.get(route('teacher.live.state', contest.id))).data); } catch { setErr('اتصال قطع است؛ دوباره تلاش می‌شود…'); }
    }, [contest.id, apply]);

    const go = async (action) => {
        if (busy.current) return;
        busy.current = true;
        try { apply((await axios.post(route('teacher.live.go', contest.id), { action })).data); } catch { setErr('فرمان نرسید؛ دوباره بزنید.'); } finally { busy.current = false; }
    };

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
            const r = Math.max(0, got.current.remaining - (performance.now() - got.current.at));
            const sec = Math.ceil(r / 1000);
            setLeft(sec);
            if (s?.phase === 'question' && sec <= 5 && sec > 0 && lastTick.current !== sec) { lastTick.current = sec; beep(sound, 520, 70); }
        }, 200);
        return () => clearInterval(id);
    }, [s?.phase, sound]);

    // همه جواب دادند → جواب را نشان بده
    useEffect(() => {
        if (s?.phase === 'question' && s.online > 0 && s.answered >= s.online && autoRevealed.current !== s.current) {
            autoRevealed.current = s.current;
            setTimeout(() => go('reveal'), 900);
        }
    }, [s?.phase, s?.answered, s?.online, s?.current]); // eslint-disable-line react-hooks/exhaustive-deps

    const primary = !s ? null : s.phase === 'lobby' || s.phase === 'draft' ? ['start', '▶ شروعِ مسابقه'] : s.phase === 'question' ? ['reveal', '👀 نمایشِ جواب']
        : s.phase === 'reveal' ? ['next', s.current + 1 >= s.total ? '🏆 پایان و سکوی قهرمانی' : 'سؤالِ بعد ←'] : null;

    useEffect(() => {
        const onKey = (e) => { if ((e.key === ' ' || e.key === 'Enter' || e.key === 'ArrowLeft') && primary) { e.preventDefault(); go(primary[0]); } };
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
                <button type="button" className="lvh-pill" onClick={() => setSound(!sound)}>{sound ? '🔊' : '🔇'}</button>
                <button type="button" className="lvh-pill" onClick={full}>⛶ تمام‌صفحه</button>
            </header>
            {err && <div className="lvh-err">{err}</div>}

            {!s ? <div className="lvh-center"><div className="lvh-spin" /></div> : s.phase === 'lobby' || s.phase === 'draft' ? (
                <main className="lvh-lobby">
                    <div className="lvh-trophy">🏆</div>
                    <h1>{contest.title}</h1>
                    <p className="lvh-how">بچه‌ها! با گوشی یا تبلت وارد <b>استارماه</b> شوید و از منو <b>«🏆 مسابقه‌ی زنده»</b> را بزنید.</p>
                    <div className="lvh-count"><b>{fa(s.players)}</b><span>نفر آماده‌اند{s.audience ? ` از ${fa(s.audience)}` : ''}</span></div>
                    {s.starts_in != null && s.mode === 'auto' && <div className="lvh-starts">⏰ شروعِ خودکار تا {mmss(s.starts_in)} دیگر</div>}
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
                <main className="lvh-play">
                    <div className="lvh-qrow">
                        {s.phase === 'question' ? (
                            <div className="lvh-timer" style={{ '--p': `${Math.min(100, (left / s.seconds) * 100)}%` }} data-low={left <= 5}>
                                <span>{fa(left)}</span>
                            </div>
                        ) : <div className="lvh-timer done"><span>✓</span></div>}
                        <h2 className="lvh-q" dir={dirOf(q?.prompt)}>{q?.prompt}</h2>
                        <div className="lvh-ans"><b>{fa(s.answered)}</b><span>جواب</span></div>
                    </div>
                    <div className={`lvh-tiles n${q?.choices.length}`}>
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
                                    <span className="lvh-name">{r.name}{r.streak >= 2 ? ` 🔥${fa(r.streak)}` : ''}</span>
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
