import { Link, usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function SmartExamResult() {
    const { exam = {}, attempt = {}, analysis, showAnswers } = usePage().props;
    const passed = attempt.percent >= 50;

    return (
        <ThemedDash title="نتیجه‌ی آزمون" active="smart">
            <div className="k3-card" style={{ textAlign: 'center', background: `linear-gradient(135deg,${passed ? '#2bb673,#1a8a52' : '#6d28d9,#4c1d95'})` }}>
                <div style={{ fontSize: 56 }}>{attempt.status === 'needs_review' ? '📝' : passed ? '🎉' : '💪'}</div>
                <div style={{ fontWeight: 900, fontSize: 22, marginTop: 4 }}>{exam.title}</div>
                {attempt.status === 'needs_review'
                    ? <div style={{ marginTop: 8, fontSize: 14 }}>پاسخ‌های تشریحی توسط معلم بررسی می‌شوند.</div>
                    : (
                        <div style={{ display: 'flex', gap: 12, justifyContent: 'center', marginTop: 16, flexWrap: 'wrap' }}>
                            <M v={`${fa(attempt.score)}/${fa(attempt.max)}`} l="نمره" />
                            <M v={`${fa(attempt.percent)}٪`} l="درصد" />
                            <M v={attempt.xp ? `⚡${fa(attempt.xp)}` : '—'} l={attempt.xp ? 'امتیاز' : 'XP قبلاً گرفته'} />
                        </div>
                    )}
            </div>

            {analysis && (
                <>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))', gap: 14, marginTop: 14 }}>
                        <div className="k3-card">
                            <div style={{ fontWeight: 900, fontSize: 15, marginBottom: 10 }}>💪 نقاط قوت</div>
                            {analysis.strengths?.length ? analysis.strengths.map((t, i) => <span key={i} className="tag tag-ok" style={{ marginInlineEnd: 6, marginBottom: 6, display: 'inline-block' }}>{t}</span>) : <span style={{ opacity: .7, fontSize: 13 }}>در حال شکل‌گیری…</span>}
                        </div>
                        <div className="k3-card">
                            <div style={{ fontWeight: 900, fontSize: 15, marginBottom: 10 }}>🎯 مباحث نیازمند تمرین</div>
                            {analysis.weakTopics?.length ? analysis.weakTopics.map((w, i) => (
                                <div key={i} style={{ marginBottom: 8 }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, fontWeight: 700 }}><span>{w.topic}</span><span style={{ color: '#ffb3b3' }}>{fa(w.pct)}٪</span></div>
                                    <div style={{ height: 8, borderRadius: 6, background: 'rgba(255,255,255,.12)', overflow: 'hidden', marginTop: 4 }}><i style={{ display: 'block', height: '100%', width: `${w.pct}%`, background: '#e8505b' }} /></div>
                                </div>
                            )) : <span style={{ opacity: .7, fontSize: 13 }}>ضعف مشخصی دیده نمی‌شود — عالی! 🌟</span>}
                        </div>
                    </div>

                    {showAnswers && analysis.wrong?.length > 0 && (
                        <div className="k3-card" style={{ marginTop: 14 }}>
                            <div style={{ fontWeight: 900, fontSize: 15, marginBottom: 10 }}>📚 سؤال‌هایی که اشتباه زدی (با توضیح)</div>
                            {analysis.wrong.map((w, i) => (
                                <div key={i} style={{ padding: '9px 0', borderTop: i ? '1px solid rgba(255,255,255,.1)' : 0 }}>
                                    <div style={{ fontWeight: 700, fontSize: 14 }}>{w.prompt}</div>
                                    {w.explanation && <div style={{ opacity: .85, fontSize: 13, marginTop: 4, lineHeight: 1.9 }}>💡 {w.explanation}</div>}
                                </div>
                            ))}
                        </div>
                    )}
                </>
            )}

            <div style={{ display: 'flex', gap: 10, justifyContent: 'center', marginTop: 18 }}>
                <Link href={route('student.smart.index')} className="k3-btn">🧠 آزمون‌های دیگر</Link>
                <Link href="/dashboard" className="k3-btn ghost">🏠 خانه</Link>
            </div>
        </ThemedDash>
    );
}
function M({ v, l }) { return <div style={{ background: 'rgba(0,0,0,.22)', borderRadius: 14, padding: '12px 18px' }}><b style={{ fontSize: 20 }}>{v}</b><div style={{ fontSize: 11.5, opacity: .85 }}>{l}</div></div>; }
