import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const card = { background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 18, color: '#fff' };

const RATING = {
    'خیلی خوب': ['#1f7a45', '#7be05a'], 'خوب': ['#1b4b8a', '#8fc9ff'], 'قابل قبول': ['#8a5a00', '#ffcf6b'],
    'نیاز به تلاش': ['#a04413', '#ffab7a'], 'غایب': ['#8a1f1f', '#ff8d8d'],
    'کامل': ['#1f7a45', '#7be05a'], 'ناقص': ['#8a5a00', '#ffcf6b'], 'انجام نداده': ['#8a1f1f', '#ff8d8d'],
};
const ICON = { 'غایب': '🚫', 'کامل': '✅', 'ناقص': '⚠️', 'انجام نداده': '❌' };

/** نمرات کلاسیِ دانش‌آموز — نمای زیبا و کامل از وضعیتِ خودش. */
export default function Grades() {
    const { subjects = [], stats = {} } = usePage().props;

    return (
        <ThemedDash title="نمرات کلاسی من" active="grades">
            {/* خلاصه */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 12, marginBottom: 8 }}>
                {[['📔', 'فعالیت‌ها', stats.total, 'var(--acc)'], ['📚', 'درس‌ها', stats.subjects, '#8fc9ff'], ['⭐', 'امتیاز نمرات', stats.xp, '#7be05a']].map(([ic, t, v, c]) => (
                    <div key={t} style={{ ...card, textAlign: 'center' }}>
                        <div style={{ fontSize: 24 }}>{ic}</div>
                        <div style={{ fontSize: 24, fontWeight: 800, color: c }}>{fa(v ?? 0)}</div>
                        <div style={{ opacity: .7, fontSize: 12 }}>{t}</div>
                    </div>
                ))}
            </div>

            {subjects.length === 0 && (
                <div style={{ ...card, textAlign: 'center', marginTop: 12 }}>
                    <div style={{ fontSize: 40 }}>📭</div>
                    <div style={{ marginTop: 8, opacity: .85 }}>هنوز نمره‌ای برایت ثبت نشده. به‌زودی این‌جا پر می‌شود!</div>
                </div>
            )}

            {subjects.map((s) => (
                <div key={s.lesson} style={{ marginTop: 18 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '0 4px 10px', fontWeight: 800, fontSize: 16, color: '#fff' }}>
                        <span style={{ width: 5, height: 18, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))' }} />
                        📚 {s.lesson} <span style={{ opacity: .6, fontSize: 12, fontWeight: 500 }}>({fa(s.count)} نمره)</span>
                    </div>
                    <div style={{ ...card }}>
                        {s.items.map((g, i) => {
                            const rc = RATING[g.value];
                            const isNum = g.score_type === 'numeric';
                            return (
                                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 0', borderBottom: i === s.items.length - 1 ? 0 : '1px solid rgba(255,255,255,.08)', flexWrap: 'wrap' }}>
                                    <div style={{ flex: 1, minWidth: 150 }}>
                                        <div style={{ fontWeight: 700 }}>{g.title}</div>
                                        {g.topic && <div style={{ opacity: .65, fontSize: 12 }}>{g.topic}</div>}
                                        {g.feedback && <div style={{ opacity: .8, fontSize: 12, marginTop: 3 }}>💬 {g.feedback}</div>}
                                        {g.jdate && <div style={{ opacity: .55, fontSize: 11, marginTop: 2 }}>📅 {g.jdate}</div>}
                                    </div>
                                    <div style={{ textAlign: 'center' }}>
                                        {isNum ? (
                                            <div style={{ fontWeight: 800, fontSize: 20, color: g.value >= g.max * 0.5 ? '#7be05a' : '#ff8d8d' }}>
                                                {g.value != null ? fa(g.value) : '—'}<span style={{ fontSize: 12, opacity: .6 }}> / {fa(g.max)}</span>
                                            </div>
                                        ) : (
                                            <span style={{ background: rc ? rc[0] : 'rgba(255,255,255,.1)', color: rc ? rc[1] : '#fff', borderRadius: 20, padding: '6px 14px', fontWeight: 800, fontSize: 13 }}>
                                                {ICON[g.value] || ''} {g.value || '—'}
                                            </span>
                                        )}
                                        {g.xp !== 0 && <div style={{ fontSize: 11, marginTop: 4, color: g.xp > 0 ? '#7be05a' : '#ff8d8d', fontWeight: 700 }}>{g.xp > 0 ? '+' : ''}{fa(g.xp)} XP</div>}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            ))}
        </ThemedDash>
    );
}
