import { usePage } from '@inertiajs/react';
import ThemedDash from '@/Layouts/ThemedDash';
import NotificationCenter from '@/Components/NotificationCenter';

/** کارتابلِ اعلان‌های دانش‌آموز — همان فیدِ زنگوله، کامل و قابلِ علامت‌زدن. */
export default function Notices() {
    const { notices = [] } = usePage().props;

    return (
        <ThemedDash title="اعلان‌ها و پیام‌ها" active="notices">
            <NotificationCenter notices={notices} tone="dark" />
        </ThemedDash>
    );
}
