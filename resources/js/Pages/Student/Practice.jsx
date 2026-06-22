import { usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import Themed from '@/Layouts/Themed';
import { fa, ui } from '@/theme';

/** جلسه‌ی تمرین تم‌دار: پاسخ‌دهی → نمره‌دهی سمت سرور → پاداش. */
export default function Practice() {
    const { theme, token, questions } = usePage().props;
    const w = (k, d = '') => theme?.narrative?.[k] ?? d;
    const skin = theme?.skin ?? {};

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
        setTimeout(() => (last ? finish(next) : advance()), 650);
    };
    const advance = () => { setPicked(null); setIdx(idx + 1); };

    const finish = async (all) => {
        setBusy(true);
        try {
            const { data } = await axios.post(route('practice.submit'), { token, answers: all });
            setResult(data);
        } finally { setBusy(false); }
    };

    if (result) {
        const pct = Math.round((result.correct / result.total) * 100);
        return (
            <Themed title="نتیجه">
                <div style={{ textAlign: 'center', paddingTop: 30 }}>
                    <div style={{ fontSize: 70 }}>{pct >= 60 ? '🎉' : '💪'}</div>
                    <div style={ui.h}>{pct >= 60 ? w('reward_title', 'آفرین!') : 'تمرین خوبی بود!'}</div>
                    <p style={ui.muted}>{fa(result.correct)} از {fa(result.total)} درست</p>

                    <div style={{ ...ui.card, marginTop: 16 }}>
                        <div style={{ fontSize: 30, fontWeight: 800, color: 'var(--acc)' }}>+{fa(result.xp)} {w('xp_unit', 'ستاره')}</div>
                        <div style={ui.muted}>به حساب تو اضافه شد</div>
                    </div>

                    {result.new_badges?.length > 0 && (
                        <div style={{ ...ui.card, marginTop: 12 }}>
                            <div style={{ fontWeight: 800, marginBottom: 8 }}>نشان جدید! 🏅</div>
                            {result.new_badges.map((b, i) => <span key={i} style={{ ...ui.pill, ...ui.pillAcc, margin: 4, display: 'inline-block' }}>{b.emoji} {b.name}</span>)}
                        </div>
                    )}

                    <button onClick={() => router.visit(route('dashboard'))} style={{ ...ui.btn, marginTop: 18 }}>بازگشت به خانه</button>
                    <button onClick={() => router.reload()} style={{ ...ui.btn, ...ui.ghost, marginTop: 10 }}>یک تمرین دیگر</button>
                </div>
            </Themed>
        );
    }

    return (
        <Themed title="تمرین">
            {/* نوار پیشرفت */}
            <div style={ui.row()}>
                <div style={{ ...ui.bar, flex: 1 }}><div style={ui.barFill(((idx) / questions.length) * 100)} /></div>
                <span style={{ ...ui.pill, ...ui.pillAcc }}>{fa(idx + 1)}/{fa(questions.length)}</span>
            </div>

            <div style={{ ...ui.card, marginTop: 16, textAlign: 'center', position: 'relative', overflow: 'hidden', background: 'linear-gradient(120deg,var(--p2),var(--bg2))' }}>
                <span style={{ position: 'absolute', left: -8, top: -14, fontSize: 90, opacity: .2 }}>{skin.hero}</span>
                <div style={{ ...ui.pill, display: 'inline-block' }}>{skin.mascot} {q.skill}</div>
                <div style={{ fontWeight: 800, fontSize: 18, lineHeight: 2.1, marginTop: 12 }}>{q.prompt}</div>
            </div>

            <div style={{ display: 'grid', gap: 10, marginTop: 16, opacity: busy ? .5 : 1 }}>
                {q.choices.map((c, i) => (
                    <button key={i} onClick={() => choose(c.value)} disabled={picked != null}
                        style={opt(picked === c.value ? 'sel' : '')}>{fa(c.value)}</button>
                ))}
            </div>
        </Themed>
    );
}

const opt = (st) => ({
    padding: 16, borderRadius: 16, fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit', fontSize: 16, color: '#fff',
    background: st === 'sel' ? 'rgba(245,181,63,.2)' : 'rgba(255,255,255,.06)',
    border: `1.5px solid ${st === 'sel' ? 'var(--acc)' : 'rgba(255,255,255,.12)'}`,
});
