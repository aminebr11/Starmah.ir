import { usePage } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import NotificationCenter from '@/Components/NotificationCenter';

export default function Notices() {
    const { notices = [] } = usePage().props;
    return (
        <DashLayout title="اعلان‌ها و پیام‌ها" roleLabel="معلم" menu={teacherMenu} active="notices">
            <NotificationCenter notices={notices} />
        </DashLayout>
    );
}
