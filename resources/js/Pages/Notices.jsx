import { usePage } from '@inertiajs/react';
import DashLayout, { adminMenu, schoolMenu, teacherMenu } from '@/Layouts/DashLayout';
import NotificationCenter from '@/Components/NotificationCenter';

const MENUS = { super_admin: adminMenu, school_admin: schoolMenu, teacher: teacherMenu };
const LABELS = { super_admin: 'ادمین کل', school_admin: 'مدیر مدرسه', teacher: 'معلم', parent: 'والد' };

/**
 * کارتابلِ اعلان‌ها برای نقش‌های مدیریتی (ادمین کل، مدیرِ مدرسه، والد).
 *
 * منویِ خودِ نقش رندر می‌شود؛ پیش از این همه‌ی نقش‌های غیرِ معلم منویِ
 * دانش‌آموز می‌گرفتند و ۱۱ لینکِ ۴۰۳ تولید می‌شد.
 */
export default function Notices() {
    const { notices = [], role = 'school_admin' } = usePage().props;

    return (
        <DashLayout title="اعلان‌ها و پیام‌ها" roleLabel={LABELS[role] ?? 'کاربر'}
            menu={MENUS[role] ?? schoolMenu} active="notices">
            <NotificationCenter notices={notices} />
        </DashLayout>
    );
}
