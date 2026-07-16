import { usePage, useForm, Link } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

/** ساخت تکلیف/آزمون: انتخاب کلاس، نوع، مهارت‌ها و تعداد سؤال. */
export default function AssignmentCreate() {
    const { classrooms = [], subjects = [] } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        classroom_id: classrooms?.[0]?.id ?? '',
        title: '', type: 'practice', skill_ids: [], question_count: 10, due_at: '',
    });

    const toggleSkill = (id) => setData('skill_ids', data.skill_ids.includes(id) ? data.skill_ids.filter((x) => x !== id) : [...data.skill_ids, id]);
    const submit = (e) => { e.preventDefault(); post(route('teacher.assignments.store')); };

    return (
        <DashLayout title="تکلیف / آزمون جدید" roleLabel="معلم" menu={teacherMenu} active="assignments">
            {/* گزینه‌ی کاربرگ‌سازِ هوشمند */}
            <Link href={route('teacher.worksheets')} className="panel" style={{ maxWidth: 680, display: 'flex', alignItems: 'center', gap: 14, textDecoration: 'none', color: '#fff', background: 'linear-gradient(135deg,#7a5cf0,#3d7bf0)', border: 0, marginBottom: 16 }}>
                <div style={{ fontSize: 34 }}>🎨</div>
                <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 900, fontSize: 16 }}>ایجاد کاربرگ هوشمند</div>
                    <div style={{ fontSize: 13, opacity: .92, marginTop: 2 }}>با هوش مصنوعی کاربرگ موضوعی و تصویریِ جذاب بساز و در بانک کاربرگ‌ها ذخیره کن</div>
                </div>
                <div style={{ fontSize: 22 }}>‹</div>
            </Link>

            <form onSubmit={submit} className="panel" style={{ maxWidth: 680 }}>
                <h3>📝 ساخت تکلیف یا آزمون</h3>

                <Field label="عنوان" err={errors.title}>
                    <input className="input" value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="مثلاً: تمرین ضرب هفته" />
                </Field>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                    <Field label="کلاس">
                        <select className="input" value={data.classroom_id} onChange={(e) => setData('classroom_id', e.target.value)}>
                            {classrooms.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </Field>
                    <Field label={`تعداد سؤال: ${data.question_count}`}>
                        <input type="range" min="3" max="30" value={data.question_count} onChange={(e) => setData('question_count', +e.target.value)} style={{ width: '100%' }} />
                    </Field>
                </div>

                <Field label="نوع">
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        {[['practice', 'تمرین'], ['homework', 'تکلیف'], ['quiz', 'کوییز'], ['exam', 'آزمون']].map(([v, l]) => (
                            <button type="button" key={v} onClick={() => setData('type', v)}
                                className={`tag ${data.type === v ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '8px 16px' }}>{l}</button>
                        ))}
                    </div>
                </Field>

                <Field label="مهارت‌های هدف" err={errors.skill_ids ? 'حداقل یک مهارت انتخاب کن' : null}>
                    {subjects.map((s) => (
                        <div key={s.id} style={{ marginBottom: 8 }}>
                            <div style={{ color: 'var(--muted)', fontSize: 13 }}>{s.name}</div>
                            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 4 }}>
                                {s.skills.map((sk) => (
                                    <button type="button" key={sk.id} onClick={() => toggleSkill(sk.id)}
                                        className={`tag ${data.skill_ids.includes(sk.id) ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '7px 13px' }}>{sk.name}</button>
                                ))}
                            </div>
                        </div>
                    ))}
                </Field>

                <button type="submit" disabled={processing} className="btn" style={{ width: '100%' }}>ساخت تکلیف</button>
            </form>
        </DashLayout>
    );
}

const Field = ({ label, err, children }) => (
    <div className="field"><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>
);
