import { usePage, router } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const card = { background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 16, color: '#fff' };

/** پاسخنامه‌ی دانش‌آموز — چه زده و پاسخ درست چه بوده. */
export default function ExamReview() {
    const { exam, items = [], score, max, accuracy } = usePage().props;
    const rights = items.filter((q) => q.is_correct === true).length;

    return (
        <ThemedDash title={`پاسخنامه — ${exam.title}`} active="exams">
            <div style={{ ...card, display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                <b style={{ fontSize: 16 }}>📄 پاسخنامه‌ی «{exam.title}»</b>
                <span style={{ marginInlineStart: 'auto', fontWeight: 800, color: '#7be05a' }}>✅ {fa(rights)} درست</span>
                {max ? <span style={{ fontWeight: 800 }}>نمره: {fa(score)}/{fa(max)}{accuracy != null ? ` (${fa(Math.round(accuracy))}٪)` : ''}</span> : null}
                <button onClick={() => router.visit(route('exams'))} style={{ background: 'rgba(255,255,255,.15)', color: '#fff', border: 0, padding: '8px 16px', borderRadius: 12, fontWeight: 800, cursor: 'pointer', fontFamily: 'inherit' }}>← بازگشت</button>
            </div>

            <div style={{ display: 'grid', gap: 12, marginTop: 12 }}>
                {items.map((q) => {
                    const ok = q.is_correct === true;
                    const wrong = q.is_correct === false;
                    const edge = q.type === 'desc' ? '#f0b32e' : ok ? '#2bb673' : wrong ? '#e8505b' : 'rgba(255,255,255,.2)';
                    return (
                        <div key={q.i} style={{ ...card, borderInlineStart: `5px solid ${edge}` }}>
                            <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 6 }}>
                                <span style={{ fontWeight: 800, opacity: .8 }}>سؤال {fa(q.i + 1)}</span>
                                {q.type === 'desc'
                                    ? <span style={{ fontSize: 12, fontWeight: 700, color: '#ffd27a' }}>✍️ تشریحی{q.desc_score != null ? ` — نمره: ${fa(q.desc_score)}` : ' — در انتظار تصحیح معلم'}</span>
                                    : <span style={{ fontSize: 12, fontWeight: 800, color: ok ? '#7be05a' : '#ff8f9a' }}>{ok ? '✅ درست' : '❌ نادرست'}</span>}
                            </div>
                            <div style={{ fontWeight: 700, lineHeight: 1.9 }}>{q.prompt}</div>

                            {q.type === 'desc' ? (
                                <div style={{ marginTop: 8, background: 'rgba(255,255,255,.06)', borderRadius: 10, padding: 10, fontSize: 13.5 }}>
                                    <b style={{ opacity: .8 }}>پاسخ تو:</b> {q.mine || <span style={{ opacity: .6 }}>—</span>}
                                </div>
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
                                    {!q.mine && <div style={{ fontSize: 12, color: '#ffb3ba', marginTop: 2 }}>به این سؤال پاسخ ندادی.</div>}
                                </div>
                            )}

                            {q.explanation && <div style={{ marginTop: 8, fontSize: 12.5, opacity: .85, background: 'rgba(255,255,255,.05)', borderRadius: 10, padding: '8px 12px' }}>💡 {q.explanation}</div>}
                        </div>
                    );
                })}
            </div>
        </ThemedDash>
    );
}
