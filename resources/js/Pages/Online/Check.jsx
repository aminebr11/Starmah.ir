import { useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import Mascot from '@/Components/Mascot';
import { fa } from '@/online/parts';

/**
 * «🔍 آزمایشِ اتصالِ کلاس» — روی همین دستگاه، قدم‌به‌قدم:
 * تنظیمِ سایت ← سرورِ سایت تا سرورِ رسانه (و کلیدها) ← این دستگاه تا سرورِ رسانه ← اتصالِ صدا/تصویر ← میکروفون.
 * هر قدم که قرمز شود، دلیل و راهِ درست کردنش را به فارسی می‌گوید.
 */
const withTimeout = (p, ms, msg) => Promise.race([p, new Promise((_, rej) => setTimeout(() => rej(new Error(msg)), ms))]);
const ICON = { wait: '⚪', run: '⏳', ok: '✅', bad: '❌', warn: '⚠️', skip: '➖' };
const KIND = { host: 'مستقیم', srflx: 'مستقیم (از پشتِ NAT)', prflx: 'مستقیم', relay: 'از راهِ TURN' };

export default function Check({ mode, wanted, url, ice, isAdmin, back }) {
    const lk = mode === 'livekit';
    const steps = lk
        ? [['mode', '⚙️ تنظیمِ سایت'], ['server', '🖥️ سرورِ سایت ← سرورِ رسانه و کلیدها'], ['reach', '📶 این دستگاه ← سرورِ رسانه'], ['rtc', '🎧 مسیرِ صدا و تصویر'], ['mic', '🎤 میکروفون و ارسالِ صدا']]
        : [['mode', '⚙️ تنظیمِ سایت'], ['rtc', '🌐 سرورهای اتصال (STUN/TURN)'], ['mic', '🎤 میکروفون']];
    const [res, setRes] = useState({});
    const [running, setRunning] = useState(false);
    const [done, setDone] = useState(false);
    const log = useRef([]);

    const set = (k, status, msg) => {
        log.current.push(`${ICON[status]} ${steps.find((s) => s[0] === k)?.[1]}: ${msg || ''}`);
        setRes((r) => ({ ...r, [k]: { status, msg } }));
    };

    const run = async () => {
        setRunning(true); setDone(false); setRes({}); log.current = [];
        let room = null; let mic = null;
        try {
            // ۱) تنظیمِ سایت
            if (lk) set('mode', 'ok', `سرورِ رسانه‌ی LiveKit: ${url}`);
            else if (wanted === 'livekit') set('mode', 'bad', 'در ادمینِ کل LiveKit انتخاب شده ولی نشانی/کلید/رمز کامل نیست؛ فعلاً حالتِ داخلی به کار می‌رود.');
            else set('mode', 'warn', 'سایت روی حالتِ «داخلی» است (بدونِ سرورِ رسانه). اگر LiveKit دارید، در ادمینِ کل ← کلاسِ آنلاین آن را انتخاب و ذخیره کنید.');

            if (lk) {
                // ۲) سرورِ سایت ← LiveKit و درستیِ کلیدها
                set('server', 'run');
                let srv = null;
                try { srv = (await axios.post(route('online.check.server'))).data; } catch (e) { srv = { ok: false, message: `سایت جواب نداد (${e?.response?.status || 'شبکه'}).` }; }
                set('server', srv.ok ? 'ok' : 'bad', srv.message);

                // ۳) این دستگاه ← LiveKit (DNS، SSL، فیلتر)
                set('reach', 'run');
                const http = srv.http || String(url).replace(/^ws(s?):\/\//i, 'http$1://');
                if (window.location.protocol === 'https:' && /^ws:\/\//i.test(url)) {
                    set('reach', 'bad', 'نشانیِ سرور باید با wss:// شروع شود (سایت روی https است). در ادمینِ کل اصلاح کنید.');
                } else {
                    const t0 = performance.now();
                    try {
                        const ctl = new AbortController(); const tm = setTimeout(() => ctl.abort(), 9000);
                        const r = await fetch(`${http}/`, { cache: 'no-store', signal: ctl.signal });
                        clearTimeout(tm);
                        const txt = (await r.text()).trim();
                        if (r.ok && txt !== 'OK') set('reach', 'bad', `این دستگاه به ${http} رسید ولی جواب از LiveKit نبود؛ دامنه احتمالاً به سرورِ دیگری (مثلاً هاستِ سایت) هم اشاره می‌کند. رکوردهای A را در cPanel ← Zone Editor بررسی کنید.`);
                        else set('reach', r.ok ? 'ok' : 'bad', r.ok ? `جواب داد در ${fa(Math.round(performance.now() - t0))} میلی‌ثانیه (${txt.slice(0, 10)})` : `جوابِ ناجور (کد ${fa(r.status)})`);
                    } catch (e) {
                        set('reach', 'bad', `این دستگاه به ${http} نمی‌رسد. روی همین گوشی این نشانی را در مرورگر باز کنید؛ باید «OK» ببینید. اگر باز نشد: DNS (رکوردِ A)، گواهیِ SSL یا محدودیتِ اینترنتِ این خط. (${e?.name === 'AbortError' ? 'زمان تمام شد' : e?.message || e})`);
                    }
                }

                // ۴) اتصالِ واقعی به اتاقِ آزمایش
                set('rtc', 'run');
                if (!srv.token) {
                    set('rtc', 'skip', 'چون مرحله‌ی سرور کامل نشد، آزمایش نشد.');
                } else {
                    try {
                        const LK = await import('livekit-client');
                        room = new LK.Room({ adaptiveStream: false, dynacast: false });
                        const t0 = performance.now();
                        await withTimeout(room.connect(srv.url, srv.token, { autoSubscribe: false }), 25000, 'زمان تمام شد');
                        const how = await pathOf(room);
                        set('rtc', 'ok', `وصل شد در ${fa((Math.round(performance.now() - t0) / 1000).toFixed(1))} ثانیه${how ? ` · مسیر: ${how}` : ''}`);
                    } catch (e) {
                        const m = String(e?.message || e).replace(/eyJ[\w.-]+/g, '…');
                        const hint = /signal|websocket|401|unauthori|token/i.test(m)
                            ? 'اتصالِ اولیه (wss) برقرار نشد: نشانی، SSL یا کلید/رمز را بررسی کنید.'
                            : 'اتصالِ اولیه برقرار شد ولی مسیرِ صدا/تصویر نه: در فایروالِ VPS و پنلِ فروشنده این‌ها باز باشد: UDP ‏50000–60000، TCP ‏7881، UDP ‏3478 و TCP ‏5349.';
                        set('rtc', 'bad', `${hint} (${m.slice(0, 140)})`);
                        try { room?.disconnect(); } catch { /* */ } room = null;
                    }
                }
            } else {
                // حالتِ داخلی: آیا STUN/TURN از این دستگاه جواب می‌دهند؟
                set('rtc', 'run');
                try {
                    const kinds = await gather(ice);
                    const hasTurn = (ice || []).some((s) => [].concat(s.urls).some((u) => /^turns?:/i.test(u)));
                    if (kinds.relay) set('rtc', 'ok', `STUN و TURN هر دو جواب دادند (${fa(kinds.relay)} مسیرِ TURN).`);
                    else if (kinds.srflx) set('rtc', hasTurn ? 'warn' : 'ok', hasTurn ? 'STUN جواب داد ولی TURN نه؛ نشانی/نام/رمزِ TURN یا پورت‌هایش را بررسی کنید. روی اینترنتِ همراه ممکن است وصل نشود.' : 'STUN جواب داد. برای اینترنتِ همراه یک سرورِ TURN هم پیشنهاد می‌شود.');
                    else set('rtc', 'bad', 'هیچ سرورِ اتصالی جواب نداد؛ این شبکه احتمالاً اتصالِ مستقیم را می‌بندد. TURN (ترجیحاً روی TCP/TLS) لازم است.');
                } catch (e) { set('rtc', 'bad', `این مرورگر WebRTC ندارد یا اجازه نداد (${e?.message || e}).`); }
            }

            // ۵) میکروفون (و در LiveKit: ارسالِ واقعیِ صدا)
            set('mic', 'run');
            try {
                const s = await navigator.mediaDevices.getUserMedia({ audio: true });
                mic = s.getAudioTracks()[0];
                if (room) {
                    const t0 = performance.now();
                    await withTimeout(room.localParticipant.publishTrack(mic), 20000, 'زمانِ ارسال تمام شد');
                    set('mic', 'ok', `میکروفون اجازه داد و صدا به سرور فرستاده شد (${fa((Math.round(performance.now() - t0) / 1000).toFixed(1))} ثانیه).`);
                } else set('mic', 'ok', 'میکروفون اجازه داد.');
            } catch (e) {
                const n = e?.name || '';
                set('mic', n === 'NotAllowedError' ? 'warn' : 'bad', n === 'NotAllowedError'
                    ? 'اجازه‌ی میکروفون داده نشد (برای شنیدنِ کلاس لازم نیست؛ فقط برای صحبت). از تنظیماتِ مرورگر اجازه بدهید.'
                    : room ? `ارسالِ صدا به سرور انجام نشد (${e?.message || e}).` : `میکروفون در دسترس نیست (${e?.message || n}).`);
            }
        } finally {
            mic?.stop();
            try { await room?.disconnect(); } catch { /* */ }
            setRunning(false); setDone(true);
        }
    };

    const report = () => [
        'آزمایشِ اتصالِ کلاسِ ستاره ماه',
        `حالت: ${lk ? 'LiveKit' : 'داخلی'}${url ? ` · ${url}` : ''}`,
        `دستگاه: ${navigator.userAgent.slice(0, 120)}`,
        navigator.connection?.effectiveType ? `شبکه: ${navigator.connection.effectiveType}` : '',
        ...log.current,
    ].filter(Boolean).join('\n');
    const bad = Object.values(res).some((r) => r.status === 'bad');

    return (
        <div className="oc-lobby" dir="rtl">
            <Head title="آزمایشِ اتصالِ کلاس" />
            <div className="oc-lobby-card oc-check">
                <Mascot mood={running ? 'think' : done ? (bad ? 'idle' : 'happy') : 'wave'} size={100} />
                <h1>🔍 آزمایشِ اتصالِ کلاس</h1>
                <p>روی همان گوشی یا رایانه‌ای که با آن سرِ کلاس می‌آیید و با همان اینترنت اجرا کنید.</p>
                <ol className="oc-steps">
                    {steps.map(([k, t]) => {
                        const r = res[k] || { status: 'wait' };
                        return (
                            <li key={k} className={`st-${r.status}`}>
                                <span>{ICON[r.status]}</span>
                                <div><b>{t}</b>{r.msg && <small>{r.msg}</small>}</div>
                            </li>
                        );
                    })}
                </ol>
                {done && (
                    <div className={`oc-check-sum ${bad ? 'bad' : 'ok'}`}>
                        {bad ? 'یک یا چند مرحله ایراد دارد؛ پیامِ همان مرحله راهِ درست کردنش را می‌گوید.' : '🎉 همه‌چیز درست است؛ صدا و تصویرِ کلاس روی این دستگاه کار می‌کند.'}
                    </div>
                )}
                <button type="button" className="oc-go" onClick={run} disabled={running}>{running ? 'در حالِ آزمایش…' : done ? '↻ آزمایشِ دوباره' : '▶️ شروعِ آزمایش'}</button>
                {done && (
                    <button type="button" className="oc-back" onClick={() => navigator.clipboard?.writeText(report()).then(() => alert('گزارش کپی شد.')).catch(() => alert(report()))}>
                        📋 کپیِ گزارش
                    </button>
                )}
                {isAdmin && <a className="oc-back" href={route('admin.online')}>⚙️ تنظیماتِ کلاسِ آنلاین</a>}
                <a href={back} className="oc-back">بازگشت</a>
            </div>
        </div>
    );
}

/** مسیرِ برقرارشده (مستقیم / TCP / TURN) از آمارِ WebRTC. */
async function pathOf(room) {
    try {
        const pm = room.engine?.pcManager;
        for (const t of [pm?.subscriber, pm?.publisher]) {
            const pc = t?.pc || t?._pc;
            const st = pc?.getStats ? await pc.getStats() : await t?.getStats?.();
            if (!st) continue;
            let pair = null; const cands = {};
            st.forEach((v) => {
                if (v.type === 'candidate-pair' && (v.nominated || v.selected) && v.state === 'succeeded') pair = v;
                if (v.type === 'local-candidate') cands[v.id] = v;
            });
            const c = pair && cands[pair.localCandidateId];
            if (c) return `${KIND[c.candidateType] || c.candidateType} · ${String(c.relayProtocol || c.protocol || '').toUpperCase()}`;
        }
    } catch { /* */ }
    return '';
}

/** جمع کردنِ مسیرهای ممکن با سرورهای STUN/TURN تنظیم‌شده (حالتِ داخلی). */
function gather(iceServers) {
    return new Promise((resolve, reject) => {
        if (typeof RTCPeerConnection !== 'function') { reject(new Error('WebRTC')); return; }
        const pc = new RTCPeerConnection({ iceServers: iceServers || [] });
        const kinds = {};
        pc.createDataChannel('check');
        pc.onicecandidate = (e) => {
            const m = e.candidate && / typ (\w+)/.exec(e.candidate.candidate);
            if (m) kinds[m[1]] = (kinds[m[1]] || 0) + 1;
        };
        const end = () => { try { pc.close(); } catch { /* */ } resolve(kinds); };
        pc.onicegatheringstatechange = () => { if (pc.iceGatheringState === 'complete') end(); };
        pc.createOffer().then((o) => pc.setLocalDescription(o)).catch(reject);
        setTimeout(end, 9000);
    });
}
