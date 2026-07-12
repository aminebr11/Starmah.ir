import { usePage, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';
import axios from 'axios';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function SmartExamTake() {
    const { exam = {}, token, questions = [], saved = {} } = usePage().props;
    const rules = exam.rules || {};
    const onePer = exam.onePerPage;
    const total = questions.length;

    const [answers, setAnswers] = useState(saved.answers || {});
    const [idx, setIdx] = useState(0);
    const [submitting, setSubmitting] = useState(false);
    const startRef = useRef(Date.now());
    const durationSec = (rules.duration || 60) * 60;
    const [left, setLeft] = useState(durationSec);

    // تایمر واقعی
    useEffect(() => {
        const t = setInterval(() => {
            const elapsed = Math.floor((Date.now() - startRef.current) / 1000);
            const rem = durationSec - elapsed;
            setLeft(rem);
            if (rem <= 0) { clearInterval(t); doSubmit(true); }
        }, 1000);
        return () => clearInterval(t);
    }, []);

    // ذخیره‌ی خودکار هر ۱۵ ثانیه (ادامه بعد از قطع اینترنت)
    const saveProgress = useCallback(() => {
        axios.post(route('student.smart.save', exam.id), { token, progress: { answers } }).catch(() => {});
    }, [answers]);
    useEffect(() => { const t = setInterval(saveProgress, 15000); return () => clearInterval(t); }, [saveProgress]);

    const setAns = (i, value) => setAnswers((a) => ({ ...a, [i]: { i, value } }));
    const answeredCount = Object.values(answers).filter((a) => a?.value != null && a.value !== '').length;

    const doSubmit = (auto = false) => {
        if (submitting) return;
        if (!auto && answeredCount < total && !confirm(`${fa(total - answeredCount)} سؤال بی‌پاسخ مانده. ارسال نهایی؟`)) return;
        setSubmitting(true);
        const duration = Math.round((Date.now() - startRef.current) / 1000);
        router.post(route('student.smart.submit', exam.id), { token, answers: Object.values(answers), duration_sec: duration }, {
            onFinish: () => setSubmitting(false),
        });
    };

    const mm = String(Math.max(0, Math.floor(left / 60))).padStart(2, '0');
    const ss = String(Math.max(0, left % 60)).padStart(2, '0');
    const q = questions[idx];

    const Question = (question, i) => (
        <div key={i} className="k3-card" style={{ marginBottom: onePer ? 0 : 12 }}>
            <div style={{ fontSize: 12.5, opacity: .75, marginBottom: 6 }}>سؤال {fa(i + 1)} از {fa(total)} · بارم {fa(question.points)}</div>
            {question.media && <img src={question.media} alt="" style={{ maxWidth: '100%', borderRadius: 12, marginBottom: 10 }} />}
            <div style={{ fontWeight: 800, fontSize: 17, lineHeight: 2 }}>{question.prompt}</div>
            {question.type === 'desc' || question.type === 'blank' ? (
                <textarea className="smart-input" style={{ marginTop: 12, minHeight: question.type === 'desc' ? 90 : 44 }}
                    value={answers[i]?.value || ''} onChange={(e) => setAns(i, e.target.value)} placeholder={question.type === 'blank' ? 'پاسخ کوتاه' : 'پاسخ تشریحی خود را بنویس…'} />
            ) : (
                <div style={{ display: 'grid', gap: 10, marginTop: 14, gridTemplateColumns: question.choices.length > 2 ? '1fr 1fr' : '1fr' }}>
                    {question.choices.map((c, ci) => {
                        const sel = answers[i]?.value === c.value;
                        return (
                            <button key={ci} onClick={() => setAns(i, c.value)}
                                style={{ padding: 14, borderRadius: 14, fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit', fontSize: 15.5, color: '#fff',
                                    background: sel ? 'linear-gradient(180deg,#7c3aed,#5b21b6)' : 'rgba(255,255,255,.08)', border: sel ? '2px solid #c4b5fd' : '1px solid rgba(255,255,255,.16)' }}>
                                {c.value}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );

    return (
        <ThemedDash title={exam.title || 'آزمون'} active="smart">
            {/* هدر: تایمر + پیشرفت */}
            <div className="k3-card" style={{ background: 'linear-gradient(135deg,#6d28d9,#4c1d95)', position: 'sticky', top: 8, zIndex: 5 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 22 }}>🧠</span>
                    <b style={{ flex: 1, fontSize: 15 }}>{exam.title}</b>
                    <span dir="ltr" className="smart-timer" style={{ fontSize: 18, background: 'rgba(0,0,0,.28)', borderRadius: 10, padding: '4px 12px', color: left < 60 ? '#ffd9dc' : '#fff' }}>⏱ {fa(mm)}:{fa(ss)}</span>
                </div>
                <div style={{ height: 10, borderRadius: 6, background: 'rgba(255,255,255,.18)', overflow: 'hidden', marginTop: 10 }}>
                    <div style={{ width: `${(answeredCount / total) * 100}%`, height: '100%', background: 'linear-gradient(90deg,#a78bfa,#7c3aed)', transition: 'width .3s' }} />
                </div>
                <div style={{ fontSize: 11.5, opacity: .85, marginTop: 5 }}>پاسخ‌داده: {fa(answeredCount)} از {fa(total)} · ذخیره‌ی خودکار فعال 💾</div>
            </div>

            <div style={{ marginTop: 14 }}>
                {onePer ? Question(q, idx) : questions.map((qq, i) => Question(qq, i))}
            </div>

            {/* ناوبری سؤال‌ها */}
            {onePer && (
                <div style={{ display: 'flex', gap: 8, marginTop: 12, flexWrap: 'wrap', alignItems: 'center' }}>
                    <button onClick={() => setIdx(Math.max(0, idx - 1))} disabled={idx === 0} className="k3-btn ghost" style={{ fontSize: 13 }}>→ قبلی</button>
                    {idx < total - 1
                        ? <button onClick={() => setIdx(idx + 1)} className="k3-btn" style={{ fontSize: 13 }}>بعدی ←</button>
                        : <button onClick={() => doSubmit(false)} disabled={submitting} className="k3-btn" style={{ fontSize: 14 }}>🏁 ارسال نهایی</button>}
                    <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap', marginInlineStart: 'auto' }}>
                        {questions.map((_, i) => (
                            <button key={i} onClick={() => setIdx(i)} style={{ width: 30, height: 30, borderRadius: 8, border: 0, cursor: 'pointer', fontFamily: 'inherit', fontWeight: 800, fontSize: 12,
                                background: i === idx ? '#7c3aed' : answers[i]?.value != null && answers[i]?.value !== '' ? '#2bb673' : 'rgba(255,255,255,.12)', color: '#fff' }}>{fa(i + 1)}</button>
                        ))}
                    </div>
                </div>
            )}
            {!onePer && <button onClick={() => doSubmit(false)} disabled={submitting} className="k3-btn" style={{ width: '100%', marginTop: 14, fontSize: 15 }}>🏁 ارسال نهایی آزمون</button>}
        </ThemedDash>
    );
}
