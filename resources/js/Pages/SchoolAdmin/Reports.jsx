import { usePage } from '@inertiajs/react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Reports() {
    const { classes = [] } = usePage().props;
    return (
        <DashLayout title="گزارش‌ها" roleLabel="مدیر مدرسه" menu={schoolMenu} active="reports">
            <div className="panel">
                <h3>📈 تعداد دانش‌آموز هر کلاس</h3>
                {classes.map((c) => (
                    <div key={c.id} style={{ margin: '12px 0' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
                            <b>{c.name}</b><span style={{ color: 'var(--muted)' }}>{fa(c.students_count)} نفر</span>
                        </div>
                        <div className="bar" style={{ height: 10, borderRadius: 8, background: '#eef2f8' }}>
                            <div style={{ width: `${Math.min(c.students_count * 8, 100)}%`, height: '100%', borderRadius: 8, background: 'linear-gradient(90deg,var(--gold),var(--gold-2))' }} />
                        </div>
                    </div>
                ))}
            </div>
        </DashLayout>
    );
}
