import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const TONE = { good: '#2bb673', mid: '#f0952e', low: '#e8505b' };

export default function Reports() {
    const { student = {}, overview = {}, skills = [], strengths = [], weaknesses = [], byType = [], exams = [], trend = [], tips = [], printedAt, crossSubject = {} } = usePage().props;

    const maxTrend = Math.max(1, ...trend.map((t) => t.value));
    const maxType = Math.max(1, ...byType.map((t) => Math.abs(t.points)));

    return (
        <ThemedDash title="گزارش‌ها و نمودارها" active="reports">
            {/* سربرگ */}
            <div className="k3-card" style={{ background: 'linear-gradient(135deg,rgba(124,92,240,.35),rgba(45,30,110,.35))' }}>
                <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 12 }}>
                    <span style={{ fontSize: 40 }}>📈</span>
                    <div style={{ flex: 1, minWidth: 180 }}>
                        <div style={{ fontWeight: 900, fontSize: 20 }}>کارنامه‌ی تحلیلی {student.name}</div>
                        <div style={{ opacity: .82, fontSize: 13, marginTop: 2 }}>
                            {student.classroom ? `کلاس ${student.classroom}` : ''}{student.teacher ? ` · معلم: ${student.teacher}` : ''}
                        </div>
                    </div>
                    <button onClick={() => window.print()} className="k3-btn ghost" style={{ fontSize: 13, flex: 'none' }}>🖨️ چاپ / PDF</button>
                </div>
            </div>

            {/* کارت‌های کلیدی */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(140px,1fr))', gap: 12, marginTop: 16 }}>
                <Kpi icon="⚡" label="امتیاز کل" value={fa(overview.xp)} c1="#f5b53f" c2="#d98f0f" />
                <Kpi icon="🎯" label="میانگین تسلط" value={`${fa(overview.avgMastery)}٪`} c1="#2bb673" c2="#1a8a52" />
                <Kpi icon="💻" label="میانگین آزمون‌ها" value={overview.examAvg === null ? '—' : `${fa(overview.examAvg)}٪`} c1="#7c5cf0" c2="#4c2fb0" />
                <Kpi icon="⭐" label="ستاره‌ها" value={fa(overview.stars)} c1="#0ea5b7" c2="#0a7d8a" />
                <Kpi icon="🏅" label="نشان‌ها" value={fa(overview.badges)} c1="#e8862e" c2="#a5570f" />
            </div>

            {/* کارنامه‌ی درس‌به‌درس در همه‌ی بخش‌ها */}
            <CrossSubject data={crossSubject} />

            {/* نقاط قوت و ضعف */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(300px,1fr))', gap: 16, marginTop: 16 }}>
                <div className="k3-card">
                    <ST>💪 نقاط قوت</ST>
                    {strengths.length === 0 && <Empty>هنوز داده‌ای برای تحلیل نداریم.</Empty>}
                    {strengths.map((s, i) => <Bar key={i} name={s.name} pct={s.mastery} color="#2bb673" />)}
                </div>
                <div className="k3-card">
                    <ST>🔧 نیاز به تمرین بیشتر</ST>
                    {weaknesses.length === 0 && <Empty>ضعف مشخصی دیده نمی‌شود — عالی! 🎉</Empty>}
                    {weaknesses.map((s, i) => <Bar key={i} name={s.name} pct={s.mastery} color="#e8505b" />)}
                </div>
            </div>

            {/* روند امتیاز ۶ هفته */}
            <div className="k3-card" style={{ marginTop: 16 }}>
                <ST>📅 روند پیشرفت (۶ هفته‌ی اخیر)</ST>
                <div style={{ display: 'flex', alignItems: 'flex-end', gap: 10, height: 150, padding: '10px 4px 0' }}>
                    {trend.map((t, i) => (
                        <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6, height: '100%', justifyContent: 'flex-end' }}>
                            <b style={{ fontSize: 12, color: 'var(--acc)' }}>{fa(t.value)}</b>
                            <div style={{ width: '100%', maxWidth: 46, height: `${(t.value / maxTrend) * 100}%`, minHeight: 4, borderRadius: '8px 8px 3px 3px', background: 'linear-gradient(180deg,var(--p1),var(--p2))', boxShadow: '0 4px 12px -4px rgba(0,0,0,.5)' }} />
                            <span style={{ fontSize: 10.5, opacity: .75, textAlign: 'center' }}>{t.label}</span>
                        </div>
                    ))}
                </div>
            </div>

            {/* امتیاز بر اساس نوع + نتایج آزمون */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(300px,1fr))', gap: 16, marginTop: 16 }}>
                <div className="k3-card">
                    <ST>🎲 امتیاز بر اساس نوع فعالیت</ST>
                    {byType.length === 0 && <Empty>هنوز فعالیتی ثبت نشده.</Empty>}
                    {byType.map((t, i) => <Bar key={i} name={t.label} pct={Math.round((Math.abs(t.points) / maxType) * 100)} label={fa(t.points)} color={['#3d7bf0', '#a24cf0', '#18a97c', '#f0952e', '#0ea5b7', '#e8505b'][i % 6]} />)}
                </div>
                <div className="k3-card">
                    <ST>💻 نتایج آزمون‌ها</ST>
                    {exams.length === 0 && <Empty>هنوز در آزمونی شرکت نکرده‌ای.</Empty>}
                    <div style={{ display: 'grid', gap: 8 }}>
                        {exams.map((e, i) => (
                            <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 10px', borderRadius: 12, background: 'rgba(255,255,255,.06)' }}>
                                <span style={{ width: 42, height: 42, borderRadius: 10, flex: 'none', display: 'grid', placeItems: 'center', fontWeight: 900, fontSize: 13, color: '#fff', background: e.percent >= 50 ? '#2bb673' : '#e8505b' }}>{fa(e.percent)}٪</span>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontWeight: 700, fontSize: 13.5, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{e.title}</div>
                                    <div style={{ fontSize: 11, opacity: .65 }}>{e.date}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* بخش والدین */}
            <div className="k3-card" style={{ marginTop: 16, background: 'linear-gradient(135deg,rgba(43,182,115,.22),rgba(14,120,80,.15))' }}>
                <ST>👨‍👩‍👧 راهنمای والدین</ST>
                <div style={{ opacity: .85, fontSize: 13, marginBottom: 12, marginTop: -6 }}>این بخش به شما کمک می‌کند فرزندتان را بهتر بشناسید و مسیر پیشرفت او را همراهی کنید.</div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(260px,1fr))', gap: 10 }}>
                    {tips.map((t, i) => (
                        <div key={i} style={{ display: 'flex', gap: 10, padding: 12, borderRadius: 14, background: 'rgba(255,255,255,.08)', borderInlineStart: `4px solid ${TONE[t.tone] || '#888'}` }}>
                            <span style={{ fontSize: 22, flex: 'none' }}>{t.icon}</span>
                            <span style={{ fontSize: 13.5, lineHeight: 2 }}>{t.text}</span>
                        </div>
                    ))}
                </div>
            </div>

            <div style={{ textAlign: 'center', opacity: .6, fontSize: 12, margin: '18px 0 6px' }}>تهیه‌شده در {printedAt} · ستاره ماه</div>
        </ThemedDash>
    );
}

const hue = (p) => p == null ? '#8896ad' : p >= 70 ? '#2bb673' : p >= 50 ? '#f0952e' : '#e8505b';
const SEC_ICON = { smart: '🧠', game: '🎮', mission: '🎯', worksheet: '🎨' };
const SEC_LABEL = { smart: 'آزمون هوشمند', game: 'بازی', mission: 'مأموریت', worksheet: 'کاربرگ' };

/** کارنامه‌ی درس‌به‌درس: عملکردِ هر درس در همه‌ی بخش‌ها (آزمون هوشمند/بازی/مأموریت/کاربرگ). */
function CrossSubject({ data }) {
    const subjects = data?.subjects || [];
    return (
        <div className="k3-card" style={{ marginTop: 16 }}>
            <ST>📚 کارنامه‌ی درس‌به‌درس (در همه‌ی بخش‌ها)</ST>
            {subjects.length === 0 && <Empty>هنوز در هیچ درسی فعالیتِ نمره‌داری ثبت نشده — با آزمون‌ها، بازی‌ها و مأموریت‌ها شروع کن!</Empty>}
            {subjects.length > 0 && (
                <div style={{ display: 'grid', gap: 12 }}>
                    <div style={{ fontSize: 12, opacity: .7 }}>میانگین کلِ درس‌ها: <b style={{ color: hue(data.overall) }}>{fa(data.overall)}٪</b> · مجموعِ فعالیت‌ها: {fa(data.activities)}</div>
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
                                {Object.entries(s.sections).map(([k, v]) => (
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

function Kpi({ icon, label, value, c1, c2 }) {
    return (
        <div style={{ borderRadius: 18, padding: 15, color: '#fff', background: `linear-gradient(135deg,${c1},${c2})`, boxShadow: '0 10px 24px -12px rgba(0,0,0,.5)', textAlign: 'center' }}>
            <div style={{ fontSize: 22 }}>{icon}</div>
            <div style={{ fontSize: 26, fontWeight: 900, lineHeight: 1.3 }}>{value}</div>
            <div style={{ fontSize: 12, opacity: .9 }}>{label}</div>
        </div>
    );
}
function ST({ children }) {
    return <div style={{ fontWeight: 900, fontSize: 16, marginBottom: 14, display: 'flex', alignItems: 'center', gap: 8 }}>{children}</div>;
}
function Empty({ children }) {
    return <div style={{ opacity: .7, fontSize: 13, padding: '6px 0' }}>{children}</div>;
}
function Bar({ name, pct, color, label }) {
    return (
        <div style={{ marginBottom: 11 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, fontWeight: 700, marginBottom: 5 }}>
                <span>{name}</span><span style={{ color }}>{label ?? `${fa(pct)}٪`}</span>
            </div>
            <div style={{ height: 12, borderRadius: 8, background: 'rgba(255,255,255,.12)', overflow: 'hidden' }}>
                <i style={{ display: 'block', height: '100%', width: `${Math.max(3, pct)}%`, background: color, borderRadius: 8, transition: 'width .6s' }} />
            </div>
        </div>
    );
}
