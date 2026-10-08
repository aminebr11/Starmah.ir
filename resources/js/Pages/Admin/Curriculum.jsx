import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Curriculum() {
    const { levels = {}, books = [], chapters = {}, flash } = usePage().props;
    const [openBook, setOpenBook] = useState(null);
    const levelNames = Object.keys(levels);
    const [level, setLevel] = useState(levelNames[0] || '');
    const [grade, setGrade] = useState((levels[levelNames[0]] || [])[0] || '');
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const grades = levels[level] || [];
    const list = books.filter((b) => b.level === level && b.grade === grade);

    const form = useForm({ level, grade, name: '', icon: '📘' });
    const add = (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, level, grade }));
        form.post(route('admin.curriculum.store'), { preserveScroll: true, onSuccess: () => form.setData('name', '') });
    };
    const remove = (id) => { if (confirm('این درس حذف شود؟')) router.delete(route('admin.curriculum.destroy', id), { preserveScroll: true }); };

    const pickLevel = (l) => { setLevel(l); setGrade((levels[l] || [])[0] || ''); setOpenBook(null); };
    const book = list.find((b) => b.id === openBook);

    return (
        <DashLayout title="دروس و کتاب‌ها" roleLabel="ادمین کل" menu={adminMenu} active="curriculum">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div className="panel">
                <h3>🎚️ انتخاب مقطع و پایه</h3>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 12 }}>
                    {levelNames.map((l) => (
                        <button key={l} onClick={() => pickLevel(l)} className={`tag ${level === l ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 16px', fontSize: 14 }}>{l}</button>
                    ))}
                </div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {grades.map((g) => (
                        <button key={g} onClick={() => { setGrade(g); setOpenBook(null); }} className={`tag ${grade === g ? 'tag-ok' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '8px 14px' }}>پایه‌ی {g}</button>
                    ))}
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.6fr', gap: 20, alignItems: 'start' }} className="themes-grid">
                <form onSubmit={add} className="panel">
                    <h3>➕ افزودن درس به پایه‌ی {grade}</h3>
                    <div className="field"><label>آیکون</label>
                        <input className="input" value={form.data.icon} onChange={(e) => form.setData('icon', e.target.value)} placeholder="📘" />
                    </div>
                    <div className="field"><label>نام درس / کتاب</label>
                        <input className="input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="مثلاً ریاضی" />
                        {form.errors.name && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{form.errors.name}</div>}
                    </div>
                    <button type="submit" disabled={form.processing} className="btn" style={{ width: '100%' }}>➕ افزودن</button>
                </form>

                <div className="panel">
                    <h3>📚 دروس پایه‌ی {grade} ({fa(list.length)})</h3>
                    {list.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز درسی برای این پایه ثبت نشده.</p>}
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(160px,1fr))', gap: 12 }}>
                        {list.map((b) => (
                            <div key={b.id} onClick={() => setOpenBook(openBook === b.id ? null : b.id)}
                                style={{ border: openBook === b.id ? '2px solid var(--gold)' : '1px solid var(--line)', background: openBook === b.id ? '#fff8e8' : '#fff', borderRadius: 14, padding: 14, display: 'flex', alignItems: 'center', gap: 10, position: 'relative', cursor: 'pointer' }}>
                                <span style={{ fontSize: 26 }}>{b.icon || '📘'}</span>
                                <span style={{ fontWeight: 700 }}>{b.name}
                                    <span style={{ display: 'block', fontSize: 11.5, fontWeight: 500, color: 'var(--muted)' }}>{fa((chapters[`${b.grade}|${b.name}`] || []).length)} فصل</span>
                                </span>
                                <button onClick={(e) => { e.stopPropagation(); remove(b.id); }} title="حذف" style={{ position: 'absolute', top: 6, insetInlineEnd: 6, background: 'none', border: 0, color: '#e8505b', cursor: 'pointer', fontSize: 15 }}>✕</button>
                            </div>
                        ))}
                    </div>
                    <p style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 12 }}>💡 روی هر درس بزنید تا فصل‌هایش را ببینید و ویرایش کنید.</p>
                </div>
            </div>

            {book && <Chapters book={book} rows={chapters[`${book.grade}|${book.name}`] || []} />}
        </DashLayout>
    );
}

/**
 * فصل‌های یک کتاب. فصل‌ها منبعِ دسته‌بندیِ بانک سؤال و ورودیِ هوش مصنوعی‌اند؛
 * هرچه دقیق‌تر باشند، سؤال‌های تولیدشده به محتوای همان فصلِ کتاب نزدیک‌ترند.
 */
function Chapters({ book, rows }) {
    const add = useForm({ text: '', replace: false });
    const [edit, setEdit] = useState(null);
    const submit = (e) => {
        e.preventDefault();
        add.post(route('admin.chapters.store', book.id), { preserveScroll: true, onSuccess: () => add.reset() });
    };
    const save = () => router.put(route('admin.chapters.update', edit.id), edit, { preserveScroll: true, onSuccess: () => setEdit(null) });
    const del = (id) => { if (confirm('این فصل حذف شود؟ سؤال‌های قبلی عنوانِ فصل را نگه می‌دارند.')) router.delete(route('admin.chapters.destroy', id), { preserveScroll: true }); };

    return (
        <div className="panel" style={{ marginTop: 20 }}>
            <h3>📑 فصل‌های «{book.name}» — پایه‌ی {book.grade}</h3>
            <p style={{ color: 'var(--muted)', fontSize: 13, lineHeight: 2, marginTop: 0 }}>
                سؤال‌های بانک با همین فصل‌ها دسته‌بندی می‌شوند و هوش مصنوعی عنوان و درس‌های فصل را می‌خواند
                تا سؤال را دقیقاً از محتوای همان فصل بسازد. معلم‌ها اگر فصلی را پیدا نکنند، می‌توانند برای مدرسه‌ی خودشان اضافه کنند.
            </p>

            {rows.length === 0 && <div style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 12 }}>هنوز فصلی ثبت نشده — فهرستِ فصل‌های کتاب را پایین بچسبانید.</div>}
            <div style={{ display: 'grid', gap: 8, marginBottom: 16 }}>
                {rows.map((c) => edit?.id === c.id ? (
                    <div key={c.id} style={{ display: 'grid', gridTemplateColumns: '70px 1fr', gap: 8, border: '2px solid var(--gold)', borderRadius: 12, padding: 10 }}>
                        <input className="input" type="number" min={1} value={edit.number} onChange={(e) => setEdit({ ...edit, number: +e.target.value })} dir="ltr" />
                        <input className="input" value={edit.title} onChange={(e) => setEdit({ ...edit, title: e.target.value })} />
                        <input className="input" style={{ gridColumn: '1/-1' }} value={edit.lessons} onChange={(e) => setEdit({ ...edit, lessons: e.target.value })} placeholder="درس‌ها/مبحث‌های فصل (با ویرگول جدا کنید) — اختیاری" />
                        <div style={{ gridColumn: '1/-1', display: 'flex', gap: 8 }}>
                            <button type="button" onClick={save} className="btn btn-sm">💾 ذخیره</button>
                            <button type="button" onClick={() => setEdit(null)} className="btn btn-ghost btn-sm">انصراف</button>
                        </div>
                    </div>
                ) : (
                    <div key={c.id} style={{ display: 'flex', gap: 10, alignItems: 'center', border: '1px solid var(--line)', borderRadius: 12, padding: '10px 12px' }}>
                        <span className="tag tag-info" style={{ flex: 'none' }}>فصل {fa(c.number)}</span>
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <b>{c.title}</b>
                            {c.lessons && <div style={{ fontSize: 12, color: 'var(--muted)' }}>{c.lessons}</div>}
                        </div>
                        <button type="button" onClick={() => setEdit({ ...c })} className="btn btn-ghost btn-sm">✏️</button>
                        <button type="button" onClick={() => del(c.id)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
                    </div>
                ))}
            </div>

            <form onSubmit={submit}>
                <div className="field">
                    <label>➕ افزودن فصل — هر خط یک فصل</label>
                    <textarea className="input" rows={6} dir="rtl" value={add.data.text} onChange={(e) => add.setData('text', e.target.value)}
                        placeholder={'فصل ۱: عدد و الگوهای عددی | الگوها، عددهای بزرگ\nفصل ۲: کسر | کسرهای مساوی، مقایسه‌ی کسرها\nفصل ۳: ضرب و تقسیم'} />
                    <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4, lineHeight: 1.9 }}>
                        فهرستِ فصل‌ها را از صفحه‌ی «فهرست»ِ کتاب کپی کنید. شماره اختیاری است؛ بعد از «|» می‌توانید درس‌های فصل را با ویرگول بنویسید.
                    </div>
                    {add.errors.text && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{add.errors.text}</div>}
                </div>
                <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 13, marginBottom: 10 }}>
                    <input type="checkbox" checked={add.data.replace} onChange={(e) => add.setData('replace', e.target.checked)} />
                    جایگزینیِ کامل (فصل‌های فعلیِ این درس پاک و فهرستِ جدید ثبت شود)
                </label>
                <button type="submit" disabled={add.processing || !add.data.text.trim()} className="btn">📑 ثبتِ فصل‌ها</button>
            </form>
        </div>
    );
}
