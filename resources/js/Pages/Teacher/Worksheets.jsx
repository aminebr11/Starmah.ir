import { useState, useEffect } from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const THEME_EMOJI = { stars: '🌙', pitch: '⚽', blocks: '🟩', speed: '🏎️', classic: '📘' };

/** بانک کاربرگ‌ها — دقیقاً مثل بانک سؤالات: دسته‌بندی مقطع→کلاس→درس→شماره درس. */
export default function Worksheets() {
    const { grouped = [], flash } = usePage().props;
    const total = grouped.reduce((n, l) => n + l.count, 0);
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    return (
        <DashLayout title="بانک کاربرگ‌ها" roleLabel="معلم" menu={teacherMenu} active="assignments"
            actions={<Link href={route('teacher.worksheets.create')} className="btn btn-sm">🎨 کاربرگ جدید</Link>}>

            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8', lineHeight: 1.9 }}><b>{banner}</b></div>}

            {total === 0 ? (
                <div className="panel" style={{ textAlign: 'center', padding: 40 }}>
                    <div style={{ fontSize: 44 }}>🎨</div>
                    <p style={{ color: 'var(--muted)', marginTop: 10 }}>هنوز کاربرگی نساخته‌ای.</p>
                    <Link href={route('teacher.worksheets.create')} className="btn" style={{ marginTop: 8 }}>ساخت اولین کاربرگ</Link>
                </div>
            ) : (
                <div className="panel">
                    <h3 style={{ marginTop: 0 }}>🎨 بانک کاربرگ‌ها <span className="tag tag-info">{fa(total)} کاربرگ</span></h3>
                    {grouped.map((lv) => <LevelGroup key={lv.level} lv={lv} />)}
                </div>
            )}
        </DashLayout>
    );
}

function LevelGroup({ lv }) {
    const [open, setOpen] = useState(true);
    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 14, marginBottom: 12, overflow: 'hidden' }}>
            <button onClick={() => setOpen(!open)} style={{ width: '100%', textAlign: 'start', border: 0, cursor: 'pointer', background: 'linear-gradient(135deg,#16264f,#0a1836)', color: '#fff', padding: '11px 14px', fontWeight: 800, fontSize: 15, display: 'flex', alignItems: 'center', gap: 8 }}>
                <span>{open ? '▾' : '▸'}</span> 🎓 مقطع: {lv.level}
                <span className="tag" style={{ background: 'rgba(255,255,255,.2)', color: '#fff', marginInlineStart: 'auto' }}>{fa(lv.count)}</span>
            </button>
            {open && (
                <div style={{ padding: 12 }}>
                    {lv.grades.map((g) => (
                        <div key={g.grade} style={{ marginBottom: 10 }}>
                            <div style={{ fontWeight: 800, color: 'var(--navy-800)', fontSize: 14, margin: '4px 0 6px', borderInlineStart: '4px solid var(--gold)', paddingInlineStart: 8 }}>📘 کلاس {g.grade}</div>
                            {g.subjects.map((s) => (
                                <div key={s.subject} style={{ marginInlineStart: 10, marginBottom: 8 }}>
                                    <div style={{ fontWeight: 700, color: '#2555c0', fontSize: 13, marginBottom: 6 }}>📖 {s.subject}</div>
                                    {s.lessons.map((l) => (
                                        <div key={l.lesson_no} style={{ marginInlineStart: 12, marginBottom: 8 }}>
                                            <div style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--muted)', marginBottom: 6 }}>📑 {l.lesson_no === '—' ? 'بدون شماره درس' : `درس ${l.lesson_no}`}</div>
                                            <div style={{ display: 'grid', gap: 10, gridTemplateColumns: 'repeat(auto-fill,minmax(230px,1fr))' }}>
                                                {l.items.map((w) => <WCard key={w.id} w={w} />)}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ))}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function WCard({ w }) {
    const del = () => { if (confirm('این کاربرگ حذف شود؟')) router.delete(route('teacher.worksheets.destroy', w.id), { preserveScroll: true }); };
    return (
        <div style={{ border: '1px solid var(--line)', borderRadius: 12, padding: 12 }}>
            {w.image && (
                <Link href={route('teacher.worksheets.show', w.id)} style={{ display: 'block', marginBottom: 8 }}>
                    <img src={w.image} alt={w.title} loading="lazy"
                        style={{ width: '100%', aspectRatio: '1024/560', objectFit: 'cover', borderRadius: 12, border: '1px solid var(--line)' }} />
                </Link>
            )}
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span style={{ fontSize: 22 }}>{THEME_EMOJI[w.theme] || '📄'}</span>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 800, color: 'var(--navy-800)' }}>{w.title}</div>
                    <div style={{ fontSize: 11.5, color: 'var(--muted)' }}>{w.date}</div>
                </div>
            </div>
            <div style={{ display: 'flex', gap: 6, marginTop: 8, flexWrap: 'wrap' }}>
                <span className="tag tag-info">{fa(w.count)} سؤال</span>
                {w.published
                    ? <span className="tag tag-ok">منتشرشده · {fa(w.submissions)} ارسال</span>
                    : <span className="tag" style={{ background: '#f0f3f8', color: 'var(--muted)' }}>منتشرنشده</span>}
                {w.has_image && <span className="tag" style={{ background: '#ede9fe', color: '#6d28d9' }}>🎨 تصویردار</span>}
            </div>
            <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
                <Link href={route('teacher.worksheets.show', w.id)} className="btn btn-sm" style={{ flex: 1 }}>🖼️ مشاهده / انتشار</Link>
                {w.can_edit && <button onClick={del} className="btn btn-ghost btn-sm" style={{ color: '#b0333f' }}>حذف</button>}
            </div>
        </div>
    );
}
