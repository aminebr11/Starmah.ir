import { useState } from 'react';
import { makeSubjectPalette } from '@/lib/subjects';

/**
 * نمایشِ «تسلط» برای دانش‌آموز و والد — کلی، درس‌به‌درس، مبحث‌ها و
 * توضیحِ ساده‌ی این‌که عدد از کجا آمده. داده از MasteryService.
 */
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const SRC = { exam: ['🧠', 'آزمون'], game: ['🎮', 'بازی'], mission: ['🎯', 'مأموریت'], grade: ['📔', 'نمره‌ی معلم'], homework: ['📒', 'تکلیف'], legacy: ['✏️', 'تمرین'] };

function Ring({ value, color = '#8896ad', size = 132, label }) {
    const R = size / 2 - 10, C = size / 2, circ = 2 * Math.PI * R;
    const v = value ?? 0;
    return (
        <div className="mp-ring" style={{ width: size, height: size }}>
            <svg viewBox={`0 0 ${size} ${size}`} style={{ transform: 'rotate(-90deg)' }}>
                <circle cx={C} cy={C} r={R} fill="none" stroke="#edf0f6" strokeWidth="12" />
                <circle cx={C} cy={C} r={R} fill="none" stroke={color} strokeWidth="12" strokeLinecap="round"
                    strokeDasharray={`${(v / 100) * circ} ${circ}`} style={{ transition: 'stroke-dasharray .8s' }} />
            </svg>
            <div className="mp-ring-v"><b style={{ color }}>{value == null ? '؟' : `${fa(value)}٪`}</b>{label && <small>{label}</small>}</div>
        </div>
    );
}

function Confidence({ v }) {
    const dots = v >= 70 ? 3 : v >= 40 ? 2 : v > 0 ? 1 : 0;
    const t = ['کم', 'کم', 'متوسط', 'زیاد'][dots];
    return <span className="mp-conf" title={`اطمینان از برآورد: ${t}`}>{[1, 2, 3].map((i) => <i key={i} className={i <= dots ? 'on' : ''} />)}<small>اطمینان {t}</small></span>;
}

export default function MasteryPanel({ data, levels = [], who = 'kid' }) {
    const [open, setOpen] = useState(null);
    const [help, setHelp] = useState(false);
    if (!data) return null;
    const pal = makeSubjectPalette((data.subjects || []).map((s) => s.name));
    const lvl = data.level;
    const you = who === 'kid' ? 'تو' : 'فرزندتان';

    return (
        <div className="mp">
            {/* کلی */}
            <div className="mp-hero">
                <Ring value={data.overall} color={lvl?.color} label="تسلطِ کلی" />
                <div className="mp-hero-b">
                    {data.overall == null ? (
                        <>
                            <h3>🧭 هنوز داده‌ی کافی نداریم</h3>
                            <p>{who === 'kid' ? 'هر آزمون، بازی و مأموریتی که انجام بدهی' : 'هر آزمون، بازی و مأموریتی که فرزندتان انجام دهد'} یک «نشانه» از یادگیری است. وقتی برای یک درس دستِ‌کم ۳ نشانه جمع شود — یا معلم یک نمره‌ی کلاسی در آن درس ثبت کند — تسلطش اینجا محاسبه می‌شود.</p>
                        </>
                    ) : (
                        <>
                            <span className="mp-badge" style={{ background: lvl.color }}>{lvl.emoji} {lvl.label}</span>
                            <h3>{who === 'kid' ? lvl.kid : `سطحِ کلیِ ${you}: ${lvl.label}`}</h3>
                            <p>بر پایه‌ی <b>{fa(data.observations)}</b> پاسخ و نتیجه در آزمون‌ها، بازی‌ها، مأموریت‌ها، تکلیف‌ها و نمره‌های معلم. <Confidence v={data.confidence} /></p>
                        </>
                    )}
                    <button type="button" className="mp-help-btn" onClick={() => setHelp(!help)}>{help ? 'بستن' : '❓ تسلط چطور حساب می‌شود؟'}</button>
                </div>
            </div>

            {help && (
                <div className="mp-help">
                    <ul>
                        <li>🧩 <b>هر پاسخ یک نشانه است.</b> جوابِ درست، نزدیک‌شدن به تسلط و جوابِ غلط، نشانه‌ی جایی است که باید تمرین شود.</li>
                        <li>📔 <b>نمره‌های کلاسیِ معلم</b> (عددی، توصیفی و تکلیف) از دفترِ کلاسی مستقیم در تسلطِ همان درس و مبحث حساب می‌شوند؛ یک نمره‌ی معلم به‌تنهایی برای شروعِ محاسبه کافی است.</li>
                        <li>⚖️ <b>همه‌ی نشانه‌ها هم‌وزن نیستند.</b> نمره‌ی معلم و آزمون سنگین‌تر از بازی حساب می‌شوند (در بازی راهنما و تکرار هست). درست‌زدنِ سؤالِ سخت هم امتیازِ بیشتری از سؤالِ آسان دارد.</li>
                        <li>⏳ <b>کارهای تازه مهم‌ترند.</b> چون ذهن به‌مرور فراموش می‌کند (منحنیِ فراموشیِ ابینگهاوس)، اثرِ هر نتیجه حدودِ هر ۴۵ روز نصف می‌شود؛ پس با تمرینِ دوباره، تسلط دوباره بالا می‌رود.</li>
                        <li>🎲 <b>یک جواب کسی را صفر یا صد نمی‌کند.</b> با چند نشانه، عدد نزدیکِ وسط می‌ماند و هرچه نشانه بیشتر شود، به عملکردِ واقعی نزدیک‌تر و «اطمینان» بیشتر می‌شود (برآوردِ آماریِ بیزی).</li>
                        <li>🏁 <b>سطح‌ها</b> بر پایه‌ی «یادگیری در حدِ تسلط» هستند: «خیلی خوب» یعنی ۸۵٪ یا بیشتر؛ «خوب» ۷۰٪، «قابل قبول» ۵۰٪ (همان چهار سطحِ ارزشیابیِ توصیفی).</li>
                    </ul>
                    <div className="mp-levels">
                        {levels.map((l) => <span key={l.key} style={{ background: l.color }}>{l.emoji} {l.label} <small>{l.min > 0 ? `${fa(l.min)}٪+` : `زیرِ ${fa(50)}٪`}</small></span>)}
                    </div>
                </div>
            )}

            {/* درس‌ها */}
            <div className="mp-grid">
                {(data.subjects || []).map((s) => {
                    const c = pal(s.name);
                    const isOpen = open === s.name;
                    const rated = s.mastery != null;
                    return (
                        <div key={s.name} className={`mp-subj ${rated ? '' : 'pending'}`} style={{ '--sa': c.a, '--sb': c.b }}>
                            <button type="button" className="mp-subj-top" onClick={() => setOpen(isOpen ? null : s.name)}>
                                <span className="mp-subj-ic" style={{ background: c.grad }}>{c.emoji}</span>
                                <span className="mp-subj-n">
                                    <b>{s.name}</b>
                                    <small>{rated ? <>{s.level.emoji} {s.level.label}</> : `${fa(s.n)} نشانه — ${fa(s.need || 1)} نشانه‌ی دیگر (یا یک نمره‌ی معلم) لازم است`}</small>
                                </span>
                                <span className="mp-subj-v" style={{ color: rated ? s.level.color : '#9aa4ba' }}>
                                    {rated ? `${fa(s.mastery)}٪` : '…'}
                                    {s.trend != null && s.trend !== 0 && <em className={s.trend > 0 ? 'up' : 'down'}>{s.trend > 0 ? '▲' : '▼'} {fa(Math.abs(s.trend))}</em>}
                                </span>
                            </button>
                            <div className="mp-bar"><i style={{ width: `${rated ? s.mastery : 0}%`, background: rated ? s.level.color : '#c9d0de' }} />{[50, 70, 85].map((m) => <u key={m} style={{ insetInlineStart: `${m}%` }} />)}</div>
                            <div className="mp-subj-meta">
                                {Object.entries(s.sources || {}).map(([k, n]) => <span key={k}>{SRC[k]?.[0]} {SRC[k]?.[1]} {fa(n)}</span>)}
                                {rated && <Confidence v={s.confidence} />}
                            </div>
                            {isOpen && (
                                <div className="mp-topics">
                                    {s.raw != null && <div className="mp-raw">درصدِ سادهِ پاسخ‌های درست: <b>{fa(s.raw)}٪</b> از {fa(s.n)} نشانه</div>}
                                    {s.topics?.length ? s.topics.map((t) => (
                                        <div key={t.name} className="mp-topic">
                                            <span>{t.name}</span>
                                            {t.mastery != null
                                                ? <b style={{ color: t.level.color }}>{t.level.emoji} {fa(t.mastery)}٪</b>
                                                : <small>{fa(t.n)} نشانه</small>}
                                        </div>
                                    )) : <div className="mp-raw">برای این درس هنوز مبحثِ جداگانه‌ای ثبت نشده.</div>}
                                    {rated && <div className="mp-kid">💬 {s.level.kid}</div>}
                                </div>
                            )}
                        </div>
                    );
                })}
                {!(data.subjects || []).length && <div className="mp-empty">هنوز هیچ فعالیتِ درسی ثبت نشده. اولین بازی یا مأموریت را انجام بده 🚀</div>}
            </div>

            {(data.weakTopics?.length > 0 || data.strongTopics?.length > 0) && (
                <div className="mp-two">
                    {data.weakTopics?.length > 0 && (
                        <div className="mp-box warn">
                            <h4>🎯 بهترین چیزها برای تمرینِ این هفته</h4>
                            {data.weakTopics.map((t) => <div key={t.subject + t.name} className="mp-topic"><span>{t.name} <small>({t.subject})</small></span><b style={{ color: t.level.color }}>{fa(t.mastery)}٪</b></div>)}
                        </div>
                    )}
                    {data.strongTopics?.length > 0 && (
                        <div className="mp-box good">
                            <h4>🏆 مبحث‌هایی که {you === 'تو' ? 'مسلطی' : 'مسلط است'}</h4>
                            {data.strongTopics.map((t) => <div key={t.subject + t.name} className="mp-topic"><span>{t.name} <small>({t.subject})</small></span><b style={{ color: t.level.color }}>{fa(t.mastery)}٪</b></div>)}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

/** نشانِ کوچکِ تسلط برای جدول‌ها و کاشی‌ها: عدد یا «—». */
export function MasteryTag({ value, title }) {
    if (value == null) return <span className="tag" title={title || 'هنوز داده‌ی کافی نیست'} style={{ background: '#eef1f6', color: '#7a8499' }}>—</span>;
    const cls = value >= 85 ? 'tag-ok' : value >= 70 ? 'tag-info' : value >= 50 ? 'tag-warn' : 'tag-bad';
    return <span className={`tag ${cls}`} title={title}>{fa(value)}٪</span>;
}
