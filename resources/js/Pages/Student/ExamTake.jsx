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
    const [descText, setDescText] = useState('');
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState(null);
    const q = questions[idx];
    const last = idx === questions.length - 1;

    const advance = (next) => { last ? finish(next) : (setPicked(null), setDescText(''), setIdx(idx + 1)); };

    const choose = (val) => {
        if (picked != null) return;
        setPicked(val);
        const next = [...answers, { i: q.i, value: val }];
        setAnswers(next);
        setTimeout(() => advance(next), 350);
    };
    const submitDesc = () => {
        const next = [...answers, { i: q.i, value: descText.trim() }];
        setAnswers(next);
        advance(next);
    };
    const finish = async (all) => {
        setBusy(true);
        const { data } = await axios.post(route('exams.submit', assignment.id), { token, answers: all });
        setResult(data);
    };

    if (result) {
        const graded = result.total > 0;
        const pct = graded ? Math.round(result.correct / result.total * 100) : 0;
        return (
            <ThemedDash title="نتیجه آزمون">
                <div style={{ ...card, textAlign: 'center', maxWidth: 460, margin: '20px auto' }}>
                    <div style={{ fontSize: 64 }}>{graded ? (pct >= 50 ? '🎉' : '💪') : '📝'}</div>
                    {graded ? <>
                        <h2>نمره‌ی تو: {fa(result.correct)} از {fa(result.total)}</h2>
                        <div style={{ fontSize: 26, fontWeight: 800, color: 'var(--acc)', margin: '10px 0' }}>+{fa(result.points)} امتیاز</div>
                    </> : <h2 style={{ margin: '10px 0' }}>پاسخت ثبت شد ✅</h2>}
                    {result.desc > 0 && <div style={{ opacity: .8, fontSize: 13, marginBottom: 10 }}>✍️ {fa(result.desc)} سؤال تشریحی توسط معلم بررسی می‌شود.</div>}
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
                {q.type === 'desc' ? (
                    <div style={{ marginTop: 14 }}>
                        <textarea value={descText} onChange={(e) => setDescText(e.target.value)} rows="5" placeholder="پاسخت را این‌جا بنویس…"
                            style={{ width: '100%', borderRadius: 14, padding: 14, fontFamily: 'inherit', fontSize: 15, background: '#fff', color: '#1b2742', border: '1.5px solid rgba(255,255,255,.35)', resize: 'vertical' }} />
                        <button onClick={submitDesc} disabled={busy || !descText.trim()}
                            style={{ marginTop: 12, width: '100%', background: 'linear-gradient(135deg,var(--p1),var(--p2))', color: '#fff', padding: '14px', borderRadius: 14, fontWeight: 800, border: 0, cursor: 'pointer', fontFamily: 'inherit', fontSize: 16 }}>
                            {last ? '✅ پایان آزمون' : 'سؤال بعدی ←'}
                        </button>
                    </div>
                ) : (
                    <div style={{ display: 'grid', gridTemplateColumns: q.type === 'tf' ? '1fr 1fr' : '1fr 1fr', gap: 10, marginTop: 14 }}>
                        {q.choices.map((c, i) => (
                            <button key={i} onClick={() => choose(c.value)} disabled={picked != null}
                                style={{ padding: 16, borderRadius: 14, fontWeight: 800, fontSize: 16, color: '#fff', cursor: 'pointer', fontFamily: 'inherit',
                                    background: picked === c.value ? 'rgba(245,181,63,.25)' : 'rgba(255,255,255,.07)',
                                    border: `1.5px solid ${picked === c.value ? 'var(--acc)' : 'rgba(255,255,255,.15)'}` }}>{fa(c.value)}</button>
                        ))}
                    </div>
                )}
            </div>
        </ThemedDash>
    );
}
