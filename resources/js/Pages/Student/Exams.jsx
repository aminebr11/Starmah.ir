import { usePage, Link } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const card = { background: 'rgba(255,255,255,.07)', border: '1px solid rgba(255,255,255,.12)', borderRadius: 18, padding: 16, color: '#fff' };
const T = { exam: '📝 آزمون', quiz: '❓ کوییز', homework: '📚 تکلیف', practice: '🎮 تمرین' };

export default function Exams() {
    const { exams = [] } = usePage().props;
    return (
        <ThemedDash title="آزمون‌های من" active="exams">
            {exams.length === 0 && <div style={card}><span style={{ opacity: .7 }}>فعلاً آزمونی برای تو منتشر نشده.</span></div>}
            <div style={{ display: 'grid', gap: 12 }}>
                {exams.map((e) => (
                    <div key={e.id} style={{ ...card, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 10 }}>
                        <div>
                            <span style={{ fontSize: 11, fontWeight: 700, padding: '4px 10px', borderRadius: 20, background: 'rgba(255,255,255,.12)' }}>{T[e.type] ?? e.type}</span>
                            <b style={{ marginInlineStart: 8 }}>{e.title}</b>
                            <div style={{ opacity: .7, fontSize: 12, marginTop: 4 }}>{fa(e.count)} سؤال</div>
                        </div>
                        {e.done
                            ? <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                                <span style={{ color: '#7be05a', fontWeight: 800 }}>✅ نمره: {fa(e.score)}/{fa(e.max)}</span>
                                <Link href={route('exams.review', e.id)} style={{ background: 'rgba(255,255,255,.15)', color: '#fff', padding: '8px 16px', borderRadius: 12, fontWeight: 800, textDecoration: 'none' }}>📄 پاسخنامه</Link>
                            </div>
                            : <Link href={route('exams.take', e.id)} style={{ background: 'linear-gradient(135deg,var(--p1),var(--p2))', color: '#fff', padding: '10px 20px', borderRadius: 12, fontWeight: 800, textDecoration: 'none' }}>شروع آزمون ▶️</Link>}
                    </div>
                ))}
            </div>
        </ThemedDash>
    );
}
