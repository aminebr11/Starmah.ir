import { usePage, Link, useForm } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** نمایش/چاپ کاربرگ + انتشار برای کلاس + دیدن کاربرگ‌های پرشده‌ی دانش‌آموزان. */
export default function WorksheetView() {
    const { worksheet, classrooms = [], canEdit, submissions = [] } = usePage().props;
    const print = () => window.print();
    const pub = useForm({ classroom_id: worksheet.classroom_id || (classrooms[0]?.id ?? '') });
    const publish = () => pub.post(route('teacher.worksheets.publish', worksheet.id), { preserveScroll: true });

    return (
        <DashLayout title={worksheet.title} roleLabel="معلم" menu={teacherMenu} active="assignments"
            actions={<>
                <button onClick={print} className="btn btn-sm">🖨️ چاپ / ذخیره PDF</button>
                <Link href={route('teacher.worksheets')} className="btn btn-ghost btn-sm">← بانک کاربرگ‌ها</Link>
            </>}>

            <style>{`@media print { .dash-topbar, .dash-side, .dash-actions, .no-print { display:none !important; } .dash-main { padding:0 !important; } .ws-sheet { box-shadow:none !important; } }`}</style>

            {/* انتشار */}
            {canEdit && (
                <div className="panel no-print" style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <b>{worksheet.published ? '✅ این کاربرگ منتشر شده است' : '📤 انتشار برای کلاس'}</b>
                    <select className="input" style={{ width: 'auto' }} value={pub.data.classroom_id} onChange={(e) => pub.setData('classroom_id', e.target.value)}>
                        <option value="">— انتخاب کلاس —</option>
                        {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                    <button onClick={publish} disabled={!pub.data.classroom_id || pub.processing} className="btn btn-sm">{worksheet.published ? 'انتشار دوباره / تغییر کلاس' : 'انتشار و اعلان به دانش‌آموزان'}</button>
                </div>
            )}

            {/* تصویرِ کاربرگ سرلوحه است، نه جایگزینِ سؤال‌ها.
                پیش از این اگر تصویری وجود داشت، کلِ سؤال‌ها نمایش داده نمی‌شد. */}
            {worksheet.image && (
                <div className="ws-sheet" style={{ marginTop: 8, textAlign: 'center' }}>
                    <img src={worksheet.image} alt={worksheet.title} style={{ maxWidth: '100%', borderRadius: 16 }} />
                </div>
            )}
            {worksheet.html && <div className="ws-sheet" style={{ marginTop: 12 }} dangerouslySetInnerHTML={{ __html: worksheet.html }} />}
            {!worksheet.html && worksheet.file && (
                <div className="panel" style={{ marginTop: 12, textAlign: 'center' }}>
                    <div style={{ fontWeight: 800, marginBottom: 8 }}>📄 فایلِ کاربرگ</div>
                    <a href={worksheet.file} target="_blank" rel="noreferrer" className="btn">⬇️ بازکردن / دانلودِ فایل</a>
                </div>
            )}

            {/* کاربرگ‌های پرشده‌ی دانش‌آموزان */}
            {canEdit && (
                <div className="panel no-print" style={{ marginTop: 16 }}>
                    <h3 style={{ marginTop: 0 }}>📥 کاربرگ‌های ارسالی دانش‌آموزان ({fa(submissions.length)})</h3>
                    {submissions.length === 0 ? <p style={{ color: 'var(--muted)' }}>هنوز کسی کاربرگِ پرشده نفرستاده است.</p> : (
                        <div style={{ display: 'grid', gap: 10, gridTemplateColumns: 'repeat(auto-fill,minmax(200px,1fr))' }}>
                            {submissions.map((s) => (
                                <a key={s.id} href={s.url} target="_blank" rel="noreferrer" style={{ border: '1px solid var(--line)', borderRadius: 12, padding: 12, textDecoration: 'none', color: 'var(--ink)' }}>
                                    <div style={{ fontWeight: 800 }}>👤 {s.student}</div>
                                    <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 2 }}>{s.date}</div>
                                    {s.note && <div style={{ fontSize: 12.5, marginTop: 4 }}>{s.note}</div>}
                                    <div style={{ marginTop: 8, fontWeight: 800, fontSize: 13, color: '#2555c0' }}>⬇️ مشاهده فایل</div>
                                </a>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </DashLayout>
    );
}
