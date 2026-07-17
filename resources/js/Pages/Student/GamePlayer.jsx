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
    // وضعیتِ «قبلاً کامل‌شده» را فقط یک‌بار هنگام ورود ثبت می‌کنیم؛ چون پس از پایانِ بازی
    // صفحه با props تازه بارگذاری می‌شود و status به 'completed' تغییر می‌کند (نباید بارِ اول «قبلاً گرفته‌ای» نشان دهد).
    const [alreadyDone] = useState(attempt.status === 'completed');

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
    const [fx, setFx] = useState(null); // افکت لحظه‌ای تخته (dice/goal/chest)
    const startRef = useRef(Date.now());

    useEffect(() => { if (flash?.flash && done) setResult(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash, done]);

    const q = questions[step];
    const correctIdx = q ? q.choices.findIndex((c) => c.correct) : -1;

    const answer = (ci) => {
        if (revealed) return;
        const ok = ci === correctIdx;
        setPicked(ci); setRevealed(true);
        setAnswers({ ...answers, [step]: ci });
        if (ok) {
            setScore(score + (q.points || 10));
            setCorrect(correctCount + 1);
            setFx(Date.now());
        } else if (rules.lives) setLives((l) => Math.max(0, l - 1));
    };

    const next = () => {
        const outOfLives = rules.lives && lives <= 0;
        if (step < total - 1 && !outOfLives) {
            setStep(step + 1); setPicked(null); setRevealed(false); setHintN(0);
        } else finish();
    };

    const finish = () => {
        setDone(true);
        const duration = Math.round((Date.now() - startRef.current) / 1000);
        router.post(route('gameworld.finish', game.id), { answers, hints_used: hintsUsed, duration_sec: duration }, { preserveScroll: true });
    };

    const useHint = () => { if (hintN < 2) { setHintN(hintN + 1); setHintsUsed(hintsUsed + 1); } };

    const pct = total ? Math.round(((step + (done ? 1 : 0)) / total) * 100) : 0;
    const themeVars = { '--gp1': skin.p1 || 'var(--p1)', '--gp2': skin.p2 || 'var(--p2)', '--gacc': skin.acc || 'var(--acc)' };
    const character = skin.character || skin.mascot || '🧑‍🚀';

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

                {/* اطلاع XP یک‌باره */}
                {alreadyDone && !done && (
                    <div className="k3-card" style={{ marginTop: 12, padding: '11px 15px', background: 'linear-gradient(135deg,rgba(240,149,46,.35),rgba(160,90,10,.3))', border: '1px solid rgba(240,149,46,.5)' }}>
                        <b>ℹ️ این بازی را قبلاً کامل کرده‌ای (⚡{fa(attempt.score)} گرفتی).</b>
                        <div style={{ fontSize: 12.5, opacity: .9, marginTop: 3 }}>می‌توانی برای تمرین دوباره بازی کنی، ولی امتیاز (XP) فقط برای بارِ اول محاسبه می‌شود.</div>
                    </div>
                )}

                {/* تخته‌ی بازی — سفارشی (تم‌ساز ادمین) یا داخلی */}
                {!done && (
                    game.board_html
                        ? <CustomBoard html={game.board_html} css={game.board_css} pos={correctCount} total={total} percent={total ? Math.round((correctCount / total) * 100) : 0} char={character} score={score} />
                        : <Board template={game.template} total={total} pos={correctCount} fx={fx} char={character} />
                )}

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

                        {!revealed && (q.hint1 || q.hint2) && (
                            <div style={{ marginTop: 12 }}>
                                <button onClick={useHint} disabled={hintN >= 2 || (!q.hint2 && hintN >= 1)} className="k3-btn ghost" style={{ fontSize: 13 }}>💡 راهنما</button>
                                {hintN >= 1 && q.hint1 && <div style={{ marginTop: 8, fontSize: 13, opacity: .9 }}>💡 {q.hint1}</div>}
                                {hintN >= 2 && q.hint2 && <div style={{ marginTop: 6, fontSize: 13, opacity: .9 }}>💡 {q.hint2}</div>}
                            </div>
                        )}

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

                {done && <Finish score={score} total={total} correct={correctCount} result={result} rules={rules} gameId={game.id} noXp={alreadyDone} />}
            </div>
        </ThemedDash>
    );
}

/* ===== تخته‌ی سفارشیِ تم‌ساز (HTML ادمین + جای‌گیرها) ===== */
function CustomBoard({ html, css, pos, total, percent, char, score }) {
    const rendered = String(html)
        .replaceAll('{{pos}}', fa(pos)).replaceAll('{{total}}', fa(total))
        .replaceAll('{{percent}}', String(percent)).replaceAll('{{char}}', char)
        .replaceAll('{{score}}', fa(score));
    return (
        <div className="k3-card gboard-custom" style={{ padding: 14, overflow: 'hidden' }}>
            {css && <style>{`.gboard-custom{ ${''} } ${css}`}</style>}
            <div dir="rtl" dangerouslySetInnerHTML={{ __html: rendered }} />
        </div>
    );
}

/* ===== تخته‌های داخلی (انیمیشنی) ===== */
function Board({ template, total, pos, fx, char }) {
    if (template === 'football') return <FootballBoard total={total} pos={pos} fx={fx} char={char} />;
    if (template === 'treasure') return <TreasureBoard total={total} pos={pos} char={char} />;
    return <SnakeBoard total={total} pos={pos} fx={fx} char={char} />;
}

/* مار و پله‌ی واقعی: ۳۰ خانه، چیدمان مارپیچ، مار/نردبان، مهره‌ی متحرک، تاس */
const LADDERS = { 3: 5, 11: 6, 22: 4 };   // خانه → چند خانه بالا (تزئینی + متن)
const SNAKES = { 8: 4, 17: 5, 27: 6 };
function SnakeBoard({ total, pos, fx, char }) {
    const COLS = 6, ROWS = 5, CELLS = 30;
    const cell = total ? Math.min(CELLS - 1, Math.round((pos / total) * (CELLS - 1))) : 0;
    // چیدمان مارپیچ از پایین: ردیف منطقی r (۰=پایین)، ستون بصری
    const r = Math.floor(cell / COLS), c = cell % COLS;
    const visCol = r % 2 === 0 ? c : COLS - 1 - c;
    const visRow = ROWS - 1 - r;
    const dice = fx ? (fx % 6) + 1 : null;
    return (
        <div className="k3-card" style={{ padding: 12, background: 'linear-gradient(160deg,#14532d,#166534)' }}>
            <div dir="ltr" style={{ position: 'relative' }}>
                <div style={{ display: 'grid', gridTemplateColumns: `repeat(${COLS},1fr)`, gap: 4 }}>
                    {Array.from({ length: CELLS }).map((_, vi) => {
                        const vr = Math.floor(vi / COLS), vc = vi % COLS;
                        const lr = ROWS - 1 - vr;
                        const lc = lr % 2 === 0 ? vc : COLS - 1 - vc;
                        const n = lr * COLS + lc; // شماره‌ی منطقی خانه
                        const deco = LADDERS[n] ? '🪜' : (SNAKES[n] ? '🐍' : (n === CELLS - 1 ? '🏁' : ''));
                        return (
                            <div key={vi} style={{ aspectRatio: '1', borderRadius: 8, display: 'grid', placeItems: 'center', fontSize: deco ? 16 : 10.5, fontWeight: 800, color: '#fff', position: 'relative',
                                background: n <= cell ? 'rgba(250,204,21,.35)' : (vi % 2 ? 'rgba(255,255,255,.10)' : 'rgba(255,255,255,.16)'),
                                boxShadow: n === cell ? '0 0 0 2px #facc15' : 'none', transition: 'background .4s' }}>
                                {deco || fa(n + 1)}
                            </div>
                        );
                    })}
                </div>
                {/* مهره‌ی متحرک */}
                <div className="gtoken" style={{ position: 'absolute', width: `${100 / COLS}%`, height: `${100 / ROWS}%`,
                    top: `${(visRow / ROWS) * 100}%`, left: `${(visCol / COLS) * 100}%`,
                    display: 'grid', placeItems: 'center', fontSize: 26, transition: 'top .8s cubic-bezier(.2,.9,.3,1.2), left .8s cubic-bezier(.2,.9,.3,1.2)', pointerEvents: 'none', zIndex: 2, filter: 'drop-shadow(0 4px 6px rgba(0,0,0,.5))' }}>
                    {char}
                </div>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 12, marginTop: 10, fontWeight: 800, fontSize: 13 }}>
                <span key={fx} className={dice ? 'gdice' : ''} style={{ fontSize: 24 }}>🎲</span>
                {dice ? <span>تاس: {fa(dice)} — جلو رفتی!</span> : <span>پاسخ درست = پرتاب تاس و حرکت مهره</span>}
                <span style={{ opacity: .8 }}>خانه‌ی {fa(cell + 1)} از {fa(30)}</span>
            </div>
        </div>
    );
}

/* فوتبال: زمین چمن + توپ متحرک به‌سمت دروازه + شادی گل */
function FootballBoard({ total, pos, fx, char }) {
    const p = total ? pos / total : 0;
    const goal = fx && pos > 0;
    return (
        <div className="k3-card" style={{ padding: 0, overflow: 'hidden', background: 'repeating-linear-gradient(90deg,#15803d 0 40px,#166534 40px 80px)' }}>
            <div dir="ltr" style={{ position: 'relative', height: 120, margin: 10, border: '2px solid rgba(255,255,255,.7)', borderRadius: 10 }}>
                {/* خط وسط و دایره */}
                <div style={{ position: 'absolute', left: '50%', top: 0, bottom: 0, width: 2, background: 'rgba(255,255,255,.6)' }} />
                <div style={{ position: 'absolute', left: '50%', top: '50%', width: 36, height: 36, border: '2px solid rgba(255,255,255,.6)', borderRadius: '50%', transform: 'translate(-50%,-50%)' }} />
                {/* دروازه */}
                <div style={{ position: 'absolute', left: 0, top: '28%', bottom: '28%', width: 16, border: '2px solid #fff', borderRight: 'none', borderRadius: '4px 0 0 4px', background: 'repeating-linear-gradient(45deg,rgba(255,255,255,.25) 0 3px,transparent 3px 6px)' }} />
                {/* توپ + بازیکن */}
                <div key={fx} className={goal ? 'gkick' : ''} style={{ position: 'absolute', top: '50%', left: `${88 - p * 76}%`, transform: 'translateY(-50%)', fontSize: 24, transition: 'left .8s cubic-bezier(.3,1.2,.4,1)', zIndex: 2 }}>⚽</div>
                <div style={{ position: 'absolute', top: '50%', left: `${92 - p * 76}%`, transform: 'translateY(-50%)', fontSize: 26, transition: 'left .8s', zIndex: 1 }}>{char}</div>
                {goal && <div className="ggoal" style={{ position: 'absolute', inset: 0, display: 'grid', placeItems: 'center', fontWeight: 900, fontSize: 28, color: '#fde047', textShadow: '0 2px 8px rgba(0,0,0,.6)', zIndex: 3, pointerEvents: 'none' }}>⚽ گُــــل!</div>}
            </div>
            <div style={{ textAlign: 'center', fontWeight: 800, fontSize: 13, color: '#fff', paddingBottom: 10 }}>
                {fa(pos)} حمله‌ی موفق از {fa(total)} — هر پاسخ درست یک شوت به‌سمت دروازه!
            </div>
        </div>
    );
}

/* گنج‌یابی: مسیر نقشه با صندوق‌هایی که با پاسخ درست باز می‌شوند */
function TreasureBoard({ total, pos, char }) {
    return (
        <div className="k3-card" style={{ padding: 14, background: 'linear-gradient(150deg,#92700c,#6b4e05)', position: 'relative', overflow: 'hidden' }}>
            <div style={{ position: 'absolute', inset: 0, opacity: .12, fontSize: 60, display: 'flex', justifyContent: 'space-around', alignItems: 'center' }}>🏝️🌴⚓🦜</div>
            <div dir="ltr" style={{ display: 'flex', alignItems: 'center', gap: 4, flexWrap: 'wrap', justifyContent: 'center', position: 'relative' }}>
                {Array.from({ length: total }).map((_, i) => (
                    <div key={i} style={{ display: 'flex', alignItems: 'center' }}>
                        <span key={i < pos ? 'o' + i : 'c' + i} className={i < pos ? 'gchest' : ''}
                            style={{ fontSize: 26, filter: i < pos ? 'none' : 'grayscale(.8) brightness(.75)', position: 'relative' }}>
                            {i < pos ? '💰' : '🎁'}
                            {i === pos && <span style={{ position: 'absolute', top: -22, left: '50%', transform: 'translateX(-50%)', fontSize: 20 }} className="gbob">{char}</span>}
                        </span>
                        {i < total - 1 && <span style={{ color: 'rgba(255,255,255,.5)', letterSpacing: 2, fontSize: 12 }}>┄┄</span>}
                    </div>
                ))}
                <span style={{ fontSize: 34, marginInlineStart: 6 }} className={pos >= total ? 'gchest' : ''}>{pos >= total ? '🏆' : '❌'}</span>
            </div>
            <div style={{ textAlign: 'center', fontWeight: 800, fontSize: 13, marginTop: 10 }}>🗺️ {fa(pos)} صندوق از {fa(total)} باز شد — هر پاسخ درست یک کلید!</div>
        </div>
    );
}

function Finish({ score, total, correct, result, rules, gameId, noXp }) {
    const passPct = total ? Math.round((correct / total) * 100) : 0;
    const passed = passPct >= (rules.pass ?? 50);
    return (
        <div className="k3-card" style={{ textAlign: 'center' }}>
            <div style={{ fontSize: 60 }} className="gbob">{passed ? '🏆' : '🎯'}</div>
            <div style={{ fontWeight: 900, fontSize: 22, marginTop: 6 }}>{result || (passed ? 'قبول شدی! 🎉' : 'تلاش خوبی بود!')}</div>
            <div style={{ display: 'flex', gap: 12, justifyContent: 'center', marginTop: 16, flexWrap: 'wrap' }}>
                <Metric v={`${fa(correct)}/${fa(total)}`} l="پاسخ درست" />
                <Metric v={`${fa(passPct)}٪`} l="دقت" />
                <Metric v={noXp ? '—' : `⚡${fa(score)}`} l={noXp ? 'XP (قبلاً گرفته‌ای)' : 'امتیاز'} />
            </div>
            {noXp && <div style={{ marginTop: 12, fontSize: 13, background: 'rgba(240,149,46,.25)', border: '1px solid rgba(240,149,46,.5)', borderRadius: 12, padding: '9px 13px', display: 'inline-block' }}>ℹ️ امتیاز (XP) این بازی را قبلاً گرفته‌ای — این دور فقط تمرین بود.</div>}
            {passed && !noXp && <div style={{ marginTop: 14, fontSize: 14 }}>🏅 نشانِ «قهرمانِ این بازی» برایت ثبت شد!</div>}
            {rules.group_race && !noXp && score > 0 && <div style={{ marginTop: 12, fontSize: 13.5, background: 'rgba(43,182,115,.22)', border: '1px solid rgba(43,182,115,.5)', borderRadius: 12, padding: '9px 13px', display: 'inline-block' }}>🏆 امتیازِ تو به مجموعِ امتیازِ تیمت اضافه شد و جایگاهِ تیمت را در رقابتِ گروهی بالا برد!</div>}
            <div style={{ display: 'flex', gap: 10, justifyContent: 'center', marginTop: 18, flexWrap: 'wrap' }}>
                <Link href={route('gameworld')} className="k3-btn">🎮 بازی‌های دیگر</Link>
                {rules.retry !== false && <Link href={route('gameworld.play', gameId)} className="k3-btn ghost">🔁 دوباره (بدون XP)</Link>}
            </div>
        </div>
    );
}
function Metric({ v, l }) {
    return <div style={{ background: 'rgba(255,255,255,.1)', borderRadius: 14, padding: '12px 16px' }}><b style={{ fontSize: 20, color: 'var(--gacc)' }}>{v}</b><div style={{ fontSize: 11.5, opacity: .8 }}>{l}</div></div>;
}
