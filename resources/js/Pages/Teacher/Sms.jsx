import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import SmsComposer from '@/Components/SmsComposer';
import { LogTable } from '@/Pages/Admin/Sms';
import { QuotaCards } from '@/Pages/SchoolAdmin/Sms';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** پیامکِ معلم — فقط دانش‌آموزانِ کلاس‌های خودش و اولیای آن‌ها. */
export default function Sms() {
    const { can = {}, classrooms = [], quota = {}, log = [], flash, errors = {} } = usePage().props;
    const [tab, setTab] = useState('send');
    const banner = typeof flash?.flash === 'string' ? flash.flash : flash?.flash?.message;

    return (
        <DashLayout title="پیامک به اولیا" roleLabel="معلم" menu={teacherMenu} active="sms">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}
            {errors.message && <div className="panel" style={{ borderColor: '#f5b5ba', background: '#fdecee' }}><b style={{ color: '#b0333f' }}>{errors.message}</b></div>}

            <QuotaCards quota={quota} can={can} />

            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', margin: '14px 0' }}>
                {[{ v: 'send', t: '📤 ارسالِ پیامک' }, { v: 'log', t: `📜 سابقه‌ی من (${fa(log.length)})` }].map((t) => (
                    <button key={t.v} onClick={() => setTab(t.v)} className={`tag ${tab === t.v ? 'tag-warn' : 'tag-info'}`}
                        style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 16px', fontSize: 13 }}>{t.t}</button>
                ))}
            </div>

            {tab === 'send'
                ? <SmsComposer sendRoute={route('teacher.sms.send')} classrooms={classrooms} can={can} quota={quota} />
                : <LogTable log={log} />}
        </DashLayout>
    );
}
