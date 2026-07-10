import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';
import NoticeList from '@/Components/NoticeList';

export default function Notices() {
    const { notices = [] } = usePage().props;
    return (
        <ThemedDash title="اعلان‌ها و پیام‌ها" active="notices">
            <NoticeList notices={notices} />
        </ThemedDash>
    );
}
