import { Link, usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const KIND = { diagnostic: 'تشخیصی', practice: 'تمرینی', class: 'کلاسی', formal: 'رسمی', remedial: 'جبرانی', game: 'بازی‌محور' };
const ST = {
    new: { t: 'جدید', c: '#2bb673', btn: '🚀 شروع آزمون' },
    in_progress: { t: 'نیمه‌تمام', c: '#e8862e', btn: '▶️ ادامه آزمون' },
    done: { t: 'انجام‌شده', c: '#3d7bf0', btn: '🔁 تلاش دوباره' },
    scheduled: { t: 'زمان‌بندی‌شده', c: '#7c5cf0', btn: '' },
    locked: { t: 'قفل', c: '#8896ad', btn: '' },
    expired: { t: 'زمان‌گذشته', c: '#e8505b', btn: '' },
};
const PAL = [['#6d28d9', '#4c1d95'], ['#0ea5b7', '#0a7d8a'], ['#e8862e', '#c06712'], ['#2bb673', '#1a8a52']];

export default function SmartExams() {
    const { exams = [] } = usePage().props;
    return (
        <ThemedDash title="آزمون هوشمند" active="smart">
            <div className="k3-card" style={{ background: 'linear-gradient(135deg,#6d28d9,#4c1d95)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 40 }}>🧠</span>
                    <div style={{ flex: 1, minWidth: 150 }}><div style={{ fontWeight: 900, fontSize: 19 }}>آزمون‌های هوشمند من</div>
                        <div style={{ opacity: .85, fontSize: 13 }}>آزمونِ آزمایشی با تحلیلِ نقاط قوت و ضعف</div></div>
                    <Link href={route('student.smart.performance')} className="k3-btn" style={{ fontSize: 13 }}>📊 کارنامه‌ی هوشمند من</Link>
                </div>
            </div>

            {exams.length === 0 && <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', opacity: .85 }}>فعلاً آزمون هوشمندی برایت منتشر نشده 📭</div>}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(240px,1fr))', gap: 14, marginTop: 14 }}>
                {exams.map((e, i) => {
                    const s = ST[e.status] || ST.new;
                    const pal = PAL[i % PAL.length];
                    const playable = e.status === 'new' || e.status === 'in_progress' || (e.status === 'done' && e.attemptsLeft > 0);
                    return (
                        <div key={e.id} className="k3-card" style={{ opacity: playable ? 1 : .9 }}>
                            <div style={{ height: 70, borderRadius: 14, marginBottom: 10, display: 'grid', placeItems: 'center', fontSize: 34, background: `linear-gradient(135deg,${pal[0]},${pal[1]})`, position: 'relative' }}>
                                🧪<span style={{ position: 'absolute', top: 8, insetInlineEnd: 8, background: s.c, color: '#fff', borderRadius: 20, padding: '2px 9px', fontSize: 11, fontWeight: 800 }}>{s.t}</span>
                            </div>
                            <div style={{ fontWeight: 900, fontSize: 15 }}>{e.title}</div>
                            <div style={{ fontSize: 11.5, opacity: .78, marginTop: 3 }}>{KIND[e.kind]}{e.subject ? ` · ${e.subject}` : ''}{e.topic ? ` · ${e.topic}` : ''}</div>
                            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 8, fontSize: 11 }}>
                                <span style={{ background: 'rgba(255,255,255,.12)', borderRadius: 20, padding: '3px 9px' }}>❓ {fa(e.count)}</span>
                                {e.duration && <span style={{ background: 'rgba(255,255,255,.12)', borderRadius: 20, padding: '3px 9px' }}>⏱️ {fa(e.duration)}د</span>}
                                <span style={{ background: 'rgba(255,255,255,.12)', borderRadius: 20, padding: '3px 9px' }}>🎟️ {fa(e.attemptsLeft)} تلاش</span>
                                {e.adaptive && <span style={{ background: 'rgba(255,255,255,.12)', borderRadius: 20, padding: '3px 9px' }}>🧬 تطبیقی</span>}
                            </div>
                            {e.lastScore != null && <div style={{ fontSize: 11.5, marginTop: 6, color: '#c4b5fd' }}>آخرین نتیجه: {fa(e.lastScore)}/{fa(e.lastMax)}</div>}
                            {e.opens && <div style={{ fontSize: 10.5, color: '#c4b5fd', marginTop: 4 }}>🗓️ باز می‌شود: {e.opens}</div>}
                            {e.closes && <div style={{ fontSize: 10.5, opacity: .6, marginTop: 4 }}>مهلت: {e.closes}</div>}
                            {playable
                                ? <Link href={route('student.smart.take', e.id)} className="k3-btn" style={{ display: 'block', marginTop: 10, fontSize: 13, textAlign: 'center', textDecoration: 'none' }}>{s.btn}</Link>
                                : !e.lastAttemptId && <div style={{ marginTop: 10, textAlign: 'center', fontWeight: 800, fontSize: 12, opacity: .8, background: 'rgba(0,0,0,.25)', borderRadius: 12, padding: '8px' }}>{e.status === 'expired' ? '⌛ زمان‌گذشته' : e.status === 'scheduled' ? '🗓️ هنوز باز نشده' : '🔒 در دسترس نیست'}</div>}
                            {/* پاسخنامه/نتیجه — همیشه وقتی آزمون را داده باشد */}
                            {e.lastAttemptId && (
                                <Link href={route('student.smart.result', [e.id, e.lastAttemptId])}
                                    style={{ display: 'block', marginTop: 8, textAlign: 'center', fontWeight: 800, fontSize: 12.5, background: 'rgba(255,255,255,.16)', color: '#fff', borderRadius: 12, padding: '8px', textDecoration: 'none' }}>📄 مشاهده نتیجه و پاسخنامه</Link>
                            )}
                        </div>
                    );
                })}
            </div>
        </ThemedDash>
    );
}
