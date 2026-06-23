import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
export default function Announcements() {
    return (
        <DashLayout title="اطلاعیه‌ها" roleLabel="مدیر مدرسه" menu={schoolMenu} active="announcements">
            <div className="panel" style={{ textAlign: 'center', color: 'var(--muted)', padding: 40 }}>
                <div style={{ fontSize: 50 }}>📢</div>
                <h3 style={{ justifyContent: 'center' }}>اطلاعیه‌ها و پیام‌ها</h3>
                <p>ارسال اطلاعیه به معلم‌ها و والدین — در حال آماده‌سازی. به‌زودی فعال می‌شود.</p>
            </div>
        </DashLayout>
    );
}
