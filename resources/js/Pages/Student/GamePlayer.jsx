import { Link, usePage, router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const CHEER = ['آفرین! 🎉', 'عالی بود! 🌟', 'ایول! 💪', 'درسته! ✅', 'کارِت درسته! 🚀'];
const NUDGE = ['اشکالی نداره، ادامه بده! 💛', 'دفعه‌ی بعد می‌گیریش! 🙂', 'یاد گرفتن مهم‌تره! 🌱'];

export default function GamePlayer() {
    const { game = {}, attempt = {}, flash } = usePage().props;
    const skin = game.theme?.skin || {};
    const questions = game.questions || [];
    const rules = game.rules || {};
    const total = questions.length;

    const [step, setStep] = useState(0);
    const [lives, setLives] = useState(rules.lives ?? 3);
    const [score, setScore] = useState(0);
    const [correctCount, setCorrect] = useState(0);
    const [answers, setAnswers] = useState({});
    const [picked, setPicked] = useState(null);
    const [revealed, setRevealed] = useState(false);
    const [hintN, setHintN] = useState(0);
    const [hintsUsed, setHintsUsed] = useState(0);
    const [done, setDone] = useState(false);
    const [result, setResult] = useState(null);
    const startRef = useRef(Date.now());

    useEffect(() => { if (flash?.flash && done) setResult(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash, done]);

    const q = questions[step];
    const correctIdx = q ? q.choices.findIndex((c) => c.correct) : -1;

    const answer = (ci) => {
        if (revealed) return;
        const ok = ci === correctIdx;
        setPicked(ci); setRevealed(true);
        setAnswers({ ...answers, [step]: ci });
        if (ok) { setScore(score + (q.points || 10)); setCorrect(correctCount + 1); }
        else if (rules.lives) setLives((l) => Math.max(0, l - 1));
    };

    const next = () => {
        const outOfLives = rules.lives && lives <= 0;
        if (step < total - 1 && !outOfLives) {
            setStep(step + 1); setPicked(null); setRevealed(false); setHintN(0);
        } else {
            finish();
        }
    };

    const finish = () => {
        setDone(true);
        const duration = Math.round((Date.now() - startRef.current) / 1000);
        router.post(route('gameworld.finish', game.id), { answers, hints_used: hintsUsed, duration_sec: duration }, { preserveScroll: true });
    };

    const useHint = () => { if (hintN < 2) { setHintN(hintN + 1); setHintsUsed(hintsUsed + 1); } };

    const pct = total ? Math.round(((step + (done ? 1 : 0)) / total) * 100) : 0;
    const themeVars = { '--gp1': skin.p1 || 'var(--p1)', '--gp2': skin.p2 || 'var(--p2)', '--gacc': skin.acc || 'var(--acc)' };

    return (
        <ThemedDash title={game.title || 'بازی'} active="gameworld">
            <div style={themeVars}>
                {/* هدر بازی */}
                <div className="k3-card" style={{ background: 'linear-gradient(135deg,var(--gp1),var(--gp2))' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                        <span style={{ fontSize: 26 }}>{game.theme?.emoji || '🎮'}</span>
                        <div style={{ flex: 1, minWidth: 140 }}>
                            <div style={{ fontWeight: 900, fontSize: 17 }}>{game.title}</div>
                            <div style={{ fontSize: 12, opacity: .85 }}>{game.template_name} · {fa(total)} سؤال</div>
                        </div>
                        {rules.lives ? <div style={{ fontSize: 18 }}>{'❤️'.repeat(lives)}{'🖤'.repeat(Math.max(0, (rules.lives ?? 3) - lives))}</div> : null}
                        <div style={{ background: 'rgba(0,0,0,.25)', borderRadius: 20, padding: '5px 12px', fontWeight: 800 }}>⚡ {fa(score)}</div>
                        <Link href={route('gameworld')} className="k3-btn ghost" style={{ fontSize: 12, flex: 'none' }}>خروج</Link>
                    </div>
                    {!done && game.desc && step === 0 && !revealed && <div style={{ marginTop: 8, fontSize: 12.5, opacity: .9 }}>🎯 {game.desc}</div>}
                </div>

                {/* تخته‌ی بازی بر اساس قالب */}
                {!done && <Board template={game.template} total={total} pos={correctCount} step={step} skin={skin} />}

                {/* نوار پیشرفت */}
                <div style={{ height: 12, borderRadius: 8, background: 'rgba(255,255,255,.14)', overflow: 'hidden', margin: '14px 0' }}>
                    <div style={{ width: `${pct}%`, height: '100%', background: 'linear-gradient(90deg,var(--gp1),var(--gacc))', transition: 'width .5s' }} />
                </div>

                {/* سؤال */}
                {!done && q && (
                    <div className="k3-card">
                        <div style={{ fontSize: 12.5, opacity: .75, marginBottom: 6 }}>سؤال {fa(step + 1)} از {fa(total)} · ⚡ {fa(q.points)}</div>
                        {q.media && <img src={q.media} alt="" style={{ maxWidth: '100%', borderRadius: 12, marginBottom: 10 }} />}
                        <div style={{ fontWeight: 800, fontSize: 18, lineHeight: 2 }}>{q.prompt}</div>

                        <div style={{ display: 'grid', gap: 10, marginTop: 16, gridTemplateColumns: q.choices.length > 2 ? '1fr 1fr' : '1fr' }}>
                            {q.choices.map((c, ci) => {
                                let bg = 'rgba(255,255,255,.08)', bd = '1px solid rgba(255,255,255,.16)';
                                if (revealed) {
                                    if (ci === correctIdx && rules.show_answer !== false) { bg = 'linear-gradient(180deg,#2bb673,#1d8a55)'; bd = '2px solid #7be0b0'; }
                                    else if (ci === picked && ci !== correctIdx) { bg = 'linear-gradient(180deg,#e8505b,#b03340)'; bd = '2px solid #ffb3b3'; }
                                } else if (ci === picked) { bg = 'linear-gradient(180deg,var(--gp1),var(--gp2))'; }
                                return (
                                    <button key={ci} onClick={() => answer(ci)} disabled={revealed}
                                        style={{ padding: 15, borderRadius: 15, fontWeight: 800, cursor: revealed ? 'default' : 'pointer', fontFamily: 'inherit', fontSize: 16, color: '#fff', transition: '.15s', background: bg, border: bd, boxShadow: 'inset 0 2px 4px rgba(0,0,0,.2)' }}>
                                        {c.value}{revealed && ci === correctIdx ? ' ✅' : (revealed && ci === picked && ci !== correctIdx ? ' ❌' : '')}
                                    </button>
                                );
                            })}
                        </div>

                        {/* راهنما */}
                        {!revealed && (q.hint1 || q.hint2) && (
                            <div style={{ marginTop: 12 }}>
                                <button onClick={useHint} disabled={hintN >= 2 || (!q.hint2 && hintN >= 1)} className="k3-btn ghost" style={{ fontSize: 13 }}>💡 راهنما</button>
                                {hintN >= 1 && q.hint1 && <div style={{ marginTop: 8, fontSize: 13, opacity: .9 }}>💡 {q.hint1}</div>}
                                {hintN >= 2 && q.hint2 && <div style={{ marginTop: 6, fontSize: 13, opacity: .9 }}>💡 {q.hint2}</div>}
                            </div>
                        )}

                        {/* بازخورد */}
                        {revealed && (
                            <div style={{ marginTop: 14 }}>
                                <div style={{ fontWeight: 800, fontSize: 15, color: picked === correctIdx ? '#7be05a' : '#ffb3b3' }}>
                                    {picked === correctIdx ? CHEER[step % CHEER.length] : NUDGE[step % NUDGE.length]}
                                </div>
                                {q.explanation && <div style={{ marginTop: 8, background: 'rgba(255,255,255,.08)', borderRadius: 12, padding: '10px 12px', fontSize: 13.5, lineHeight: 1.9 }}>📚 {q.explanation}</div>}
                                <button onClick={next} className="k3-btn" style={{ width: '100%', marginTop: 14, fontSize: 16 }}>
                                    {step < total - 1 && !(rules.lives && lives <= 0) ? 'سؤال بعدی ←' : '🏁 پایان بازی'}
                                </button>
                            </div>
                        )}
                    </div>
                )}

                {/* پایان */}
                {done && <Finish score={score} total={total} correct={correctCount} result={result} rules={rules} gameId={game.id} />}
            </div>
        </ThemedDash>
    );
}

/* ---- تخته‌ی بازی بر اساس قالب ---- */
function Board({ template, total, pos, skin }) {
    const char = skin.character || skin.mascot || '🧑‍🚀';
    if (template === 'football') {
        const p = total ? (pos / total) * 100 : 0;
        return (
            <div className="k3-card" style={{ background: 'linear-gradient(#2e8b57,#206540)', padding: 16, position: 'relative', overflow: 'hidden' }}>
                <div style={{ position: 'relative', height: 70 }}>
                    <div style={{ position: 'absolute', insetInlineStart: 0, top: '50%', transform: 'translateY(-50%)', fontSize: 30 }}>🥅</div>
                    <div style={{ position: 'absolute', insetInlineEnd: `${100 - p}%`, top: '50%', transform: 'translate(50%,-50%)', fontSize: 26, transition: 'inset-inline-end .6s' }}>⚽</div>
                    <div style={{ position: 'absolute', insetInlineEnd: 4, top: '50%', transform: 'translateY(-50%)', fontSize: 26 }}>{char}</div>
                </div>
                <div style={{ textAlign: 'center', fontWeight: 800, fontSize: 13 }}>⚽ {fa(pos)} حمله‌ی موفق از {fa(total)}</div>
            </div>
        );
    }
    if (template === 'treasure') {
        return (
            <div className="k3-card" style={{ background: 'linear-gradient(135deg,#b8860b,#7a5a06)', padding: 16 }}>
                <div style={{ display: 'flex', gap: 8, justifyContent: 'center', flexWrap: 'wrap' }}>
                    {Array.from({ length: total }).map((_, i) => (
                        <span key={i} style={{ fontSize: 26, transition: '.3s', filter: i < pos ? 'none' : 'grayscale(.7) brightness(.7)' }}>{i < pos ? '💰' : '🎁'}</span>
                    ))}
                    <span style={{ fontSize: 30 }}>{pos >= total ? '🏆' : '🗺️'}</span>
                </div>
                <div style={{ textAlign: 'center', fontWeight: 800, fontSize: 13, marginTop: 8 }}>🗺️ {fa(pos)} صندوق باز شد از {fa(total)}</div>
            </div>
        );
    }
    // snake (پیش‌فرض)
    const cells = Math.min(total, 12);
    return (
        <div className="k3-card" style={{ background: 'linear-gradient(135deg,var(--gp1),var(--gp2))', padding: 14 }}>
            <div style={{ display: 'flex', gap: 5, flexWrap: 'wrap', justifyContent: 'center' }}>
                {Array.from({ length: cells }).map((_, i) => {
                    const here = i === Math.min(pos, cells - 1);
                    return (
                        <div key={i} style={{ width: 34, height: 34, borderRadius: 9, display: 'grid', placeItems: 'center', fontSize: here ? 20 : 12, fontWeight: 800,
                            background: i < pos ? 'rgba(255,255,255,.35)' : 'rgba(0,0,0,.22)', transition: '.3s' }}>
                            {here ? char : (i === cells - 1 ? '🏁' : fa(i + 1))}
                        </div>
                    );
                })}
            </div>
            <div style={{ textAlign: 'center', fontWeight: 800, fontSize: 13, marginTop: 8 }}>🎲 خانه‌ی {fa(pos)} از {fa(total)}</div>
        </div>
    );
}

function Finish({ score, total, correct, result, rules, gameId }) {
    const passPct = total ? Math.round((correct / total) * 100) : 0;
    const passed = passPct >= (rules.pass ?? 50);
    return (
        <div className="k3-card" style={{ textAlign: 'center' }}>
            <div style={{ fontSize: 60 }}>{passed ? '🏆' : '🎯'}</div>
            <div style={{ fontWeight: 900, fontSize: 22, marginTop: 6 }}>{result || (passed ? 'قبول شدی! 🎉' : 'تلاش خوبی بود!')}</div>
            <div style={{ display: 'flex', gap: 12, justifyContent: 'center', marginTop: 16, flexWrap: 'wrap' }}>
                <Metric v={`${fa(correct)}/${fa(total)}`} l="پاسخ درست" />
                <Metric v={`${fa(passPct)}٪`} l="دقت" />
                <Metric v={`⚡${fa(score)}`} l="امتیاز" />
            </div>
            {passed && <div style={{ marginTop: 14, fontSize: 14 }}>🏅 نشانِ «قهرمانِ این بازی» برایت ثبت شد!</div>}
            <div style={{ display: 'flex', gap: 10, justifyContent: 'center', marginTop: 18, flexWrap: 'wrap' }}>
                <Link href={route('gameworld')} className="k3-btn">🎮 بازی‌های دیگر</Link>
                {rules.retry !== false && <Link href={route('gameworld.play', gameId)} className="k3-btn ghost">🔁 دوباره</Link>}
            </div>
        </div>
    );
}
function Metric({ v, l }) {
    return <div style={{ background: 'rgba(255,255,255,.1)', borderRadius: 14, padding: '12px 16px' }}><b style={{ fontSize: 20, color: 'var(--gacc)' }}>{v}</b><div style={{ fontSize: 11.5, opacity: .8 }}>{l}</div></div>;
}
