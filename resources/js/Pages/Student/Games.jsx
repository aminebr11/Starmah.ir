import { Link, usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Games() {
    const { games = [] } = usePage().props;
    const live = games.filter((g) => g.live);
    const soon = games.filter((g) => !g.live);

    return (
        <ThemedDash title="بازی‌ها" active="games">
            <div className="k3-card">
                <div style={{ fontWeight: 900, fontSize: 18 }}>🎮 اتاق بازی</div>
                <div style={{ opacity: .82, fontSize: 13, marginTop: 3 }}>بازی کن، سؤال‌ها را درست جواب بده و امتیاز بگیر!</div>
            </div>

            {live.length === 0 && soon.length === 0 && (
                <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', opacity: .8 }}>هنوز بازی‌ای منتشر نشده — به‌زودی! 🕹️</div>
            )}

            {live.length > 0 && (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(220px,1fr))', gap: 14, marginTop: 16 }}>
                    {live.map((g, i) => (
                        <Link key={g.id} href={route('games.play', g.id)} className="k3-homecard" style={{ '--c1': PAL[i % PAL.length][0], '--c2': PAL[i % PAL.length][1] }}>
                            <span className="hc-em">🎮</span>
                            <span className="hc-lbl">{g.title}</span>
                            <span className="hc-sub">{g.subject ? `${g.subject} · ` : ''}{fa(g.count)} سؤال</span>
                            <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                                <span style={{ background: 'rgba(0,0,0,.25)', borderRadius: 20, padding: '3px 10px', fontSize: 11.5, fontWeight: 800 }}>⚡ {fa(g.points)} امتیاز</span>
                                {g.played && <span style={{ background: 'rgba(255,255,255,.28)', borderRadius: 20, padding: '3px 10px', fontSize: 11.5, fontWeight: 800 }}>✓ انجام‌شده</span>}
                            </div>
                        </Link>
                    ))}
                </div>
            )}

            {soon.length > 0 && (
                <>
                    <div style={{ fontWeight: 800, opacity: .8, margin: '18px 4px 8px' }}>⏰ به‌زودی منتشر می‌شود</div>
                    <div style={{ display: 'grid', gap: 10 }}>
                        {soon.map((g) => (
                            <div key={g.id} className="k3-card" style={{ opacity: .7, display: 'flex', alignItems: 'center', gap: 10 }}>
                                <span style={{ fontSize: 24 }}>🔒</span>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontWeight: 800 }}>{g.title}</div>
                                    {g.when && <div style={{ fontSize: 12, opacity: .8 }}>انتشار: {g.when}</div>}
                                </div>
                                <span style={{ fontSize: 12, fontWeight: 800, color: 'var(--acc)' }}>⚡ {fa(g.points)}</span>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </ThemedDash>
    );
}

const PAL = [['#e8505b', '#b0333f'], ['#3d7bf0', '#2555c0'], ['#2bb673', '#1a8a52'], ['#a24cf0', '#6f2fb0'], ['#f0952e', '#c06712'], ['#0ea5b7', '#0a7d8a']];
