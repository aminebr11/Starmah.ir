import { useState } from 'react';
import { usePage, Link, useForm, router } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import QuestionEditor, { Q_TYPES, blankQ } from '@/Components/QuestionEditor';
import SubmissionViewer from '@/Components/SubmissionViewer';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** نمایش/چاپ کاربرگ + انتشار برای کلاس + دیدن کاربرگ‌های پرشده‌ی دانش‌آموزان. */
export default function WorksheetView() {
    const { worksheet, classrooms = [], canEdit, submissions = [], themes = [], gradeOptions = {}, maxXp = 50 } = usePage().props;
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

            {/* کاربرگ‌های پرشده‌ی دانش‌آموزان — بازکردن و تصحیح داخلِ خودِ سایت */}
            {canEdit && <Submissions initial={submissions} grades={gradeOptions} maxXp={maxXp} />}
        </DashLayout>
    );
}

function Submissions({ initial, grades, maxXp }) {
    const [list, setList] = useState(initial);
    // از اعلانِ زنگوله (?sub=…) همان کاربرگ مستقیم باز شود
    const [open, setOpen] = useState(() => {
        const id = typeof window !== 'undefined' ? Number(new URLSearchParams(window.location.search).get('sub')) : 0;
        const k = id ? initial.findIndex((x) => x.id === id) : -1;
        return k >= 0 ? { items: initial, index: k } : null;
    });
    const [filter, setFilter] = useState('all');
    const pending = list.filter((s) => !s.graded).length;
    const shown = filter === 'todo' ? list.filter((s) => !s.graded) : filter === 'done' ? list.filter((s) => s.graded) : list;
    const merge = (arr, sub) => arr.map((x) => (x.id === sub.id ? { ...x, ...sub } : x));
    // نمایشگر فهرستِ ثابتِ لحظه‌ی بازشدن را می‌گیرد (با فیلترِ «منتظرِ تصحیح» موردِ تصحیح‌شده از زیرِ دستش نپرد)
    const saved = (sub) => { setList((l) => merge(l, sub)); setOpen((o) => (o ? { ...o, items: merge(o.items, sub) } : o)); };

    return (
        <div className="panel no-print" style={{ marginTop: 16 }}>
            <h3 style={{ marginTop: 0 }}>📥 کاربرگ‌های ارسالی دانش‌آموزان ({fa(list.length)})</h3>
            {list.length === 0 ? <p style={{ color: 'var(--muted)' }}>هنوز کسی کاربرگِ پرشده نفرستاده است.</p> : (
                <>
                    <p style={{ color: 'var(--muted)', fontSize: 12.5, margin: '0 0 8px' }}>روی هر کاربرگ بزنید: همان‌جا باز می‌شود، روی برگه تیک/ضربدر بزنید و نمره و امتیاز بدهید.</p>
                    <div className="ws-filter">
                        <button type="button" className={filter === 'all' ? 'on' : ''} onClick={() => setFilter('all')}>همه ({fa(list.length)})</button>
                        <button type="button" className={filter === 'todo' ? 'on' : ''} onClick={() => setFilter('todo')}>منتظرِ تصحیح ({fa(pending)})</button>
                        <button type="button" className={filter === 'done' ? 'on' : ''} onClick={() => setFilter('done')}>تصحیح‌شده ({fa(list.length - pending)})</button>
                    </div>
                    <div className="ws-subs">
                        {shown.map((s) => (
                            <button key={s.id} type="button" className="ws-sub" onClick={() => setOpen({ items: shown, index: shown.indexOf(s) })}>
                                <div className="ws-sub-thumb" style={!s.pdf ? { backgroundImage: `url("${s.marked_url || s.url}")` } : undefined}>
                                    {s.pdf && '📄'}
                                    <span className={`ws-sub-badge ${s.graded ? 'done' : ''}`}>{s.graded ? `✅ ${s.grade || 'تصحیح شد'}` : '⏳ تصحیح نشده'}</span>
                                </div>
                                <div className="ws-sub-b">
                                    <b>👤 {s.student}</b>
                                    <span>{s.date}{s.graded && s.xp > 0 ? ` · ⚡ ${fa(s.xp)}` : ''}</span>
                                </div>
                            </button>
                        ))}
                    </div>
                </>
            )}
            {open && (
                <SubmissionViewer items={open.items} index={open.index} canGrade grades={grades} maxXp={maxXp}
                    onSaved={saved} onClose={() => setOpen(null)} />
            )}
        </div>
    );
}
