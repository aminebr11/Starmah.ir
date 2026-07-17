import { Link, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** اجرای یک مأموریتِ روزانه — پاسخ‌دهی، نمره‌دهیِ امنِ سمت سرور، امتیاز/نشانِ یک‌بار در روز. */
export default function MissionPlay() {
    const { mission = {}, token, questions = [] } = usePage().props;
    const [idx, setIdx] = useState(0);
    const [answers, setAnswers] = useState([]);
    const [picked, setPicked] = useState(null);
    const [result, setResult] = useState(null);
    const [busy, setBusy] = useState(false);

    const q = questions[idx];
    const last = idx === questions.length - 1;

    const choose = (val) => {
        if (picked != null) return;
        setPicked(val);
        const next = [...answers, { i: q.i, value: val }];
        setAnswers(next);
        setTimeout(() => (last ? finish(next) : advance()), 500);
    };
    const advance = () => { setPicked(null); setIdx(idx + 1); };

    const finish = async (all) => {
        setBusy(true);
        try {
            const { data } = await axios.post(route('missions.submit'), { token, answers: all });
            setResult(data);
        } finally { setBusy(false); }
    };

    if (result) {
        const pct = result.total ? Math.round((result.correct / result.total) * 100) : 0;
        return (
            <ThemedDash title="نتیجه‌ی مأموریت" active="practice">
                <div className="k3-card" style={{ textAlign: 'center' }}>
                    <div style={{ fontSize: 64 }}>{result.passed ? '🏆' : '💪'}</div>
                    <div style={{ fontWeight: 900, fontSize: 22, marginTop: 6 }}>{result.passed ? 'مأموریت انجام شد!' : 'تلاشِ خوبی بود!'}</div>
                    <div style={{ display: 'flex', gap: 12, justifyContent: 'center', marginTop: 16, flexWrap: 'wrap' }}>
                        <Metric v={`${fa(result.correct)}/${fa(result.total)}`} l="پاسخ درست" />
                        <Metric v={`${fa(pct)}٪`} l="دقت" />
                        <Metric v={result.already ? '—' : `⚡${fa(result.xp)}`} l={result.already ? 'قبلاً گرفتی' : 'امتیاز'} />
                    </div>
                    {result.already && <div style={{ marginTop: 12, fontSize: 13, background: 'rgba(240,149,46,.22)', border: '1px solid rgba(240,149,46,.5)', borderRadius: 12, padding: '9px 13px', display: 'inline-block' }}>ℹ️ امروز این مأموریت را قبلاً انجام دادی — این دور فقط تمرین بود. فردا دوباره امتیاز می‌گیری.</div>}
                    {result.badge && <div style={{ marginTop: 14, fontSize: 15, fontWeight: 800 }}>نشانِ جدید! {result.badge.emoji} {result.badge.name}</div>}
                    <div style={{ display: 'flex', gap: 10, justifyContent: 'center', marginTop: 18, flexWrap: 'wrap' }}>
                        <Link href={route('missions')} className="k3-btn">🎯 مأموریت‌های دیگر</Link>
                        <button onClick={() => router.visit(route('dashboard'))} className="k3-btn ghost">🏠 خانه</button>
                    </div>
                </div>
            </ThemedDash>
        );
    }

    return (
        <ThemedDash title={mission.title} active="practice">
            <div className="k3-card" style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                <span style={{ fontSize: 26 }}>{mission.badge_icon || '🎯'}</span>
                <b style={{ flex: 1 }}>{mission.title}</b>
                <span className="k3-chip">{fa(idx + 1)}/{fa(questions.length)}</span>
            </div>

            <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', background: 'linear-gradient(120deg,var(--p2,#4c1d95),var(--bg2,#1b2742))' }}>
                <div style={{ fontWeight: 800, fontSize: 18, lineHeight: 2.1 }}>{q.prompt}</div>
            </div>

            <div style={{ display: 'grid', gap: 10, marginTop: 16, opacity: busy ? .5 : 1 }}>
                {q.choices.map((c, i) => (
                    <button key={i} onClick={() => choose(c.value)} disabled={picked != null}
                        style={opt(picked === c.value)}>{c.value}</button>
                ))}
            </div>
        </ThemedDash>
    );
}

function Metric({ v, l }) {
    return <div style={{ background: 'rgba(255,255,255,.1)', borderRadius: 14, padding: '12px 16px' }}><b style={{ fontSize: 20, color: 'var(--gacc,#f5b53f)' }}>{v}</b><div style={{ fontSize: 11.5, opacity: .8 }}>{l}</div></div>;
}
const opt = (sel) => ({
    padding: 15, borderRadius: 16, fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit', fontSize: 16, color: '#fff',
    background: sel ? 'rgba(245,181,63,.22)' : 'rgba(255,255,255,.06)',
    border: `1.5px solid ${sel ? 'var(--acc,#f5b53f)' : 'rgba(255,255,255,.12)'}`,
});
