import { useEffect, useMemo, useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
import '@/../css/xp-rules.css';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const num = (n) => Number(n || 0).toLocaleString('fa-IR');
const PCT_PRESETS = [0, 50, 100, 150, 200];

/** مقدارِ نهاییِ یک نمونه برای قاعده‌ی درصدی (همان گردکردنِ سرور، با حفظِ علامت). */
const scaled = (n, pct) => Math.round(Math.abs(n) * pct / 100);

const same = (a, b) => a.on === b.on && (a.amount ?? null) === (b.amount ?? null) && (a.pct ?? null) === (b.pct ?? null) && (a.cap ?? null) === (b.cap ?? null);

const pick = (groups) => {
    const out = {};
    groups.forEach((g) => g.rules.forEach((r) => { out[r.key] = { ...r.value }; }));
    return out;
};

function Switch({ on, onChange, label }) {
    return (
        <button type="button" role="switch" aria-checked={on} aria-label={label} className={`xr-switch ${on ? 'on' : ''}`} onClick={() => onChange(!on)}>
            <span className="xr-knob" />
        </button>
    );
}

/** کنترلِ مقدارِ ثابت (و جریمه‌ها با «کسرِ …»). */
function FixedControl({ rule, v, set, max }) {
    const amount = v.amount ?? 0;
    const step = (d) => set({ amount: Math.max(0, Math.min(max, amount + d)) });
    const penalty = rule.sign < 0;
    return (
        <div className="xr-fixed">
            <span className="xr-ctl-l">{penalty ? 'مقدارِ کسر' : 'مقدارِ امتیاز'}</span>
            <div className="xr-stepper">
                <button type="button" onClick={() => step(-1)} disabled={!v.on || amount <= 0} aria-label="کمتر">−</button>
                <input type="number" inputMode="numeric" min={0} max={max} value={amount} disabled={!v.on}
                    onChange={(e) => set({ amount: Math.max(0, Math.min(max, parseInt(e.target.value || '0', 10) || 0)) })} aria-label={rule.label} />
                <button type="button" onClick={() => step(1)} disabled={!v.on || amount >= max} aria-label="بیشتر">+</button>
            </div>
            <span className={`xr-pill ${penalty ? 'bad' : 'good'}`}>{penalty ? `${fa(amount)}- امتیاز` : `+${fa(amount)} امتیاز`}</span>
        </div>
    );
}

/** کنترلِ ضریبِ درصدی با نمونه‌ی زنده. */
function ScaleControl({ rule, v, set, max }) {
    const pct = v.pct ?? 100;
    const [n, tpl] = rule.ex || [10, '{n} امتیاز'];
    const out = scaled(n, pct);
    const penalty = rule.sign < 0;
    return (
        <div className="xr-scale">
            <div className="xr-scale-top">
                <span className="xr-ctl-l">ضریب</span>
                <b className={`xr-pct ${pct > 100 ? 'up' : pct < 100 ? 'down' : ''}`}>{fa(pct)}٪</b>
                <div className="xr-presets">
                    {PCT_PRESETS.map((p) => (
                        <button type="button" key={p} className={pct === p ? 'on' : ''} disabled={!v.on} onClick={() => set({ pct: p })}>{fa(p)}٪</button>
                    ))}
                </div>
            </div>
            <input type="range" className="xr-range" min={0} max={max} step={5} value={pct} disabled={!v.on}
                style={{ '--xr-fill': `${(pct / max) * 100}%` }}
                onChange={(e) => set({ pct: parseInt(e.target.value, 10) })} aria-label={`ضریبِ ${rule.label}`} />
            <div className="xr-example">
                <span>🧮 {tpl.replace('{n}', fa(n))}</span>
                <span className="xr-arrow">←</span>
                <b className={penalty ? 'bad' : ''}>{!v.on || out === 0 ? 'بدونِ امتیاز' : penalty ? `کسرِ ${fa(out)} امتیاز` : `${fa(out)} امتیاز`}</b>
            </div>
        </div>
    );
}

/** امتیازِ دستیِ معلم: سقفِ اختیاریِ هر بار. */
function ManualControl({ rule, v, set, max }) {
    const capped = v.cap !== null && v.cap !== undefined;
    return (
        <div className="xr-manual">
            <span className="xr-ctl-l">سقفِ هر بار</span>
            <div className="xr-seg">
                <button type="button" className={!capped ? 'on' : ''} disabled={!v.on} onClick={() => set({ cap: null })}>بدونِ سقف</button>
                <button type="button" className={capped ? 'on' : ''} disabled={!v.on} onClick={() => set({ cap: capped ? v.cap : 20 })}>با سقف</button>
            </div>
            {capped && (
                <div className="xr-cap">
                    <input type="number" inputMode="numeric" min={1} max={max} value={v.cap} disabled={!v.on}
                        onChange={(e) => set({ cap: Math.max(1, Math.min(max, parseInt(e.target.value || '1', 10) || 1)) })} aria-label={`سقفِ ${rule.label}`} />
                    <small>امتیاز (برای افزودن و کم‌کردن)</small>
                </div>
            )}
        </div>
    );
}

function RuleRow({ rule, v, set, limits }) {
    const isDefault = same(v, rule.default);
    const changed = !same(v, rule.value);
    const Ctl = rule.kind === 'fixed' ? FixedControl : rule.kind === 'scale' ? ScaleControl : ManualControl;
    const max = rule.kind === 'fixed' ? limits.fixed : rule.kind === 'scale' ? limits.pct : limits.cap;

    return (
        <div className={`xr-rule ${v.on ? '' : 'off'} ${changed ? 'dirty' : ''}`}>
            <div className="xr-rule-h">
                <div className="xr-ic" aria-hidden>{rule.icon}</div>
                <div className="xr-rule-t">
                    <div className="xr-rule-name">
                        <b>{rule.label}</b>
                        {isDefault
                            ? <span className="xr-badge def">پیش‌فرض</span>
                            : <button type="button" className="xr-badge mod" title="برگرداندنِ همین مورد به پیش‌فرض" onClick={() => set({ ...rule.default })}>↺ پیش‌فرض</button>}
                        {changed && <span className="xr-badge new">ذخیره‌نشده</span>}
                    </div>
                    <p>{rule.desc}</p>
                </div>
                <Switch on={v.on} label={`${v.on ? 'خاموش‌کردنِ' : 'روشن‌کردنِ'} ${rule.label}`} onChange={(on) => set({ on })} />
            </div>
            {v.on ? <Ctl rule={rule} v={v} set={set} max={max} /> : <div className="xr-offnote">😴 خاموش است — از این منبع هیچ امتیازی ثبت نمی‌شود.</div>}
            {rule.stats && (
                <div className="xr-stat">📈 ۳۰ روزِ اخیر: <b>{num(rule.stats.count)}</b> بار · <b dir="ltr">{num(rule.stats.total)}</b> امتیاز</div>
            )}
        </div>
    );
}

/** مدیرِ مدرسه: «⚡ مدیریتِ امتیازها» — کدام فعالیت XP بدهد و چقدر. */
export default function XpRules() {
    const { groups = [], limits = { fixed: 1000, pct: 300, cap: 1000 }, schoolName, ready = true, hasStats = false, errors = {} } = usePage().props;
    const initial = useMemo(() => pick(groups), [groups]);
    const [vals, setVals] = useState(initial);
    const [saving, setSaving] = useState(false);
    useEffect(() => { setVals(initial); }, [initial]);

    const all = groups.flatMap((g) => g.rules);
    const dirtyKeys = all.filter((r) => vals[r.key] && !same(vals[r.key], r.value)).map((r) => r.key);
    const custom = all.filter((r) => vals[r.key] && !same(vals[r.key], r.default)).length;
    const onCount = all.filter((r) => vals[r.key]?.on).length;
    const total30 = all.reduce((s, r) => s + (r.stats?.total || 0), 0);

    useEffect(() => {
        if (!dirtyKeys.length) return undefined;
        const warn = (e) => { e.preventDefault(); e.returnValue = ''; };
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, [dirtyKeys.length]);

    const setRule = (key) => (patch) => setVals((s) => ({ ...s, [key]: { ...s[key], ...patch } }));
    const setGroup = (g, on) => setVals((s) => {
        const next = { ...s };
        g.rules.forEach((r) => { next[r.key] = { ...next[r.key], on }; });
        return next;
    });

    const save = () => {
        setSaving(true);
        router.put(route('school.xp-rules.update'), { rules: vals }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };
    const discard = () => setVals(initial);
    const resetAll = () => {
        if (!confirm('همه‌ی امتیازها به حالتِ پیش‌فرضِ سیستم برگردد؟ تنظیم‌های فعلیِ مدرسه پاک می‌شود.')) return;
        router.post(route('school.xp-rules.reset'), {}, { preserveScroll: true });
    };
    const jump = (key) => document.getElementById(`xr-g-${key}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    const firstError = Object.values(errors)[0];

    return (
        <DashLayout title="مدیریتِ امتیازها" roleLabel="مدیر مدرسه" menu={schoolMenu} active="xprules">
            <div className="xr">
                <div className="xr-hero">
                    <div className="xr-hero-t">
                        <div className="xr-bolt" aria-hidden>⚡</div>
                        <div>
                            <h2>مدیریتِ امتیازها</h2>
                            <p>این‌جا تعیین می‌کنید بچه‌های {schoolName ? `«${schoolName}»` : 'مدرسه'} برای چه کارهایی امتیاز (XP) بگیرند و چقدر. هر تغییری فقط روی امتیازهای <b>بعدی</b> اثر دارد؛ امتیازهای گرفته‌شده سرِ جایشان می‌مانند.</p>
                        </div>
                    </div>
                    <div className="xr-kpis">
                        <div><em>{fa(onCount)}<small>/{fa(all.length)}</small></em><span>منبعِ روشن</span></div>
                        <div><em>{fa(custom)}</em><span>تفاوت با پیش‌فرض</span></div>
                        <div><em dir="ltr">{hasStats ? num(total30) : '—'}</em><span>امتیازِ ۳۰ روزِ اخیر</span></div>
                    </div>
                </div>

                {!ready && <div className="xr-warn">⏳ پایگاه‌داده هنوز به‌روز نشده؛ تا چند لحظه‌ی دیگر ذخیره‌کردن ممکن می‌شود.</div>}
                {firstError && <div className="xr-warn bad">⚠️ {firstError}</div>}

                <div className="xr-tips">
                    <div><span>🔘</span><p><b>کلید</b> یعنی این منبع امتیاز بدهد یا نه.</p></div>
                    <div><span>🎚️</span><p><b>ضریب</b> برای امتیازهایی است که معلم یا خودِ بازی تعیین می‌کند؛ ۱۰۰٪ یعنی بدونِ تغییر.</p></div>
                    <div><span>🧢</span><p><b>سقف</b> بیشترین امتیازی است که معلم در یک بار می‌تواند دستی بدهد یا کم کند.</p></div>
                </div>

                <nav className="xr-chips" aria-label="دسته‌ها">
                    {groups.map((g) => {
                        const on = g.rules.filter((r) => vals[r.key]?.on).length;
                        return (
                            <button type="button" key={g.key} onClick={() => jump(g.key)}>
                                <span aria-hidden>{g.icon}</span>{g.label}<i>{fa(on)}/{fa(g.rules.length)}</i>
                            </button>
                        );
                    })}
                </nav>

                {groups.map((g) => {
                    const on = g.rules.filter((r) => vals[r.key]?.on).length;
                    return (
                        <section className="xr-group" id={`xr-g-${g.key}`} key={g.key}>
                            <header className="xr-group-h">
                                <span className="xr-group-ic" aria-hidden>{g.icon}</span>
                                <h3>{g.label}</h3>
                                <small>{fa(on)} از {fa(g.rules.length)} روشن</small>
                                <div className="xr-group-act">
                                    <button type="button" onClick={() => setGroup(g, true)} disabled={on === g.rules.length}>همه روشن</button>
                                    <button type="button" onClick={() => setGroup(g, false)} disabled={on === 0}>همه خاموش</button>
                                </div>
                            </header>
                            <div className="xr-rules">
                                {g.rules.map((r) => vals[r.key] && (
                                    <RuleRow key={r.key} rule={r} v={vals[r.key]} set={setRule(r.key)} limits={limits} />
                                ))}
                            </div>
                        </section>
                    );
                })}

                <div className="xr-foot">
                    <p>💡 جریمه‌ها (تأخیر، غیبت، تذکر) همیشه «کم‌کردن» می‌مانند؛ ضریب فقط اندازه‌شان را عوض می‌کند.</p>
                    <button type="button" className="xr-reset" onClick={resetAll} disabled={custom === 0 && !dirtyKeys.length}>↩️ بازگشت به پیش‌فرض‌ها</button>
                </div>

                <div className={`xr-bar ${dirtyKeys.length ? 'show' : ''}`} aria-live="polite">
                    <span className="xr-bar-n">{fa(dirtyKeys.length)}</span>
                    <b>تغییرِ ذخیره‌نشده</b>
                    <button type="button" className="xr-bar-cancel" onClick={discard} disabled={saving}>لغو</button>
                    <button type="button" className="xr-bar-save" onClick={save} disabled={saving || !ready}>{saving ? 'در حالِ ذخیره…' : '💾 ذخیره'}</button>
                </div>
            </div>
        </DashLayout>
    );
}
