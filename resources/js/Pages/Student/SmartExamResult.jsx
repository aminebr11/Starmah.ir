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
                        <div style={{ marginTop: 16 }}>
                            <div style={{ fontWeight: 900, fontSize: 16, margin: '0 4px 10px', display: 'flex', alignItems: 'center', gap: 8 }}>
                                <SadFace size={26} /> سؤال‌هایی که باید بیشتر تمرین کنی
                            </div>
                            <div style={{ display: 'grid', gap: 12 }}>
                                {analysis.wrong.map((w, i) => (
                                    <div key={i} className={`wrong-tpl wt-c${i % WT.length}`} style={{ '--wt': WT[i % WT.length][0], '--wt2': WT[i % WT.length][1] }}>
                                        <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
                                            <span style={{ flex: 'none', marginTop: 2 }}><SadFace size={30} /></span>
                                            <div style={{ flex: 1, minWidth: 0 }}>
                                                <div style={{ fontWeight: 800, fontSize: 14.5 }}>{w.prompt}</div>
                                                {w.topic && <span style={{ display: 'inline-block', marginTop: 5, background: 'rgba(255,255,255,.25)', borderRadius: 20, padding: '2px 10px', fontSize: 11, fontWeight: 700 }}>سرفصل: {w.topic}</span>}
                                                {w.explanation && <div style={{ marginTop: 8, background: 'rgba(255,255,255,.18)', borderRadius: 12, padding: '9px 12px', fontSize: 13, lineHeight: 1.9 }}>💡 {w.explanation}</div>}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* پاسخنامه‌ی کامل — چه زدی و پاسخ درست چه بود */}
                    {showAnswers && analysis.review?.length > 0 && (
                        <div style={{ marginTop: 16 }}>
                            <div style={{ fontWeight: 900, fontSize: 16, margin: '0 4px 10px' }}>📄 پاسخنامه‌ی کامل</div>
                            <div style={{ display: 'grid', gap: 12 }}>
                                {analysis.review.map((q) => {
                                    const ok = q.is_correct === true;
                                    const wrong = q.is_correct === false;
                                    const edge = q.type === 'desc' ? '#f0b32e' : ok ? '#2bb673' : wrong ? '#e8505b' : 'rgba(255,255,255,.2)';
                                    return (
                                        <div key={q.i} className="k3-card" style={{ borderInlineStart: `5px solid ${edge}` }}>
                                            <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 6 }}>
                                                <span style={{ fontWeight: 800, opacity: .8 }}>سؤال {fa(q.i + 1)}</span>
                                                {q.type === 'desc'
                                                    ? <span style={{ fontSize: 12, fontWeight: 700, color: '#ffd27a' }}>✍️ تشریحی</span>
                                                    : <span style={{ fontSize: 12, fontWeight: 800, color: ok ? '#7be05a' : '#ff8f9a' }}>{ok ? '✅ درست' : '❌ نادرست'}</span>}
                                            </div>
                                            <div style={{ fontWeight: 700, lineHeight: 1.9 }}>{q.prompt}</div>
                                            {q.type === 'desc' ? (
                                                <div style={{ marginTop: 8, background: 'rgba(255,255,255,.06)', borderRadius: 10, padding: 10, fontSize: 13.5 }}><b style={{ opacity: .8 }}>پاسخ تو:</b> {q.mine || <span style={{ opacity: .6 }}>—</span>}</div>
                                            ) : (
                                                <div style={{ display: 'grid', gap: 6, marginTop: 8 }}>
                                                    {q.choices.map((c, k) => {
                                                        const isCorrect = c === q.correct;
                                                        const isMine = c === q.mine;
                                                        const bg = isCorrect ? 'rgba(43,182,115,.25)' : isMine ? 'rgba(232,80,91,.22)' : 'rgba(255,255,255,.05)';
                                                        const bd = isCorrect ? '#2bb673' : isMine ? '#e8505b' : 'rgba(255,255,255,.12)';
                                                        return (
                                                            <div key={k} style={{ display: 'flex', alignItems: 'center', gap: 8, background: bg, border: `1px solid ${bd}`, borderRadius: 10, padding: '8px 12px', fontSize: 14 }}>
                                                                <span>{fa(c)}</span>
                                                                {isCorrect && <span style={{ marginInlineStart: 'auto', fontSize: 12, color: '#7be05a', fontWeight: 800 }}>✅ پاسخ درست</span>}
                                                                {isMine && !isCorrect && <span style={{ marginInlineStart: 'auto', fontSize: 12, color: '#ff8f9a', fontWeight: 800 }}>پاسخ تو</span>}
                                                            </div>
                                                        );
                                                    })}
                                                    {!q.mine && <div style={{ fontSize: 12, color: '#ffb3ba' }}>به این سؤال پاسخ ندادی.</div>}
                                                </div>
                                            )}
                                            {q.explanation && <div style={{ marginTop: 8, fontSize: 12.5, opacity: .85, background: 'rgba(255,255,255,.05)', borderRadius: 10, padding: '8px 12px' }}>💡 {q.explanation}</div>}
                                        </div>
                                    );
                                })}
                            </div>
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

// پالت‌های رنگیِ مختلف برای سؤال‌های غلط
const WT = [['#e8505b', '#b0333f'], ['#e8862e', '#a5570f'], ['#a24cf0', '#6f2fb0'], ['#0ea5b7', '#0a7d8a'], ['#3d7bf0', '#2555c0'], ['#d6336c', '#a01f4f']];

// شکلِ SVGِ ناراحت (صورتک) — برای پاسخ‌های غلط
function SadFace({ size = 28 }) {
    return (
        <svg width={size} height={size} viewBox="0 0 48 48" fill="none" style={{ display: 'block', filter: 'drop-shadow(0 2px 3px rgba(0,0,0,.3))' }}>
            <circle cx="24" cy="24" r="21" fill="#ffd166" stroke="#fff" strokeWidth="2" />
            <circle cx="16.5" cy="19" r="3" fill="#3b2a12" />
            <circle cx="31.5" cy="19" r="3" fill="#3b2a12" />
            <path d="M15 33c3-4 15-4 18 0" stroke="#3b2a12" strokeWidth="3" strokeLinecap="round" fill="none" />
            <path d="M31 12l6-2" stroke="#3b2a12" strokeWidth="2.4" strokeLinecap="round" />
            <path d="M17 12l-6-2" stroke="#3b2a12" strokeWidth="2.4" strokeLinecap="round" />
            <circle cx="35" cy="28" r="2.2" fill="#6db3f2" />
        </svg>
    );
}
