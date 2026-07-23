import { usePage, Link } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const card = { background: 'rgba(255,255,255,.06)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 16, color: '#fff' };
const MEDAL = ['🥇', '🥈', '🥉'];

/** «تیمِ ما» — مجموعِ امتیازِ تیم، اعضا و دفترِ ریزِ اخیر (امتیاز از کجا آمده). */
export default function Team() {
    const { teams = [], mine, rank, ledger = [] } = usePage().props;

    return (
        <ThemedDash title="تیمِ ما" active="team">
            <div className="k3-card" style={{ background: mine ? `linear-gradient(135deg,${mine.color},#1b2742)` : 'linear-gradient(135deg,#3d7bf0,#2555c0)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 40 }}>{mine?.emoji || '🏆'}</span>
                    <div style={{ flex: 1, minWidth: 160 }}>
                        <div style={{ fontWeight: 900, fontSize: 19 }}>{mine?.name || 'تیمِ من'}</div>
                        <div style={{ opacity: .85, fontSize: 12.5 }}>{rank ? `رتبه‌ی ${fa(rank)} در رقابتِ تیم‌ها` : 'رقابتِ تیم‌ها'}</div>
                    </div>
                    <div style={{ background: 'rgba(0,0,0,.25)', borderRadius: 14, padding: '8px 16px', textAlign: 'center' }}>
                        <b style={{ fontSize: 22 }}>⚡{fa(mine?.total || 0)}</b>
                        <div style={{ fontSize: 10.5, opacity: .85 }}>امتیازِ تیم</div>
                    </div>
                </div>
            </div>

            {/* رتبه‌بندیِ تیم‌ها */}
            <div className="k3-card" style={{ marginTop: 14 }}>
                <div style={{ fontWeight: 900, fontSize: 15, marginBottom: 10 }}>🏆 رقابتِ تیم‌ها</div>
                {teams.map((t, i) => {
                    const max = Math.max(1, ...teams.map((x) => x.total));
                    return (
                        <div key={t.theme_id} style={{ marginBottom: 10, opacity: t.mine ? 1 : .9 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, fontWeight: 800, marginBottom: 4 }}>
                                <span>{MEDAL[i] || `${fa(i + 1)}.`} {t.emoji} {t.name}{t.mine ? ' (تیمِ تو)' : ''}</span>
                                <span>⚡{fa(t.total)}</span>
                            </div>
                            <div style={{ height: 10, borderRadius: 6, background: 'rgba(255,255,255,.12)', overflow: 'hidden' }}>
                                <i style={{ display: 'block', height: '100%', width: `${(t.total / max) * 100}%`, background: t.color, borderRadius: 6 }} />
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* اعضای تیمِ من — فقط جمعِ کلِ هرکس (بدونِ ریزِ خصوصیِ دیگران) */}
            {mine && (
                <div className="k3-card" style={{ marginTop: 14 }}>
                    <div style={{ fontWeight: 900, fontSize: 15, marginBottom: 4 }}>⭐ اعضای تیمِ ما ({fa(mine.count)})</div>
                    <div style={{ opacity: .7, fontSize: 11.5, marginBottom: 10 }}>فقط مجموعِ امتیازِ هر هم‌تیمی نمایش داده می‌شود.</div>
                    <div style={{ display: 'grid', gap: 6 }}>
                        {mine.members.map((m, i) => (
                            <div key={m.id} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 10px', borderRadius: 12, background: m.me ? 'rgba(245,181,63,.16)' : 'rgba(255,255,255,.05)' }}>
                                <span style={{ width: 26, textAlign: 'center', fontWeight: 900 }}>{MEDAL[i] || fa(i + 1)}</span>
                                <span style={{ flex: 1, fontWeight: m.me ? 900 : 700 }}>{m.name}{m.me ? ' (تو)' : ''}</span>
                                <span style={{ fontWeight: 800, color: 'var(--acc)' }}>⚡{fa(m.xp)}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* دفترِ ریزِ خودم (خصوصی — فقط امتیازهای خودِ دانش‌آموز) */}
            <div className="k3-card" style={{ marginTop: 14 }}>
                <div style={{ fontWeight: 900, fontSize: 15, marginBottom: 4 }}>📜 ریزِ امتیازهای من</div>
                <div style={{ opacity: .7, fontSize: 12, marginBottom: 10 }}>امتیازهایی که خودت از بازی، مأموریت، آزمون و… آورده‌ای و به تیمت اضافه شده — این‌ها فقط برای خودت دیده می‌شوند.</div>
                {ledger.length === 0 && <div style={{ opacity: .75, fontSize: 13 }}>هنوز امتیازی نگرفته‌ای — با انجامِ مأموریت و بازی، تیمت را بالا ببر! 💪</div>}
                <div style={{ display: 'grid', gap: 6 }}>
                    {ledger.map((e, i) => (
                        <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 10px', borderRadius: 12, background: 'rgba(255,255,255,.05)' }}>
                            <span style={{ fontWeight: 900, minWidth: 44, color: e.amount >= 0 ? '#7be0b0' : '#ffb3b3' }}>{e.amount >= 0 ? '+' : ''}{fa(e.amount)}</span>
                            <span style={{ flex: 1, fontSize: 12.5 }}>{e.reason}</span>
                            <span style={{ opacity: .55, fontSize: 10.5 }}>{e.date}</span>
                        </div>
                    ))}
                </div>
            </div>

            <div style={{ marginTop: 14, textAlign: 'center' }}>
                <Link href="/missions" className="k3-btn ghost" style={{ fontSize: 13 }}>🎯 مأموریت‌ها را انجام بده و تیمت را بالا ببر</Link>
            </div>
        </ThemedDash>
    );
}
