import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const PALETTE = ['#ffd23f', '#5b8def', '#2dd4bf', '#ff6b9d', '#8b7cf6', '#22c55e', '#ff8a4c', '#3ad0ff'];
const EMOJI = { 'ریاضی': '🧮', 'فارسی': '📖', 'علوم': '🔬', 'اجتماعی': '🌍', 'مطالعات اجتماعی': '🌍', 'قرآن': '📗', 'هدیه‌های آسمانی': '🕌', 'هنر': '🎨', 'ورزش': '🏃', 'املا': '✍️', 'انگلیسی': '🔤', 'زبان انگلیسی': '🔤' };
const colorFor = (s) => { let h = 0; for (const c of (s || '')) h = (h * 31 + c.charCodeAt(0)) >>> 0; return PALETTE[h % PALETTE.length]; };
const emojiFor = (s) => EMOJI[s] || '📘';

export default function Schedule() {
    const { days = [], entries = {}, today, jtoday } = usePage().props;

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
        </ThemedDash>
    );
}
