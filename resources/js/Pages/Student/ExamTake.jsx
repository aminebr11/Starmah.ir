import { usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import ThemedDash from '@/Layouts/ThemedDash';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const card = { background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 18, color: '#fff' };

export default function ExamTake() {
    const { theme, assignment, token, questions } = usePage().props;
    const skin = theme?.skin ?? {};
    const [idx, setIdx] = useState(0);
    const [answers, setAnswers] = useState([]);
    const [picked, setPicked] = useState(null);
    const [result, setResult] = useState(null);
    const q = questions[idx];
    const last = idx === questions.length - 1;

    const choose = (val) => {
        if (picked != null) return;
        setPicked(val);
        const next = [...answers, { i: q.i, value: val }];
        setAnswers(next);
        setTimeout(() => last ? finish(next) : (setPicked(null), setIdx(idx + 1)), 350);
    };
    const finish = async (all) => {
        const { data } = await axios.post(route('exams.submit', assignment.id), { token, answers: all });
        setResult(data);
    };

    if (result) {
        const pct = Math.round(result.correct / result.total * 100);
        return (
            <ThemedDash title="نتیجه آزمون">
                <div style={{ ...card, textAlign: 'center', maxWidth: 460, margin: '20px auto' }}>
                    <div style={{ fontSize: 64 }}>{pct >= 50 ? '🎉' : '💪'}</div>
                    <h2>نمره‌ی تو: {fa(result.correct)} از {fa(result.total)}</h2>
                    <div style={{ fontSize: 26, fontWeight: 800, color: 'var(--acc)', margin: '10px 0' }}>+{fa(result.points)} امتیاز</div>
                    <button onClick={() => router.visit(route('exams'))} style={{ background: 'linear-gradient(135deg,var(--p1),var(--p2))', color: '#fff', padding: '12px 26px', borderRadius: 14, fontWeight: 800, border: 0, cursor: 'pointer', fontFamily: 'inherit' }}>بازگشت به آزمون‌ها</button>
                </div>
            </ThemedDash>
        );
    }

    return (
        <ThemedDash title={assignment.title}>
            <div style={{ maxWidth: 560, margin: '0 auto' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 14 }}>
                    <div style={{ flex: 1, height: 8, borderRadius: 6, background: 'rgba(255,255,255,.12)' }}>
                        <div style={{ width: `${idx / questions.length * 100}%`, height: '100%', borderRadius: 6, background: 'var(--acc)' }} />
                    </div>
                    <span style={{ fontWeight: 800 }}>{fa(idx + 1)}/{fa(questions.length)}</span>
                </div>
                <div style={{ ...card, textAlign: 'center', background: 'linear-gradient(120deg,var(--p2),rgba(0,0,0,.25))' }}>
                    <div style={{ fontSize: 12, opacity: .8 }}>{skin.mascot} {q.skill}</div>
                    <div style={{ fontWeight: 800, fontSize: 18, lineHeight: 2.1, marginTop: 10 }}>{q.prompt}</div>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginTop: 14 }}>
                    {q.choices.map((c, i) => (
                        <button key={i} onClick={() => choose(c.value)} disabled={picked != null}
                            style={{ padding: 16, borderRadius: 14, fontWeight: 800, fontSize: 16, color: '#fff', cursor: 'pointer', fontFamily: 'inherit',
                                background: picked === c.value ? 'rgba(245,181,63,.25)' : 'rgba(255,255,255,.07)',
                                border: `1.5px solid ${picked === c.value ? 'var(--acc)' : 'rgba(255,255,255,.15)'}` }}>{fa(c.value)}</button>
                    ))}
                </div>
            </div>
        </ThemedDash>
    );
}
