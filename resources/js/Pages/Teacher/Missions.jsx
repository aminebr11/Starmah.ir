import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const DIFF = { easy: 'آسان', medium: 'متوسط', hard: 'دشوار' };
const BADGES = ['🎖️', '🏅', '🥇', '⭐', '🚀', '🔥', '💎', '🧠', '🎯', '👑'];
const TYPES = [
    { v: 'quiz', ic: '🧠', t: 'سؤالِ بانک', d: 'چند سؤال از بانکِ سؤالِ تو' },
    { v: 'podcast', ic: '🎧', t: 'گوش‌دادن به پادکست', d: 'یک پادکستِ کلاس را کامل گوش بده' },
    { v: 'worksheet', ic: '🎨', t: 'انجام کاربرگ', d: 'یک کاربرگ را پر و ارسال کن' },
    { v: 'game', ic: '🎮', t: 'انجام یک بازی', d: 'یکی از بازی‌های کلاس را کامل کن' },
];
const TYPE_LABEL = Object.fromEntries(TYPES.map((t) => [t.v, `${t.ic} ${t.t}`]));

export default function Missions() {
    const { missions = [], facets = [], classrooms = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [editId, setEditId] = useState(null);
    useEffect(() => { if (flash?.flash) { setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); window.scrollTo({ top: 0 }); } }, [flash]);

    const blank = { title: '', type: 'quiz', subject: '', lesson_no: '', difficulty: '', classroom_id: '', question_count: 5, xp_reward: 20, badge_name: '', badge_icon: '🎖️', is_active: true };
    const form = useForm(blank);
    const lessons = (facets.find((s) => s.subject === form.data.subject)?.lessons) || [];

    const submit = (e) => {
        e.preventDefault();
        if (editId) form.put(route('teacher.missions.update', editId), { preserveScroll: true, onSuccess: () => { form.reset(); setEditId(null); } });
        else form.post(route('teacher.missions.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };
    const edit = (m) => {
        setEditId(m.id);
        form.setData({ ...blank, ...m, subject: m.subject || '', lesson_no: m.lesson_no || '', difficulty: m.difficulty || '', classroom_id: m.classroom_id || '', badge_name: m.badge_name || '', badge_icon: m.badge_icon || '🎖️' });
        window.scrollTo({ top: 0 });
    };
    const cancel = () => { setEditId(null); form.reset(); };
    const del = (m) => confirm(`مأموریت «${m.title}» حذف شود؟`) && router.delete(route('teacher.missions.destroy', m.id), { preserveScroll: true });
    const toggle = (m) => router.post(route('teacher.missions.toggle', m.id), {}, { preserveScroll: true });

    return (
        <DashLayout title="مأموریت‌های روزانه" roleLabel="معلم" menu={teacherMenu} active="missions">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div className="panel">
                <h3 style={{ marginTop: 0 }}>🎯 {editId ? 'ویرایش مأموریت' : 'ساخت مأموریت روزانه'}</h3>
                <p style={{ color: 'var(--muted)', fontSize: 13, marginTop: 0 }}>
                    سؤال‌های مأموریت به‌صورت خودکار از <b>بانکِ سؤالِ خودت</b> (بر اساس درس/شماره‌درس/سطح انتخابی) انتخاب می‌شوند.
                    دانش‌آموز هر روز می‌تواند هر مأموریت را یک‌بار انجام دهد و امتیاز/نشان بگیرد.
                </p>
                <form onSubmit={submit}>
                    {/* نوعِ مأموریت */}
                    <label style={{ fontWeight: 700, display: 'block', marginBottom: 6, fontSize: 13 }}>نوعِ مأموریت</label>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 8, marginBottom: 14 }}>
                        {TYPES.map((ty) => (
                            <button type="button" key={ty.v} onClick={() => form.setData('type', ty.v)}
                                style={{ textAlign: 'right', cursor: 'pointer', fontFamily: 'inherit', borderRadius: 12, padding: 10, border: form.data.type === ty.v ? '2px solid var(--gold)' : '1px solid var(--line)', background: form.data.type === ty.v ? '#fff8e8' : '#fff' }}>
                                <div style={{ fontSize: 22 }}>{ty.ic}</div>
                                <div style={{ fontWeight: 800, fontSize: 13, marginTop: 2 }}>{ty.t}</div>
                                <div style={{ fontSize: 11, color: 'var(--muted)' }}>{ty.d}</div>
                            </button>
                        ))}
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 12 }}>
                        <Field label="عنوان مأموریت" err={form.errors.title}>
                            <input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder={form.data.type === 'quiz' ? 'مثلاً: چالشِ ضرب امروز' : 'مثلاً: پادکستِ درس ۳ را گوش بده'} />
                        </Field>
                        {form.data.type === 'quiz' && <>
                            <Field label="درس (از بانک)">
                                <select className="input" value={form.data.subject} onChange={(e) => { form.setData('subject', e.target.value); form.setData('lesson_no', ''); }}>
                                    <option value="">همه‌ی درس‌ها</option>
                                    {facets.map((s) => <option key={s.subject} value={s.subject}>{s.subject}</option>)}
                                </select>
                            </Field>
                            <Field label="شماره درس">
                                <select className="input" value={form.data.lesson_no} onChange={(e) => form.setData('lesson_no', e.target.value)} disabled={!form.data.subject}>
                                    <option value="">همه</option>
                                    {lessons.map((l) => <option key={l} value={l === '—' ? '' : l}>{l}</option>)}
                                </select>
                            </Field>
                            <Field label="سطح دشواری">
                                <select className="input" value={form.data.difficulty} onChange={(e) => form.setData('difficulty', e.target.value)}>
                                    <option value="">همه</option><option value="easy">آسان</option><option value="medium">متوسط</option><option value="hard">دشوار</option>
                                </select>
                            </Field>
                            <Field label="تعداد سؤال" err={form.errors.question_count}>
                                <input type="number" min={1} max={20} dir="ltr" className="input" value={form.data.question_count} onChange={(e) => form.setData('question_count', +e.target.value)} />
                            </Field>
                        </>}
                        <Field label="کلاس (خالی = همه‌ی کلاس‌ها)">
                            <select className="input" value={form.data.classroom_id} onChange={(e) => form.setData('classroom_id', e.target.value)}>
                                <option value="">همه‌ی کلاس‌ها</option>
                                {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </Field>
                        <Field label="امتیاز (XP)" err={form.errors.xp_reward}>
                            <input type="number" min={0} max={500} dir="ltr" className="input" value={form.data.xp_reward} onChange={(e) => form.setData('xp_reward', +e.target.value)} />
                        </Field>
                        <Field label="نامِ نشان (اختیاری)">
                            <input className="input" value={form.data.badge_name} onChange={(e) => form.setData('badge_name', e.target.value)} placeholder="مثلاً: قهرمانِ ضرب" />
                        </Field>
                    </div>
                    <div style={{ marginTop: 10 }}>
                        <label style={{ fontWeight: 700, display: 'block', marginBottom: 6, fontSize: 13 }}>آیکونِ نشان</label>
                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                            {BADGES.map((b) => (
                                <button type="button" key={b} onClick={() => form.setData('badge_icon', b)}
                                    style={{ fontSize: 20, width: 40, height: 40, borderRadius: 10, cursor: 'pointer', border: form.data.badge_icon === b ? '2px solid var(--gold)' : '1px solid var(--line)', background: form.data.badge_icon === b ? '#fff8e8' : '#fff' }}>{b}</button>
                            ))}
                        </div>
                    </div>
                    <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 14, marginTop: 12 }}>
                        <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} /> فعال باشد
                    </label>
                    <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                        <button type="submit" disabled={form.processing} className="btn">{editId ? '💾 ذخیره تغییرات' : '➕ ساخت مأموریت'}</button>
                        {editId && <button type="button" onClick={cancel} className="btn btn-ghost">انصراف</button>}
                    </div>
                </form>
            </div>

            <div className="panel">
                <h3>🗄️ مأموریت‌های من ({fa(missions.length)})</h3>
                {missions.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز مأموریتی نساخته‌ای. برای شروع، فرمِ بالا را پر کن.</p>}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(260px,1fr))', gap: 12, marginTop: 8 }}>
                    {missions.map((m) => (
                        <div key={m.id} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 13, opacity: m.is_active ? 1 : .55 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <span style={{ fontSize: 22 }}>{m.badge_icon || '🎯'}</span>
                                <b style={{ flex: 1 }}>{m.title}</b>
                                <span className={`tag ${m.is_active ? 'tag-ok' : ''}`} style={{ fontSize: 11 }}>{m.is_active ? 'فعال' : 'غیرفعال'}</span>
                            </div>
                            <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 6 }}>
                                <span className="tag tag-info" style={{ fontSize: 10.5, marginInlineEnd: 4 }}>{TYPE_LABEL[m.type] || TYPE_LABEL.quiz}</span>
                                {m.type === 'quiz' && [m.subject || 'همه‌ی درس‌ها', m.lesson_no ? `درس ${m.lesson_no}` : null, m.difficulty ? DIFF[m.difficulty] : null].filter(Boolean).join(' · ')}
                            </div>
                            <div style={{ fontSize: 12, color: 'var(--muted-2)', marginTop: 4 }}>
                                {fa(m.question_count)} سؤال · ⚡{fa(m.xp_reward)}{m.badge_name ? ` · 🎖️ ${m.badge_name}` : ''}
                            </div>
                            <div style={{ fontSize: 11.5, color: 'var(--muted-2)', marginTop: 4 }}>امروز {fa(m.today)} نفر · مجموع {fa(m.completions)} انجام</div>
                            <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                                <button onClick={() => edit(m)} className="btn btn-ghost btn-sm">✏️ ویرایش</button>
                                <button onClick={() => toggle(m)} className="btn btn-ghost btn-sm">{m.is_active ? '⏸️ غیرفعال' : '▶️ فعال'}</button>
                                <button onClick={() => del(m)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}

function Field({ label, err, children }) {
    return <div className="field" style={{ margin: 0 }}><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
