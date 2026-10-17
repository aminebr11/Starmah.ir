import { useEffect, useRef, useState, useCallback } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import '@/../css/live-contest.css';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const SHAPES = ['▲', '◆', '●', '■'];
// فقط حرف‌ها (نه رقم‌های فارسی) جهت را راست‌به‌چپ می‌کنند؛ «۷ × ۸ = ?» چپ‌به‌راست می‌ماند
const dirOf = (t) => (/[ء-يٱ-ۓۺ-ۿ]/.test(String(t ?? '')) ? 'rtl' : 'ltr');
const clock = (s) => {
    const h = Math.floor(s / 3600); const m = Math.floor((s % 3600) / 60); const x = s % 60;
    return fa(h ? `${h}:${String(m).padStart(2, '0')}:${String(x).padStart(2, '0')}` : `${m}:${String(x).padStart(2, '0')}`);
};
const CHEERS = ['آفرین! 🌟', 'عالی بود! 🚀', 'دمت گرم! 💪', 'فوق‌العاده! ✨', 'مثلِ قهرمان‌ها! 🏅'];
const HUGS = ['اشکالی ندارد، بعدی مالِ توست! 💛', 'ادامه بده، داری یاد می‌گیری! 🌱', 'نزدیک بود! 💪'];
const TIPS = ['⚡ هر چه زودتر جواب بدهی، امتیازِ بیشتری می‌گیری.', '🔥 جواب‌های درستِ پشتِ سرِ هم جایزه دارند.', '🌟 سؤالِ آخر طلایی است: امتیازش دو برابر!', '🎯 اول سؤال را کامل بخوان، بعد بزن.'];
const buzz = (p) => { try { navigator.vibrate?.(p); } catch { /* */ } };

/** گوشی/تبلتِ دانش‌آموز در «مسابقه‌ی زنده». */
export default function LivePlay({ contest }) {
    const [s, setS] = useState(null);
    const [now, setNow] = useState(performance.now());
    const [picked, setPicked] = useState({});
    const [msg, setMsg] = useState(null);
    const [missed, setMissed] = useState(null);   // «سؤالِ … را از دست دادی» پس از قطعیِ اینترنت
    const [tip, setTip] = useState(0);
    const got = useRef({ at: 0, remaining: 0, lead: 0, starts: null });
    // 🎵 آهنگِ مسابقه (اگر معلم گذاشته): خودکار پخش، هنگامِ سؤال آرام‌تر، با دکمه‌ی قطع که در گوشی می‌ماند
    const music = useRef(null);
    const [trackId, setTrackId] = useState(contest.music || null);
    const [muted, setMuted] = useState(() => { try { return localStorage.getItem('live-music-muted-student') === '1'; } catch { return false; } });
    const [blocked, setBlocked] = useState(false);
    const last = useRef({ phase: null, q: -1 });

    const load = useCallback(async () => {
        try {
            // بی‌مهلت، یک درخواستِ گیرکرده روی اینترنتِ ضعیف همه‌ی به‌روزرسانی‌ها را متوقف می‌کرد
            // و گوشی سؤالِ قدیمی را نشان می‌داد تا یک‌باره چند سؤال جلو بپرد
            const { data } = await axios.get(route('live.poll', contest.id), { timeout: 5000 });
            got.current = { at: performance.now(), remaining: data.remaining, lead: data.lead || 0, starts: data.starts_in, ends: data.ends_in };
            setTrackId(data.music || null);
            setS((old) => {
                // لرزشِ گوشی: درست/نادرست
                if (data.phase === 'reveal' && old?.phase !== 'reveal' && data.mine) buzz(data.mine.correct ? [40, 60, 40] : 160);
                if (data.phase === 'question' && (old?.phase !== 'question' || old?.current !== data.current)) buzz(25);
                // گوشی به‌خاطرِ اینترنت عقب مانده بود و یک یا چند سؤال گذشت
                if (old && old.current >= 0 && ['question', 'reveal'].includes(data.phase) && data.current > old.current + 1) {
                    const from = old.current + 2; const to = data.current;
                    setMissed(from === to ? `سؤالِ ${fa(from)}` : `سؤال‌های ${fa(from)} تا ${fa(to)}`);
                }
                return data;
            });
            setMsg(null);
        } catch (e) {
            setMsg(e?.response?.status === 403 ? 'این مسابقه برای کلاسِ تو نیست.' : 'اینترنت قطع شد؛ دوباره وصل می‌شویم…');
        }
    }, [contest.id]);

    useEffect(() => {
        let alive = true; let t;
        const loop = async () => {
            await load();
            const fast = got.current.lead > 0 || (got.current.starts != null && got.current.starts < 15);
            if (alive) t = setTimeout(loop, document.hidden ? 4000 : fast ? 700 : 1300);
        };
        loop();
        const vis = () => { if (!document.hidden) load(); };
        document.addEventListener('visibilitychange', vis);
        return () => { alive = false; clearTimeout(t); document.removeEventListener('visibilitychange', vis); };
    }, [load]);

    useEffect(() => { const id = setInterval(() => setNow(performance.now()), 200); return () => clearInterval(id); }, []);

    const playMusic = useCallback(() => {
        const a = music.current;
        if (!a || muted) return;
        a.play().then(() => setBlocked(false)).catch(() => setBlocked(true));
    }, [muted]);
    useEffect(() => {
        const a = music.current;
        if (!a) return;
        if (muted) { a.pause(); setBlocked(false); } else playMusic();
        try { localStorage.setItem('live-music-muted-student', muted ? '1' : '0'); } catch { /* */ }
    }, [muted, playMusic, trackId]);
    // مرورگر پخشِ خودکار را نگذاشت → با اولین لمس شروع شود
    useEffect(() => {
        if (!blocked) return undefined;
        const go = () => playMusic();
        window.addEventListener('pointerdown', go, { once: true });
        return () => window.removeEventListener('pointerdown', go);
    }, [blocked, playMusic]);
    useEffect(() => {
        const a = music.current;
        if (!a) return;
        a.volume = s?.phase === 'question' ? 0.25 : s?.phase === 'end' ? 0.8 : 0.5;
    }, [s?.phase, trackId]);
    useEffect(() => { const id = setInterval(() => setTip((x) => (x + 1) % TIPS.length), 4500); return () => clearInterval(id); }, []);

    // «now» هر ۲۰۰ میلی‌ثانیه به‌روز می‌شود و ممکن است از زمانِ آخرین پاسخِ سرور عقب‌تر باشد
    const since = Math.max(0, now - got.current.at);
    const leadMs = Math.max(0, got.current.lead - since);
    const remMs = Math.max(0, got.current.remaining - Math.max(0, since - got.current.lead));
    const left = Math.ceil(remMs / 1000);
    const startsIn = got.current.starts != null ? Math.max(0, Math.ceil(got.current.starts - since / 1000)) : null;
    const endsIn = got.current.ends != null ? Math.max(0, Math.ceil(got.current.ends - since / 1000)) : null;

    if (s && (last.current.phase !== s.phase || last.current.q !== s.current)) last.current = { phase: s.phase, q: s.current };

    useEffect(() => { if (!missed) return undefined; const t = setTimeout(() => setMissed(null), 6000); return () => clearTimeout(t); }, [missed]);
    // اگر چند ثانیه هیچ پاسخی از سرور نرسیده، صفحه‌ی گوشی ممکن است کهنه باشد
    const stale = s && ['question', 'reveal'].includes(s.phase) && since > 4500;

    const answer = async (k) => {
        if (!s || s.phase !== 'question' || leadMs > 0 || picked[s.current] != null || s.mine) return;
        setPicked((p) => ({ ...p, [s.current]: k }));
        buzz(30);
        try {
            await axios.post(route('live.answer', contest.id), { q: s.current, choice: k }, { timeout: 8000 });
        } catch (e) {
            setPicked((p) => ({ ...p, [s.current]: undefined }));
            setMsg(e?.response?.data?.message || 'جواب نرسید؛ دوباره بزن.');
        }
    };

    const myChoice = s ? (s.mine?.choice ?? picked[s.current]) : null;
    const q = s?.question;
    // امتیازِ ممکن همین لحظه (با گذشتِ زمان کم می‌شود)
    const potential = s && s.phase === 'question' ? Math.round((500 + 500 * (remMs / (s.seconds * 1000))) * (s.golden ? 2 : 1)) : 0;
    const n = q?.choices.length || 4;

    return (
        <div className={`lvp phase-${s?.phase || 'wait'} ${s?.golden ? 'golden' : ''} ${s?.phase === 'reveal' ? (s.mine?.correct ? 'res-ok' : 'res-no') : ''}`}>
            <Head title={`🏆 ${contest.title}`} />
            <header className="lvp-top">
                <Link href={route('live')} className="lvp-x" aria-label="خروج">✕</Link>
                <b className="lvp-title">{contest.title}</b>
                {trackId && (
                    <button type="button" className={`lvp-mus ${blocked ? 'blocked' : ''} ${muted ? 'off' : ''}`} aria-label={muted ? 'پخشِ آهنگ' : 'قطعِ آهنگ'}
                        onClick={() => (blocked ? playMusic() : setMuted(!muted))}>{blocked ? '▶🎵' : muted ? '🔇' : '🎵'}</button>
                )}
                {s?.me && <span className="lvp-score"><small>امتیاز</small>{fa(s.me.score)}</span>}
            </header>
            {msg && <div className="lvp-msg">{msg}</div>}
            {stale && !msg && <div className="lvp-msg lvp-weak">📶 اینترنتِ تو ضعیف است؛ صفحه ممکن است عقب باشد… در حالِ وصل شدن</div>}
            {missed && <div className="lvp-msg lvp-missed">📶 به‌خاطرِ اینترنت، {missed} را از دست دادی. اشکالی ندارد، ادامه بده! 💪</div>}
            {trackId && <audio key={trackId} ref={music} src={route('live.music', contest.id) + '?t=' + trackId} loop preload="auto" playsInline
                autoPlay={!muted} onPlay={() => setBlocked(false)} onCanPlay={() => { if (!muted && music.current?.paused) playMusic(); }} />}

            {!s ? <div className="lvp-center"><div className="lvh-spin" /></div> : s.phase === 'lobby' || s.phase === 'draft' ? (
                <main className="lvp-center lvp-lobby">
                    <div className="lvp-badge">✅ وارد شدی!</div>
                    {startsIn != null ? (
                        <div className="lvp-countdown">
                            <span>{startsIn > 0 ? 'شروعِ مسابقه تا' : 'الان شروع می‌شود…'}</span>
                            {startsIn > 0 && <b>{clock(startsIn)}</b>}
                        </div>
                    ) : (
                        <h2>چشمت به تخته باشد؛ معلم به‌زودی شروع می‌کند.</h2>
                    )}
                    {s.ends && <div className="lvp-ends">🏁 پایانِ مسابقه: {s.ends}</div>}
                    <div className="lvp-players">👥 {fa(s.players)} نفر آماده‌اند</div>
                    {s.names?.length > 0 && (
                        <div className="lvp-names">{s.names.map((nm, i) => <span key={nm + i} style={{ animationDelay: `${(i % 8) * 0.06}s` }}>{nm}</span>)}</div>
                    )}
                    <div className="lvp-tip" key={tip}>{TIPS[tip]}</div>
                    <div className="lvp-pulse" />
                </main>
            ) : s.phase === 'end' ? (
                <main className="lvp-center lvp-end">
                    {s.me?.rank <= 3 && <div className="lvp-confetti" aria-hidden>{Array.from({ length: 28 }, (_, i) => <i key={i} style={{ left: `${(i * 37) % 100}%`, animationDelay: `${(i % 9) * 0.3}s`, background: ['#ffd23f', '#e8505b', '#2e8bff', '#2bb673'][i % 4] }} />)}</div>}
                    <div className="lvp-big pop">{s.me?.rank === 1 ? '🥇' : s.me?.rank === 2 ? '🥈' : s.me?.rank === 3 ? '🥉' : '🏁'}</div>
                    {s.timed_out && <div className="lvp-ends">⏰ زمانِ مسابقه تمام شد؛ رتبه‌ها نهایی است.</div>}
                    <h2>{s.me?.rank ? `رتبه‌ی ${fa(s.me.rank)} از ${fa(s.players)}` : 'مسابقه تمام شد'}</h2>
                    {s.summary && (
                        <div className="lvp-stats">
                            <div><b>{fa(s.me.score)}</b><span>امتیاز</span></div>
                            <div><b>{fa(s.summary.correct)}/{fa(s.total)}</b><span>درست</span></div>
                            <div><b>{fa(s.summary.accuracy)}٪</b><span>دقت</span></div>
                            <div><b>{s.summary.avg_s != null ? fa(s.summary.avg_s) : '—'}</b><span>ثانیه‌ی میانگین</span></div>
                            <div><b>🔥{fa(s.summary.best_streak)}</b><span>بهترین زنجیره</span></div>
                            <div className="xp"><b>+{fa(s.summary.xp)}</b><span>امتیازِ کارنامه</span></div>
                        </div>
                    )}
                    <div className="lvp-podium">
                        {[1, 0, 2].map((k) => s.podium?.[k] && (
                            <div key={k} className={`p${k + 1} ${s.me?.rank === k + 1 ? 'me' : ''}`}>
                                <span>{['🥇', '🥈', '🥉'][k]}</span><b>{s.podium[k].name}</b><small>{fa(s.podium[k].score)}</small><i />
                            </div>
                        ))}
                    </div>
                    {s.summary && <p className="lvp-note">🎁 +{fa(s.summary.xp)} امتیاز به کارنامه‌ات اضافه شد.</p>}
                    <Link href={route('live')} className="lvp-btn">بازگشت به مسابقه‌ها</Link>
                </main>
            ) : s.phase === 'reveal' ? (
                <main className={`lvp-center lvp-reveal ${s.mine ? (s.mine.correct ? 'ok' : 'no') : 'late'}`}>
                    {s.mine ? (
                        s.mine.correct ? (
                            <>
                                <div className="lvp-big pop">🎉</div>
                                <h2>{CHEERS[s.current % CHEERS.length]}</h2>
                                <div className="lvp-points">+{fa(s.mine.points)}</div>
                                {s.golden && <div className="lvp-golden-tag">🌟 امتیازِ دوبرابرِ سؤالِ طلایی</div>}
                                {s.me?.streak >= 2 && <div className="lvp-streak">🔥 {fa(s.me.streak)} درستِ پشتِ سرِ هم!</div>}
                            </>
                        ) : (
                            <>
                                <div className="lvp-big">💛</div>
                                <h2>{HUGS[s.current % HUGS.length]}</h2>
                            </>
                        )
                    ) : (
                        <>
                            <div className="lvp-big">⏰</div>
                            <h2>زمان تمام شد!</h2>
                        </>
                    )}
                    {(!s.mine || !s.mine.correct) && q && <p className="lvp-right">جوابِ درست: <b className={`lvp-chip c${s.answer}`}>{SHAPES[s.answer]} <span dir={dirOf(q.choices[s.answer])}>{q.choices[s.answer]}</span></b></p>}
                    {s.me?.rank && (
                        <div className="lvp-rank">
                            <span>رتبه‌ی تو</span>
                            <b>{fa(s.me.rank)}</b>
                            {s.me.delta > 0 && <em className="up">▲{fa(s.me.delta)}</em>}
                            {s.me.delta < 0 && <em className="down">▼{fa(-s.me.delta)}</em>}
                        </div>
                    )}
                    {s.me?.gap && <div className="lvp-gap">فقط {fa(s.me.gap)} امتیاز تا رسیدن به {s.me.above}! 💨</div>}
                    {s.me?.rank === 1 && <div className="lvp-gap">👑 تو الان اولی؛ نگهش دار!</div>}
                </main>
            ) : (
                <main className="lvp-q">
                    <div className="lvp-qhead">
                        <span className="lvp-qn">سؤالِ {fa(s.current + 1)} از {fa(s.total)}</span>
                        {endsIn != null && <span className={`lvp-endsin ${endsIn <= 60 ? 'low' : ''}`}>🏁 {clock(endsIn)}</span>}
                        {s.golden && <span className="lvp-golden-tag">🌟 طلایی ×۲</span>}
                        {leadMs === 0 && <span className={`lvp-time ${left <= 5 ? 'low' : ''}`}>⏱ {fa(left)}</span>}
                    </div>
                    <div className="lvp-tbar"><i style={{ width: `${leadMs > 0 ? 100 : Math.min(100, (remMs / (s.seconds * 1000)) * 100)}%` }} /></div>
                    <div className="lvp-prompt" dir={dirOf(q?.prompt)}>{q?.prompt}</div>
                    {leadMs > 0 ? (
                        <div className="lvp-center lvp-ready">
                            <span>آماده باش…</span>
                            <b key={Math.ceil(leadMs / 1000)}>{fa(Math.ceil(leadMs / 1000))}</b>
                        </div>
                    ) : myChoice != null ? (
                        <div className="lvp-center lvp-sent">
                            <div className={`lvp-chip big c${myChoice}`}>{SHAPES[myChoice]}</div>
                            <h2>جوابت ثبت شد!</h2>
                            <p>منتظرِ بقیه باش… 👀</p>
                            {s.online > 0 && <div className="lvp-players">✍️ {fa(s.answered)} از {fa(s.online)} نفر جواب داده‌اند</div>}
                        </div>
                    ) : (
                        <>
                            <div className="lvp-potential">⚡ امتیازِ این لحظه: <b>{fa(potential)}</b></div>
                            <div className={`lvp-tiles n${n}`}>
                                {q?.choices.map((c, k) => (
                                    <button key={k} type="button" className={`lvp-tile c${k}`} onClick={() => answer(k)} disabled={left <= 0}>
                                        <i>{SHAPES[k]}</i><span dir={dirOf(c)}>{c}</span>
                                    </button>
                                ))}
                            </div>
                        </>
                    )}
                </main>
            )}
        </div>
    );
}
