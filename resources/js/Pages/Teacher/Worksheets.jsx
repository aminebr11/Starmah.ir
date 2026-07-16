import { usePage, Link, router } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const THEME_EMOJI = { stars: '🌙', pitch: '⚽', blocks: '🟩', speed: '🏎️', classic: '📘' };
const SCOPE_FA = { teacher: 'شخصی', school: 'مدرسه', global: 'سراسری' };

/** بانک کاربرگ‌ها — لیستِ کاربرگ‌های ساخته‌شده. */
export default function Worksheets() {
    const { items = [] } = usePage().props;
    const del = (id) => { if (confirm('این کاربرگ حذف شود؟')) router.delete(route('teacher.worksheets.destroy', id), { preserveScroll: true }); };

    return (
        <DashLayout title="بانک کاربرگ‌ها" roleLabel="معلم" menu={teacherMenu} active="assignments"
            actions={<Link href={route('teacher.worksheets.create')} className="btn btn-sm">🎨 کاربرگ جدید</Link>}>

            {items.length === 0 && (
                <div className="panel" style={{ textAlign: 'center', padding: 40 }}>
                    <div style={{ fontSize: 44 }}>🎨</div>
                    <p style={{ color: 'var(--muted)', marginTop: 10 }}>هنوز کاربرگی نساخته‌ای.</p>
                    <Link href={route('teacher.worksheets.create')} className="btn" style={{ marginTop: 8 }}>ساخت اولین کاربرگ</Link>
                </div>
            )}

            <div style={{ display: 'grid', gap: 14, gridTemplateColumns: 'repeat(auto-fill,minmax(240px,1fr))' }}>
                {items.map((w) => (
                    <div key={w.id} className="panel" style={{ padding: 16 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span style={{ fontSize: 26 }}>{THEME_EMOJI[w.theme] || '📄'}</span>
                            <div style={{ flex: 1 }}>
                                <div style={{ fontWeight: 800, color: 'var(--navy-800)' }}>{w.title}</div>
                                <div style={{ fontSize: 12, color: 'var(--muted)' }}>{[w.subject, w.grade].filter(Boolean).join(' · ')}</div>
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                            <span className="tag tag-info">{fa(w.count)} سؤال</span>
                            <span className="tag" style={{ background: '#efe9ff', color: '#4c2fb0' }}>{SCOPE_FA[w.scope] || w.scope}</span>
                            <span className="tag" style={{ background: '#f0f3f8', color: 'var(--muted)' }}>{w.date}</span>
                        </div>
                        <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                            <Link href={route('teacher.worksheets.show', w.id)} className="btn btn-sm" style={{ flex: 1 }}>🖼️ مشاهده / چاپ</Link>
                            {w.can_edit && <button onClick={() => del(w.id)} className="btn btn-ghost btn-sm" style={{ color: '#b0333f' }}>حذف</button>}
                        </div>
                    </div>
                ))}
            </div>
        </DashLayout>
    );
}
