import { usePage, Link } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import SubmissionsBoard from '@/Components/SubmissionsBoard';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** «📥 کاربرگ‌های ارسالی» — همه‌ی کاربرگ‌های پرشده‌ی بچه‌ها از همه‌ی کاربرگ‌ها، در یک فهرست. */
export default function WorksheetInbox() {
    const { submissions = [], worksheets = [], gradeOptions = {}, maxXp = 50, gradingReady = true } = usePage().props;
    const pending = submissions.filter((s) => !s.graded).length;

    return (
        <DashLayout title="کاربرگ‌های ارسالی" roleLabel="معلم" menu={teacherMenu} active="wsinbox"
            actions={<Link href={route('teacher.worksheets')} className="btn btn-sm btn-ghost">🎨 کاربرگ‌های من</Link>}>
            <div className="dash-cards">
                <div className="dcard"><div className="ic">📥</div><div className="lbl">کاربرگِ ارسالی</div><div className="val">{fa(submissions.length)}</div></div>
                <div className="dcard"><div className="ic">⏳</div><div className="lbl">منتظرِ تصحیح</div><div className="val">{fa(pending)}</div></div>
                <div className="dcard"><div className="ic">📄</div><div className="lbl">کاربرگ</div><div className="val">{fa(worksheets.length)}</div></div>
            </div>
            {!gradingReady && (
                <div className="panel" style={{ background: '#fff4f4' }}>بخشِ تصحیح هنوز روی سرور فعال نشده؛ مدیرِ کل از «🩺 سلامتِ سیستم» مایگریشن‌ها را اجرا کند.</div>
            )}
            <div className="panel">
                <SubmissionsBoard initial={submissions} worksheets={worksheets} grades={gradeOptions} maxXp={maxXp} />
            </div>
        </DashLayout>
    );
}
