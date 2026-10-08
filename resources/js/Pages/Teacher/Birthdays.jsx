import { useState, useEffect } from 'react';
import { usePage, useForm } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import PersonCell from '@/Components/PersonCell';
import { useSort, SortBar, firstName, lastName } from '@/lib/useSort';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const first = (n) => String(n || '').trim().split(/\s+/)[0] || n;
const when = (o) => (o === 0 ? 'امروز 🎉' : o === 1 ? 'فردا' : o > 0 ? `${fa(o)} روزِ دیگر` : o === -1 ? 'دیروز' : `${fa(-o)} روز پیش`);
const GIFTS = [0, 10, 20, 50];

/** تولدِ دانش‌آموزان — انتخابِ قالبِ تبریک، هدیه‌ی امتیاز و پیامک به ولی. */
export default function Birthdays() {
    const { birthdays = [], templates = [], teacher = '', smsOk = false, smsReason = '', focus = null, flash } = usePage().props;
    const [openId, setOpenId] = useState(focus || birthdays.find((b) => b.offset === 0 && !b.sent)?.id || null);
    const banner = typeof flash?.flash === 'string' ? flash.flash : flash?.flash?.message;
    const bs = useSort(birthdays, { when: 'offset', name: (r) => firstName(r.name), family: (r) => lastName(r.name), age: 'age', cls: 'class' }, { id: 'teacher-birthdays', firstDir: { age: 'desc' } });
    const today = bs.sorted.filter((b) => b.offset === 0);
    const soon = bs.sorted.filter((b) => b.offset > 0);
    const past = bs.sorted.filter((b) => b.offset < 0);

    return (
        <DashLayout title="تولدِ دانش‌آموزان" roleLabel="معلم" menu={teacherMenu} active="birthdays">
            {banner && <div className="panel bd-flash"><b>{banner}</b></div>}

            <div className="panel bd-intro">
                <span className="bd-cake" aria-hidden="true">🎂</span>
                <div>
                    <b>یک تبریکِ کوچک، یک روزِ بزرگ</b>
                    <p>تاریخِ تولد از پرونده‌ی هر دانش‌آموز (به تقویمِ شمسی) خوانده می‌شود. روزِ تولد در زنگوله‌ی شما اعلان می‌آید؛ اینجا یک قالب انتخاب کنید، اگر خواستید هدیه‌ی امتیاز بدهید و بفرستید.</p>
                </div>
            </div>

            {birthdays.length === 0 && (
                <div className="panel" style={{ color: 'var(--muted)' }}>در ۳۰ روزِ آینده تولدی نیست. اگر تاریخِ تولدِ دانش‌آموزی ثبت نشده، از «دانش‌آموزان ← پرونده ← ویرایش» اضافه‌اش کنید.</div>
            )}

            {birthdays.length > 1 && <SortBar s={bs} options={[['when', 'تاریخ تولد'], ['name', 'نام'], ['family', 'نام خانوادگی'], ['age', 'سن'], ['cls', 'کلاس']]} />}

            {[['🎉 امروز', today], ['📅 به‌زودی (۳۰ روزِ آینده)', soon], ['⏪ هفته‌ی گذشته — هنوز دیر نیست', past]].map(([t, list]) => list.length > 0 && (
                <div key={t} className="panel">
                    <h3>{t}</h3>
                    <div className="bd-list">
                        {list.map((b) => (
                            <div key={b.id} className={`bd-item ${b.offset === 0 ? 'today' : ''}`}>
                                <div className="bd-row">
                                    <PersonCell name={b.name} avatar={b.avatar} sub={[b.class, b.team].filter(Boolean).join(' · ')} size={42} />
                                    <div className="bd-when">
                                        <b>{when(b.offset)}</b>
                                        <small>{b.date}{b.age ? ` · ${fa(b.age)} ساله` : ''}</small>
                                    </div>
                                    {b.sent
                                        ? <span className="tag tag-ok">✅ تبریک فرستاده شد</span>
                                        : <button type="button" className="btn btn-sm" onClick={() => setOpenId(openId === b.id ? null : b.id)}>
                                            {openId === b.id ? 'بستن' : '🎁 تبریک بفرست'}</button>}
                                </div>
                                {openId === b.id && !b.sent && (
                                    <Composer b={b} templates={templates} teacher={teacher} smsOk={smsOk} smsReason={smsReason} onDone={() => setOpenId(null)} />
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </DashLayout>
    );
}

function Composer({ b, templates: all, teacher, smsOk, smsReason, onDone }) {
    // قالب‌هایی که سن را می‌گویند فقط وقتی تاریخِ تولد سال هم دارد
    const templates = all.filter((t) => b.age || !t.includes('{age}'));
    const render = (t) => t.replaceAll('{name}', first(b.name)).replaceAll('{age}', b.age ? fa(b.age) : '').replaceAll('{teacher}', teacher);
    const [pick, setPick] = useState(0);
    const f = useForm({ text: templates[0] || '', gift: 10, to_parent: false });
    useEffect(() => { f.setData('text', templates[pick] || ''); /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [pick]);
    const send = (e) => { e.preventDefault(); f.post(route('teacher.birthdays.send', b.id), { preserveScroll: true, onSuccess: onDone }); };

    return (
        <form onSubmit={send} className="bd-compose">
            <div className="bd-tpls">
                {templates.map((t, i) => (
                    <button type="button" key={i} className={`bd-tpl ${pick === i ? 'on' : ''}`} onClick={() => setPick(i)}>{render(t)}</button>
                ))}
            </div>
            <div className="field" style={{ margin: '10px 0 0' }}>
                <label>متنِ نهایی (می‌توانید ویرایش کنید)</label>
                <textarea className="input" rows={3} value={render(f.data.text)} onChange={(e) => f.setData('text', e.target.value)} />
                {f.errors.text && <small style={{ color: '#e8505b' }}>{f.errors.text}</small>}
            </div>
            <div className="bd-opts">
                <span>🎁 هدیه‌ی امتیاز:</span>
                {GIFTS.map((g) => (
                    <button type="button" key={g} className={`filter-chip ${f.data.gift === g ? 'on' : ''}`} onClick={() => f.setData('gift', g)}>{g ? `+${fa(g)}` : 'بدونِ هدیه'}</button>
                ))}
            </div>
            <label className="bd-sms" title={smsOk ? '' : smsReason}>
                <input type="checkbox" disabled={!smsOk || !b.has_parent} checked={f.data.to_parent} onChange={(e) => f.setData('to_parent', e.target.checked)} />
                📩 پیامکِ همین تبریک به ولی هم برود
                {!smsOk && <small> — {smsReason}</small>}
                {smsOk && !b.has_parent && <small> — شماره‌ی ولی ثبت نشده</small>}
            </label>
            <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                <button type="submit" className="btn" disabled={f.processing}>{f.processing ? 'در حال ارسال…' : `🎂 ارسالِ تبریک به ${first(b.name)}`}</button>
                <button type="button" className="btn btn-ghost" onClick={onDone}>انصراف</button>
            </div>
        </form>
    );
}
