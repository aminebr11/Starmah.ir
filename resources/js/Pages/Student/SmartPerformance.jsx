import { Link, usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const hue = (p) => p >= 70 ? '#2bb673' : p >= 50 ? '#e8862e' : '#e8505b';

export default function SmartPerformance() {
    const { subjects = [], totalAnswered = 0, overallPct = 0 } = usePage().props;
    return (
        <ThemedDash title="کارنامه‌ی هوشمند من" active="smart">
            <div className="k3-card" style={{ background: 'linear-gradient(135deg,#6d28d9,#4c1d95)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 40 }}>📊</span>
                    <div style={{ flex: 1, minWidth: 160 }}>
                        <div style={{ fontWeight: 900, fontSize: 19 }}>کارنامه‌ی هوشمند من</div>
                        <div style={{ opacity: .85, fontSize: 13 }}>امتیاز و پیشرفتِ تو در هر درس و سرفصل — نقاط قوت و ضعف</div>
                    </div>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <div style={{ background: 'rgba(0,0,0,.22)', borderRadius: 14, padding: '8px 14px', textAlign: 'center' }}><b style={{ fontSize: 20 }}>{fa(overallPct)}٪</b><div style={{ fontSize: 10.5, opacity: .85 }}>میانگین کل</div></div>
                        <div style={{ background: 'rgba(0,0,0,.22)', borderRadius: 14, padding: '8px 14px', textAlign: 'center' }}><b style={{ fontSize: 20 }}>{fa(totalAnswered)}</b><div style={{ fontSize: 10.5, opacity: .85 }}>سؤال پاسخ‌داده</div></div>
                    </div>
                </div>
                <Link href={route('student.smart.index')} className="k3-btn ghost" style={{ marginTop: 12, fontSize: 12.5 }}>← بازگشت به آزمون‌ها</Link>
            </div>

            {subjects.length === 0 && <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', opacity: .85 }}>هنوز آزمونی نداده‌ای تا کارنامه‌ات ساخته شود 📭</div>}

            {subjects.map((s, i) => (
                <div key={i} className="k3-card" style={{ marginTop: 14 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                        <span style={{ width: 46, height: 46, borderRadius: 12, display: 'grid', placeItems: 'center', fontWeight: 900, color: '#fff', background: hue(s.pct) }}>{fa(s.pct)}٪</span>
                        <div style={{ flex: 1 }}>
                            <div style={{ fontWeight: 900, fontSize: 16 }}>{s.name}</div>
                            <div style={{ fontSize: 11.5, opacity: .7 }}>{fa(s.total)} سؤال · {s.pct >= 70 ? 'مسلط 🌟' : s.pct >= 50 ? 'در حال پیشرفت 📈' : 'نیاز به تمرین 💪'}</div>
                        </div>
                    </div>
                    {/* سرفصل‌ها */}
                    <div style={{ display: 'grid', gap: 8 }}>
                        {s.topics.map((t, j) => (
                            <div key={j}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, fontWeight: 700, marginBottom: 3 }}>
                                    <span>{t.topic} <span style={{ opacity: .55, fontWeight: 400 }}>({fa(t.total)})</span></span>
                                    <span style={{ color: hue(t.pct) }}>{fa(t.pct)}٪</span>
                                </div>
                                <div style={{ height: 9, borderRadius: 6, background: 'rgba(255,255,255,.12)', overflow: 'hidden' }}>
                                    <i style={{ display: 'block', height: '100%', width: `${Math.max(3, t.pct)}%`, background: hue(t.pct), transition: 'width .5s' }} />
                                </div>
                            </div>
                        ))}
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 10 }}>
                        {s.strengths.length > 0 && <div style={{ flex: 1, minWidth: 140 }}><b style={{ fontSize: 12.5, color: '#7be0b0' }}>💪 قوت: </b><span style={{ fontSize: 12.5 }}>{s.strengths.join('، ')}</span></div>}
                        {s.weaknesses.length > 0 && <div style={{ flex: 1, minWidth: 140 }}><b style={{ fontSize: 12.5, color: '#ffb3b3' }}>🎯 تمرین: </b><span style={{ fontSize: 12.5 }}>{s.weaknesses.join('، ')}</span></div>}
                    </div>
                </div>
            ))}
        </ThemedDash>
    );
}
