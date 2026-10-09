import { usePage, Link } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const STATUS = { todo: ['✏️ انجامش بده', '#ffd23f', '#2b1d00'], sent: ['⏳ منتظرِ تصحیح', 'rgba(255,255,255,.18)', '#fff'], graded: ['✅ تصحیح شد', '#2bb673', '#fff'] };

/** دانش‌آموز: «🎧 املا و روخوانی» — فهرستِ کارهای صوتیِ کلاس. */
export default function AudioTasks() {
    const { tasks = [] } = usePage().props;
    const todo = tasks.filter((t) => t.status === 'todo').length;

    return (
        <ThemedDash title="املا و روخوانی" active="listen">
            <div className="k3-card lt-hero">
                <div className="lt-hero-ic">🎧</div>
                <div>
                    <div style={{ fontWeight: 900, fontSize: 20 }}>املا و روخوانیِ صوتی</div>
                    <div style={{ opacity: .9, fontSize: 13.5, lineHeight: 1.9 }}>
                        {todo ? <>{fa(todo)} کارِ تازه داری! هدفون یا صدای گوشی را آماده کن 🎶</> : 'همه را انجام دادی! 🌟'}
                    </div>
                </div>
            </div>
            {tasks.length === 0 ? (
                <div className="k3-card" style={{ marginTop: 14, textAlign: 'center' }}>هنوز معلمت املا یا روخوانی‌ای نفرستاده است.</div>
            ) : (
                <div className="lt-list">
                    {tasks.map((t) => {
                        const [label, bg, color] = STATUS[t.status];
                        return (
                            <Link key={t.id} href={route('listen.show', t.id)} className={`k3-card lt-card ${t.kind}`}>
                                <div className="lt-card-ic">{t.kind === 'dictation' ? '📝' : '🎙️'}</div>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontWeight: 900, fontSize: 16 }}>{t.title}</div>
                                    <div style={{ fontSize: 12.5, opacity: .8 }}>{t.kind === 'dictation' ? 'املای صوتی' : 'روخوانی'} · {t.date}{t.due ? ` · ⏰ ${t.due}` : ''}</div>
                                    {t.status === 'graded' && <div style={{ fontWeight: 800, marginTop: 4 }}>نمره: {t.grade || (t.score != null ? `${fa(t.score)} از ۲۰` : '—')}</div>}
                                </div>
                                <span className="lt-status" style={{ background: t.overdue ? '#e8505b' : bg, color: t.overdue ? '#fff' : color }}>{t.overdue ? '⏰ دیر شده' : label}</span>
                            </Link>
                        );
                    })}
                </div>
            )}
        </ThemedDash>
    );
}
