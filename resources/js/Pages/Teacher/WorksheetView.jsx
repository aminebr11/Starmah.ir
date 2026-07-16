import { usePage, Link } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

/** نمایش تصویرِ کاربرگ + دکمه‌ی چاپ. */
export default function WorksheetView() {
    const { worksheet } = usePage().props;
    const print = () => window.print();

    return (
        <DashLayout title={worksheet.title} roleLabel="معلم" menu={teacherMenu} active="assignments"
            actions={<>
                <button onClick={print} className="btn btn-sm">🖨️ چاپ / ذخیره PDF</button>
                <Link href={route('teacher.worksheets')} className="btn btn-ghost btn-sm">← بانک کاربرگ‌ها</Link>
            </>}>

            <style>{`@media print { .dash-topbar, .dash-side, .dash-actions, .no-print { display:none !important; } .dash-main { padding:0 !important; } .ws-sheet { box-shadow:none !important; } }`}</style>

            <div className="ws-sheet" style={{ marginTop: 8 }} dangerouslySetInnerHTML={{ __html: worksheet.html || '' }} />
        </DashLayout>
    );
}
