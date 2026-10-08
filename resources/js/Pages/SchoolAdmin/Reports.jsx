import { usePage, Link } from '@inertiajs/react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
import { AreaTrend, HBars, PAL } from '@/Components/Charts';
import { PeriodPicker, PeopleTable, teacherColumns, teacherDetail, tone } from '@/Components/VisitKit';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
function Card({ ic, lbl, v }) { return <div className="dcard"><div className="ic">{ic}</div><div className="lbl">{lbl}</div><div className="val">{fa(v)}</div></div>; }

export default function Reports() {
    const { report, trend = [], days = 30, periods } = usePage().props;
    if (!report) return <DashLayout title="گزارش‌ها" roleLabel="مدیر مدرسه" menu={schoolMenu} active="reports"><div className="panel">داده‌ای نیست.</div></DashLayout>;
    const t = report.totals;
    const teachers = report.per_teacher || [];
    const bars = teachers.map((tt) => ({ label: tt.name, value: tt.score, sub: tt.classes || 'بدونِ کلاس', color: tone(tt.score) }));
    const top = teachers[0];

    return (
        <DashLayout title="گزارش‌ها و تحلیل عملکرد" roleLabel="مدیر مدرسه" menu={schoolMenu} active="reports"
            actions={<a href="/print/roster" target="_blank" rel="noopener" className="btn btn-sm">🖨️ چاپِ فهرستِ دانش‌آموزان</a>}>
            <div className="dash-cards">
                <Card ic="👩‍🏫" lbl="معلم‌ها" v={t.teachers} />
                <Card ic="🎓" lbl="دانش‌آموزان" v={t.students} />
                <Card ic="🏛️" lbl="کلاس‌ها" v={t.classes} />
                <Card ic="⭐" lbl="مجموع امتیاز مدرسه" v={t.points} />
            </div>
            <div className="panel">
                <h3>📈 نبضِ مدرسه — امتیازِ روزانه‌ی کلِ دانش‌آموزان (۲۸ روز)</h3>
                <AreaTrend data={trend} color={PAL[0]} />
            </div>

            <div className="panel">
                <div className="vk-head">
                    <h3 style={{ margin: 0 }}>👩‍🏫 تحلیلِ عملکردِ هر معلم</h3>
                    <PeriodPicker days={days} periods={periods} />
                </div>
                <p style={{ color: 'var(--muted)', fontSize: 13 }}>
                    از کارهای واقعیِ هر معلم در {fa(days)} روزِ اخیر: محتوا، بازی، آزمون، مأموریت و کاربرگی که ساخته، نمره و حضور و غیابی که ثبت کرده،
                    پیام‌هایش، حضورِ خودش در سایت، و این‌که شاگردانش چقدر سر زده‌اند و کارهایشان را انجام داده‌اند.
                </p>
                {top?.score > 0 && (
                    <div className="vk-spot-card good" style={{ marginBottom: 14 }}>
                        <b>🏅 بیشترین عملکرد: {top.name}</b>
                        <span style={{ color: 'var(--muted)', fontSize: 12.5 }}> — {fa(top.score)} از ۱۰۰ · {fa(top.produced)} محتوا/بازی/آزمون · حضورِ شاگردان {fa(top.student_rate)}٪</span>
                    </div>
                )}
                {teachers.length > 1 && <div style={{ marginBottom: 16 }}><HBars items={bars} unit=" از ۱۰۰" /></div>}
                <PeopleTable rows={teachers} columns={teacherColumns()} subOf={teacherDetail} emptyText="هنوز معلمی ثبت نشده." />
                <div className="vk-note">
                    روی نامِ هر معلم بزنید تا ریزِ کارهایش را ببینید. «عملکرد» = حضورِ معلم (۲۵٪) + محتوا و کارهای کلاسی (۳۵٪) + حضورِ شاگردان (۲۰٪) + انجامِ وظایفِ شاگردان (۲۰٪).
                    {' '}<Link href="/school/visits">👁️ گزارشِ کاملِ بازدید و مشارکت ←</Link>
                </div>
            </div>
        </DashLayout>
    );
}
