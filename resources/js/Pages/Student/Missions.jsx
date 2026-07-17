import { Link, usePage, router } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const DIFF = { easy: 'آسان', medium: 'متوسط', hard: 'دشوار' };
const TYPE_META = {
    quiz: { ic: '🧠', label: 'سؤالِ بانک', go: 'شروع مأموریت' },
    podcast: { ic: '🎧', label: 'گوش‌دادن به پادکست', go: 'برو به پادکست‌ها' },
    worksheet: { ic: '🎨', label: 'انجام کاربرگ', go: 'برو به کاربرگ‌ها' },
    game: { ic: '🎮', label: 'انجام یک بازی', go: 'برو به بازی‌ها' },
};

/** مأموریت‌های روزانه‌ی دانش‌آموز — از مأموریت‌هایی که معلم تعریف کرده (سؤال‌ها از بانکِ معلم). */
export default function Missions() {
    const { missions = [], streak = 0 } = usePage().props;
    const doneCount = missions.filter((m) => m.done_today).length;

    return (
        <ThemedDash title="مأموریت‌های روزانه" active="practice">
            <div className="k3-card" style={{ background: 'linear-gradient(135deg,#e8862e,#c0561a)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 40 }}>🎯</span>
                    <div style={{ flex: 1, minWidth: 160 }}>
                        <div style={{ fontWeight: 900, fontSize: 19 }}>مأموریت‌های امروزِ تو</div>
                        <div style={{ opacity: .9, fontSize: 12.5 }}>این مأموریت‌ها را معلمت آماده کرده — هر کدام را که کامل کنی، امتیاز و گاهی نشان می‌گیری!</div>
                    </div>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <div style={{ background: 'rgba(0,0,0,.22)', borderRadius: 14, padding: '8px 14px', textAlign: 'center' }}><b style={{ fontSize: 20 }}>{fa(doneCount)}/{fa(missions.length)}</b><div style={{ fontSize: 10.5, opacity: .85 }}>امروز</div></div>
                        <div style={{ background: 'rgba(0,0,0,.22)', borderRadius: 14, padding: '8px 14px', textAlign: 'center' }}><b style={{ fontSize: 20 }}>🔥{fa(streak)}</b><div style={{ fontSize: 10.5, opacity: .85 }}>روزِ پیاپی</div></div>
                    </div>
                </div>
            </div>

            {missions.length === 0 && (
                <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', opacity: .85 }}>
                    فعلاً مأموریتی برایت تعریف نشده 📭<div style={{ fontSize: 12.5, opacity: .7, marginTop: 6 }}>معلمت به‌زودی مأموریت‌های تازه می‌گذارد.</div>
                </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(260px,1fr))', gap: 14, marginTop: 14 }}>
                {missions.map((m) => (
                    <div key={m.id} className="k3-card" style={{ borderTop: `4px solid ${m.done_today ? '#2bb673' : '#e8862e'}`, opacity: m.available ? 1 : .6 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                            <span style={{ fontSize: 30 }}>{m.badge_icon || (TYPE_META[m.type] || TYPE_META.quiz).ic}</span>
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div style={{ fontWeight: 900, fontSize: 15.5 }}>{m.title}</div>
                                <div style={{ fontSize: 11.5, opacity: .7 }}>{(TYPE_META[m.type] || TYPE_META.quiz).label}{m.type === 'quiz' ? ' · ' + ([m.subject, m.lesson_no ? `درس ${m.lesson_no}` : null, m.difficulty ? DIFF[m.difficulty] : null].filter(Boolean).join(' · ') || 'عمومی') : ''}</div>
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 8, marginTop: 10, flexWrap: 'wrap', fontSize: 12 }}>
                            {m.type === 'quiz' && <span className="k3-chip">📝 {fa(m.question_count)} سؤال</span>}
                            <span className="k3-chip">⚡ {fa(m.xp_reward)}</span>
                            {m.badge_name && <span className="k3-chip">🎖️ {m.badge_name}</span>}
                        </div>
                        <div style={{ marginTop: 12 }}>
                            {m.done_today
                                ? <div style={{ background: 'rgba(43,182,115,.2)', border: '1px solid rgba(43,182,115,.5)', borderRadius: 12, padding: '9px 12px', textAlign: 'center', fontWeight: 800, fontSize: 13 }}>✅ امروز انجام دادی — فردا دوباره!</div>
                                : m.type === 'quiz'
                                    ? (!m.available
                                        ? <div style={{ fontSize: 12.5, opacity: .75, textAlign: 'center' }}>سؤالی برای این مأموریت آماده نیست.</div>
                                        : <Link href={route('missions.play', m.id)} className="k3-btn" style={{ width: '100%', textAlign: 'center' }}>🚀 شروع مأموریت</Link>)
                                    : m.claimable
                                        ? <button onClick={() => router.post(route('missions.claim', m.id), {}, { preserveScroll: true })} className="k3-btn" style={{ width: '100%', background: 'linear-gradient(135deg,#2bb673,#0f9d58)' }}>🎁 دریافتِ جایزه (+{fa(m.xp_reward)})</button>
                                        : <Link href={m.link || '#'} className="k3-btn ghost" style={{ width: '100%', textAlign: 'center' }}>← {(TYPE_META[m.type] || TYPE_META.quiz).go}</Link>}
                        </div>
                    </div>
                ))}
            </div>

        </ThemedDash>
    );
}
