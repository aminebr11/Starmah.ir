import { usePage } from '@inertiajs/react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Reports() {
    const { top_schools = [] } = usePage().props;
    return (
        <DashLayout title="گزارش‌ها" roleLabel="ادمین کل" menu={adminMenu} active="reports">
            <div className="panel">
                <h3>🏆 فعال‌ترین مدارس (بر اساس تعداد کاربر)</h3>
                <table className="tbl">
                    <thead><tr><th>#</th><th>مدرسه</th><th>شهر</th><th>کاربران</th></tr></thead>
                    <tbody>
                        {top_schools.map((s, i) => (
                            <tr key={s.id}><td>{fa(i + 1)}</td><td style={{ fontWeight: 700 }}>{s.name}</td><td>{s.city ?? '—'}</td><td>{fa(s.users_count)}</td></tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="panel" style={{ textAlign: 'center', color: 'var(--muted)' }}>
                📊 نمودارهای تحلیلی پیشرفته (درآمد، رشد، فعالیت روزانه) در نسخه‌های بعدی اضافه می‌شوند.
            </div>
        </DashLayout>
    );
}
