import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
import { Donut, HBars } from '@/Components/Charts';
import {
    PeriodPicker, Kpi, DailyBars, HoursStrip, RankList, PeopleTable, Spotlight, Dot,
    studentColumns, studentDetail, parentColumns, teacherColumns, teacherDetail, fa, mins,
} from '@/Components/VisitKit';

/** مدیرِ مدرسه: بازدید، حضورِ آنلاین و مشارکتِ معلم‌ها، دانش‌آموزان و والدین. */
export default function Visits() {
    const {
        days, periods, summary, students = [], teachers = [], parents = [], classes = [], teams = [],
        onlineNow = [], series = [], hours = [], sections = [], devices = [], classOptions = [],
    } = usePage().props;
    const [tab, setTab] = useState('teachers');
    const S = summary.students, T = summary.teachers, P = summary.parents;
    const topTeacher = teachers[0];
    const topClass = classes[0];

    return (
        <DashLayout title="گزارشِ بازدید و مشارکت" roleLabel="مدیر مدرسه" menu={schoolMenu} active="visits">
            <div className="panel">
                <div className="vk-head">
                    <p>چه کسی آنلاین است، چه کسی سر نزده و کدام معلم و کلاس بیشترین مشارکت را دارد.</p>
                    <PeriodPicker days={days} periods={periods} />
                </div>
                <div className="vk-grid">
                    <Kpi pulse label="الان آنلاین" value={fa(onlineNow.length)} sub={`${fa(S.online)} دانش‌آموز · ${fa(T.online)} معلم · ${fa(P.online)} ولی`} color="#2bb673" />
                    <Kpi icon="🎓" label="حضورِ دانش‌آموزان" value={`${fa(S.rate)}٪`} sub={`${fa(S.active)} از ${fa(S.total)} نفر`} color="#3d7bf0" />
                    <Kpi icon="✅" label="انجامِ وظایفِ دانش‌آموزان" value={S.completion == null ? '—' : `${fa(S.completion)}٪`} sub={`${fa(S.tasks)} کار`} color="#149d8a" />
                    <Kpi icon="👩‍🏫" label="حضورِ معلم‌ها" value={`${fa(T.rate)}٪`} sub={`${fa(T.active)} از ${fa(T.total)} معلم`} color="#8b5cf6" />
                    <Kpi icon="👪" label="حضورِ والدین" value={`${fa(P.rate)}٪`} sub={`${fa(P.active)} از ${fa(P.total)} ولی`} color="#e0912f" />
                    <Kpi icon="💤" label="هرگز وارد نشده" value={fa(S.never + T.never + P.never)} sub={`${fa(S.never)} دانش‌آموز · ${fa(T.never)} معلم`} color="#e8505b" />
                </div>
            </div>

            {(topTeacher?.score > 0 || topClass?.score > 0) && (
                <div className="vk-spot vk-sec">
                    {topTeacher?.score > 0 && <div className="vk-spot-card good"><h4>🏅 پرتلاش‌ترین معلم</h4><div style={{ fontSize: 18, fontWeight: 900 }}>{topTeacher.name}</div><div className="vk-note">عملکرد {fa(topTeacher.score)} از ۱۰۰ · {fa(topTeacher.produced)} محتوا/بازی/آزمون · حضورِ شاگردان {fa(topTeacher.student_rate)}٪</div></div>}
                    {topClass?.score > 0 && <div className="vk-spot-card good"><h4>🏆 پرمشارکت‌ترین کلاس</h4><div style={{ fontSize: 18, fontWeight: 900 }}>{topClass.label}</div><div className="vk-note">{fa(topClass.active)} از {fa(topClass.members)} دانش‌آموز فعال · انجامِ وظایف {topClass.completion == null ? '—' : `${fa(topClass.completion)}٪`}</div></div>}
                </div>
            )}

            <div className="panel vk-sec">
                <h3>🟢 همین حالا آنلاین ({fa(onlineNow.length)})</h3>
                {onlineNow.length ? (
                    <div className="vk-online">
                        {onlineNow.map((o, i) => <span key={i} className="vk-online-p"><Dot on /> <b>{o.name}</b> <small>{o.role}{o.where ? ` · ${o.where}` : ''}</small></span>)}
                    </div>
                ) : <div className="vk-empty">الان کسی آنلاین نیست.</div>}
            </div>

            <div className="vk-two vk-sec">
                <div className="panel"><h3>📈 بازدیدکنندگانِ روزانه‌ی مدرسه</h3><DailyBars data={series} /></div>
                <div className="panel"><h3>🏆 رتبه‌ی کلاس‌ها در مشارکت</h3><RankList items={classes} unit="دانش‌آموز" /></div>
            </div>
            <div className="vk-two vk-sec">
                <div className="panel"><h3>🕐 ساعت‌های پربازدید</h3><HoursStrip hours={hours} /></div>
                <div className="panel"><h3>📱 از چه دستگاهی؟</h3><Donut items={devices} centerLabel="روز-نفر" /></div>
            </div>
            <div className="vk-two vk-sec">
                <div className="panel"><h3>🧭 پربازدیدترین بخش‌ها</h3><HBars items={sections} colorByIndex /></div>
                <div className="panel"><h3>⚡ رقابتِ تیم‌ها در مشارکت</h3><RankList items={teams} unit="عضو" empty="هنوز تیمی تعیین نشده" /></div>
            </div>

            <div className="vk-tabs">
                <button type="button" className={tab === 'teachers' ? 'on' : ''} onClick={() => setTab('teachers')}>👩‍🏫 معلم‌ها ({fa(teachers.length)})</button>
                <button type="button" className={tab === 'students' ? 'on' : ''} onClick={() => setTab('students')}>🎓 دانش‌آموزان ({fa(students.length)})</button>
                <button type="button" className={tab === 'parents' ? 'on' : ''} onClick={() => setTab('parents')}>👪 والدین ({fa(parents.length)})</button>
            </div>
            <div className="panel" style={{ marginTop: 10 }}>
                {tab === 'teachers' && <>
                    <PeopleTable rows={teachers} columns={teacherColumns()} subOf={teacherDetail} emptyText="هنوز معلمی ثبت نشده." />
                    <div className="vk-note">«عملکرد» ترکیبی است از حضورِ خودِ معلم (۲۵٪)، محتوا و کارهای کلاسیِ ثبت‌شده (۳۵٪)، حضورِ شاگردانش (۲۰٪) و انجامِ وظایفِ شاگردانش (۲۰٪). روی هر معلم بزنید تا ریزِ کارهایش را ببینید.</div>
                </>}
                {tab === 'students' && <>
                    <Spotlight best={students.filter((r) => r.score > 0).slice(0, 5)} worst={students.filter((r) => !r.online && r.days === 0).slice(0, 6)} />
                    <div style={{ marginTop: 14 }}><PeopleTable rows={students} columns={studentColumns()} classOptions={classOptions} subOf={studentDetail} /></div>
                </>}
                {tab === 'parents' && <PeopleTable rows={parents} columns={parentColumns()} initialSort="days" emptyText="هنوز ولی‌ای ثبت نشده." />}
            </div>
            <div className="vk-note" style={{ marginTop: 8 }}>زمانِ فعال فقط وقتی شمرده می‌شود که صفحه باز و جلوی چشمِ کاربر باشد. آمار از روزِ فعال‌شدنِ این گزارش جمع می‌شود.</div>
        </DashLayout>
    );
}
