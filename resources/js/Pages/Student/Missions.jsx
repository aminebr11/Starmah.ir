import { Link, usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const DIFF = { easy: 'آسان', medium: 'متوسط', hard: 'دشوار' };

const TYPE_META = {
    quiz:      { ic: '🧠', label: 'سؤالِ بانک', go: 'شروعِ مأموریت', c1: '#a24cf0', c2: '#6f2fb0' },
    podcast:   { ic: '🎧', label: 'پادکست', go: 'برو به پادکست‌ها', c1: '#a24cf0', c2: '#6f2fb0' },
    video:     { ic: '🎬', label: 'ویدیوی درسی', go: 'برو به ویدیوها', c1: '#e8505b', c2: '#b3303c' },
    material:  { ic: '📄', label: 'جزوه و فایل', go: 'برو به جزوه‌ها', c1: '#3d7bf0', c2: '#2555c0' },
    worksheet: { ic: '🎨', label: 'کاربرگ', go: 'برو به کاربرگ‌ها', c1: '#f0952e', c2: '#c06712' },
    game:      { ic: '🎮', label: 'بازی', go: 'برو به بازی‌ها', c1: '#2bb673', c2: '#0f9d58' },
    exam:      { ic: '🧪', label: 'آزمونِ هوشمند', go: 'برو به آزمون‌ها', c1: '#00a3b4', c2: '#00727e' },
};
const meta = (t) => TYPE_META[t] || TYPE_META.quiz;

/** مأموریت‌های روزانه — تخته‌ی مأموریتِ دانش‌آموز با رشته‌ی روزها و جعبه‌ی گنج. */
export default function Missions() {
    const { missions = [], streak = 0, combo = {}, history = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) { setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); window.scrollTo({ top: 0, behavior: 'smooth' }); } }, [flash]);

    const done = missions.filter((m) => m.done_today);
    const todo = missions.filter((m) => !m.done_today);
    const pct = missions.length ? Math.round((done.length / missions.length) * 100) : 0;

    return (
        <ThemedDash title="مأموریت‌های روزانه" active="practice">
            {banner && (
                <div className="k3-card" style={{ background: 'linear-gradient(135deg,#2bb673,#0f9d58)', fontWeight: 800 }}>{banner}</div>
            )}

            {/* ── سرلوحه: پیشرفتِ امروز و رشته‌ی روزها ── */}
            <div className="k3-card" style={{ background: 'linear-gradient(135deg,#e8862e,#c0561a)', marginTop: banner ? 12 : 0 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 40 }}>🎯</span>
                    <div style={{ flex: 1, minWidth: 160 }}>
                        <div style={{ fontWeight: 900, fontSize: 19 }}>تخته‌ی مأموریتِ امروز</div>
                        <div style={{ opacity: .9, fontSize: 12.5, lineHeight: 1.8 }}>
                            هر مأموریت را که تمام کنی امتیاز می‌گیری — و اگر <b>همه‌شان</b> را تمام کنی، جعبه‌ی گنج باز می‌شود!
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <Pill v={`${fa(done.length)}/${fa(missions.length)}`} l="امروز" />
                        <Pill v={`🔥${fa(streak)}`} l="روزِ پیاپی" />
                    </div>
                </div>

                {missions.length > 0 && (
                    <div style={{ marginTop: 14 }}>
                        <div style={{ height: 12, borderRadius: 99, background: 'rgba(0,0,0,.25)', overflow: 'hidden' }}>
                            <div style={{ width: `${pct}%`, height: '100%', borderRadius: 99, background: 'linear-gradient(90deg,#ffe066,#2bb673)', transition: 'width .5s' }} />
                        </div>
                        <div style={{ fontSize: 11.5, opacity: .9, marginTop: 5 }}>{fa(pct)}٪ از مأموریت‌های امروز</div>
                    </div>
                )}
            </div>

            {/* ── جعبه‌ی گنجِ روزانه ── */}
            {missions.length > 0 && <TreasureChest combo={combo} />}

            {/* ── هفته‌ی من ── */}
            {history.length > 0 && <WeekStrip history={history} />}

            {missions.length === 0 && (
                <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', opacity: .85 }}>
                    فعلاً مأموریتی برایت تعریف نشده 📭
                    <div style={{ fontSize: 12.5, opacity: .7, marginTop: 6 }}>معلمت به‌زودی مأموریت‌های تازه می‌گذارد.</div>
                </div>
            )}

            {todo.length > 0 && <Section t="🚀 مانده برای امروز" n={todo.length} />}
            <Grid list={todo} />

            {done.length > 0 && <Section t="✅ انجام‌شده‌های امروز" n={done.length} />}
            <Grid list={done} />
        </ThemedDash>
    );
}

/* ═══════════════════ جعبه‌ی گنج ═══════════════════ */
function TreasureChest({ combo }) {
    const { total = 0, done = 0, xp = 0, ready, claimed, claimed_xp: claimedXp = 0, streak = 0 } = combo;
    const left = Math.max(0, total - done);

    const claim = () => router.post(route('missions.combo'), {}, { preserveScroll: true });

    const bg = claimed
        ? 'linear-gradient(135deg,#0f9d58,#0a6e3d)'
        : ready ? 'linear-gradient(135deg,#f5b53f,#e8862e)' : 'linear-gradient(135deg,#3a3f63,#242845)';

    return (
        <div className="k3-card" style={{ marginTop: 14, background: bg, position: 'relative', overflow: 'hidden' }}>
            {ready && <Sparkles />}
            <div style={{ display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap', position: 'relative' }}>
                <span style={{ fontSize: 46, filter: ready ? 'none' : claimed ? 'none' : 'grayscale(.7)' }}>{claimed ? '🏆' : ready ? '🎁' : '🔒'}</span>
                <div style={{ flex: 1, minWidth: 180 }}>
                    <div style={{ fontWeight: 900, fontSize: 17 }}>جعبه‌ی گنجِ روزانه</div>
                    <div style={{ fontSize: 12.5, opacity: .9, lineHeight: 1.9, marginTop: 2 }}>
                        {claimed
                            ? <>امروز جعبه را باز کردی و <b>{fa(claimedXp)}</b> امتیازِ اضافه گرفتی. فردا دوباره! 🎉</>
                            : ready
                                ? <>همه‌ی مأموریت‌های امروز را تمام کردی! جعبه آماده‌ی بازشدن است.</>
                                : <>با تمام‌کردنِ <b>{fa(left)}</b> مأموریتِ باقی‌مانده، جعبه باز می‌شود.</>}
                    </div>
                    {!claimed && (
                        <div style={{ fontSize: 11.5, opacity: .8, marginTop: 4 }}>
                            جایزه: <b>⚡{fa(xp)}</b> امتیاز{streak > 0 ? ` (شاملِ پاداشِ ${fa(streak)} روزِ پیاپی 🔥)` : ''}
                        </div>
                    )}
                </div>
                {ready && (
                    <button onClick={claim} className="k3-btn" style={{ background: 'linear-gradient(135deg,#fff,#ffe9b8)', color: '#7a4a08', fontWeight: 900 }}>
                        🎁 بازکردنِ جعبه (+{fa(xp)})
                    </button>
                )}
            </div>

            {!claimed && total > 0 && (
                <div style={{ display: 'flex', gap: 5, marginTop: 12, position: 'relative' }}>
                    {Array.from({ length: total }).map((_, i) => (
                        <span key={i} style={{ flex: 1, height: 8, borderRadius: 99, background: i < done ? '#ffe066' : 'rgba(255,255,255,.18)' }} />
                    ))}
                </div>
            )}
        </div>
    );
}

const Sparkles = () => (
    <>
        {[[6, 14], [88, 10], [30, 82], [70, 78], [50, 8]].map(([x, y], i) => (
            <span key={i} style={{ position: 'absolute', left: `${x}%`, top: `${y}%`, fontSize: 16, opacity: .6 }}>✨</span>
        ))}
    </>
);

/* ═══════════════════ هفته‌ی من ═══════════════════ */
function WeekStrip({ history }) {
    const max = Math.max(1, ...history.map((h) => h.count));
    return (
        <div className="k3-card" style={{ marginTop: 14 }}>
            <div style={{ fontWeight: 800, fontSize: 14, marginBottom: 10 }}>📅 هفته‌ی من</div>
            <div style={{ display: 'flex', gap: 6, alignItems: 'flex-end' }}>
                {history.map((h) => (
                    <div key={h.date} style={{ flex: 1, textAlign: 'center' }}>
                        <div style={{ height: 54, display: 'flex', alignItems: 'flex-end', justifyContent: 'center' }}>
                            <div title={`${h.count} مأموریت · ${h.xp} امتیاز`}
                                style={{
                                    width: '78%', borderRadius: '8px 8px 3px 3px',
                                    height: `${h.count ? Math.max(14, (h.count / max) * 54) : 5}px`,
                                    background: h.count ? (h.today ? 'linear-gradient(180deg,#ffe066,#e8862e)' : 'linear-gradient(180deg,#7aa2f7,#3d7bf0)') : 'rgba(255,255,255,.14)',
                                }} />
                        </div>
                        <div style={{ fontSize: 11, opacity: h.today ? 1 : .65, fontWeight: h.today ? 900 : 600, marginTop: 4 }}>{h.label}</div>
                        <div style={{ fontSize: 10, opacity: .55 }}>{h.count ? fa(h.count) : '—'}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}

/* ═══════════════════ کارت‌ها ═══════════════════ */
const Section = ({ t, n }) => (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '18px 0 8px' }}>
        <b style={{ fontSize: 15 }}>{t}</b>
        <span className="k3-chip">{fa(n)}</span>
        <span style={{ flex: 1, height: 1, background: 'rgba(255,255,255,.12)' }} />
    </div>
);

const Grid = ({ list }) => (
    list.length === 0 ? null : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(260px,1fr))', gap: 14 }}>
            {list.map((m) => <MissionCard key={m.id} m={m} />)}
        </div>
    )
);

function MissionCard({ m }) {
    const t = meta(m.type);
    const sub = m.type === 'quiz'
        ? ([m.subject, m.lesson_no ? `درس ${fa(m.lesson_no)}` : null, m.difficulty ? DIFF[m.difficulty] : null].filter(Boolean).join(' · ') || 'عمومی')
        : (m.resource_title || 'هر موردی');

    return (
        <div className="k3-card" style={{ borderTop: `4px solid ${m.done_today ? '#2bb673' : t.c1}`, opacity: m.available ? 1 : .6 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <span style={{ fontSize: 26, width: 46, height: 46, borderRadius: 14, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', background: `linear-gradient(135deg,${t.c1},${t.c2})`, flex: '0 0 auto' }}>
                    {m.badge_icon || t.ic}
                </span>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 900, fontSize: 15.5 }}>{m.title}</div>
                    <div style={{ fontSize: 11.5, opacity: .7 }}>{t.label} · {sub}</div>
                </div>
            </div>

            {m.description && (
                <div style={{ fontSize: 12.5, opacity: .85, marginTop: 9, lineHeight: 1.9, background: 'rgba(255,255,255,.06)', borderRadius: 10, padding: '7px 10px' }}>
                    💬 {m.description}
                </div>
            )}

            <div style={{ display: 'flex', gap: 8, marginTop: 10, flexWrap: 'wrap', fontSize: 12 }}>
                {m.type === 'quiz' && <span className="k3-chip">📝 {fa(m.question_count)} سؤال</span>}
                <span className="k3-chip">⚡ {fa(m.xp_reward)}</span>
                {m.badge_name && <span className="k3-chip">🎖️ {m.badge_name}</span>}
                {m.teacher && <span className="k3-chip">👩‍🏫 {m.teacher}</span>}
            </div>

            <div style={{ marginTop: 12 }}>
                {m.done_today
                    ? <div style={{ background: 'rgba(43,182,115,.2)', border: '1px solid rgba(43,182,115,.5)', borderRadius: 12, padding: '9px 12px', textAlign: 'center', fontWeight: 800, fontSize: 13 }}>✅ امروز انجام دادی — فردا دوباره!</div>
                    : m.type === 'quiz'
                        ? (!m.available
                            ? <div style={{ fontSize: 12.5, opacity: .75, textAlign: 'center' }}>سؤالی برای این مأموریت آماده نیست.</div>
                            : <Link href={route('missions.play', m.id)} className="k3-btn" style={{ width: '100%', textAlign: 'center' }}>🚀 {t.go}</Link>)
                        : m.claimable
                            ? <button onClick={() => router.post(route('missions.claim', m.id), {}, { preserveScroll: true })} className="k3-btn" style={{ width: '100%', background: 'linear-gradient(135deg,#2bb673,#0f9d58)' }}>🎁 دریافتِ جایزه (+{fa(m.xp_reward)})</button>
                            : <Link href={m.link || '#'} className="k3-btn ghost" style={{ width: '100%', textAlign: 'center' }}>← {t.go}</Link>}
            </div>
        </div>
    );
}

const Pill = ({ v, l }) => (
    <div style={{ background: 'rgba(0,0,0,.22)', borderRadius: 14, padding: '8px 14px', textAlign: 'center' }}>
        <b style={{ fontSize: 20 }}>{v}</b>
        <div style={{ fontSize: 10.5, opacity: .85 }}>{l}</div>
    </div>
);
