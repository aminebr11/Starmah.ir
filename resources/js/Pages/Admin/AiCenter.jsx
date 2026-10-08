import { useMemo, useState } from 'react';
import { usePage, router, useForm } from '@inertiajs/react';
import axios from 'axios';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';
import { AreaTrend, HBars } from '@/Components/Charts';
import { PeriodPicker } from '@/Components/VisitKit';
import { useSort, SortTh, SortBar, firstName, lastName } from '@/lib/useSort';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const num = (n) => fa(Math.round(n || 0).toLocaleString('en-US'));
const short = (n) => (n >= 1e6 ? `${fa((n / 1e6).toFixed(1))} میلیون` : n >= 1e3 ? `${fa((n / 1e3).toFixed(1))} هزار` : fa(n || 0));
const money = (n, cur) => (n ? `${num(n)} ${cur}` : `۰ ${cur}`);

/** مرکزِ هوش مصنوعی — سرویس و مدل، آزمایشِ اتصال، مصرفِ توکن و هزینه. */
export default function AiCenter() {
    const { active, providers = [], prices = {}, currency, days, usage = {}, flash, errors = {}, server = {}, qtest: qtest0 } = usePage().props;
    const [qtest, setQtest] = useState(qtest0);
    const testQ = async () => {
        setBusy('q');
        try { const { data } = await axios.post(route('admin.ai.testq')); setQtest(data); }
        catch (e) { setQtest({ ok: false, message: e.response?.status === 429 ? 'آزمایش‌ها زیاد شد؛ یک دقیقه صبر کنید.' : `درخواست ناموفق بود (${e.response?.status || 'بدونِ پاسخ'}) — احتمالاً سقفِ زمانِ هاست.` }); }
        finally { setBusy(null); }
    };
    const banner = typeof flash?.flash === 'string' ? flash.flash : flash?.flash?.message;
    const act = providers.find((p) => p.key === active);
    const t = usage.totals || {};
    const [tests, setTests] = useState(() => Object.fromEntries(providers.map((p) => [p.key, p.test])));
    const [busy, setBusy] = useState(null);
    const [tab, setTab] = useState('schools');

    const form = useForm({
        active,
        providers: Object.fromEntries(providers.map((p) => [p.key, { key: '', model: p.model, base: p.base || '' }])),
    });
    const setP = (k, f, v) => form.setData('providers', { ...form.data.providers, [k]: { ...form.data.providers[k], [f]: v } });
    const save = (activeKey) => {
        form.transform((d) => ({ ...d, active: activeKey ?? d.active }));
        form.post(route('admin.ai.save'), { preserveScroll: true, onSuccess: () => form.setData('providers', Object.fromEntries(Object.entries(form.data.providers).map(([k, v]) => [k, { ...v, key: '' }]))) });
    };
    const test = async (k) => {
        setBusy(k);
        try {
            const { data } = await axios.post(route('admin.ai.test'), { provider: k });
            setTests((x) => ({ ...x, [k]: data }));
        } catch (e) {
            setTests((x) => ({ ...x, [k]: { ok: false, error: e.response?.status === 429 ? 'آزمایش‌ها زیاد شد؛ یک دقیقه صبر کنید.' : 'درخواست ناموفق بود.' } }));
        } finally { setBusy(null); }
    };
    const actTest = tests[active];
    const okRate = t.requests ? Math.round((t.ok / t.requests) * 100) : null;

    return (
        <DashLayout title="مرکزِ هوش مصنوعی" roleLabel="ادمین کل" menu={adminMenu} active="ai">
            {banner && <div className="ai-flash">✅ {banner}</div>}
            {errors.active && <div className="ai-flash bad">⚠️ {errors.active}</div>}

            {/* ===== وضعیتِ فعال ===== */}
            <section className="ai-hero">
                <div className={`ai-orb ${active === 'off' ? 'off' : actTest ? (actTest.ok ? 'ok' : 'bad') : 'idle'}`}>
                    <span>{active === 'off' ? '⏸️' : act?.emoji}</span>
                </div>
                <div className="ai-hero-b">
                    <small>سرویسِ فعالِ کلِ سامانه</small>
                    <h2>{active === 'off' ? 'هوش مصنوعی خاموش است' : <>{act?.label} <em>{act?.model}</em></>}</h2>
                    <p>
                        {active === 'off' ? 'همه‌ی بخش‌ها از متن‌ها و سؤال‌های آماده استفاده می‌کنند.'
                            : actTest ? (actTest.ok ? <>🟢 اتصال برقرار است · پاسخ در {fa(actTest.ms)} میلی‌ثانیه · آخرین آزمایش {actTest.at}</> : <>🔴 آخرین آزمایش ناموفق بود: {actTest.error}</>)
                                : 'هنوز آزمایش نشده — دکمه‌ی «آزمایشِ اتصال» را بزنید.'}
                        {act?.last_ok && active !== 'off' && <><br />آخرین درخواستِ موفقِ واقعی: {act.last_ok}</>}
                    </p>
                    {active !== 'off' && (
                        <button type="button" className="ai-test-big" disabled={busy === active} onClick={() => test(active)}>
                            {busy === active ? <><i className="ai-spin" /> در حالِ آزمایش…</> : '⚡ آزمایشِ اتصال'}
                        </button>
                    )}
                    {active !== 'off' && (
                        <button type="button" className="ai-test-big alt" disabled={busy === 'q'} onClick={testQ}>
                            {busy === 'q' ? <><i className="ai-spin" /> در حالِ طراحیِ ۲ سؤالِ آزمایشی…</> : '🧪 آزمایشِ طراحیِ سؤال'}
                        </button>
                    )}
                    {actTest?.ok && actTest.reply && <div className="ai-reply">💬 «{actTest.reply}»</div>}
                    {qtest && (
                        <div className={`ai-reply ${qtest.ok ? '' : 'bad'}`}>
                            {qtest.ok
                                ? <>🧪 طراحیِ سؤال کار می‌کند: {fa(qtest.n)} سؤال در {fa(qtest.seconds)} ثانیه{qtest.sample && <><br />«{qtest.sample}»</>}</>
                                : <>🧪 طراحیِ سؤال ناموفق: {qtest.message || 'پاسخی نیامد.'}</>}
                            {qtest.at && <small style={{ display: 'block', opacity: .7 }}>{qtest.at}</small>}
                        </div>
                    )}
                    {server.max_execution_time > 0 && server.max_execution_time < 60 && (
                        <div className="ai-reply bad">⏱️ سقفِ زمانِ PHP روی این هاست {fa(server.max_execution_time)} ثانیه است. مدل‌های «فکرکننده» (GPT‑5، o4) ممکن است در این زمان نرسند؛ مدل‌های سریع‌تر (gpt-4o-mini، gpt-4.1-mini، deepseek-chat، claude-haiku) را انتخاب کنید یا از پشتیبانیِ هاست بخواهید max_execution_time را ۱۲۰ کند.</div>
                    )}
                </div>
                <div className="ai-kpis">
                    <div><b>{short(t.requests)}</b><span>درخواست ({fa(days)} روز)</span></div>
                    <div><b>{short((t.in || 0) + (t.out || 0))}</b><span>توکن</span></div>
                    <div><b>{money(t.cost, currency)}</b><span>هزینه‌ی تخمینی</span></div>
                    <div><b>{okRate == null ? '—' : `${fa(okRate)}٪`}</b><span>موفقیت</span></div>
                    <div><b>{t.ms ? `${fa((t.ms / 1000).toFixed(1))} ث` : '—'}</b><span>میانگینِ پاسخ</span></div>
                </div>
            </section>

            {/* ===== سرویس‌ها ===== */}
            <div className="ai-sec-h">
                <h3>🔌 سرویس‌ها و مدل‌ها</h3>
                <button type="button" className={`ai-off ${active === 'off' ? 'on' : ''}`} onClick={() => save(active === 'off' ? (providers.find((p) => p.configured)?.key || 'anthropic') : 'off')}>
                    {active === 'off' ? '▶️ روشن‌کردنِ هوش مصنوعی' : '⏸️ خاموش‌کردنِ موقت'}
                </button>
            </div>
            <div className="ai-grid">
                {providers.map((p) => {
                    const f = form.data.providers[p.key] || {};
                    const r = tests[p.key];
                    return (
                        <article key={p.key} className={`ai-card ${p.active ? 'active' : ''}`}>
                            {p.active && <span className="ai-ribbon">فعال</span>}
                            <header>
                                <span className="ai-logo">{p.emoji}</span>
                                <div><b>{p.label}</b><small>{p.vendor}</small></div>
                                <span className={`ai-dot ${r ? (r.ok ? 'ok' : 'bad') : p.has_key ? 'idle' : 'none'}`} title={r ? (r.ok ? 'متصل' : 'خطا') : p.has_key ? 'آزمایش نشده' : 'کلید ندارد'} />
                            </header>
                            <p className="ai-hint">{p.hint}{p.site && <> · <span dir="ltr">{p.site}</span></>}</p>
                            {p.key === 'custom' && (
                                <label className="ai-f"><span>نشانیِ سرویس (Base URL)</span>
                                    <input className="input" dir="ltr" value={f.base} onChange={(e) => setP(p.key, 'base', e.target.value)} placeholder="https://api.example.com/v1" />
                                </label>
                            )}
                            <label className="ai-f"><span>کلیدِ API {p.key_hint && <em>· ذخیره‌شده: <span dir="ltr">{p.key_hint}</span></em>}</span>
                                <input className="input" type="password" dir="ltr" autoComplete="off" value={f.key} onChange={(e) => setP(p.key, 'key', e.target.value)} placeholder={p.has_key ? 'برای تغییر، کلیدِ تازه را وارد کنید' : 'کلید را اینجا بچسبانید'} />
                            </label>
                            <label className="ai-f"><span>مدل</span>
                                <input className="input" dir="ltr" list={`m-${p.key}`} value={f.model} onChange={(e) => setP(p.key, 'model', e.target.value)} placeholder={p.default} />
                                <datalist id={`m-${p.key}`}>{p.models.map((m) => <option key={m} value={m} />)}</datalist>
                            </label>
                            {p.models.length > 0 && (
                                <div className="ai-chips">{p.models.map((m) => <button type="button" key={m} className={f.model === m ? 'on' : ''} onClick={() => setP(p.key, 'model', m)} dir="ltr">{m}</button>)}</div>
                            )}
                            {r && (
                                <div className={`ai-res ${r.ok ? 'ok' : 'bad'}`}>
                                    {r.ok ? <>🟢 متصل · {fa(r.ms)}ms · {fa(r.in)}+{fa(r.out)} توکن<br /><span>«{r.reply}»</span></> : <>🔴 {r.error}</>}
                                    {r.at && <small>{r.at}</small>}
                                </div>
                            )}
                            <footer>
                                <button type="button" className="btn btn-ghost btn-sm" disabled={form.processing} onClick={() => save()}>💾 ذخیره</button>
                                <button type="button" className="btn btn-ghost btn-sm" disabled={busy === p.key || !p.has_key} onClick={() => test(p.key)} title={p.has_key ? '' : 'اول کلید را ذخیره کنید'}>
                                    {busy === p.key ? '⏳' : '⚡'} آزمایش
                                </button>
                                {!p.active && <button type="button" className="btn btn-sm" disabled={form.processing} onClick={() => save(p.key)}>⭐ فعال کن</button>}
                            </footer>
                        </article>
                    );
                })}
            </div>
            <p className="ai-note">🔒 کلیدها فقط روی سرور ذخیره می‌شوند و هرگز کامل نمایش داده نمی‌شوند. خالی‌گذاشتنِ کادرِ کلید یعنی «بدونِ تغییر». با «فعال کن»، همه‌ی بخش‌ها (دستیار، طراحیِ سؤالِ آزمون و بازی و مأموریت، کاربرگ، اطلاعیه‌نویس) بلافاصله از همان سرویس استفاده می‌کنند.</p>

            {/* ===== مصرف ===== */}
            <div className="ai-sec-h">
                <h3>📊 مصرفِ توکن و هزینه</h3>
                <PeriodPicker days={days} />
            </div>
            <div className="ai-two">
                <div className="panel"><h3>📈 توکنِ مصرفیِ روزانه</h3><AreaTrend data={usage.series || []} valueLabel="توکن" empty="هنوز مصرفی ثبت نشده" /></div>
                <div className="panel"><h3>🧩 به تفکیکِ کاربرد</h3>
                    <HBars items={(usage.byFeature || []).map((f) => ({ label: f.label, value: f.tokens, sub: `${fa(f.requests)} درخواست${f.cost ? ` · ${money(f.cost, currency)}` : ''}` }))} colorByIndex />
                </div>
            </div>

            <div className="ai-tabs">
                {[['schools', '🏫 مدارس و معلم‌ها'], ['users', '👤 پرمصرف‌ترین کاربران'], ['models', '🧠 مدل‌ها'], ['errors', '⚠️ خطاهای اخیر']].map(([k, l]) => (
                    <button key={k} type="button" className={tab === k ? 'on' : ''} onClick={() => setTab(k)}>{l}</button>
                ))}
            </div>
            <div className="panel" style={{ marginTop: 10 }}>
                {tab === 'schools' && <Schools rows={usage.schools || []} currency={currency} />}
                {tab === 'users' && (
                    <Table id="ai-users" empty="هنوز مصرفی نیست." items={usage.topUsers || []}
                        cols={[{ h: '#' }, { h: 'کاربر', k: 'family', get: (u) => lastName(u.name) }, { h: 'نقش', k: 'role' }, { h: 'مدرسه', k: 'school' }, { h: 'درخواست', k: 'requests', d: 1 }, { h: 'توکن', k: 'tokens', d: 1 }, { h: 'هزینه', k: 'cost', d: 1 }]}
                        bar={[['name', 'نام', (u) => firstName(u.name)], ['family', 'نام خانوادگی'], ['tokens', 'توکن'], ['cost', 'هزینه']]}
                        render={(u, i) => [fa(i + 1), <b key="n">{u.name}</b>, u.role, u.school, num(u.requests), num(u.tokens), money(u.cost, currency)]} />
                )}
                {tab === 'models' && (
                    <Table id="ai-models" empty="هنوز مصرفی نیست." items={usage.models || []}
                        cols={[{ h: 'سرویس', k: 'label' }, { h: 'مدل', k: 'model' }, { h: 'درخواست', k: 'requests', d: 1 }, { h: 'موفق', k: 'ok', d: 1 }, { h: 'توکنِ ورودی', k: 'in', d: 1 }, { h: 'توکنِ خروجی', k: 'out', d: 1 }, { h: 'میانگینِ پاسخ', k: 'ms', d: 1 }, { h: 'هزینه', k: 'cost', d: 1 }]}
                        render={(m) => [m.label, <span key="m" dir="ltr">{m.model}</span>, num(m.requests), num(m.ok), num(m.in), num(m.out), `${fa(m.ms)}ms`, money(m.cost, currency)]} />
                )}
                {tab === 'errors' && (
                    <Table id="ai-errors" empty="خطایی ثبت نشده 🎉" items={usage.recentErrors || []}
                        cols={[{ h: 'زمان', k: 'at', get: (e) => e.at_raw ?? e.at, d: 1 }, { h: 'سرویس', k: 'provider' }, { h: 'مدل', k: 'model' }, { h: 'کدِ خطا', k: 'status' }, { h: 'کاربرد', k: 'feature' }]}
                        render={(e) => [e.at, e.provider, <span key="m" dir="ltr">{e.model}</span>, fa(e.status), e.feature]} />
                )}
            </div>

            <Prices providers={providers} prices={prices} currency={currency} usedModels={(usage.models || []).map((m) => m.model)} />
        </DashLayout>
    );
}

/** جدولِ ساده‌ی قابلِ مرتب‌سازی. cols: [{ h: عنوان, k?: کلید, get?: (row) => مقدار, d?: اولِ کار نزولی }] */
function Table({ id, cols, items, render, empty, bar }) {
    const getters = {}, firstDir = {};
    cols.forEach((c) => { if (c.k) { getters[c.k] = c.get || c.k; if (c.d) firstDir[c.k] = 'desc'; } });
    (bar || []).forEach(([k, , get]) => { if (get && !getters[k]) getters[k] = get; });
    const s = useSort(items, getters, { id, firstDir });
    return (
        <div className="vk-tblwrap">
            {bar && items.length > 1 && <SortBar s={s} options={bar.map(([k, t]) => [k, t])} />}
            <table className="tbl vk-tbl" style={{ minWidth: 640 }}>
                <thead><tr>{cols.map((c) => (c.k ? <SortTh key={c.h} s={s} k={c.k}>{c.h}</SortTh> : <th key={c.h}>{c.h}</th>))}</tr></thead>
                <tbody>
                    {s.sorted.map((r, i) => <tr key={i}>{render(r, i).map((c, j) => <td key={j}>{c}</td>)}</tr>)}
                    {!items.length && <tr><td colSpan={cols.length} style={{ color: 'var(--muted)' }}>{empty}</td></tr>}
                </tbody>
            </table>
        </div>
    );
}

/** هر مدرسه، با بازشدن: معلم‌ها — مصرفِ خودشان و مصرفِ دانش‌آموزانشان جدا. */
function Schools({ rows, currency }) {
    const [open, setOpen] = useState(null);
    const ss = useSort(rows, { name: 'name', tokens: 'tokens', cost: 'cost', requests: 'requests', users: 'users' }, { id: 'ai-schools', firstDir: { tokens: 'desc', cost: 'desc', requests: 'desc', users: 'desc' } });
    if (!rows.length) return <div className="vk-empty">هنوز مصرفی ثبت نشده. با اولین استفاده از دستیار یا طراحیِ سؤال، اینجا پر می‌شود.</div>;
    const max = Math.max(1, ...rows.map((r) => r.tokens));
    return (
        <div className="ai-schools">
            {rows.length > 1 && <SortBar s={ss} options={[['tokens', 'توکن'], ['cost', 'هزینه'], ['requests', 'درخواست'], ['users', 'کاربر'], ['name', 'نام مدرسه']]} />}
            {ss.sorted.map((s) => (
                <div key={s.id ?? 'none'} className="ai-school">
                    <button type="button" className="ai-school-h" onClick={() => setOpen(open === s.id ? null : s.id)}>
                        <span className="ai-school-n"><b>🏫 {s.name}</b><small>{fa(s.requests)} درخواست · {fa(s.users)} کاربر · ورودی {short(s.in)} / خروجی {short(s.out)}</small></span>
                        <span className="ai-school-v"><b>{short(s.tokens)}</b><small>{money(s.cost, currency)}</small></span>
                        <span className="ai-more">{open === s.id ? '▴' : '▾'}</span>
                    </button>
                    <div className="ai-bar"><i style={{ width: `${(s.tokens / max) * 100}%` }} /></div>
                    {open === s.id && (
                        s.teachers.length ? (
                            <SchoolTeachers teachers={s.teachers} currency={currency} />
                        ) : <div className="vk-empty">در این مدرسه مصرفی به نامِ معلمی ثبت نشده (مثلاً فقط مدیر استفاده کرده).</div>
                    )}
                </div>
            ))}
        </div>
    );
}

/** جدولِ معلم‌های یک مدرسه (کامپوننتِ جدا تا هوکِ مرتب‌سازی داخلِ حلقه صدا زده نشود). */
function SchoolTeachers({ teachers, currency }) {
    const ts = useSort(teachers, { name: (r) => firstName(r.name), family: (r) => lastName(r.name), own: 'own_tokens', stu: 'stu_tokens', tokens: 'tokens', cost: 'cost' }, { id: 'ai-school-teachers', firstDir: { own: 'desc', stu: 'desc', tokens: 'desc', cost: 'desc' } });
    return (
        <div className="vk-tblwrap"><table className="tbl vk-tbl" style={{ minWidth: 620, marginTop: 8 }}>
            <thead><tr><SortTh s={ts} k="family">معلم</SortTh><SortTh s={ts} k="own">مصرفِ خودِ معلم</SortTh><SortTh s={ts} k="stu">مصرفِ دانش‌آموزانش</SortTh><SortTh s={ts} k="tokens">جمع</SortTh><SortTh s={ts} k="cost">هزینه</SortTh></tr></thead>
            <tbody>{ts.sorted.map((tt) => (
                <tr key={tt.id}>
                    <td><b>{tt.name}</b></td>
                    <td>{num(tt.own_tokens)} <small style={{ color: 'var(--muted)' }}>({fa(tt.own_req)} درخواست)</small></td>
                    <td>{num(tt.stu_tokens)} <small style={{ color: 'var(--muted)' }}>({fa(tt.stu_req)} درخواست)</small></td>
                    <td><b>{num(tt.tokens)}</b></td>
                    <td>{money(tt.cost, currency)}</td>
                </tr>
            ))}</tbody>
        </table></div>
    );
}

function Prices({ providers, prices, currency, usedModels }) {
    const [all, setAll] = useState(false);
    const allModels = useMemo(() => {
        const set = new Set();
        providers.forEach((p) => { set.add(p.model); p.models.forEach((m) => set.add(m)); });
        usedModels.forEach((m) => m && m !== '—' && set.add(m));
        return [...set].filter(Boolean);
    }, [providers, usedModels]);
    // پیش‌فرض: فقط مدل‌های سرویس‌های کلیددار، مدل‌های استفاده‌شده و مدل‌هایی که قیمت دارند
    const focus = useMemo(() => new Set([
        ...providers.filter((p) => p.has_key || p.active).map((p) => p.model),
        ...usedModels.filter((m) => m && m !== '—'), ...Object.keys(prices),
    ]), [providers, usedModels, prices]);
    const models = all ? allModels : allModels.filter((m) => focus.has(m));
    const form = useForm({ currency, prices: Object.fromEntries(allModels.map((m) => [m, { in: prices[m]?.in ?? '', out: prices[m]?.out ?? '' }])) });
    const set = (m, f, v) => form.setData('prices', { ...form.data.prices, [m]: { ...form.data.prices[m], [f]: v } });
    return (
        <div className="panel ai-prices">
            <h3>💰 قیمتِ پایه‌ی توکن</h3>
            <p className="ai-note" style={{ marginTop: 0 }}>
                قیمتِ هر <b>یک میلیون توکن</b> را برای ورودی (متنی که می‌فرستیم) و خروجی (پاسخِ مدل) وارد کنید؛ هزینه‌ی هر مدرسه، معلم و کاربر خودکار حساب می‌شود.
                مثلاً اگر قیمتِ خروجی ۲۰۰٬۰۰۰ تومان باشد، پاسخی با ۵۰۰ توکن حدودِ ۱۰۰ تومان می‌شود.
            </p>
            <div className="ai-cur">واحد:
                {['تومان', 'دلار', 'ریال'].map((c) => <button type="button" key={c} className={form.data.currency === c ? 'on' : ''} onClick={() => form.setData('currency', c)}>{c}</button>)}
            </div>
            <div className="vk-tblwrap">
                <table className="tbl vk-tbl" style={{ minWidth: 560 }}>
                    <thead><tr><th>مدل</th><th>ورودی (هر ۱ میلیون توکن)</th><th>خروجی (هر ۱ میلیون توکن)</th></tr></thead>
                    <tbody>{models.map((m) => (
                        <tr key={m}>
                            <td dir="ltr" style={{ textAlign: 'right' }}><b>{m}</b></td>
                            <td><input className="input" type="number" min="0" step="any" dir="ltr" value={form.data.prices[m]?.in ?? ''} onChange={(e) => set(m, 'in', e.target.value)} placeholder="۰" /></td>
                            <td><input className="input" type="number" min="0" step="any" dir="ltr" value={form.data.prices[m]?.out ?? ''} onChange={(e) => set(m, 'out', e.target.value)} placeholder="۰" /></td>
                        </tr>
                    ))}</tbody>
                </table>
            </div>
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 12 }}>
                <button type="button" className="btn" disabled={form.processing} onClick={() => form.post(route('admin.ai.prices'), { preserveScroll: true })}>💾 ذخیره‌ی قیمت‌ها</button>
                {allModels.length > models.length || all ? <button type="button" className="btn btn-ghost" onClick={() => setAll(!all)}>{all ? 'فقط مدل‌های در حالِ استفاده' : `نمایشِ همه‌ی مدل‌ها (${fa(allModels.length)})`}</button> : null}
            </div>
        </div>
    );
}
