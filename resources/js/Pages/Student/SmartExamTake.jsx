import { usePage, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';
import axios from 'axios';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function SmartExamTake() {
    const { exam = {}, token, questions = [], saved = {}, preview = null } = usePage().props;
    const rules = exam.rules || {};
    const onePer = exam.onePerPage;
    const total = questions.length;

    const [answers, setAnswers] = useState(saved.answers || {});
    const [idx, setIdx] = useState(0);
    const [submitting, setSubmitting] = useState(false);
    // نتیجه‌ی محلیِ پیش‌نمایش — هیچ‌چیز به سرور نمی‌رود
    const [pv, setPv] = useState(null);
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
        if (preview) return;   // در پیش‌نمایش چیزی ذخیره نمی‌شود
        axios.post(route('student.smart.save', exam.id), { token, progress: { answers } }).catch(() => {});
    }, [answers, preview]);
    useEffect(() => { const t = setInterval(saveProgress, 15000); return () => clearInterval(t); }, [saveProgress]);

    const setAns = (i, value) => setAnswers((a) => ({ ...a, [i]: { i, value } }));
    const answeredCount = Object.values(answers).filter((a) => a?.value != null && a.value !== '').length;

    const doSubmit = (auto = false) => {
        if (submitting) return;
        if (!auto && answeredCount < total && !confirm(`${fa(total - answeredCount)} سؤال بی‌پاسخ مانده. ارسال نهایی؟`)) return;

        // پیش‌نمایشِ معلم: تصحیح همین‌جا انجام می‌شود، نه روی سرور
        if (preview) {
            const rows = questions.map((qq, i) => {
                const given = answers[i]?.value ?? null;
                const right = (qq.choices || []).find((c) => c.correct)?.value
                    ?? (Array.isArray(qq.answer) ? qq.answer[0] : qq.answer) ?? null;
                const auto2 = qq.type === 'mc' || qq.type === 'tf';
                return {
                    i, prompt: qq.prompt, type: qq.type, points: qq.points,
                    given, right, auto: auto2,
                    ok: auto2 ? (given != null && String(given) === String(right)) : null,
                    explanation: qq.explanation,
                };
            });
            const autoRows = rows.filter((r) => r.auto);
            setPv({
                rows,
                correct: autoRows.filter((r) => r.ok).length,
                autoTotal: autoRows.length,
                manual: rows.length - autoRows.length,
                score: autoRows.filter((r) => r.ok).reduce((n, r) => n + (r.points || 0), 0),
                maxScore: rows.reduce((n, r) => n + (r.points || 0), 0),
            });
            window.scrollTo({ top: 0 });
            return;
        }

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
                <textarea style={{ marginTop: 12, minHeight: question.type === 'desc' ? 90 : 44, width: '100%', borderRadius: 14, padding: 14, fontFamily: 'inherit', fontSize: 15.5, background: 'rgba(255,255,255,.08)', color: '#fff', border: '1.5px solid rgba(255,255,255,.22)', resize: 'vertical' }}
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

    if (preview && pv) return <PreviewResult pv={pv} exam={exam} preview={preview} onRetry={() => { setPv(null); setAnswers({}); setIdx(0); }} />;

    if (total === 0) {
        return (
            <ThemedDash title={exam.title || 'آزمون'} active="smart">
                <div className="k3-card" style={{ textAlign: 'center' }}>
                    <div style={{ fontSize: 44 }}>📭</div>
                    <b>این آزمون هنوز هیچ سؤالی ندارد</b>
                    <div style={{ fontSize: 12.5, opacity: .85, marginTop: 6 }}>دانش‌آموز هم دقیقاً همین صفحه‌ی خالی را می‌دید.</div>
                    {preview && <a href={preview.back} className="k3-btn" style={{ marginTop: 14, display: 'inline-block' }}>✏️ افزودنِ سؤال</a>}
                </div>
            </ThemedDash>
        );
    }

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
                <div style={{ fontSize: 11.5, opacity: .85, marginTop: 5 }}>پاسخ‌داده: {fa(answeredCount)} از {fa(total)} · {preview ? 'پیش‌نمایش — چیزی ذخیره نمی‌شود' : 'ذخیره‌ی خودکار فعال 💾'}</div>
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

/* ═══════════════ نتیجه‌ی پیش‌نمایشِ معلم ═══════════════ */
/**
 * تصحیح فقط در مرورگرِ معلم انجام می‌شود؛ نه تلاشی ثبت می‌شود و نه نمره‌ای.
 * هدف این است که معلم پیش از انتشار ببیند سؤال‌ها، گزینه‌ها و پاسخِ درست
 * سالم‌اند — و اگر جایی ایراد داشت، برگردد و اصلاح کند.
 */
function PreviewResult({ pv, exam, preview, onRetry }) {
    const pct = pv.autoTotal ? Math.round((pv.correct / pv.autoTotal) * 100) : 0;
    const problems = pv.rows.filter((r) => r.auto && r.right == null);

    return (
        <ThemedDash title={`پیش‌نمایشِ ${exam.title || 'آزمون'}`} active="smart">
            <div className="k3-card" style={{ textAlign: 'center' }}>
                <div style={{ fontSize: 52 }}>👁️</div>
                <div style={{ fontWeight: 900, fontSize: 20, marginTop: 4 }}>پایانِ پیش‌نمایش</div>
                <div style={{ fontSize: 12.5, opacity: .85, marginTop: 6 }}>
                    این فقط یک بازبینی بود — نه تلاشی ثبت شد، نه نمره‌ای، نه امتیازی.
                </div>
                <div style={{ display: 'flex', gap: 12, justifyContent: 'center', marginTop: 16, flexWrap: 'wrap' }}>
                    <M v={`${fa(pv.correct)}/${fa(pv.autoTotal)}`} l="درست (تصحیحِ خودکار)" />
                    <M v={`${fa(pct)}٪`} l="دقت" />
                    <M v={`${fa(pv.score)}/${fa(pv.maxScore)}`} l="بارم" />
                    {pv.manual > 0 && <M v={fa(pv.manual)} l="تشریحی (تصحیحِ دستی)" />}
                </div>
                <div style={{ display: 'flex', gap: 10, justifyContent: 'center', marginTop: 18, flexWrap: 'wrap' }}>
                    <a href={preview.back} className="k3-btn">✏️ بازگشت و اصلاح</a>
                    <button onClick={onRetry} className="k3-btn ghost">🔁 اجرای دوباره</button>
                </div>
            </div>

            {problems.length > 0 && (
                <div className="k3-card" style={{ marginTop: 14, background: 'rgba(232,80,91,.18)', border: '1px solid rgba(232,80,91,.5)' }}>
                    <b>⚠️ {fa(problems.length)} سؤالِ چهارگزینه‌ای «پاسخِ درست» ندارد</b>
                    <div style={{ fontSize: 12.5, opacity: .9, marginTop: 4, lineHeight: 1.9 }}>
                        این سؤال‌ها برای دانش‌آموز همیشه غلط حساب می‌شوند. پیش از انتشار حتماً پاسخِ درستشان را مشخص کن.
                    </div>
                </div>
            )}

            <div className="k3-card" style={{ marginTop: 14 }}>
                <div style={{ fontWeight: 900, fontSize: 15, marginBottom: 10 }}>📖 بازبینیِ سؤال‌به‌سؤال</div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                    {pv.rows.map((r) => (
                        <div key={r.i} style={{
                            borderRadius: 12, padding: '10px 12px', lineHeight: 1.9,
                            background: r.auto ? (r.ok ? 'rgba(43,182,115,.14)' : 'rgba(232,80,91,.14)') : 'rgba(255,255,255,.07)',
                            border: `1px solid ${r.auto ? (r.ok ? 'rgba(43,182,115,.45)' : 'rgba(232,80,91,.45)') : 'rgba(255,255,255,.18)'}`,
                        }}>
                            <div style={{ fontWeight: 800, fontSize: 13.5 }}>
                                {r.auto ? (r.ok ? '✅' : '❌') : '✍️'} {fa(r.i + 1)}. {r.prompt}
                            </div>
                            <div style={{ fontSize: 12.5, marginTop: 4, opacity: .92 }}>
                                پاسخِ تو: <b>{r.given ?? '—'}</b>
                                {r.auto && <> · پاسخِ درست: <b>{r.right ?? '⚠️ مشخص نشده'}</b></>}
                                {!r.auto && ' · تشریحی — معلم دستی تصحیح می‌کند'}
                            </div>
                            {r.explanation && <div style={{ fontSize: 12.5, opacity: .8, marginTop: 4 }}>💡 {r.explanation}</div>}
                        </div>
                    ))}
                </div>
            </div>
        </ThemedDash>
    );
}

const M = ({ v, l }) => (
    <div style={{ background: 'rgba(255,255,255,.1)', borderRadius: 14, padding: '12px 16px' }}>
        <b style={{ fontSize: 20, color: 'var(--gacc,#f5b53f)' }}>{v}</b>
        <div style={{ fontSize: 11.5, opacity: .8 }}>{l}</div>
    </div>
);
