import { Link, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import ThemedDash from '@/Layouts/ThemedDash';
import Confetti from '@/Components/Confetti';
import ReadAloud from '@/Components/ReadAloud';
import useGameSound from '@/hooks/useGameSound';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const CHEER = ['آفرین! 🎉', 'عالی بود! 🌟', 'ایول! 💪', 'درسته! ✅', 'کارِت درسته! 🚀'];
const FIXED = ['آفرین! اشتباهت را خودت درست کردی 🎉', 'دیدی؟ با یک فکرِ دوباره رسیدی! 🌟'];
const NUDGE = ['😅 نزدیک بود! یک بارِ دیگر فکر کن.', '🤔 این نبود — گزینه‌های دیگر را هم نگاه کن.', '🙂 اشکالی ندارد، دوباره امتحان کن.'];
const LETTERS = ['الف', 'ب', 'ج', 'د', 'ه', 'و'];
const pick = (a) => a[Math.floor(Math.random() * a.length)];

/**
 * اجرای مأموریت (یا «مرورِ امروز») با بازخوردِ همان لحظه.
 * کلیدِ پاسخ هیچ‌وقت به مرورگر نمی‌آید؛ هر پاسخ در سرور بررسی می‌شود و نمره
 * و امتیاز در پایان کاملاً از وضعیتِ سمتِ سرور حساب می‌شود.
 */
export default function MissionPlay() {
    const { mission = {}, token, questions = [], a11y } = usePage().props;
    const sound = useGameSound();
    const [idx, setIdx] = useState(0);
    const [st, setSt] = useState({});           // وضعیتِ هر سؤال: { picked:[], wrong:[], removed, ok, done, answer, explanation, hint, hinted, msg }
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState(null);
    const [confetti, setConfetti] = useState(null);
    const [big, setBig] = useState(!!a11y?.largeText);

    const q = questions[idx];
    const s = (q && st[q.i]) || {};
    const last = idx === questions.length - 1;
    const pct = questions.length ? Math.round(((idx + (s.done ? 1 : 0)) / questions.length) * 100) : 0;
    const shortChoices = q ? q.choices.every((c) => String(c.value).length <= 14) : false;
    const isReview = mission.kind === 'review';
    const isRemedial = mission.kind === 'remedial';

    const patch = (i, p) => setSt((prev) => ({ ...prev, [i]: { ...(prev[i] || {}), ...p } }));

    const toggleBig = () => {
        const v = !big;
        setBig(v);
        axios.post(route('a11y.update'), { largeText: v }).catch(() => {});
    };

    const choose = async (val) => {
        if (busy || s.done || (s.wrong || []).includes(val) || s.removed === val) return;
        setBusy(true);
        sound.play('click');
        try {
            const { data } = await axios.post(route('missions.check'), { token, i: q.i, value: val });
            if (data.ok) {
                sound.play('correct');
                setConfetti(Date.now());
                patch(q.i, { ok: true, done: true, last: val, answer: data.answer, explanation: data.explanation,
                    msg: data.tries > 1 ? pick(FIXED) : pick(CHEER), tries: data.tries });
            } else {
                sound.play('wrong');
                patch(q.i, {
                    ok: false, done: data.done, last: val, tries: data.tries,
                    wrong: [...(s.wrong || []), val],
                    answer: data.answer, explanation: data.explanation,
                    msg: data.done ? '💛 اشکالی ندارد — جوابِ درست را با هم ببینیم.' : pick(NUDGE),
                });
            }
        } finally { setBusy(false); }
    };

    const askHint = async () => {
        if (busy || s.done || s.hinted) return;
        setBusy(true);
        try {
            const { data } = await axios.post(route('missions.hint'), { token, i: q.i });
            patch(q.i, { hinted: true, hint: data.hint, removed: data.remove });
        } finally { setBusy(false); }
    };

    const next = () => {
        if (last) return finish();
        setIdx(idx + 1);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const finish = async () => {
        setBusy(true);
        try {
            const { data } = await axios.post(route('missions.submit'), { token });
            setResult(data);
            if (data.passed) { sound.play('win'); setConfetti(Date.now()); }
            window.scrollTo({ top: 0 });
        } finally { setBusy(false); }
    };

    const qSize = big ? 24 : 19;
    const cSize = big ? (shortChoices ? 26 : 19) : (shortChoices ? 21 : 16);

    /* ── نتیجه ── */
    if (result) {
        return (
            <ThemedDash title={isRemedial ? 'نتیجه‌ی جبران' : isReview ? 'نتیجه‌ی مرور' : 'نتیجه‌ی مأموریت'} active="practice">
                <div style={{ position: 'relative' }}><Confetti fire={confetti} big /></div>
                <div className="k3-card" style={{ textAlign: 'center' }}>
                    <div style={{ fontSize: 60 }}>{result.passed ? '🏆' : '💪'}</div>
                    <div style={{ fontWeight: 900, fontSize: 22, marginTop: 4 }}>
                        {isRemedial ? (result.xp > 0 ? 'آفرین! بخشی از امتیازت برگشت 🎉' : 'تمرینِ جبرانی تمام شد') : isReview ? 'مرورِ امروز تمام شد!' : result.passed ? 'مأموریت انجام شد!' : 'تلاشِ خوبی بود!'}
                    </div>
                    {!result.passed && !isReview && !isRemedial && (
                        <div style={{ fontSize: 13, opacity: .85, marginTop: 6, lineHeight: 1.9 }}>
                            برای «قبولی» {fa(mission.pass_percent ?? 60)}٪ لازم است — ولی امتیازِ پاسخ‌های درستت را گرفتی.
                        </div>
                    )}
                    <div style={{ display: 'flex', gap: 10, justifyContent: 'center', marginTop: 16, flexWrap: 'wrap' }}>
                        <Metric v={`${fa(result.correct)} از ${fa(result.total)}`} l="پاسخِ درست" />
                        <Metric v={`${fa(result.percent)}٪`} l="امتیازِ دقت" />
                        <Metric v={result.already ? '—' : `⚡ ${fa(result.xp_total)}`} l={result.already ? 'امروز قبلاً گرفتی' : isRemedial ? 'امتیازِ جبران‌شده' : 'امتیازِ کل'} />
                    </div>
                    {result.already && (
                        <div style={{ marginTop: 12, fontSize: 13, background: 'rgba(240,149,46,.22)', border: '1px solid rgba(240,149,46,.5)', borderRadius: 12, padding: '9px 13px', display: 'inline-block', lineHeight: 1.8 }}>
                            ℹ️ امروز این را قبلاً انجام دادی — این دور فقط تمرین بود. فردا دوباره امتیاز می‌گیری.
                        </div>
                    )}
                </div>

                {isRemedial && result.remedial?.length > 0 && (
                    <div className="k3-card" style={{ marginTop: 14 }}>
                        <div style={{ fontWeight: 900, fontSize: 16, marginBottom: 6 }}>🔁 وضعیتِ جبران</div>
                        {result.remedial.map((r, k) => (
                            <GrowthRow key={k} icon={r.done ? '🏆' : r.passed ? '✅' : '🔄'} xp={r.xp || undefined}
                                title={`${r.chapter ? `📘 ${r.chapter} — ` : ''}${r.title || ''}`}
                                sub={r.done ? `کامل شد! ${fa(r.recovered)} از ${fa(r.cap)} امتیاز برگشت`
                                    : r.passed ? `نوبتِ ${fa(r.step)} از ${fa(r.steps)} قبول شد — نوبتِ بعد: ${r.next}`
                                        : `${fa(r.percent)}٪ — فردا دوباره امتحان کن (برای قبولی ${fa(mission.pass_percent)}٪ لازم است)`} />
                        ))}
                    </div>
                )}
                {result.remedial_made > 0 && (
                    <div className="k3-card" style={{ marginTop: 14, background: 'linear-gradient(135deg,#ff7a45,#e8505b)', border: 0 }}>
                        🔁 برای {fa(result.remedial_made)} سؤالی که اشتباه ماند، «جبرانِ اشتباه» ساخته شد — از تخته‌ی مأموریت‌ها انجامش بده و بخشی از امتیاز را پس بگیر.
                    </div>
                )}
                {(result.growth?.length > 0 || result.badge || result.badges?.length > 0) && (
                    <div className="k3-card" style={{ marginTop: 14 }}>
                        <div style={{ fontWeight: 900, fontSize: 16, marginBottom: 6 }}>🌱 پاداش‌های پیشرفت</div>
                        {(result.growth || []).map((g, k) => (
                            <GrowthRow key={k} icon={g.icon} title={g.title} xp={g.xp}
                                sub={g.from && g.to ? <><Band>{g.from}</Band> ← <Band hi>{g.to}</Band></> : g.sub} hi={!!g.to} />
                        ))}
                        {result.badge && <GrowthRow icon={result.badge.emoji} title={`نشانِ «${result.badge.name}»`} sub="نشانِ این مأموریت" />}
                        {(result.badges || []).map((b, k) => <GrowthRow key={`b${k}`} icon={b.emoji} title={`نشانِ «${b.name}»`} sub={result.streak ? `${fa(result.streak)} روزِ پشت‌سرِهم تمرین کردی` : null} />)}
                    </div>
                )}

                {result.review?.length > 0 && (
                    <div className="k3-card" style={{ marginTop: 14 }}>
                        <div style={{ fontWeight: 900, fontSize: 15, marginBottom: 10 }}>📖 مرورِ پاسخ‌ها</div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                            {result.review.map((r) => {
                                const qq = questions.find((x) => x.i === r.i);
                                return (
                                    <div key={r.i} style={{ borderRadius: 12, padding: '10px 12px', lineHeight: 1.9,
                                        background: r.ok ? 'rgba(43,182,115,.14)' : 'rgba(232,80,91,.14)',
                                        border: `1px solid ${r.ok ? 'rgba(43,182,115,.45)' : 'rgba(232,80,91,.45)'}` }}>
                                        <div style={{ fontWeight: 800, fontSize: 14 }}>
                                            {r.ok ? (r.tries > 1 ? '🔁' : '✅') : '❌'} {qq?.prompt}
                                        </div>
                                        {!r.ok && <div style={{ fontSize: 13, marginTop: 4 }}>پاسخِ درست: <b>{r.answer}</b></div>}
                                        {r.explanation && <div style={{ fontSize: 13, opacity: .85, marginTop: 4 }}>💡 {r.explanation}</div>}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div style={{ display: 'flex', gap: 10, justifyContent: 'center', marginTop: 16, flexWrap: 'wrap' }}>
                    <Link href={route('missions')} className="k3-btn">🎯 مأموریت‌ها</Link>
                    <button onClick={() => router.visit(route('dashboard'))} className="k3-btn ghost">🏠 خانه</button>
                </div>
            </ThemedDash>
        );
    }

    /* ── پرسش ── */
    return (
        <ThemedDash title={mission.title} active="practice">
            <div style={{ position: 'relative' }}><Confetti fire={confetti} /></div>
            <div className="k3-card">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 26 }}>{mission.badge_icon || '🎯'}</span>
                    <b style={{ flex: 1, minWidth: 120, fontSize: 16 }}>{mission.title}</b>
                    <span className="k3-chip">{fa(idx + 1)} از {fa(questions.length)}</span>
                    <span className="k3-chip">⚡ {fa(mission.xp_reward)}</span>
                    <button type="button" onClick={toggleBig} aria-pressed={big} title="متنِ درشت"
                        style={{ border: '1px solid rgba(255,255,255,.25)', background: big ? 'var(--acc,#ffd87a)' : 'rgba(255,255,255,.1)', color: big ? '#1b2742' : '#fff',
                            borderRadius: 12, padding: '5px 10px', fontFamily: 'inherit', fontWeight: 900, cursor: 'pointer' }}>
                        <span style={{ fontSize: 12 }}>آ</span><span style={{ fontSize: 18 }}>آ</span>
                    </button>
                </div>
                {mission.description && <div style={{ fontSize: 13, opacity: .85, marginTop: 8, lineHeight: 1.9 }}>💬 {mission.description}</div>}
                <div style={{ display: 'flex', height: 8, borderRadius: 99, background: 'rgba(255,255,255,.12)', marginTop: 10, overflow: 'hidden' }}>
                    <div style={{ width: `${pct}%`, height: '100%', background: 'linear-gradient(90deg,#ffe066,#2bb673)', transition: 'width .35s' }} />
                </div>
            </div>

            <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', background: 'linear-gradient(120deg,var(--p2,#4c1d95),var(--bg2,#1b2742))' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8, flexWrap: 'wrap', marginBottom: 6 }}>
                    {a11y?.readAloud !== false ? <ReadAloud text={`${q.prompt}. ${q.choices.map((c, k) => `${LETTERS[k] || k + 1}: ${c.value}`).join('. ')}`} /> : <span />}
                    {q.objective && <span style={{ fontSize: 12, opacity: .8, background: 'rgba(0,0,0,.2)', borderRadius: 20, padding: '3px 10px' }}>🎯 {q.objective}</span>}
                </div>
                <div style={{ fontWeight: 800, fontSize: qSize, lineHeight: 2 }}>{q.prompt}</div>
            </div>

            {s.hint && (
                <div style={{ marginTop: 12, borderRadius: 16, padding: '11px 14px', background: 'rgba(79,210,255,.13)', border: '1.5px dashed rgba(79,210,255,.7)', fontSize: big ? 17 : 14.5, lineHeight: 1.9 }}>
                    💡 <b>راهنما:</b> {s.hint}
                </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: shortChoices ? '1fr 1fr' : '1fr', gap: 10, marginTop: 14 }}>
                {q.choices.map((c, k) => {
                    const v = c.value;
                    const isWrong = (s.wrong || []).includes(v);
                    const isRight = s.done && String(v) === String(s.answer);
                    const gone = s.removed === v && !isRight;
                    return (
                        <button key={k} onClick={() => choose(v)} disabled={busy || s.done || isWrong || gone}
                            aria-label={`گزینه‌ی ${LETTERS[k] || k + 1}: ${v}`}
                            style={opt({ isWrong, isRight, gone, size: cSize, center: shortChoices })}>
                            <span style={{ position: 'absolute', top: 6, insetInlineStart: 10, fontSize: 12, opacity: .6 }}>{LETTERS[k] || k + 1}</span>
                            {(isWrong || isRight) && <span style={{ position: 'absolute', top: 5, insetInlineEnd: 8, fontSize: 18 }}>{isRight ? '✅' : '❌'}</span>}
                            {v}
                        </button>
                    );
                })}
            </div>

            {s.msg && (
                <div style={{
                    marginTop: 12, borderRadius: 16, padding: '11px 14px', fontWeight: 700, lineHeight: 1.8, fontSize: big ? 17 : 15,
                    background: s.ok ? 'rgba(43,182,115,.18)' : 'rgba(255,176,58,.16)',
                    border: `1.5px solid ${s.ok ? 'rgba(59,224,143,.6)' : 'rgba(255,176,58,.55)'}`,
                }}>
                    {s.msg} {s.ok && s.tries > 1 && <Plus>+{fa(1)}</Plus>}
                    {!s.done && <div style={{ fontWeight: 500, fontSize: 13, opacity: .85, marginTop: 2 }}>🔁 یک گزینه‌ی دیگر را انتخاب کن — تلاشِ دوم نیم‌امتیاز دارد.</div>}
                </div>
            )}

            {s.done && s.explanation && (
                <div style={{ marginTop: 10, borderRadius: 16, padding: '11px 14px', background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.14)', fontSize: big ? 16.5 : 14, lineHeight: 1.95 }}>
                    📖 <b>چرا؟</b> {s.explanation}
                </div>
            )}

            <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
                {s.done ? (
                    <button onClick={next} disabled={busy} className="k3-btn" style={{ flex: 1 }}>
                        {last ? (busy ? '…' : '🏁 پایان و دیدنِ نتیجه') : 'سؤالِ بعدی ←'}
                    </button>
                ) : (
                    !s.hinted && <button onClick={askHint} disabled={busy} className="k3-btn ghost" style={{ flex: 1 }}>💡 راهنما</button>
                )}
            </div>

            <div style={{ textAlign: 'center', fontSize: 12.5, opacity: .7, marginTop: 12, lineHeight: 1.8 }}>
                هر سؤال دو فرصت دارد · تلاشِ دوم = نیم امتیاز · اشتباه کردن بخشی از یادگیری است 🌱
            </div>
        </ThemedDash>
    );
}

function Metric({ v, l }) {
    return (
        <div style={{ background: 'rgba(255,255,255,.1)', borderRadius: 14, padding: '12px 16px', minWidth: 92 }}>
            <b style={{ fontSize: 20, color: 'var(--acc,#f5b53f)' }}>{v}</b>
            <div style={{ fontSize: 12, opacity: .8 }}>{l}</div>
        </div>
    );
}

const Plus = ({ children }) => (
    <span style={{ direction: 'ltr', unicodeBidi: 'isolate', display: 'inline-block', background: 'linear-gradient(180deg,#ffd23f,#e9a400)', color: '#2b1d00', borderRadius: 20, padding: '1px 10px', fontWeight: 900, fontSize: 13, marginInlineStart: 4 }}>{children}</span>
);

const Band = ({ children, hi }) => (
    <span style={{ display: 'inline-block', fontSize: 11.5, fontWeight: 800, borderRadius: 20, padding: '1px 9px',
        background: hi ? 'rgba(79,210,255,.2)' : 'rgba(255,210,63,.18)', color: hi ? '#8fe3ff' : '#ffe17a',
        border: `1px solid ${hi ? 'rgba(79,210,255,.55)' : 'rgba(255,210,63,.55)'}` }}>{children}</span>
);

function GrowthRow({ icon, title, sub, xp, hi }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 12px', borderRadius: 16, marginTop: 8,
            background: hi ? 'rgba(79,210,255,.1)' : 'rgba(0,0,0,.22)', border: `1px solid ${hi ? 'rgba(79,210,255,.5)' : 'rgba(255,255,255,.1)'}` }}>
            <span style={{ width: 38, height: 38, borderRadius: 12, display: 'grid', placeItems: 'center', fontSize: 19, background: 'rgba(255,255,255,.1)', flex: 'none' }}>{icon}</span>
            <div style={{ flex: 1, fontSize: 14, fontWeight: 800, lineHeight: 1.7 }}>
                {title}
                {sub && <div style={{ fontWeight: 500, opacity: .8, fontSize: 12 }}>{sub}</div>}
            </div>
            {xp ? <Plus>+{fa(xp)}</Plus> : null}
        </div>
    );
}

const opt = ({ isWrong, isRight, gone, size, center }) => ({
    position: 'relative', padding: center ? '22px 12px' : '15px 14px', borderRadius: 18, fontWeight: 800,
    cursor: isWrong || gone ? 'default' : 'pointer', fontFamily: 'inherit', fontSize: size, color: '#fff',
    textAlign: center ? 'center' : 'start', minHeight: center ? 84 : 'auto',
    textDecoration: gone ? 'line-through' : 'none', opacity: gone ? 0.3 : 1,
    background: isRight ? 'rgba(43,182,115,.28)' : isWrong ? 'rgba(232,80,91,.22)' : 'rgba(255,255,255,.06)',
    border: `2px solid ${isRight ? '#3be08f' : isWrong ? '#ff6b78' : 'rgba(255,255,255,.14)'}`,
    boxShadow: isRight ? '0 0 0 4px rgba(59,224,143,.25)' : 'none',
    transition: 'background .2s, border-color .2s',
});
