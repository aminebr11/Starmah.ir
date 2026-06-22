import { usePage, useForm } from '@inertiajs/react';
import ThemedLayout from '@/Layouts/Themed';
import { ui } from '@/theme';

/** ساخت تکلیف/آزمون: انتخاب کلاس، نوع، مهارت‌ها و تعداد سؤال. */
export default function AssignmentCreate() {
    const { classrooms, subjects } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        classroom_id: classrooms?.[0]?.id ?? '',
        title: '',
        type: 'practice',
        skill_ids: [],
        question_count: 10,
        due_at: '',
    });

    const toggleSkill = (id) => setData('skill_ids', data.skill_ids.includes(id) ? data.skill_ids.filter((x) => x !== id) : [...data.skill_ids, id]);
    const submit = (e) => { e.preventDefault(); post(route('teacher.assignments.store')); };

    return (
        <ThemedLayout title="تکلیف جدید">
            <div style={ui.h}>تکلیف / آزمون جدید 📝</div>

            <form onSubmit={submit} style={{ display: 'grid', gap: 14, marginTop: 16 }}>
                <Field label="عنوان">
                    <input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="مثلاً: تمرین ضرب هفته" style={ui.input} />
                    {errors.title && <Err>{errors.title}</Err>}
                </Field>

                <Field label="کلاس">
                    <select value={data.classroom_id} onChange={(e) => setData('classroom_id', e.target.value)} style={ui.input}>
                        {classrooms.map((c) => <option key={c.id} value={c.id} style={{ color: '#000' }}>{c.name}</option>)}
                    </select>
                </Field>

                <Field label="نوع">
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        {[['practice', 'تمرین'], ['homework', 'تکلیف'], ['quiz', 'کوییز'], ['exam', 'آزمون']].map(([v, l]) => (
                            <button type="button" key={v} onClick={() => setData('type', v)}
                                style={{ ...ui.pill, cursor: 'pointer', fontFamily: 'inherit', ...(data.type === v ? ui.pillAcc : {}) }}>{l}</button>
                        ))}
                    </div>
                </Field>

                <Field label="مهارت‌های هدف">
                    {subjects.map((s) => (
                        <div key={s.id} style={{ marginBottom: 8 }}>
                            <div style={ui.muted}>{s.name}</div>
                            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 4 }}>
                                {s.skills.map((sk) => (
                                    <button type="button" key={sk.id} onClick={() => toggleSkill(sk.id)}
                                        style={{ ...ui.pill, cursor: 'pointer', fontFamily: 'inherit', ...(data.skill_ids.includes(sk.id) ? ui.pillAcc : {}) }}>{sk.name}</button>
                                ))}
                            </div>
                        </div>
                    ))}
                    {errors.skill_ids && <Err>حداقل یک مهارت انتخاب کن</Err>}
                </Field>

                <Field label={`تعداد سؤال: ${data.question_count}`}>
                    <input type="range" min="3" max="30" value={data.question_count} onChange={(e) => setData('question_count', +e.target.value)} style={{ width: '100%' }} />
                </Field>

                <button type="submit" disabled={processing} style={ui.btn}>ساخت تکلیف</button>
            </form>
        </ThemedLayout>
    );
}

const Field = ({ label, children }) => (
    <div><div style={{ fontWeight: 700, fontSize: 13, marginBottom: 6 }}>{label}</div>{children}</div>
);
const Err = ({ children }) => <div style={{ color: '#ff6b6b', fontSize: 12, marginTop: 4 }}>{children}</div>;
