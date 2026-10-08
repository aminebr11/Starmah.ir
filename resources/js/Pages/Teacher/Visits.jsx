import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import { PeriodPicker, Kpi, DailyBars, RankList, PeopleTable, Spotlight, studentColumns, studentDetail, parentColumns, fa, mins } from '@/Components/VisitKit';

/** معلم: بازدید و مشارکتِ دانش‌آموزانِ کلاس‌هایش (و والدین‌شان). */
export default function Visits() {
    const { days, periods, summary, students = [], parents = [], classes = [], teams = [], series = [], classOptions = [] } = usePage().props;
    const [tab, setTab] = useState('students');
    const s = summary.students, p = summary.parents;
    const best = students.filter((r) => r.score > 0).slice(0, 5);
    const worst = students.filter((r) => !r.online && r.days === 0).slice(0, 6);

    return (
        <DashLayout title="بازدید و مشارکتِ دانش‌آموزان" roleLabel="معلم" menu={teacherMenu} active="visits">
            <div className="panel">
                <div className="vk-head">
                    <p>چه کسی سر زده، چقدر مانده و چند درصد از کارهایی که گذاشته‌اید را انجام داده است.</p>
                    <PeriodPicker days={days} periods={periods} />
                </div>
                <div className="vk-grid">
                    <Kpi pulse label="الان آنلاین" value={fa(s.online)} sub={`از ${fa(s.total)} دانش‌آموز`} color="#2bb673" />
                    <Kpi icon="🙋" label="نرخِ حضور" value={`${fa(s.rate)}٪`} sub={`${fa(s.active)} نفر در ${fa(days)} روز`} color="#3d7bf0" />
                    <Kpi icon="✅" label="انجامِ وظایف" value={s.completion == null ? '—' : `${fa(s.completion)}٪`} sub={`${fa(s.tasks)} کارِ انجام‌شده`} color="#149d8a" />
                    <Kpi icon="⏱️" label="میانگینِ زمان" value={mins(s.avg_minutes)} sub="برای هر دانش‌آموزِ فعال" color="#8b5cf6" />
                    <Kpi icon="💤" label="غیرفعالِ ۷ روز" value={fa(s.inactive7)} sub={`${fa(s.never)} نفر هرگز وارد نشده‌اند`} color="#e8505b" />
                    <Kpi icon="👪" label="والدینِ فعال" value={`${fa(p.rate)}٪`} sub={`${fa(p.active)} از ${fa(p.total)} ولی`} color="#e0912f" />
                </div>
            </div>

            <div className="vk-two vk-sec">
                <div className="panel"><h3>📈 حضورِ روزانه‌ی کلاس</h3><DailyBars data={series} /></div>
                <div className="panel">
                    <h3>🏆 کدام تیم پرمشارکت‌تر است؟</h3>
                    <RankList items={teams} unit="عضو" empty="هنوز تیمی تعیین نشده" />
                    {classes.length > 1 && <div style={{ marginTop: 14 }}><RankList title="کلاس‌ها" items={classes} unit="دانش‌آموز" /></div>}
                </div>
            </div>

            <div className="panel vk-sec">
                <Spotlight best={best} worst={worst} worstHint="با یک پیام یا پیامک به والدین، این بچه‌ها را برگردانید." />
            </div>

            <div className="vk-tabs">
                <button type="button" className={tab === 'students' ? 'on' : ''} onClick={() => setTab('students')}>🎓 دانش‌آموزان ({fa(students.length)})</button>
                <button type="button" className={tab === 'parents' ? 'on' : ''} onClick={() => setTab('parents')}>👪 والدین ({fa(parents.length)})</button>
            </div>
            <div className="panel" style={{ marginTop: 10 }}>
                {tab === 'students'
                    ? <PeopleTable rows={students} columns={studentColumns({ showClass: classOptions.length > 1 })} classOptions={classOptions} subOf={studentDetail} />
                    : <PeopleTable rows={parents} columns={parentColumns()} initialSort="days" emptyText="هنوز ولی‌ای به دانش‌آموزان وصل نشده." />}
                <div className="vk-note">روی هر دانش‌آموز بزنید تا ریزِ کارهایش را ببینید. «انجامِ وظایف» یعنی چند درصد از محتوا، بازی، آزمون، کاربرگ، تکلیف و روزهای مأموریتی که در این بازه گذاشته‌اید را انجام داده.</div>
            </div>
        </DashLayout>
    );
}
