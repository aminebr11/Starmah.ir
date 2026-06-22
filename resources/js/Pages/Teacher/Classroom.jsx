import { usePage, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import Themed from '@/Layouts/Themed';
import { fa, ui, rankColor } from '@/theme';

/** روستر کلاس + ثبت ستاره/تذکر انضباطی برای هر دانش‌آموز. */
export default function Classroom() {
    const { classroom, students } = usePage().props;
    const [openFor, setOpenFor] = useState(null);

    const record = (studentId, type) => {
        router.post(route('teacher.discipline.store'), { student_id: studentId, type }, { preserveScroll: true, onSuccess: () => setOpenFor(null) });
    };

    return (
        <Themed title={classroom.name}>
            <div style={ui.row('space-between')}>
                <div>
                    <div style={ui.h}>{classroom.name}</div>
                    <div style={ui.muted}>کد ورود دانش‌آموزان: <b style={{ color: 'var(--acc)' }}>{classroom.join_code}</b></div>
                </div>
            </div>

            <div style={{ marginTop: 14 }}>
                {students.map((s, i) => (
                    <div key={s.id} style={{ ...ui.card, marginBottom: 8 }}>
                        <div style={ui.row('space-between')}>
                            <div style={ui.row()}>
                                <div style={{ width: 28, textAlign: 'center', fontWeight: 800, color: rankColor(i + 1) }}>{fa(i + 1)}</div>
                                <div>
                                    <div style={{ fontWeight: 700 }}>{s.name}</div>
                                    <div style={ui.muted}>{fa(s.xp)} امتیاز • تسلط {fa(s.avg)}٪</div>
                                </div>
                            </div>
                            <button onClick={() => setOpenFor(openFor === s.id ? null : s.id)} style={{ ...ui.pill, cursor: 'pointer', fontFamily: 'inherit' }}>ثبت ⋯</button>
                        </div>
                        {openFor === s.id && (
                            <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
                                <button onClick={() => record(s.id, 'star')} style={{ ...ui.btn, fontSize: 13, padding: 10 }}>🌟 ستاره‌ی تشویق</button>
                                <button onClick={() => record(s.id, 'warning')} style={{ ...ui.btn, ...ui.ghost, fontSize: 13, padding: 10 }}>⚠️ تذکر</button>
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </Themed>
    );
}
