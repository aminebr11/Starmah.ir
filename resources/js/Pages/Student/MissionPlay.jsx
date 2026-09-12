import { Link, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/**
 * اجرای یک مأموریتِ روزانه.
 * کلیدِ پاسخ هیچ‌وقت به مرورگر نمی‌آید؛ نمره‌دهی و امتیاز کاملاً سمتِ سرور
 * انجام می‌شود و «مرورِ پاسخ‌ها» تنها پس از پایان برمی‌گردد.
 */
export default function MissionPlay() {
    const { mission = {}, token, questions = [] } = usePage().props;
    const [idx, setIdx] = useState(0);
    const [answers, setAnswers] = useState([]);
    const [picked, setPicked] = useState(null);
    const [result, setResult] = useState(null);
    const [busy, setBusy] = useState(false);

    const q = questions[idx];
    const last = idx === questions.length - 1;
    const pct = questions.length ? Math.round((idx / questions.length) * 100) : 0;

    const choose = (val) => {
        if (picked != null) return;
        setPicked(val);
        const next = [...answers, { i: q.i, value: val }];
        setAnswers(next);
        setTimeout(() => (last ? finish(next) : advance()), 420);
    };
    const advance = () => { setPicked(null); setIdx(idx + 1); };

    const finish = async (all) => {
        setBusy(true);
        try {
            const { data } = await axios.post(route('missions.submit'), { token, answers: all });
            setResult(data);
            window.scrollTo({ top: 0 });
        } finally { setBusy(false); }
    };

    /* ── نتیجه ── */
    if (result) {
        const acc = result.total ? Math.round((result.correct / result.total) * 100) : 0;
        return (
            <ThemedDash title="نتیجه‌ی مأموریت" active="practice">
                <div className="k3-card" style={{ textAlign: 'center' }}>
                    <div style={{ fontSize: 64 }}>{result.passed ? '🏆' : '💪'}</div>
                    <div style={{ fontWeight: 900, fontSize: 22, marginTop: 6 }}>{result.passed ? 'مأموریت انجام شد!' : 'تلاشِ خوبی بود!'}</div>
                    {!result.passed && (
                        <div style={{ fontSize: 12.5, opacity: .8, marginTop: 6 }}>
                            برای «قبولی» باید دستِ‌کم {fa(mission.pass_percent ?? 60)}٪ درست جواب بدهی — ولی امتیازِ پاسخ‌های درستت را گرفتی.
                        </div>
                    )}

                    <div style={{ display: 'flex', gap: 12, justifyContent: 'center', marginTop: 16, flexWrap: 'wrap' }}>
                        <Metric v={`${fa(result.correct)}/${fa(result.total)}`} l="پاسخ درست" />
                        <Metric v={`${fa(acc)}٪`} l="دقت" />
                        <Metric v={result.already ? '—' : `⚡${fa(result.xp)}`} l={result.already ? 'قبلاً گرفتی' : 'امتیاز'} />
                    </div>

                    {result.already && (
                        <div style={{ marginTop: 12, fontSize: 13, background: 'rgba(240,149,46,.22)', border: '1px solid rgba(240,149,46,.5)', borderRadius: 12, padding: '9px 13px', display: 'inline-block' }}>
                            ℹ️ امروز این مأموریت را قبلاً انجام دادی — این دور فقط تمرین بود. فردا دوباره امتیاز می‌گیری.
                        </div>
                    )}
                    {result.badge && <div style={{ marginTop: 14, fontSize: 15, fontWeight: 800 }}>نشانِ جدید! {result.badge.emoji} {result.badge.name}</div>}

                    <div style={{ display: 'flex', gap: 10, justifyContent: 'center', marginTop: 18, flexWrap: 'wrap' }}>
                        <Link href={route('missions')} className="k3-btn">🎯 تخته‌ی مأموریت‌ها</Link>
                        <button onClick={() => router.visit(route('dashboard'))} className="k3-btn ghost">🏠 خانه</button>
                    </div>
                </div>

                {/* مرورِ پاسخ‌ها — یادگیری مهم‌تر از نمره است */}
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
                                        <div style={{ fontWeight: 800, fontSize: 13.5 }}>{r.ok ? '✅' : '❌'} {qq?.prompt}</div>
                                        {!r.ok && <div style={{ fontSize: 12.5, marginTop: 4 }}>پاسخِ درست: <b>{r.answer}</b></div>}
                                        {r.explanation && <div style={{ fontSize: 12.5, opacity: .85, marginTop: 4 }}>💡 {r.explanation}</div>}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </ThemedDash>
        );
    }

    /* ── پرسش ── */
    return (
        <ThemedDash title={mission.title} active="practice">
            <div className="k3-card">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 26 }}>{mission.badge_icon || '🎯'}</span>
                    <b style={{ flex: 1, minWidth: 120 }}>{mission.title}</b>
                    <span className="k3-chip">{fa(idx + 1)}/{fa(questions.length)}</span>
                    <span className="k3-chip">⚡ {fa(mission.xp_reward)}</span>
                </div>
                {mission.description && <div style={{ fontSize: 12.5, opacity: .85, marginTop: 8, lineHeight: 1.9 }}>💬 {mission.description}</div>}
                <div style={{ height: 8, borderRadius: 99, background: 'rgba(255,255,255,.12)', marginTop: 10, overflow: 'hidden' }}>
                    <div style={{ width: `${pct}%`, height: '100%', background: 'linear-gradient(90deg,#ffe066,#2bb673)', transition: 'width .35s' }} />
                </div>
            </div>

            <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', background: 'linear-gradient(120deg,var(--p2,#4c1d95),var(--bg2,#1b2742))' }}>
                <div style={{ fontWeight: 800, fontSize: 18, lineHeight: 2.1 }}>{q.prompt}</div>
            </div>

            <div style={{ display: 'grid', gap: 10, marginTop: 16, opacity: busy ? .5 : 1 }}>
                {q.choices.map((c, i) => (
                    <button key={i} onClick={() => choose(c.value)} disabled={picked != null} style={opt(picked === c.value)}>
                        <span style={{ opacity: .6, marginInlineEnd: 8 }}>{['الف', 'ب', 'ج', 'د', 'ه', 'و'][i] || i + 1})</span>
                        {c.value}
                    </button>
                ))}
            </div>

            <div style={{ textAlign: 'center', fontSize: 12, opacity: .6, marginTop: 14 }}>
                پاسخ‌ها در پایان مرور می‌شود — با خیال راحت انتخاب کن.
            </div>
        </ThemedDash>
    );
}

function Metric({ v, l }) {
    return (
        <div style={{ background: 'rgba(255,255,255,.1)', borderRadius: 14, padding: '12px 16px' }}>
            <b style={{ fontSize: 20, color: 'var(--gacc,#f5b53f)' }}>{v}</b>
            <div style={{ fontSize: 11.5, opacity: .8 }}>{l}</div>
        </div>
    );
}

const opt = (sel) => ({
    padding: 15, borderRadius: 16, fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit', fontSize: 16, color: '#fff',
    textAlign: 'start',
    background: sel ? 'rgba(245,181,63,.22)' : 'rgba(255,255,255,.06)',
    border: `1.5px solid ${sel ? 'var(--acc,#f5b53f)' : 'rgba(255,255,255,.12)'}`,
});
