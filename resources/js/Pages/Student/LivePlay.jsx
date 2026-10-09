import { useEffect, useRef, useState, useCallback } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const SHAPES = ['▲', '◆', '●', '■'];
// فقط حرف‌ها (نه رقم‌های فارسی) جهت را راست‌به‌چپ می‌کنند؛ «۷ × ۸ = ?» چپ‌به‌راست می‌ماند
const dirOf = (t) => (/[\u0621-\u064A\u0671-\u06D3\u06FA-\u06FF]/.test(String(t ?? '')) ? 'rtl' : 'ltr');
const mmss = (s) => fa(`${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`);
const CHEERS = ['آفرین! 🌟', 'عالی بود! 🚀', 'دمت گرم! 💪', 'فوق‌العاده! ✨'];
const HUGS = ['اشکالی ندارد، بعدی مالِ توست! 💛', 'ادامه بده، داری یاد می‌گیری! 🌱', 'نزدیک بود! 💪'];

/** گوشی/تبلتِ دانش‌آموز در «مسابقه‌ی زنده». */
export default function LivePlay({ contest }) {
    const [s, setS] = useState(null);
    const [left, setLeft] = useState(0);
    const [picked, setPicked] = useState({});
    const [msg, setMsg] = useState(null);
    const got = useRef({ at: 0, remaining: 0 });

    const load = useCallback(async () => {
        try {
            const { data } = await axios.get(route('live.poll', contest.id));
            got.current = { at: performance.now(), remaining: data.remaining };
            setS(data);
            setMsg(null);
        } catch (e) {
            setMsg(e?.response?.status === 403 ? 'این مسابقه برای کلاسِ تو نیست.' : 'اینترنت قطع شد؛ دوباره وصل می‌شویم…');
        }
    }, [contest.id]);

    useEffect(() => {
        let alive = true; let t;
        const loop = async () => {
            await load();
            if (alive) t = setTimeout(loop, document.hidden ? 4000 : 1500);
        };
        loop();
        return () => { alive = false; clearTimeout(t); };
    }, [load]);

    useEffect(() => {
        const id = setInterval(() => setLeft(Math.ceil(Math.max(0, got.current.remaining - (performance.now() - got.current.at)) / 1000)), 250);
        return () => clearInterval(id);
    }, []);

    const answer = async (k) => {
        if (!s || s.phase !== 'question' || picked[s.current] != null || s.mine) return;
        setPicked((p) => ({ ...p, [s.current]: k }));
        navigator.vibrate?.(30);
        try {
            await axios.post(route('live.answer', contest.id), { q: s.current, choice: k });
        } catch (e) {
            setMsg(e?.response?.data?.message || 'جواب نرسید.');
        }
    };

    const myChoice = s ? (s.mine?.choice ?? picked[s.current]) : null;
    const q = s?.question;

    return (
        <div className={`lvp phase-${s?.phase || 'wait'}`}>
            <Head title={`🏆 ${contest.title}`} />
            <header className="lvp-top">
                <Link href={route('live')} className="lvp-x" aria-label="خروج">✕</Link>
                <b>{contest.title}</b>
                {s?.me && <span className="lvp-score">⭐ {fa(s.me.score)}</span>}
            </header>
            {msg && <div className="lvp-msg">{msg}</div>}

            {!s ? <div className="lvp-center"><div className="lvh-spin" /></div> : s.phase === 'lobby' || s.phase === 'draft' ? (
                <main className="lvp-center">
                    <div className="lvp-big">✅</div>
                    <h2>وارد شدی!</h2>
                    <p>چشمت به تخته باشد؛ مسابقه به‌زودی شروع می‌شود.</p>
                    {s.starts_in != null && <div className="lvp-starts">⏰ {s.starts_in > 0 ? <>شروع تا {mmss(s.starts_in)} دیگر</> : 'الان شروع می‌شود…'}</div>}
                    <div className="lvp-players">👥 {fa(s.players)} نفر آماده‌اند</div>
                    <div className="lvp-pulse" />
                </main>
            ) : s.phase === 'end' ? (
                <main className="lvp-center">
                    <div className="lvp-big">{s.me?.rank === 1 ? '🥇' : s.me?.rank === 2 ? '🥈' : s.me?.rank === 3 ? '🥉' : '🏁'}</div>
                    <h2>{s.me ? `رتبه‌ی ${fa(s.me.rank)} از ${fa(s.players)}` : 'مسابقه تمام شد'}</h2>
                    {s.me && <p>{fa(s.me.score)} امتیاز · {fa(s.me.correct)} جوابِ درست از {fa(s.total)}</p>}
                    <div className="lvp-podium">
                        {(s.podium || []).map((r, i) => <div key={r.id}><span>{['🥇', '🥈', '🥉'][i]}</span><b>{r.name}</b><small>{fa(r.score)}</small></div>)}
                    </div>
                    <p style={{ opacity: .85 }}>🎁 امتیازِ شرکت و جواب‌های درستت به کارنامه‌ات اضافه شد.</p>
                    <Link href={route('live')} className="lvp-btn">بازگشت</Link>
                </main>
            ) : s.phase === 'reveal' ? (
                <main className="lvp-center">
                    {s.mine ? (
                        s.mine.correct ? (
                            <>
                                <div className="lvp-big pop">🎉</div>
                                <h2>{CHEERS[s.current % CHEERS.length]}</h2>
                                <div className="lvp-points">+{fa(s.mine.points)}</div>
                                {s.me?.streak >= 2 && <div className="lvp-streak">🔥 {fa(s.me.streak)} درستِ پشتِ سرِ هم!</div>}
                            </>
                        ) : (
                            <>
                                <div className="lvp-big">💛</div>
                                <h2>{HUGS[s.current % HUGS.length]}</h2>
                                <p>جوابِ درست: <b className={`lvp-chip c${s.answer}`}>{SHAPES[s.answer]} {q?.choices[s.answer]}</b></p>
                            </>
                        )
                    ) : (
                        <>
                            <div className="lvp-big">⏰</div>
                            <h2>زمان تمام شد!</h2>
                            <p>جوابِ درست: <b className={`lvp-chip c${s.answer}`}>{SHAPES[s.answer]} {q?.choices[s.answer]}</b></p>
                        </>
                    )}
                    {s.me?.rank && <div className="lvp-rank">رتبه‌ی تو الان: {fa(s.me.rank)}</div>}
                </main>
            ) : (
                <main className="lvp-q">
                    <div className="lvp-qhead">
                        <span>سؤالِ {fa(s.current + 1)} از {fa(s.total)}</span>
                        <span className={`lvp-time ${left <= 5 ? 'low' : ''}`}>⏱ {fa(left)}</span>
                    </div>
                    <div className="lvp-tbar"><i style={{ width: `${Math.min(100, (left / s.seconds) * 100)}%` }} /></div>
                    <div className="lvp-prompt" dir={dirOf(q?.prompt)}>{q?.prompt}</div>
                    {myChoice != null ? (
                        <div className="lvp-center lvp-sent">
                            <div className={`lvp-chip big c${myChoice}`}>{SHAPES[myChoice]}</div>
                            <h2>جوابت ثبت شد!</h2>
                            <p>منتظرِ بقیه باش… 👀</p>
                        </div>
                    ) : (
                        <div className={`lvp-tiles n${q?.choices.length}`}>
                            {q?.choices.map((c, k) => (
                                <button key={k} type="button" className={`lvp-tile c${k}`} onClick={() => answer(k)} disabled={left <= 0}>
                                    <i>{SHAPES[k]}</i><span dir={dirOf(c)}>{c}</span>
                                </button>
                            ))}
                        </div>
                    )}
                </main>
            )}
        </div>
    );
}
