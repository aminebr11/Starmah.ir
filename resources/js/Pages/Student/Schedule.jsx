import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const PALETTE = ['#ffd23f', '#5b8def', '#2dd4bf', '#ff6b9d', '#8b7cf6', '#22c55e', '#ff8a4c', '#3ad0ff'];
const EMOJI = { 'ریاضی': '🧮', 'فارسی': '📖', 'علوم': '🔬', 'اجتماعی': '🌍', 'مطالعات اجتماعی': '🌍', 'قرآن': '📗', 'هدیه‌های آسمانی': '🕌', 'هنر': '🎨', 'ورزش': '🏃', 'املا': '✍️', 'انگلیسی': '🔤', 'زبان انگلیسی': '🔤' };
const colorFor = (s) => { let h = 0; for (const c of (s || '')) h = (h * 31 + c.charCodeAt(0)) >>> 0; return PALETTE[h % PALETTE.length]; };
const emojiFor = (s) => EMOJI[s] || '📘';

export default function Schedule() {
    const { days = [], entries = {}, special = [], today, jtoday } = usePage().props;

    return (
        <ThemedDash title="برنامه کلاسی" active="schedule">
            {/* هدر */}
            <div style={{ textAlign: 'center', marginBottom: 18, background: 'linear-gradient(120deg,var(--p2),rgba(0,0,0,.25))', borderRadius: 20, padding: '22px 16px', color: '#fff', position: 'relative', overflow: 'hidden' }}>
                <span style={{ position: 'absolute', top: -10, insetInlineStart: -6, fontSize: 90, opacity: .15 }}>🗓️</span>
                <div style={{ fontSize: 40 }}>🗓️</div>
                <div style={{ fontWeight: 800, fontSize: 20, marginTop: 4 }}>برنامه‌ی هفتگی من</div>
                <div style={{ opacity: .9, marginTop: 4 }}>امروز: <b style={{ color: 'var(--acc)' }}>{jtoday}</b></div>
            </div>

            <div className="stu-sched">
                {days.map((d, i) => {
                    const list = entries[i] ?? [];
                    const isToday = i === today;
                    return (
                        <div key={i} className="stu-sched-day" style={{ border: isToday ? '2px solid var(--acc)' : '1px solid rgba(255,255,255,.14)', boxShadow: isToday ? '0 0 0 4px rgba(255,255,255,.08)' : 'none' }}>
                            <div className="stu-sched-head" style={{ color: isToday ? 'var(--acc)' : '#fff' }}>
                                {isToday && '⭐ '}{d}{isToday && ' (امروز)'}
                            </div>
                            {list.length === 0 && <div style={{ opacity: .45, fontSize: 12, textAlign: 'center', padding: '14px 0' }}>🎈 برنامه‌ای نیست</div>}
                            {list.map((e) => {
                                if (e.kind === 'recess') {
                                    return <div key={e.id} className="stu-recess">☕ {e.title}{e.time && <span dir="ltr" style={{ display: 'block', opacity: .85, fontSize: 10 }}>{fa(e.time)}</span>}</div>;
                                }
                                const c = colorFor(e.title);
                                return (
                                    <div key={e.id} className="stu-lesson" style={{ background: c }}>
                                        <div style={{ fontSize: 22 }}>{emojiFor(e.title)}</div>
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontWeight: 800, fontSize: 14 }}>{e.title}</div>
                                            {e.time && <div style={{ fontSize: 11, opacity: .9 }}>⏰ <span dir="ltr" style={{ display: 'inline-block' }}>{fa(e.time)}</span></div>}
                                        </div>
                                        {e.period && <div className="stu-lesson-p">زنگ {fa(e.period)}</div>}
                                    </div>
                                );
                            })}
                        </div>
                    );
                })}
            </div>

            {/* برنامه‌های تاریخ‌دار — جدا و پس از هفته‌ی همیشگی */}
            {special.length > 0 && (
                <div style={{ marginTop: 20, background: 'rgba(255,255,255,.06)', border: '1px solid rgba(255,255,255,.14)', borderRadius: 18, padding: 14, color: '#fff' }}>
                    <div style={{ fontWeight: 800, fontSize: 16, marginBottom: 4 }}>📌 برنامه‌های تاریخ‌دارِ پیشِ رو</div>
                    <div style={{ opacity: .75, fontSize: 12, marginBottom: 10 }}>این‌ها فقط در همین تاریخ‌ها برگزار می‌شوند.</div>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 420 }}>
                            <thead>
                                <tr style={{ opacity: .7, fontSize: 12 }}>
                                    <th style={thD}>تاریخ</th>
                                    <th style={thD}>روز</th>
                                    <th style={thD}>ساعت</th>
                                    <th style={thD}>برنامه</th>
                                </tr>
                            </thead>
                            <tbody>
                                {special.map((e) => (
                                    <tr key={e.id} style={{ borderTop: '1px solid rgba(255,255,255,.12)', background: e.is_today ? 'rgba(255,255,255,.10)' : 'transparent' }}>
                                        <td style={tdD}>
                                            <b>{fa(e.jdate)}</b>
                                            {e.is_today && <span style={{ marginInlineStart: 6, fontSize: 10, color: 'var(--acc)' }}>⭐ امروز</span>}
                                        </td>
                                        <td style={tdD}>{e.day}</td>
                                        <td style={tdD}><span dir="ltr" style={{ display: 'inline-block' }}>{fa(e.time || '—')}</span></td>
                                        <td style={tdD}>{e.kind === 'recess' ? `☕ ${e.title}` : `${emojiFor(e.title)} ${e.title}`}{e.period ? ` · زنگ ${fa(e.period)}` : ''}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </ThemedDash>
    );
}

const thD = { textAlign: 'start', padding: '7px 9px', fontWeight: 700 };
const tdD = { padding: '9px 9px', fontSize: 13 };
