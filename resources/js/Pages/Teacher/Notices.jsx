import { usePage } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import NoticeList from '@/Components/NoticeList';

export default function Notices() {
    const { notices = [] } = usePage().props;
    return (
        <DashLayout title="اعلان‌ها و پیام‌ها" roleLabel="معلم" menu={teacherMenu} active="notices">
            <NoticeList notices={notices} />
        </DashLayout>
    );
}
