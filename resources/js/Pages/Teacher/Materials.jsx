import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
const ROWS = [['📄','جزوه و فایل درسی'],['🎧','پادکست صوتی'],['🖼️','گالری تصاویر کلاس'],['📝','تکالیف روزانه']];
export default function Materials() {
    return (
        <DashLayout title="مطالب و محتوا" roleLabel="معلم" menu={teacherMenu} active="materials">
            <div className="panel">
                <h3>📚 بارگذاری محتوا برای کلاس</h3>
                <p style={{ color: 'var(--muted)' }}>این بخش‌ها دقیقاً مثل سایت قبلی، برای بارگذاری محتوای کلاس ساخته می‌شوند — به‌زودی یکی‌یکی فعال می‌شوند.</p>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2,1fr)', gap: 14, marginTop: 14 }}>
                    {ROWS.map(([ic, t]) => (
                        <div key={t} style={{ border: '1px dashed var(--line)', borderRadius: 16, padding: 22, textAlign: 'center' }}>
                            <div style={{ fontSize: 34 }}>{ic}</div>
                            <div style={{ fontWeight: 700, marginTop: 6 }}>{t}</div>
                            <span className="tag tag-info" style={{ marginTop: 8, display: 'inline-block' }}>به‌زودی</span>
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}
