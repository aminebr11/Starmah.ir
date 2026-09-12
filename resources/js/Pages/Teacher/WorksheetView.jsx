import { useState } from 'react';
import { usePage, Link, useForm, router } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import QuestionEditor, { Q_TYPES, blankQ } from '@/Components/QuestionEditor';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** نمایش/چاپ کاربرگ + انتشار برای کلاس + دیدن کاربرگ‌های پرشده‌ی دانش‌آموزان. */
export default function WorksheetView() {
    const { worksheet, classrooms = [], canEdit, submissions = [], themes = [] } = usePage().props;
    const print = () => window.print();
    const pub = useForm({ classroom_id: worksheet.classroom_id || (classrooms[0]?.id ?? '') });
    const publish = () => pub.post(route('teacher.worksheets.publish', worksheet.id), { preserveScroll: true });
    const unpublish = () => router.post(route('teacher.worksheets.unpublish', worksheet.id), {}, { preserveScroll: true });
    const del = () => {
        if (confirm('این کاربرگ برای همیشه از بانک و بایگانی حذف شود؟')) {
            router.delete(route('teacher.worksheets.destroy', worksheet.id));
        }
    };

    // ── ویرایشِ درجا ──────────────────────────────────────────────────
    // با ?edit=1 (از بانک/بایگانی) مستقیم در حالتِ ویرایش باز می‌شود
    const [editing, setEditing] = useState(
        typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('edit') === '1'
    );
    const [newType, setNewType] = useState('mc');
    const form = useForm({
        title: worksheet.title || '', subject: worksheet.subject || '',
        grade: worksheet.grade || '', lesson_no: worksheet.lesson_no || '',
        theme: worksheet.theme || (themes[0]?.key ?? 'classic'),
        questions: worksheet.items || [],
    });
    const saveEdit = () => form.post(route('teacher.worksheets.update', worksheet.id), {
        preserveScroll: true, onSuccess: () => setEditing(false),
    });

    return (
        <DashLayout title={worksheet.title} roleLabel="معلم" menu={teacherMenu} active="assignments"
            actions={<>
                {canEdit && worksheet.mode !== 'upload' && (
                    <button onClick={() => setEditing((v) => !v)} className="btn btn-sm">{editing ? '✕ بستنِ ویرایش' : '✏️ ویرایش کاربرگ'}</button>
                )}
                <button onClick={print} className="btn btn-sm">🖨️ چاپ / ذخیره PDF</button>
                {canEdit && <button onClick={del} className="btn btn-ghost btn-sm" style={{ color: '#b0333f' }}>🗑️ حذف</button>}
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
                    {worksheet.published && <button onClick={unpublish} className="btn btn-ghost btn-sm">🙈 پنهان‌کردن از دانش‌آموزان</button>}
                </div>
            )}

            {/* ویرایشِ کاربرگ و سؤال‌هایش — همان‌جا، بدونِ ساختِ دوباره */}
            {canEdit && editing && (
                <div className="panel no-print" style={{ marginTop: 12 }}>
                    <h3 style={{ marginTop: 0 }}>✏️ ویرایش کاربرگ</h3>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: 10 }}>
                        <label style={{ fontSize: 12.5 }}>عنوان
                            <input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        </label>
                        <label style={{ fontSize: 12.5 }}>درس
                            <input className="input" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
                        </label>
                        <label style={{ fontSize: 12.5 }}>پایه
                            <input className="input" value={form.data.grade} onChange={(e) => form.setData('grade', e.target.value)} />
                        </label>
                        <label style={{ fontSize: 12.5 }}>شماره درس
                            <input className="input" value={form.data.lesson_no} onChange={(e) => form.setData('lesson_no', e.target.value)} />
                        </label>
                        {themes.length > 0 && (
                            <label style={{ fontSize: 12.5 }}>تم
                                <select className="input" value={form.data.theme} onChange={(e) => form.setData('theme', e.target.value)}>
                                    {themes.map((t) => <option key={t.key} value={t.key}>{t.label}</option>)}
                                </select>
                            </label>
                        )}
                    </div>

                    <h4 style={{ marginBottom: 6 }}>سؤال‌ها ({fa(form.data.questions.length)})</h4>
                    <QuestionEditor questions={form.data.questions} onChange={(qs) => form.setData('questions', qs)} maxHeight={420} />

                    <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginTop: 10, flexWrap: 'wrap' }}>
                        <select className="input" value={newType} onChange={(e) => setNewType(e.target.value)} style={{ width: 'auto' }}>
                            {Q_TYPES.map((t) => <option key={t.v} value={t.v}>{t.t}</option>)}
                        </select>
                        <button onClick={() => form.setData('questions', [...form.data.questions, blankQ(newType)])} className="btn btn-ghost btn-sm">➕ افزودن سؤال</button>
                        <span style={{ flex: 1 }} />
                        <button onClick={saveEdit} disabled={form.processing} className="btn btn-sm">{form.processing ? 'در حال ذخیره…' : '💾 ذخیره‌ی تغییرات'}</button>
                    </div>
                    {form.errors.questions && <div style={{ marginTop: 8, fontSize: 12.5, color: '#b0333f' }}>{form.errors.questions}</div>}
                    {form.errors.title && <div style={{ marginTop: 8, fontSize: 12.5, color: '#b0333f' }}>{form.errors.title}</div>}
                </div>
            )}

            {/* یک برگه‌ی یکپارچه: تصویر سرلوحه‌ی خودِ کاربرگ است و سؤال‌ها داخلش */}
            {worksheet.html && <div className="ws-sheet" style={{ marginTop: 12 }} dangerouslySetInnerHTML={{ __html: worksheet.html }} />}
            {!worksheet.html && worksheet.image && (
                <div className="ws-sheet" style={{ marginTop: 12, textAlign: 'center' }}>
                    <img src={worksheet.image} alt={worksheet.title} style={{ maxWidth: '100%', borderRadius: 16 }} />
                </div>
            )}
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
