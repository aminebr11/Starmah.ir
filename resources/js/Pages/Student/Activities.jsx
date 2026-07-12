import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const TYPE_COLORS = ['#3d7bf0', '#a24cf0', '#18a97c', '#f0952e', '#e8505b', '#0ea5b7'];

export default function Activities() {
    const { entries = [], total = 0, week = 0, byType = [] } = usePage().props;
    const maxType = Math.max(1, ...byType.map((t) => Math.abs(t.points)));

    return (
        <ThemedDash title="فعالیت‌ها و امتیازها" active="activities">
            {/* خلاصه */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 12 }}>
                <Stat icon="⚡" label="امتیاز کل" value={fa(total)} c1="#f5b53f" c2="#d98f0f" />
                <Stat icon="📅" label="امتیاز این هفته" value={fa(week)} c1="#2bb673" c2="#1a8a52" />
                <Stat icon="🎁" label="تعداد فعالیت‌ها" value={fa(entries.length)} c1="#7c5cf0" c2="#4c2fb0" />
            </div>

            {/* امتیاز بر اساس نوع */}
            {byType.length > 0 && (
                <div className="k3-card" style={{ marginTop: 16 }}>
                    <div style={{ fontWeight: 900, fontSize: 16, marginBottom: 12 }}>📊 امتیاز بر اساس نوع فعالیت</div>
                    <div style={{ display: 'grid', gap: 10 }}>
                        {byType.map((t, i) => (
                            <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                <span style={{ width: 96, fontSize: 13, fontWeight: 700, flex: 'none' }}>{t.label}</span>
                                <div style={{ flex: 1, height: 14, borderRadius: 8, background: 'rgba(255,255,255,.12)', overflow: 'hidden' }}>
                                    <i style={{ display: 'block', height: '100%', width: `${(Math.abs(t.points) / maxType) * 100}%`, background: `linear-gradient(90deg,${TYPE_COLORS[i % 6]},${TYPE_COLORS[(i + 2) % 6]})`, borderRadius: 8 }} />
                                </div>
                                <b style={{ flex: 'none', color: 'var(--acc)', minWidth: 40, textAlign: 'left' }}>{fa(t.points)}</b>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* دفترکل */}
            <div className="k3-card" style={{ marginTop: 16 }}>
                <div style={{ fontWeight: 900, fontSize: 16, marginBottom: 10 }}>🧾 تاریخچه‌ی امتیازها</div>
                {entries.length === 0 && <div style={{ opacity: .75, fontSize: 13 }}>هنوز امتیازی ثبت نشده.</div>}
                <div style={{ display: 'grid', gap: 8 }}>
                    {entries.map((e) => (
                        <div key={e.id} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '9px 11px', borderRadius: 12, background: 'rgba(255,255,255,.06)' }}>
                            <span style={{ fontSize: 18 }}>{e.kind === 'plus' ? '➕' : '➖'}</span>
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div style={{ fontWeight: 700, fontSize: 13.5 }}>{e.reason || 'فعالیت'}</div>
                                <div style={{ fontSize: 11, opacity: .65 }}>{e.date}</div>
                            </div>
                            <b style={{ flex: 'none', fontSize: 15, color: e.kind === 'plus' ? '#7be0b0' : '#ffb3b3' }}>{e.amount >= 0 ? '+' : ''}{fa(e.amount)}</b>
                        </div>
                    ))}
                </div>
            </div>
        </ThemedDash>
    );
}

function Stat({ icon, label, value, c1, c2 }) {
    return (
        <div style={{ borderRadius: 18, padding: 16, color: '#fff', background: `linear-gradient(135deg,${c1},${c2})`, boxShadow: '0 10px 24px -12px rgba(0,0,0,.5)' }}>
            <div style={{ fontSize: 24 }}>{icon}</div>
            <div style={{ fontSize: 30, fontWeight: 900, lineHeight: 1.3 }}>{value}</div>
            <div style={{ fontSize: 12.5, opacity: .9 }}>{label}</div>
        </div>
    );
}
