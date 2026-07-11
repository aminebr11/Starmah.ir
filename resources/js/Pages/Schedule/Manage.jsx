import { usePage, useForm, router, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu, schoolMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const PALETTE = [
    ['#fff3d6', '#8a5a00'], ['#dcebff', '#1b4b8a'], ['#d4f5ef', '#0f766e'],
    ['#ffe0ec', '#a01a4a'], ['#e9e4ff', '#4c2fb0'], ['#e6f7d9', '#3a6b12'],
    ['#ffe4d1', '#a04413'], ['#d9f0ff', '#0b6ea8'],
];
const colorFor = (s) => { let h = 0; for (const c of (s || '')) h = (h * 31 + c.charCodeAt(0)) >>> 0; return PALETTE[h % PALETTE.length]; };
// وزن روز هفته از یک تاریخ میلادی: 0=شنبه .. 6=جمعه
const weekdayOf = (iso) => { try { return (new Date(iso + 'T00:00:00').getDay() + 1) % 7; } catch (e) { return 0; } };

export default function Manage() {
    const { role, routes, classrooms = [], classroomId, classroom, days = [], entries = {}, books = [], flash } = usePage().props;
    const menu = role === 'teacher' ? teacherMenu : schoolMenu;
    const roleLabel = role === 'teacher' ? 'معلم' : 'مدیر مدرسه';
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const [dated, setDated] = useState(false); // محدود به تاریخ خاص؟
    const form = useForm({ classroom_id: classroomId, day_of_week: 0, kind: 'class', title: '', start_time: '08:00', end_time: '09:00', period: 1, specific_date: '' });
    useEffect(() => { form.setData('classroom_id', classroomId); }, [classroomId]);

    const submit = (e) => {
        e.preventDefault();
        form.post(route(routes.store), { preserveScroll: true, onSuccess: () => form.setData({ ...form.data, title: '' }) });
    };
    const del = (id) => { if (confirm('این مورد از برنامه حذف شود؟')) router.delete(route(routes.destroy, id), { preserveScroll: true }); };
    const changeClass = (cid) => router.get(route(routes.index), { classroom_id: cid }, { preserveState: false, preserveScroll: true });

    const isRecess = form.data.kind === 'recess';
    const useBooks = !isRecess && books.length > 0;
    const custom = form.data.title && !books.some((b) => b.name === form.data.title);

    // وقتی تاریخِ خاص انتخاب می‌شود، روز هفته از همان تاریخ تعیین می‌شود
    const setSpecific = (v) => { form.setData({ ...form.data, specific_date: v, day_of_week: v ? weekdayOf(v) : form.data.day_of_week }); };

    if (!classroom) {
        return <DashLayout title="برنامه‌ی کلاسی" roleLabel={roleLabel} menu={menu} active="schedule">
            <div className="panel"><p style={{ color: 'var(--muted)' }}>{role === 'teacher' ? 'ابتدا باید یک کلاس داشته باشی.' : 'هنوز کلاسی در مدرسه ثبت نشده است.'}</p></div>
        </DashLayout>;
    }

    return (
        <DashLayout title="برنامه‌ی کلاسی" roleLabel={roleLabel} menu={menu} active="schedule"
            actions={classrooms.length > 1 && (
                <select value={classroomId} onChange={(e) => changeClass(e.target.value)} className="input" style={{ width: 'auto', padding: '8px 10px' }}>
                    {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}{c.grade ? ` — پایه ${c.grade}` : ''}</option>)}
                </select>
            )}>
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <form onSubmit={submit} className="panel">
                <h3 style={{ marginTop: 0 }}>➕ افزودن به برنامه — {classroom.name}{classroom.grade ? ` (پایه ${classroom.grade})` : ''}</h3>

                {/* نوع */}
                <div style={{ display: 'flex', gap: 8, marginBottom: 12, flexWrap: 'wrap' }}>
                    <button type="button" onClick={() => form.setData('kind', 'class')} className={`tag ${!isRecess ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 16px' }}>📚 درس</button>
                    <button type="button" onClick={() => form.setData('kind', 'recess')} className={`tag ${isRecess ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 16px' }}>☕ زنگ تفریح</button>

                    {/* دامنه: همیشگی یا تاریخ خاص */}
                    <div style={{ marginInlineStart: 'auto', display: 'flex', gap: 6, background: 'var(--cream)', borderRadius: 12, padding: 4 }}>
                        <button type="button" onClick={() => { setDated(false); form.setData('specific_date', ''); }} className={`tag ${!dated ? 'tag-ok' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '8px 13px' }}>🔁 هفتگی (همیشگی)</button>
                        <button type="button" onClick={() => setDated(true)} className={`tag ${dated ? 'tag-ok' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '8px 13px' }}>📌 تاریخِ خاص</button>
                    </div>
                </div>

                {!isRecess && books.length === 0 && (
                    <div style={{ marginBottom: 12, background: '#fff8e8', border: '1px solid var(--gold)', borderRadius: 12, padding: '10px 14px', fontSize: 13, color: '#8a5a00' }}>
                        ⚠️ برای این کلاس درسی از بانک دروس پیدا نشد. عنوان درس را دستی وارد کنید یا از ادمین کل بخواهید دروسِ این پایه را ثبت کند.
                    </div>
                )}

                <div style={{ display: 'grid', gridTemplateColumns: dated ? '1.4fr 1.2fr 0.8fr 0.8fr auto' : '1fr 1.4fr 0.8fr 0.8fr auto', gap: 10, alignItems: 'end' }} className="sched-form">
                    {dated ? (
                        <div className="field"><label>تاریخِ برگزاری</label><JalaliDatePicker value={form.data.specific_date} onChange={setSpecific} placeholder="تاریخ" /></div>
                    ) : (
                        <div className="field"><label>روز هفته</label>
                            <select className="input" value={form.data.day_of_week} onChange={(e) => form.setData('day_of_week', +e.target.value)}>
                                {days.map((d, i) => <option key={i} value={i}>{d}</option>)}
                            </select>
                        </div>
                    )}
                    <div className="field"><label>{isRecess ? 'عنوان (اختیاری)' : 'درس (از بانک دروسِ پایه)'}</label>
                        {useBooks ? (
                            <select className="input" value={books.some((b) => b.name === form.data.title) ? form.data.title : (form.data.title ? '__c' : '')}
                                onChange={(e) => { const v = e.target.value; form.setData('title', v === '__c' ? ' ' : (v === '' ? '' : v)); }}>
                                <option value="">— انتخاب درس —</option>
                                {books.map((b, i) => <option key={i} value={b.name}>{b.icon} {b.name}</option>)}
                                <option value="__c">✏️ سایر (دستی)…</option>
                            </select>
                        ) : (
                            <input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder={isRecess ? 'زنگ تفریح' : 'مثلاً: ریاضی'} />
                        )}
                        {useBooks && custom && <input className="input" style={{ marginTop: 6 }} value={form.data.title.trim()} onChange={(e) => form.setData('title', e.target.value)} placeholder="عنوان درس (دستی)" autoFocus />}
                        {form.errors.title && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{form.errors.title}</div>}
                    </div>
                    <div className="field"><label>از ساعت</label><input type="time" className="input" value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} /></div>
                    <div className="field"><label>تا ساعت</label><input type="time" className="input" value={form.data.end_time} onChange={(e) => form.setData('end_time', e.target.value)} /></div>
                    <button type="submit" disabled={form.processing} className="btn">افزودن</button>
                </div>

                {dated && form.data.specific_date && <div style={{ fontSize: 12.5, color: 'var(--muted)', marginTop: 8 }}>📌 این برنامه فقط برای روزِ <b>{days[form.data.day_of_week]}</b> در تاریخِ انتخابی اعمال می‌شود.</div>}

                {form.errors.start_time && (
                    <div style={{ marginTop: 10, background: '#fdecec', border: '1px solid #f5b5ba', color: '#c0392b', borderRadius: 12, padding: '10px 14px', fontWeight: 700 }}>🔔 {form.errors.start_time}</div>
                )}
                {form.errors.end_time && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 6 }}>{form.errors.end_time}</div>}
            </form>

            <div className="panel">
                <h3>🗓️ برنامه‌ی هفته <span style={{ fontSize: 12, fontWeight: 500, color: 'var(--muted)' }}>(به ترتیب ساعت)</span></h3>
                <div className="sched-board">
                    {days.map((d, i) => (
                        <div key={i} className="sched-day">
                            <div className="sched-day-head">{d}</div>
                            {(entries[i] ?? []).map((e) => {
                                if (e.kind === 'recess') {
                                    return (
                                        <div key={e.id} className="sched-recess">
                                            ☕ {e.title}
                                            {e.time && <span dir="ltr" style={{ opacity: .8, fontSize: 10, display: 'block' }}>{fa(e.time)}</span>}
                                            {e.jdate && <span className="sched-datebadge">📌 {e.jdate}</span>}
                                            <button onClick={() => del(e.id)} className="sched-del" title="حذف">✕</button>
                                        </div>
                                    );
                                }
                                const [bg, fg] = colorFor(e.title);
                                return (
                                    <div key={e.id} className="sched-card" style={{ background: bg, color: fg, borderColor: fg + '33' }}>
                                        <div style={{ fontWeight: 800, fontSize: 13.5 }}>{e.title}</div>
                                        {e.time && <div style={{ fontSize: 11, opacity: .85 }}>⏰ <span dir="ltr" style={{ display: 'inline-block' }}>{fa(e.time)}</span></div>}
                                        {e.jdate && <span className="sched-datebadge">📌 {e.jdate}</span>}
                                        <button onClick={() => del(e.id)} className="sched-del" title="حذف">✕</button>
                                    </div>
                                );
                            })}
                            {!(entries[i] ?? []).length && <div className="sched-empty">—</div>}
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}
