import { usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const hue = (p) => p == null ? '#8896ad' : p >= 70 ? '#2bb673' : p >= 50 ? '#f0952e' : '#e8505b';
const card = { background: 'rgba(255,255,255,.06)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 16, color: '#fff' };

const TABS = [
    { v: 'overview', t: 'خلاصه و تحلیل', ic: '📊' },
    { v: 'cross', t: 'درس‌به‌درس', ic: '📚' },
    { v: 'grades', t: 'نمرات کلاسی', ic: '📔' },
    { v: 'smart', t: 'آزمون هوشمند', ic: '🧠' },
];

/** کارنامه‌ی یکپارچه — چهار بخش در یک صفحه‌ی تب‌دار. */
export default function Report() {
    const { tab: initialTab = 'overview', report = {}, crossSubject = {}, grades = {}, smart = {} } = usePage().props;
    const [tab, setTab] = useState(TABS.some((t) => t.v === initialTab) ? initialTab : 'overview');
    const go = (v) => { setTab(v); window.history.replaceState(null, '', `/report?tab=${v}`); };

    return (
        <ThemedDash title="کارنامه‌ی من" active="report">
            <div className="k3-card" style={{ background: 'linear-gradient(135deg,#6d28d9,#4c1d95)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 38 }}>📊</span>
                    <div style={{ flex: 1, minWidth: 160 }}>
                        <div style={{ fontWeight: 900, fontSize: 19 }}>کارنامه‌ی {report.student?.name || 'من'}</div>
                        <div style={{ opacity: .85, fontSize: 12.5 }}>همه‌ی عملکردِ تو در یک نگاه — خلاصه، درس‌به‌درس، نمرات و آزمون هوشمند</div>
                    </div>
                    <button onClick={() => window.print()} className="k3-btn ghost" style={{ fontSize: 12.5 }}>🖨️ چاپ / PDF</button>
                </div>
            </div>

            {/* تب‌ها */}
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 14 }}>
                {TABS.map((t) => (
                    <button key={t.v} onClick={() => go(t.v)}
                        style={{ cursor: 'pointer', fontFamily: 'inherit', fontWeight: 800, fontSize: 13, borderRadius: 20, padding: '9px 16px', border: 0,
                            background: tab === t.v ? 'linear-gradient(135deg,var(--p1),var(--p2))' : 'rgba(255,255,255,.08)', color: '#fff' }}>
                        {t.ic} {t.t}
                    </button>
                ))}
            </div>

            <div style={{ marginTop: 14 }}>
                {tab === 'overview' && <Overview report={report} />}
                {tab === 'cross' && <CrossSubject data={crossSubject} />}
                {tab === 'grades' && <Grades grades={grades} />}
                {tab === 'smart' && <Smart smart={smart} />}
            </div>
        </ThemedDash>
    );
}

/* ---------- تب خلاصه و تحلیل ---------- */
function Overview({ report }) {
    const o = report.overview || {};
    const trend = report.trend || [];
    const maxTrend = Math.max(1, ...trend.map((t) => t.value));
    const TONE = { good: '#2bb673', mid: '#f0952e', low: '#e8505b' };
    return (
        <>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(120px,1fr))', gap: 12 }}>
                <Kpi ic="⚡" l="امتیاز کل" v={fa(o.xp)} c1="#f5b53f" c2="#d98f0f" />
                <Kpi ic="🎯" l="میانگین تسلط" v={`${fa(o.avgMastery)}٪`} c1="#2bb673" c2="#1a8a52" />
                <Kpi ic="💻" l="میانگین آزمون" v={o.examAvg == null ? '—' : `${fa(o.examAvg)}٪`} c1="#7c5cf0" c2="#4c2fb0" />
                <Kpi ic="⭐" l="ستاره‌ها" v={fa(o.stars)} c1="#0ea5b7" c2="#0a7d8a" />
                <Kpi ic="🏅" l="نشان‌ها" v={fa(o.badges)} c1="#e8862e" c2="#a5570f" />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(300px,1fr))', gap: 14, marginTop: 14 }}>
                <div style={card}>
                    <ST>💪 نقاط قوت</ST>
                    {(report.strengths || []).length === 0 && <Empty>هنوز داده‌ای نداریم.</Empty>}
                    {(report.strengths || []).map((s, i) => <Bar key={i} name={s.name} pct={s.mastery} color="#2bb673" />)}
                </div>
                <div style={card}>
                    <ST>🔧 نیاز به تمرین</ST>
                    {(report.weaknesses || []).length === 0 && <Empty>ضعف مشخصی دیده نمی‌شود 🎉</Empty>}
                    {(report.weaknesses || []).map((s, i) => <Bar key={i} name={s.name} pct={s.mastery} color="#e8505b" />)}
                </div>
            </div>

            {trend.length > 0 && (
                <div style={{ ...card, marginTop: 14 }}>
                    <ST>📅 روند امتیاز (۶ هفته‌ی اخیر)</ST>
                    <div style={{ display: 'flex', alignItems: 'flex-end', gap: 10, height: 140, padding: '10px 4px 0' }}>
                        {trend.map((t, i) => (
                            <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6, height: '100%', justifyContent: 'flex-end' }}>
                                <b style={{ fontSize: 12, color: 'var(--acc)' }}>{fa(t.value)}</b>
                                <div style={{ width: '100%', maxWidth: 44, height: `${(t.value / maxTrend) * 100}%`, minHeight: 4, borderRadius: '8px 8px 3px 3px', background: 'linear-gradient(180deg,var(--p1),var(--p2))' }} />
                                <span style={{ fontSize: 10.5, opacity: .75, textAlign: 'center' }}>{t.label}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {(report.tips || []).length > 0 && (
                <div style={{ ...card, marginTop: 14, background: 'linear-gradient(135deg,rgba(43,182,115,.18),rgba(14,120,80,.12))' }}>
                    <ST>🧭 راهنمای پیشرفت</ST>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(260px,1fr))', gap: 10 }}>
                        {report.tips.map((t, i) => (
                            <div key={i} style={{ display: 'flex', gap: 10, padding: 12, borderRadius: 14, background: 'rgba(255,255,255,.06)', borderInlineStart: `4px solid ${TONE[t.tone] || '#888'}` }}>
                                <span style={{ fontSize: 20, flex: 'none' }}>{t.icon}</span>
                                <span style={{ fontSize: 13, lineHeight: 2 }}>{t.text}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </>
    );
}

/* ---------- تب درس‌به‌درس ---------- */
const SEC_ICON = { smart: '🧠', game: '🎮', mission: '🎯', worksheet: '🎨' };
const SEC_LABEL = { smart: 'آزمون هوشمند', game: 'بازی', mission: 'مأموریت', worksheet: 'کاربرگ' };
function CrossSubject({ data }) {
    const subjects = data?.subjects || [];
    return (
        <div style={card}>
            <ST>📚 عملکردِ درس‌به‌درس در همه‌ی بخش‌ها</ST>
            {subjects.length === 0 && <Empty>هنوز فعالیتِ نمره‌داری ثبت نشده — با آزمون‌ها، بازی‌ها و مأموریت‌ها شروع کن!</Empty>}
            {subjects.length > 0 && (
                <div style={{ display: 'grid', gap: 12 }}>
                    <div style={{ fontSize: 12, opacity: .7 }}>میانگین کل: <b style={{ color: hue(data.overall) }}>{fa(data.overall)}٪</b> · فعالیت‌ها: {fa(data.activities)}</div>
                    {subjects.map((s, i) => (
                        <div key={i} style={{ background: 'rgba(255,255,255,.05)', borderRadius: 14, padding: 12 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 8 }}>
                                <span style={{ width: 42, height: 42, borderRadius: 12, display: 'grid', placeItems: 'center', fontWeight: 900, color: '#fff', background: hue(s.pct), flex: 'none' }}>{s.pct == null ? '—' : `${fa(s.pct)}٪`}</span>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontWeight: 900, fontSize: 15 }}>{s.subject}</div>
                                    <div style={{ fontSize: 11, opacity: .7 }}>{fa(s.activities)} فعالیت · {s.status === 'good' ? 'مسلط 🌟' : s.status === 'mid' ? 'در حال پیشرفت 📈' : s.status === 'low' ? 'نیاز به تمرین 💪' : 'مشارکتی'}</div>
                                </div>
                            </div>
                            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                {Object.entries(s.sections || {}).map(([k, v]) => (
                                    <span key={k} style={{ fontSize: 11.5, background: 'rgba(255,255,255,.08)', borderRadius: 20, padding: '3px 10px' }}>
                                        {SEC_ICON[k]} {SEC_LABEL[k]}{v.pct != null ? `: ${fa(v.pct)}٪` : ''} <span style={{ opacity: .6 }}>({fa(v.count)})</span>
                                    </span>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

/* ---------- تب نمرات کلاسی ---------- */
const RATING = { 'خیلی خوب': ['#1f7a45', '#7be05a'], 'خوب': ['#1b4b8a', '#8fc9ff'], 'قابل قبول': ['#8a5a00', '#ffcf6b'], 'نیاز به تلاش': ['#a04413', '#ffab7a'], 'غایب': ['#8a1f1f', '#ff8d8d'], 'کامل': ['#1f7a45', '#7be05a'], 'ناقص': ['#8a5a00', '#ffcf6b'], 'انجام نداده': ['#8a1f1f', '#ff8d8d'] };
const GICON = { 'غایب': '🚫', 'کامل': '✅', 'ناقص': '⚠️', 'انجام نداده': '❌' };
function Grades({ grades }) {
    const subjects = grades?.subjects || [];
    const stats = grades?.stats || {};
    return (
        <>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 12 }}>
                {[['📔', 'فعالیت‌ها', stats.total, 'var(--acc)'], ['📚', 'درس‌ها', stats.subjects, '#8fc9ff'], ['⭐', 'امتیاز نمرات', stats.xp, '#7be05a']].map(([ic, t, v, c]) => (
                    <div key={t} style={{ ...card, textAlign: 'center' }}>
                        <div style={{ fontSize: 22 }}>{ic}</div>
                        <div style={{ fontSize: 22, fontWeight: 800, color: c }}>{fa(v ?? 0)}</div>
                        <div style={{ opacity: .7, fontSize: 12 }}>{t}</div>
                    </div>
                ))}
            </div>
            {subjects.length === 0 && <div style={{ ...card, textAlign: 'center', marginTop: 12 }}><div style={{ fontSize: 40 }}>📭</div><div style={{ marginTop: 8, opacity: .85 }}>هنوز نمره‌ای ثبت نشده.</div></div>}
            {subjects.map((s) => (
                <div key={s.lesson} style={{ marginTop: 14 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '0 4px 8px', fontWeight: 800, fontSize: 15, color: '#fff' }}>
                        <span style={{ width: 5, height: 18, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))' }} />
                        📚 {s.lesson} <span style={{ opacity: .6, fontSize: 12, fontWeight: 500 }}>({fa(s.count)} نمره)</span>
                    </div>
                    <div style={card}>
                        {s.items.map((g, i) => {
                            const rc = RATING[g.value];
                            const isNum = g.score_type === 'numeric';
                            return (
                                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '11px 0', borderBottom: i === s.items.length - 1 ? 0 : '1px solid rgba(255,255,255,.08)', flexWrap: 'wrap' }}>
                                    <div style={{ flex: 1, minWidth: 150 }}>
                                        <div style={{ fontWeight: 700 }}>{g.title}</div>
                                        {g.topic && <div style={{ opacity: .65, fontSize: 12 }}>{g.topic}</div>}
                                        {g.feedback && <div style={{ opacity: .8, fontSize: 12, marginTop: 3 }}>💬 {g.feedback}</div>}
                                        {g.jdate && <div style={{ opacity: .55, fontSize: 11, marginTop: 2 }}>📅 {g.jdate}</div>}
                                    </div>
                                    <div style={{ textAlign: 'center' }}>
                                        {isNum ? (
                                            <div style={{ fontWeight: 800, fontSize: 19, color: g.value >= g.max * 0.5 ? '#7be05a' : '#ff8d8d' }}>{g.value != null ? fa(g.value) : '—'}<span style={{ fontSize: 12, opacity: .6 }}> / {fa(g.max)}</span></div>
                                        ) : (
                                            <span style={{ background: rc ? rc[0] : 'rgba(255,255,255,.1)', color: rc ? rc[1] : '#fff', borderRadius: 20, padding: '6px 14px', fontWeight: 800, fontSize: 13 }}>{GICON[g.value] || ''} {g.value || '—'}</span>
                                        )}
                                        {g.xp !== 0 && <div style={{ fontSize: 11, marginTop: 4, color: g.xp > 0 ? '#7be05a' : '#ff8d8d', fontWeight: 700 }}>{g.xp > 0 ? '+' : ''}{fa(g.xp)} XP</div>}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            ))}
        </>
    );
}

/* ---------- تب آزمون هوشمند ---------- */
function Smart({ smart }) {
    const subjects = smart?.subjects || [];
    const trend = smart?.trend || [];
    return (
        <>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 12 }}>
                <div style={{ ...card, textAlign: 'center' }}><div style={{ fontSize: 22, fontWeight: 900 }}>{fa(smart?.overallPct || 0)}٪</div><div style={{ opacity: .7, fontSize: 12 }}>میانگین کل</div></div>
                <div style={{ ...card, textAlign: 'center' }}><div style={{ fontSize: 22, fontWeight: 900 }}>{fa(smart?.examCount || 0)}</div><div style={{ opacity: .7, fontSize: 12 }}>آزمون</div></div>
                <div style={{ ...card, textAlign: 'center' }}><div style={{ fontSize: 22, fontWeight: 900 }}>{fa(smart?.totalAnswered || 0)}</div><div style={{ opacity: .7, fontSize: 12 }}>سؤال پاسخ‌داده</div></div>
            </div>

            {subjects.length === 0 && <div style={{ ...card, textAlign: 'center', marginTop: 12, opacity: .85 }}>هنوز آزمون هوشمندی نداده‌ای 📭</div>}

            {trend.length > 0 && (
                <div style={{ ...card, marginTop: 14 }}>
                    <ST>📈 روند تو در آزمون‌ها</ST>
                    {trend.map((t, i) => (
                        <div key={i} style={{ marginBottom: 12 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, fontWeight: 800, marginBottom: 6 }}><span>{t.subject}</span><span style={{ color: hue(t.avg) }}>میانگین {fa(t.avg)}٪</span></div>
                            <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end', overflowX: 'auto', paddingBottom: 4 }}>
                                {t.exams.map((e, j) => (
                                    <div key={j} style={{ flex: 'none', textAlign: 'center', width: 44 }} title={`${e.exam} · ${e.date}`}>
                                        <div style={{ height: 66, display: 'flex', alignItems: 'flex-end', justifyContent: 'center' }}><div style={{ width: 20, height: `${Math.max(8, e.percent)}%`, borderRadius: 6, background: hue(e.percent) }} /></div>
                                        <div style={{ fontSize: 10.5, fontWeight: 800, marginTop: 3, color: hue(e.percent) }}>{fa(e.percent)}٪</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {subjects.map((s, i) => (
                <div key={i} style={{ ...card, marginTop: 14 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                        <span style={{ width: 46, height: 46, borderRadius: 12, display: 'grid', placeItems: 'center', fontWeight: 900, color: '#fff', background: hue(s.pct) }}>{fa(s.pct)}٪</span>
                        <div style={{ flex: 1 }}><div style={{ fontWeight: 900, fontSize: 16 }}>{s.name}</div><div style={{ fontSize: 11.5, opacity: .7 }}>{fa(s.total)} سؤال</div></div>
                    </div>
                    <div style={{ display: 'grid', gap: 8 }}>
                        {s.topics.map((t, j) => (
                            <div key={j}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, fontWeight: 700, marginBottom: 3 }}><span>{t.topic} <span style={{ opacity: .55, fontWeight: 400 }}>({fa(t.total)})</span></span><span style={{ color: hue(t.pct) }}>{fa(t.pct)}٪</span></div>
                                <div style={{ height: 9, borderRadius: 6, background: 'rgba(255,255,255,.12)', overflow: 'hidden' }}><i style={{ display: 'block', height: '100%', width: `${Math.max(3, t.pct)}%`, background: hue(t.pct) }} /></div>
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </>
    );
}

/* ---------- کمکی ---------- */
function Kpi({ ic, l, v, c1, c2 }) {
    return <div style={{ borderRadius: 16, padding: 14, color: '#fff', background: `linear-gradient(135deg,${c1},${c2})`, textAlign: 'center' }}><div style={{ fontSize: 20 }}>{ic}</div><div style={{ fontSize: 22, fontWeight: 900 }}>{v}</div><div style={{ fontSize: 11.5, opacity: .9 }}>{l}</div></div>;
}
function ST({ children }) { return <div style={{ fontWeight: 900, fontSize: 15, marginBottom: 12 }}>{children}</div>; }
function Empty({ children }) { return <div style={{ opacity: .7, fontSize: 13, padding: '6px 0' }}>{children}</div>; }
function Bar({ name, pct, color, label }) {
    return (
        <div style={{ marginBottom: 10 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, fontWeight: 700, marginBottom: 5 }}><span>{name}</span><span style={{ color }}>{label ?? `${fa(pct)}٪`}</span></div>
            <div style={{ height: 11, borderRadius: 8, background: 'rgba(255,255,255,.12)', overflow: 'hidden' }}><i style={{ display: 'block', height: '100%', width: `${Math.max(3, pct)}%`, background: color, borderRadius: 8 }} /></div>
        </div>
    );
}
