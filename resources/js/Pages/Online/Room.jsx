import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import Mascot from '@/Components/Mascot';
import { toast } from '@/lib/toast';
import Board from '@/online/Board';
import RoomSync from '@/online/sync';
import createMesh from '@/online/mesh';
import ClassRecorder from '@/online/recorder';
import { importSlides } from '@/online/slides';
import { fa, Vid, AudioSink, Clock, Countdown, Chat, People, PollPanel, PollCard, ReportModal } from '@/online/parts';

/**
 * «🎥 کلاسِ آنلاین» — یک صفحه برای معلم، دانش‌آموز و ناظر (شبیهِ اسکای‌روم، با فضای کودکانه).
 *
 * داده‌ها دو راه دارند:
 *  - همگام‌سازی با سرور (حضور، دست، گفت‌وگو، نظرسنجی، تایمر، صفحه‌ی تخته) — همیشه کار می‌کند؛
 *  - اتصالِ مستقیم (WebRTC یا LiveKit) برای صدا، تصویر، اشتراکِ صفحه و تخته‌ی بی‌درنگ.
 * اگر اتصالِ مستقیمِ یک دانش‌آموز برقرار نشود، تخته و گفت‌وگو و سؤال‌ها از راهِ سرور
 * همچنان به او می‌رسد؛ فقط صدا/تصویر را ندارد و دکمه‌ی «تلاشِ دوباره» می‌بیند.
 */

const REACTIONS = ['👏', '❤️', '😂', '🌟', '👍', '😮'];
const BGS = [['blank', '⬜ ساده'], ['lines', '📒 خط‌دار'], ['grid', '🔲 شطرنجی'], ['dots', '⠿ نقطه‌ای'], ['chalk', '🟩 تخته‌سیاه']];

const xsrf = () => decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/) || [])[1] || '');

export default function Room({ room, me, config, back }) {
    const isHost = me.role === 'host';
    const isMod = me.role !== 'student';
    const R = (name, ...a) => route(`online.${name}`, [room.id, ...a]);

    const [phase, setPhase] = useState('lobby');          // lobby | waiting | in | ended | kicked
    const [joining, setJoining] = useState(false);
    const [joinErr, setJoinErr] = useState(null);
    const [st, setSt] = useState(null);                    // آخرین وضعیتِ کاملِ کلاس
    const [mine, setMine] = useState(null);                // دسترسی‌های من
    const [msgs, setMsgs] = useState([]);
    const [panel, setPanel] = useState(isMod ? 'people' : 'chat');
    const [sheet, setSheet] = useState(false);             // پنلِ کناری روی گوشی
    const [unread, setUnread] = useState(0);
    const [tiles, setTiles] = useState({});                 // uid → { audio, cam, screen }
    const [local, setLocal] = useState({ mic: null, cam: null, screen: null });
    const [link, setLink] = useState({ state: 'new', open: false, total: 0, connected: 0 });
    const [reacts, setReacts] = useState([]);
    const [mute, setMute] = useState(false);
    const [audioBlocked, setAudioBlocked] = useState(false);
    const [pollHidden, setPollHidden] = useState(null);
    const [report, setReport] = useState(false);
    const [more, setMore] = useState(false);
    const [reactOpen, setReactOpen] = useState(false);
    const [speaking, setSpeaking] = useState([]);   // شناسه‌ی کسانی که الان حرف می‌زنند
    const [fs, setFs] = useState(false);           // تمام‌صفحه‌ی تخته (معلم)
    const [busy, setBusy] = useState(null);
    const [rec, setRec] = useState(null);
    const [skew, setSkew] = useState(0);
    const [endOpen, setEndOpen] = useState(false);
    const [wait, setWait] = useState(null);

    const tr = useRef(null);          // ابزارِ رسانه (mesh یا livekit)
    const sync = useRef(null);
    const board = useRef(null);
    const stRef = useRef(null);
    const mineRef = useRef(null);
    const loaded = useRef({ page: -1, version: -1 });
    const saveT = useRef(null);
    const recRef = useRef(null);
    const screenEl = useRef(null);
    const camEl = useRef(null);
    const lastReact = useRef(0);
    const helloAt = useRef(0);
    const joinedAt = useRef(0);
    const localRef = useRef(local);
    localRef.current = local;

    const page = st?.settings?.page ?? 0;
    const media = st?.media || room.media;
    const people = st?.people ?? [];
    const pages = st?.pages ?? [];
    const pageCount = Math.max(pages.length, page + 1, 1);
    const stage = st?.settings?.stage ?? 'board';
    const hostTiles = tiles[room.teacher_id] || {};
    const canDraw = isHost || !!mine?.can_draw;
    const kidBoard = !isMod && st?.settings?.kid_view === 'board';   // بچه‌ها فقط تخته را می‌بینند
    const speakingNames = people.filter((p) => speaking.includes(p.id)).map((p) => p.name);
    const toggleFs = () => {
        const next = !fs;
        setFs(next);
        try {
            if (next) document.documentElement.requestFullscreen?.()?.catch?.(() => {});
            else if (document.fullscreenElement) document.exitFullscreen?.();
        } catch { /* مرورگر اجازه نداد؛ حالتِ تمام‌صفحه‌ی خودِ صفحه کافی است */ }
    };

    // ── تخته ─────────────────────────────────────────────────────────
    const loadPage = useCallback(async (p, merge = false) => {
        try {
            const { data } = await axios.get(R('board'), { params: { page: p } });
            let ops = data.ops;
            // معلم: کارهای ذخیره‌نشده‌ی خودش را نگه می‌دارد تا با نوشته‌ی بچه پاک نشوند
            if (merge && board.current) {
                const have = new Set(ops.map((o) => o.id));
                ops = [...ops, ...board.current.ops().filter((o) => !have.has(o.id))];
            }
            loaded.current = { page: p, version: data.version };
            board.current?.load(ops, data.bg);
        } catch { /* دورِ بعد */ }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const saveBoard = useCallback((now = false) => {
        if (!isHost) return;
        clearTimeout(saveT.current);
        const run = async (retry = false) => {
            const p = loaded.current.page;
            if (p < 0 || !board.current) return;
            try {
                const { data } = await axios.post(R('board.save'), { page: p, bg: board.current.bg(), ops: JSON.stringify(board.current.ops()), base: loaded.current.version });
                if (loaded.current.page === p) loaded.current.version = data.version;
            } catch (e) {
                // یک دانش‌آموز همین‌وقت چیزی نوشته: اول نسخه‌ی تازه، بعد ذخیره‌ی دوباره
                if (e?.response?.status === 409 && !retry) { await loadPage(p, true); return run(true); }
            }
        };
        if (now) return run();
        saveT.current = setTimeout(() => run(), 1200);
        return null;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [loadPage]);

    /** نوشته‌ی دانش‌آموز از راهِ سرور، وقتی اتصالِ مستقیم برقرار نیست. */
    const boardOpSend = useCallback((body) => {
        axios.post(R('board.op'), body).then(() => sync.current?.fast(4000)).catch(() => {});
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const send = useCallback((msg, except) => tr.current?.broadcast(msg, except), []);

    const onOp = useCallback((op) => {
        const p = loaded.current.page;
        const direct = !!tr.current?.dataOpen();
        if (op.k === '__undo') {
            send({ t: 'undo', page: p, id: op.id });
            if (!isHost && !direct) boardOpSend({ page: p, kind: 'undo', id: op.id });
        } else {
            send({ t: 'op', page: p, op });
            if (!isHost && !direct) boardOpSend({ page: p, kind: 'op', op });
        }
        saveBoard();
    }, [send, saveBoard, boardOpSend]);
    const onStroke = useCallback((s) => send({ t: 'stroke', page: loaded.current.page, ...s }), [send]);
    const onPtr = useCallback((x, y) => send({ t: 'ptr', x, y }), [send]);

    /** پیام از کانالِ داده. */
    const onData = useCallback((from, msg) => {
        if (!msg || typeof msg !== 'object') return;
        const curPage = loaded.current.page;
        const sender = stRef.current?.people?.find((p) => p.id === from);
        const fromHost = from === room.teacher_id;
        // معلم: نوشته‌ی دانش‌آموزی که اجازه‌ی تخته دارد را ثبت و برای بقیه پخش کن
        if (isHost && ['op', 'stroke', 'undo'].includes(msg.t)) {
            if (!sender?.can_draw || msg.page !== curPage) return;
            if (msg.t === 'op' && msg.op) { board.current?.add({ ...msg.op, by: from }); saveBoard(); }
            if (msg.t === 'undo') { board.current?.undo(msg.id); saveBoard(); }
            if (msg.t === 'stroke') board.current?.stroke(msg);
            send(msg, from);
            return;
        }
        if (msg.t === 'speak') { if (!isHost && fromHost) setSpeaking(Array.isArray(msg.uids) ? msg.uids : []); return; }
        if (msg.t === 'react') {
            showReact(msg.e, msg.name || sender?.name);
            if (isHost) send({ t: 'react', e: msg.e, name: sender?.name }, from);
            return;
        }
        if (!fromHost && !isHost) return;
        if (msg.t === 'op' && msg.page === curPage && msg.op) board.current?.add(msg.op);
        else if (msg.t === 'undo' && msg.page === curPage) board.current?.undo(msg.id);
        else if (msg.t === 'stroke' && msg.page === curPage) board.current?.stroke(msg);
        else if (msg.t === 'ptr') board.current?.pointer(msg.x, msg.y);
        else if (msg.t === 'bg' && msg.page === curPage) board.current?.setBg(msg.bg);
        else if (msg.t === 'sync') sync.current?.now();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const showReact = (e, name) => {
        const id = Math.random();
        setReacts((r) => [...r.slice(-14), { id, e, name, x: 10 + Math.random() * 70 }]);
        setTimeout(() => setReacts((r) => r.filter((x) => x.id !== id)), 2600);
    };

    // ── داده‌ی همگام‌سازی ────────────────────────────────────────────
    const onSync = useCallback((d) => {
        if (d.now) setSkew(d.now - Date.now());
        if (d.kicked) { setPhase('kicked'); cleanup(); return; }
        if (d.status === 'ended') { setPhase('ended'); cleanup(); return; }
        if (d.rejoin || (d.status === 'live' && !mineRef.current && phaseRef.current === 'waiting')) { join(true); return; }
        if (d.status !== 'live') { setWait(d.starts_ms || null); return; }
        if (d.me) {
            const prev = mineRef.current;
            mineRef.current = d.me;
            setMine(d.me);
            if (prev && prev.can_speak && !d.me.can_speak && localRef.current.mic) { toggleMic(false); toast('🎤 معلم میکروفونت را بست.', 'info'); }
            if (prev && prev.can_video && !d.me.can_video && localRef.current.cam) toggleCam(false);
            if (prev && !prev.can_speak && d.me.can_speak && !isMod) toast('🎤 معلم اجازه‌ی صحبت داد! دکمه‌ی میکروفون را بزن.', 'success');
            if (prev && !prev.can_draw && d.me.can_draw && !isMod) toast('✏️ حالا می‌توانی روی تخته بنویسی!', 'success');
            if (prev && d.me.stars > prev.stars) { showReact('⭐', 'معلم'); toast('⭐ آفرین! از معلم ستاره گرفتی.', 'success'); }
        }
        for (const s of d.signals || []) tr.current?.handleSignal(s);
        if (d.state) {
            const s = d.state;
            stRef.current = s;
            setSt(s);
            if (s.messages?.length) {
                setMsgs((old) => {
                    const ids = new Set(old.map((m) => m.id));
                    const add = s.messages.filter((m) => !ids.has(m.id));
                    if (add.length && add.some((m) => m.uid !== me.id)) setUnread((u) => u + add.filter((m) => m.uid !== me.id).length);
                    return [...old, ...add].filter((m) => !(s.hidden || []).includes(m.id)).slice(-200);
                });
            } else if (s.hidden?.length) {
                setMsgs((old) => old.filter((m) => !s.hidden.includes(m.id)));
            }
            // تخته: صفحه عوض شده یا (بدونِ کانالِ داده) نسخه‌ی تازه آمده
            const pg = s.settings.page;
            const ver = s.pages.find((x) => x.page === pg)?.version ?? 0;
            if (loaded.current.page !== pg) loadPage(pg);
            else if (isHost && ver > loaded.current.version) loadPage(pg, true);
            else if (!isHost && ver > loaded.current.version && !tr.current?.dataOpen()) loadPage(pg);
            if (isHost) {
                tr.current?.setSpeakers(s.people.filter((p) => p.can_speak && p.role === 'student').map((p) => p.id));
            }
        }
        if (isHost && stRef.current) tr.current?.ensure(stRef.current.people);
        if (!isHost && tr.current && !tr.current.connected() && Date.now() - helloAt.current > 14000) {
            helloAt.current = Date.now();
            tr.current.hello();
        }
        if (tr.current) setLink((l) => ({ ...l, ...tr.current.stats() }));
        // دانش‌آموزی که پس از ۲۵ ثانیه هنوز صدا ندارد: پیامِ روشن + دکمه‌ی تلاشِ دوباره
        if (!isHost && joinedAt.current && Date.now() - joinedAt.current > 25000 && !tr.current?.connected()) {
            setLink((l) => (l.state === 'nortc' || l.state === 'failed' ? l : { ...l, state: 'failed' }));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const phaseRef = useRef(phase);
    phaseRef.current = phase;

    // ── ورود ─────────────────────────────────────────────────────────
    const startMedia = async () => {
        if (tr.current) return;
        const common = {
            me: me.id, isHost, hostId: room.teacher_id,
            onTrack: (uid, slot, track) => setTiles((t) => ({ ...t, [uid]: { ...(t[uid] || {}), [slot]: track } })),
            onData,
            onSpeaking: (uids) => {
                setSpeaking(uids);
                // معلم در حالت داخلی گوینده‌ها را برای همه پخش می‌کند (در LiveKit خودش همه را می‌فهمد)
                if (isHost && media !== 'livekit') tr.current?.broadcast({ t: 'speak', uids });
            },
            onPeer: (uid, state, open) => { if (!isHost || media === 'livekit') setLink((l) => ({ ...l, state, open })); setLink((l) => ({ ...l, ...(tr.current?.stats() || {}) })); },
        };
        try {
            if (media === 'livekit') {
                const { data } = await axios.get(R('token'));
                const createLk = (await import('@/online/lk')).default;
                tr.current = await createLk({ ...common, url: data.url, token: data.token });
            } else {
                tr.current = createMesh({ ...common, ice: config.ice, budget: config.budget, signal: (to, kind, payload) => { sync.current?.fast(); return axios.post(R('signal'), { to, kind, payload }); } });
                if (!isHost) { helloAt.current = Date.now(); tr.current.hello(); }
            }
        } catch (e) {
            console.warn('[online] media', e);
            setLink((l) => ({ ...l, state: 'failed', open: false }));
            toast('اتصالِ صدا و تصویر برقرار نشد؛ تخته و گفت‌وگو کار می‌کند.', 'error');
        }
    };

    const join = async (silent = false) => {
        setJoining(true); setJoinErr(null);
        unlockAudio();
        try {
            const { data } = await axios.post(R('join'));
            if (!sync.current) {
                sync.current = new RoomSync(R('sync'), (d) => onSync(d), (e, n) => { if (n === 3) toast('اتصال به سرور ضعیف است…', 'error'); });
            }
            if (data.waiting) {
                setPhase('waiting'); setWait(data.starts_ms || null);
                sync.current.start();
                return;
            }
            setPhase('in');
            joinedAt.current = Date.now();
            // مرورگری که WebRTC ندارد: فقط تخته/گفت‌وگو/سؤال (معلم بیهوده تلاشِ اتصال نکند)
            const rtc = media === 'livekit' || typeof window.RTCPeerConnection === 'function';
            sync.current.ms = rtc ? 'ready' : 'nortc';
            if (!rtc) setLink((l) => ({ ...l, state: 'nortc' }));
            if (data.v) sync.current.v = 0;  // وضعیتِ کامل را دوباره بگیر
            onSync(data);
            sync.current.start();
            sync.current.fast(8000);
            await startMedia();
        } catch (e) {
            const msg = e?.response?.data?.error || e?.response?.data?.message || 'ورود انجام نشد. دوباره امتحان کن.';
            if (!silent) setJoinErr(msg);
            else toast(msg);
        } finally { setJoining(false); }
    };

    const unlockAudio = () => {
        try {
            const AC = window.AudioContext || window.webkitAudioContext;
            const c = new AC(); const b = c.createBuffer(1, 1, 22050); const s = c.createBufferSource();
            s.buffer = b; s.connect(c.destination); s.start(0); setTimeout(() => c.close().catch(() => {}), 500);
        } catch { /* */ }
        tr.current?.resumeAudio?.();
        document.querySelectorAll('audio[data-oc-audio]').forEach((a) => a.play().catch(() => {}));
        setAudioBlocked(false);
    };

    function cleanup() {
        sync.current?.stop();
        if (recRef.current) { recRef.current.stop(); recRef.current = null; setRec(null); }
        tr.current?.close(); tr.current = null;
        Object.values(localRef.current).forEach((t) => t?.stop());
        setLocal({ mic: null, cam: null, screen: null });
    }

    // خروج با بستنِ صفحه
    useEffect(() => {
        const bye = () => {
            if (phaseRef.current !== 'in') return;
            try { fetch(R('leave'), { method: 'POST', keepalive: true, credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } }); } catch { /* */ }
        };
        window.addEventListener('pagehide', bye);
        return () => { window.removeEventListener('pagehide', bye); bye(); cleanup(); };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // ── میکروفون، دوربین، اشتراکِ صفحه ────────────────────────────────
    const toggleMic = async (on = !localRef.current.mic) => {
        if (on) {
            if (!isMod && !mineRef.current?.can_speak) { toast('✋ اول دست بالا کن تا معلم اجازه‌ی صحبت بدهد.', 'info'); return; }
            try {
                const s = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
                const t = s.getAudioTracks()[0];
                await tr.current?.setMic(t);
                setLocal((l) => ({ ...l, mic: t }));
            } catch { toast('اجازه‌ی میکروفون داده نشد. از تنظیماتِ مرورگر اجازه بده.'); }
        } else {
            localRef.current.mic?.stop();
            await tr.current?.setMic(null);
            setLocal((l) => ({ ...l, mic: null }));
        }
    };
    const toggleCam = async (on = !localRef.current.cam) => {
        if (on) {
            if (!isMod && !mineRef.current?.can_video) { toast('معلم هنوز اجازه‌ی دوربین نداده.', 'info'); return; }
            try {
                const s = await navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 640 }, height: { ideal: 360 }, frameRate: { ideal: 20, max: 24 }, facingMode: 'user' } });
                const t = s.getVideoTracks()[0];
                await tr.current?.setCam(t);
                setLocal((l) => ({ ...l, cam: t }));
            } catch { toast('اجازه‌ی دوربین داده نشد.'); }
        } else {
            localRef.current.cam?.stop();
            await tr.current?.setCam(null);
            setLocal((l) => ({ ...l, cam: null }));
        }
    };
    const toggleScreen = async () => {
        if (localRef.current.screen) {
            localRef.current.screen.stop();
            await tr.current?.setScreen(null);
            setLocal((l) => ({ ...l, screen: null }));
            control('stage', 'board');
            return;
        }
        if (!navigator.mediaDevices?.getDisplayMedia) { toast('اشتراکِ صفحه روی این دستگاه پشتیبانی نمی‌شود (از رایانه استفاده کنید).'); return; }
        try {
            const s = await navigator.mediaDevices.getDisplayMedia({ video: { frameRate: { ideal: 10, max: 15 } }, audio: false });
            const t = s.getVideoTracks()[0];
            t.contentHint = 'detail';
            t.addEventListener('ended', () => { tr.current?.setScreen(null); setLocal((l) => ({ ...l, screen: null })); control('stage', 'board'); });
            await tr.current?.setScreen(t);
            setLocal((l) => ({ ...l, screen: t }));
            control('stage', 'screen');
        } catch { /* لغو شد */ }
    };

    // ── فرمان‌ها ──────────────────────────────────────────────────────
    const control = async (action, value, user, perm) => {
        try {
            const { data } = await axios.post(R('control'), { action, value, user, perm });
            sync.current?.now();
            send({ t: 'sync' });
            return data;
        } catch (e) { toast(e?.response?.data?.message || 'انجام نشد.'); return null; }
    };

    const goPage = async (p) => {
        if (!isHost || p < 0) return;
        await saveBoard(true);
        loaded.current = { page: -1, version: -1 };
        await control('page', p);
    };
    const addPage = async (bg = 'blank') => {
        await saveBoard(true);
        const p = pageCount;
        await axios.post(R('board.save'), { page: p, bg, ops: '[]' });
        loaded.current = { page: -1, version: -1 };
        await control('page', p);
    };
    const setBg = (bg) => {
        board.current?.setBg(bg);
        send({ t: 'bg', page: loaded.current.page, bg });
        saveBoard(true);
    };
    const removePage = async () => {
        if (pageCount <= 1 || !confirm('این صفحه‌ی تخته حذف شود؟')) return;
        const p = loaded.current.page;
        await axios.post(R('board.save'), { page: p, remove: true });
        loaded.current = { page: -1, version: -1 };
        await control('page', Math.max(0, p - 1));
    };
    const uploadSlides = async (file) => {
        if (!file) return;
        setBusy('در حالِ آماده کردنِ اسلایدها…');
        try {
            await saveBoard(true);
            const urls = await importSlides(file, R('asset.upload'), (i, n) => setBusy(`اسلایدِ ${fa(i)} از ${fa(n)}…`));
            const start = pageCount;
            for (let i = 0; i < urls.length; i += 1) await axios.post(R('board.save'), { page: start + i, bg: urls[i], ops: '[]' });
            loaded.current = { page: -1, version: -1 };
            await control('page', start);
            toast(`🖼️ ${fa(urls.length)} صفحه به تخته اضافه شد.`, 'success');
        } catch (e) {
            console.warn(e);
            toast(e?.response?.data?.message || 'فایل آماده نشد. عکس (JPG/PNG) یا PDF بفرستید.');
        } finally { setBusy(null); }
    };

    const react = (e) => {
        if (Date.now() - lastReact.current < 1200) return;
        lastReact.current = Date.now();
        showReact(e, 'من');
        send({ t: 'react', e, name: me.name });
    };

    const chat = (body) => axios.post(R('chat'), { body }).then(() => sync.current?.now()).catch((e) => toast(e?.response?.data?.message || 'پیام فرستاده نشد.'));
    const hideMsg = (id) => axios.delete(R('chat.hide', id)).then(() => { setMsgs((m) => m.filter((x) => x.id !== id)); sync.current?.now(); }).catch(() => {});
    const hand = () => axios.post(R('hand'), { up: !mine?.hand }).then(() => { setMine((m) => ({ ...m, hand: !m?.hand })); sync.current?.now(); }).catch(() => {});
    const answer = (pollId, choice) => axios.post(R('poll.answer', pollId), { choice }).then(() => sync.current?.now()).catch((e) => toast(e?.response?.data?.message || 'ثبت نشد.'));
    const newPoll = (d) => axios.post(R('poll'), d).then(() => { sync.current?.now(); send({ t: 'sync' }); }).catch(() => toast('سؤال فرستاده نشد.'));
    const closePoll = (id) => axios.post(R('poll.close', id)).then(() => { sync.current?.now(); send({ t: 'sync' }); }).catch(() => {});

    const perm = (uid, p, v) => control('perm', v, uid, p);
    const star = async (uid) => { const d = await control('star', null, uid); if (d) { showReact('⭐'); toast(d.xp ? `⭐ ستاره داده شد (+${fa(d.xp)} امتیاز)` : '⭐ ستاره داده شد', 'success'); } };
    const kick = (p) => { if (confirm(`«${p.name}» از کلاس بیرون گذاشته شود؟`)) control('kick', null, p.id); };
    const spot = async (uid) => {
        await control('spotlight', null, uid);
        if (media !== 'livekit') tr.current?.relayCam(uid ? tiles[uid]?.cam : null);
    };

    // ── ضبط ──────────────────────────────────────────────────────────
    const toggleRec = async () => {
        if (recRef.current) {
            setBusy('در حالِ ذخیره‌ی فیلم…');
            await recRef.current.stop();
            recRef.current = null; setRec(null); setBusy(null);
            control('recording', false);
            toast('🎬 فیلمِ کلاس ذخیره شد.', 'success');
            return;
        }
        if (!ClassRecorder.supported()) { toast('این مرورگر ضبط را پشتیبانی نمی‌کند (کروم یا اج روی رایانه).'); return; }
        const r = new ClassRecorder({
            uploadUrl: R('record'),
            finishUrl: R('record.finish'),
            getStage: () => ({ mode: localRef.current.screen && stRef.current?.settings?.stage === 'screen' ? 'screen' : 'board',
                canvases: board.current?.canvases() || [], screen: screenEl.current, cam: camEl.current }),
            getAudioTracks: () => [localRef.current.mic, ...(tr.current?.remoteAudioTracks() || [])].filter(Boolean),
            onState: (s) => { if (s === 'error') toast('بخشی از ضبط بارگذاری نشد.'); },
        });
        r.start();
        recRef.current = r;
        setRec(Date.now());
        control('recording', true);
    };

    const endClass = async () => {
        setEndOpen(false);
        if (recRef.current) await toggleRec();
        await saveBoard(true);
        await control('end');
        setPhase('ended');
        cleanup();
    };
    const leave = async () => {
        try { await axios.post(R('leave')); } catch { /* */ }
        cleanup();
        window.location.href = back;
    };

    // تخته پس از نمایشِ صفحه‌ی کلاس بارگذاری شود (پیش از آن بوم وجود ندارد)
    useEffect(() => {
        if (phase === 'in' && stRef.current) { loaded.current = { page: -1, version: -1 }; loadPage(stRef.current.settings.page); }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [phase]);

    // پنلِ گفت‌وگو باز شد → خوانده‌شده
    useEffect(() => { if (panel === 'chat' && (sheet || window.innerWidth > 900 || window.matchMedia('(orientation: portrait)').matches)) setUnread(0); }, [panel, sheet, msgs.length]);

    // ── صداها ────────────────────────────────────────────────────────
    const audioTracks = useMemo(() => Object.entries(tiles).flatMap(([uid, t]) => (t.audio && Number(uid) !== me.id ? [[uid, t.audio]] : [])), [tiles, me.id]);

    const poll = st?.poll;
    const showPollCard = !isMod && poll && pollHidden !== `${poll.id}:${poll.status}` && (poll.status === 'open' || (poll.status === 'shown' && poll.mine !== null));
    const hands = people.filter((p) => p.online && p.hand).length;
    const spotlightName = st?.settings?.spotlight ? people.find((p) => p.id === st.settings.spotlight)?.name : null;

    // ═════════════════════════════ صفحه‌ها ═══════════════════════════════
    if (phase === 'lobby' || phase === 'waiting') {
        return (
            <div className="oc-lobby" dir="rtl">
                <Head title={room.title} />
                <div className="oc-lobby-card">
                    <Mascot mood={phase === 'waiting' ? 'sleep' : 'wave'} size={120} />
                    <h1>{room.title}</h1>
                    <p>👩‍🏫 {room.teacher}{room.classroom ? ` · ${room.classroom}` : ''}</p>
                    {phase === 'waiting' ? (
                        <>
                            <div className="oc-wait">⏳ کلاس هنوز شروع نشده؛ همین‌جا بمان، معلم که بیاید خودکار وارد می‌شوی.</div>
                            {room.starts_at && <div className="oc-when">🗓️ {room.starts_at}</div>}
                        </>
                    ) : (
                        <>
                            {room.starts_at && room.status === 'scheduled' && <div className="oc-when">🗓️ {room.starts_at}</div>}
                            <ul className="oc-tips">
                                <li>🎧 هدفون بگذار تا صدا بهتر باشد.</li>
                                <li>✋ برای صحبت دست بالا کن؛ معلم میکروفون را باز می‌کند.</li>
                                <li>⭐ پاسخِ درست به سؤال‌ها و ستاره‌ی معلم امتیاز دارد!</li>
                            </ul>
                            {joinErr && <div className="oc-err">{joinErr}</div>}
                            <button type="button" className="oc-go" onClick={() => join()} disabled={joining}>
                                {joining ? 'در حالِ ورود…' : isHost ? (room.status === 'live' ? '🎥 ورود به کلاس' : '▶️ شروعِ کلاس') : isMod ? '🛡️ ورود به‌عنوانِ ناظر' : '🚀 ورود به کلاس'}
                            </button>
                        </>
                    )}
                    <a href={back} className="oc-back">بازگشت</a>
                </div>
            </div>
        );
    }

    if (phase === 'ended' || phase === 'kicked') {
        const myRow = people.find((p) => p.id === me.id);
        return (
            <div className="oc-lobby" dir="rtl">
                <Head title={room.title} />
                <div className="oc-lobby-card">
                    <Mascot mood={phase === 'ended' ? 'happy' : 'idle'} size={120} />
                    <h1>{phase === 'ended' ? 'کلاس تمام شد 🌟' : 'از کلاس بیرون آمدی'}</h1>
                    {phase === 'ended' && !isMod && <p>ممنون که آمدی! {myRow?.stars ? `امروز ${fa(myRow.stars)} ستاره گرفتی. ⭐` : 'تا کلاسِ بعد!'}</p>}
                    {phase === 'ended' && !isMod && <p className="oc-small">امتیازِ حضور بعد از پایانِ کلاس به حسابت اضافه می‌شود.</p>}
                    {isMod && <button type="button" className="oc-go" onClick={() => setReport(true)}>📋 گزارشِ حضور</button>}
                    <a href={back} className="oc-back">بازگشت</a>
                </div>
                {report && <ReportModal url={R('report')} onClose={() => setReport(false)} />}
            </div>
        );
    }

    const linkBadge = isHost
        ? (media === 'livekit' ? (link.state === 'connected' ? '🟢 سرورِ رسانه' : link.state === 'failed' ? '🔴 سرورِ رسانه در دسترس نیست' : '🟡 اتصال به سرورِ رسانه…') : link.total ? `${link.connected === link.total ? '🟢' : '🟡'} ${fa(link.connected)}/${fa(link.total)} وصل` : '⚪ منتظرِ بچه‌ها')
        : link.state === 'connected' ? '🟢 وصل' : link.state === 'nortc' ? '🔇 این دستگاه صدا ندارد' : link.state === 'failed' ? '🔴 صدا وصل نیست' : '🟡 در حالِ اتصال…';

    const sidePanel = (
        <aside className={`oc-side ${sheet ? 'open' : ''}`}>
            <div className="oc-tabs" role="tablist">
                <button type="button" className={panel === 'chat' ? 'on' : ''} onClick={() => setPanel('chat')}>💬 گفت‌وگو{unread > 0 && panel !== 'chat' && <i>{fa(unread)}</i>}</button>
                <button type="button" className={panel === 'people' ? 'on' : ''} onClick={() => setPanel('people')}>👥 نفرات{hands > 0 && <i>✋{fa(hands)}</i>}</button>
                <button type="button" className={panel === 'poll' ? 'on' : ''} onClick={() => setPanel('poll')}>📊 سؤال</button>
                <button type="button" className="oc-side-x" onClick={() => setSheet(false)} aria-label="بستن">×</button>
            </div>
            <div className="oc-side-body">
                {panel === 'chat' && <Chat msgs={msgs} me={me.id} chatOn={st?.settings?.chat !== false} isMod={isMod} onSend={chat} onHide={hideMsg} />}
                {panel === 'people' && <People people={people} me={me.id} isMod={isMod} isHost={isHost} tiles={tiles} speaking={speaking} onPerm={perm} onStar={star} onKick={kick} onSpot={spot}
                    spotlight={st?.settings?.spotlight} onAll={(a, v) => control(a, v)} />}
                {panel === 'poll' && <PollPanel poll={poll} isMod={isMod} onCreate={newPoll} onClose={closePoll} />}
            </div>
        </aside>
    );

    return (
        <div className={`oc ${isHost ? 'is-host' : ''} ${kidBoard ? 'kid-board' : ''} ${fs ? 'oc-fs' : ''}`} dir="rtl">
            <Head title={room.title} />
            {audioTracks.map(([uid, t]) => <AudioSink key={`${uid}:${t.id}`} track={t} muted={mute} onBlocked={() => setAudioBlocked(true)} />)}

            <header className="oc-top">
                <button type="button" className="oc-ib" onClick={leave} title="خروج" aria-label="خروج">→</button>
                <div className="oc-title">
                    <b>{st?.title || room.title}</b>
                    <span><i className="oc-live" />زنده · <Clock from={st?.started_at} skew={skew} /></span>
                </div>
                <Countdown end={st?.settings?.timer_end} skew={skew} />
                {st?.settings?.recording && <span className="oc-rec">⏺ ضبط</span>}
                <span className={`oc-link ${link.state === 'failed' && !isHost ? 'bad' : ''}`} title={media === 'livekit' ? 'سرورِ رسانه' : 'اتصالِ مستقیم'}>{linkBadge}</span>
                {!isHost && link.state === 'failed' && <button type="button" className="oc-ib" onClick={() => { helloAt.current = Date.now(); joinedAt.current = Date.now(); setLink((l) => ({ ...l, state: 'connecting' })); tr.current?.hello(); }} title="تلاشِ دوباره">↻</button>}
                <span className="oc-count">👥 {fa(people.filter((p) => p.online).length)}</span>
                {isMod && <button type="button" className="oc-end" onClick={() => setEndOpen(true)}>⏹ پایانِ کلاس</button>}
            </header>

            {st?.settings?.announce && <div className="oc-announce">📢 {st.settings.announce}</div>}
            {audioBlocked && <button type="button" className="oc-unmute" onClick={unlockAudio}>🔊 برای شنیدنِ صدای کلاس این‌جا را بزن</button>}
            {!isHost && (link.state === 'failed' || link.state === 'nortc') && (
                <div className="oc-announce oc-weak">
                    {link.state === 'nortc' ? 'این مرورگر صدا و تصویرِ کلاس را پشتیبانی نمی‌کند (کروم یا سافاریِ به‌روز را امتحان کن)؛ تخته، گفت‌وگو و سؤال‌ها کار می‌کند.' : 'صدا و تصویر وصل نشد؛ تخته، گفت‌وگو و سؤال‌ها کار می‌کند. دکمه‌ی ↻ را بزن یا اینترنت را عوض کن.'}
                </div>
            )}

            <div className="oc-main">
                <section className="oc-stage-wrap">
                    <div className={`oc-stage stage-${stage}`}>
                        <div className="oc-board" style={{ display: stage === 'screen' ? 'none' : undefined }}>
                            <Board ref={board} canDraw={canDraw} isHost={isHost} me={me.id} onOp={onOp} onStroke={onStroke} onPtr={onPtr} />
                        </div>
                        {stage === 'screen' && (
                            <div className="oc-screen">
                                {isHost ? (local.screen ? <Vid track={local.screen} fit="contain" onEl={(el) => { screenEl.current = el; }} /> : <div className="oc-empty">اشتراکِ صفحه متوقف شد.</div>)
                                    : hostTiles.screen ? <Vid track={hostTiles.screen} fit="contain" /> : <div className="oc-empty">🖥️ معلم صفحه‌اش را نشان می‌دهد…</div>}
                            </div>
                        )}
                        {/* دوربینِ معلم (یا دانش‌آموزِ نمایش‌داده‌شده) */}
                        <div className="oc-pips">
                            {isHost && local.cam && <Vid track={local.cam} mirror className="oc-pip" label="من" onEl={(el) => { camEl.current = el; }} />}
                            {!isHost && hostTiles.cam && <Vid track={hostTiles.cam} className={`oc-pip ${speaking.includes(room.teacher_id) ? 'spk' : ''}`} label={spotlightName ? `👁️ ${spotlightName}` : `👩‍🏫 ${room.teacher}`} />}
                            {!isHost && local.cam && <Vid track={local.cam} mirror className="oc-pip small" label="من" />}
                            {media === 'livekit' && Object.entries(tiles).filter(([uid, t]) => Number(uid) !== room.teacher_id && Number(uid) !== me.id && t.cam)
                                .slice(0, 4).map(([uid, t]) => <Vid key={uid} track={t.cam} className="oc-pip small" label={people.find((p) => p.id === Number(uid))?.name} />)}
                        </div>
                        {speakingNames.length > 0 && (
                            <div className="oc-spk-chip" aria-live="polite">🎙️ {speakingNames.join('، ')} {speakingNames.length > 1 ? 'صحبت می‌کنند' : 'صحبت می‌کند'}</div>
                        )}
                        <div className="oc-reacts" aria-hidden="true">
                            {reacts.map((r) => <span key={r.id} style={{ right: `${r.x}%` }}>{r.e}{r.name && <small>{r.name}</small>}</span>)}
                        </div>
                        {showPollCard && <PollCard poll={poll} onAnswer={answer} onDismiss={() => setPollHidden(`${poll.id}:${poll.status}`)} />}
                        {busy && <div className="oc-busy"><Mascot mood="think" size={64} /><span>{busy}</span></div>}
                    </div>

                    {isHost && stage !== 'screen' && (
                        <div className="oc-pages">
                            <button type="button" onClick={() => goPage(page - 1)} disabled={page <= 0} aria-label="صفحه‌ی قبل">◀</button>
                            <span>صفحه‌ی {fa(page + 1)} از {fa(pageCount)}</span>
                            <button type="button" onClick={() => goPage(page + 1)} disabled={page >= pageCount - 1} aria-label="صفحه‌ی بعد">▶</button>
                            <button type="button" onClick={() => addPage('blank')}>➕ صفحه</button>
                            <select aria-label="زمینه‌ی صفحه" onChange={(e) => { setBg(e.target.value); e.target.value = ''; }} value="">
                                <option value="" disabled>🎨 زمینه</option>
                                {BGS.map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                            </select>
                            <label className="oc-file">🖼️ اسلاید / PDF<input type="file" accept="image/*,application/pdf,.pdf" onChange={(e) => { uploadSlides(e.target.files?.[0]); e.target.value = ''; }} /></label>
                            <button type="button" onClick={removePage} disabled={pageCount <= 1} aria-label="حذفِ صفحه">🗑️</button>
                            <button type="button" onClick={toggleFs} title="تخته را تمام‌صفحه کن">⛶ تمام‌صفحه</button>
                        </div>
                    )}
                    {!isHost && <div className="oc-pages ro">صفحه‌ی {fa(page + 1)} از {fa(pageCount)}{canDraw && ' · ✏️ می‌توانی بنویسی'}</div>}
                </section>
                {sidePanel}
            </div>

            <nav className="oc-bar">
                {(isMod || mine?.can_speak) && (
                    <button type="button" className={`oc-cb ${local.mic ? 'on' : 'off'}`} onClick={() => toggleMic()}>
                        <span>{local.mic ? '🎤' : '🔇'}</span><small>{local.mic ? 'میکروفون' : 'میک خاموش'}</small>
                    </button>
                )}
                {(isMod || mine?.can_video) && (
                    <button type="button" className={`oc-cb ${local.cam ? 'on' : 'off'}`} onClick={() => toggleCam()}>
                        <span>{local.cam ? '🎥' : '📷'}</span><small>دوربین</small>
                    </button>
                )}
                {isHost && (
                    <button type="button" className={`oc-cb ${local.screen ? 'on' : ''}`} onClick={toggleScreen}>
                        <span>🖥️</span><small>{local.screen ? 'توقفِ اشتراک' : 'اشتراکِ صفحه'}</small>
                    </button>
                )}
                {!isMod && (
                    <button type="button" className={`oc-cb hand ${mine?.hand ? 'on' : ''}`} onClick={hand}>
                        <span>✋</span><small>{mine?.hand ? 'پایین بیاور' : 'دست بالا'}</small>
                    </button>
                )}
                <div className={`oc-reactbar ${reactOpen ? 'open' : ''}`}>
                    {REACTIONS.map((e) => <button key={e} type="button" onClick={() => { react(e); setReactOpen(false); }} aria-label={`واکنش ${e}`}>{e}</button>)}
                </div>
                <button type="button" className={`oc-cb oc-only-n ${reactOpen ? 'on' : ''}`} onClick={() => setReactOpen((o) => !o)}>
                    <span>😀</span><small>واکنش</small>
                </button>
                <button type="button" className={`oc-cb ${mute ? 'off' : ''}`} onClick={() => setMute((m) => !m)}>
                    <span>{mute ? '🔈' : '🔊'}</span><small>{mute ? 'صدا بسته' : 'صدا'}</small>
                </button>
                {isHost && config.recording && (
                    <button type="button" className={`oc-cb ${rec ? 'rec' : ''}`} onClick={toggleRec}>
                        <span>{rec ? '⏹' : '⏺'}</span><small>{rec ? 'توقفِ ضبط' : 'ضبط'}</small>
                    </button>
                )}
                {isMod && (
                    <button type="button" className="oc-cb" onClick={() => setMore((m) => !m)}>
                        <span>⚙️</span><small>ابزارها</small>
                    </button>
                )}
                {isHost && (
                    <button type="button" className={`oc-cb ${st?.settings?.kid_view === 'board' ? 'on' : ''}`} onClick={() => control('kidview', st?.settings?.kid_view === 'board' ? 'normal' : 'board')}
                        title="بچه‌ها فقط تخته را ببینند یا همه‌ی صحنه را">
                        <span>{st?.settings?.kid_view === 'board' ? '🎯' : '👀'}</span><small>{st?.settings?.kid_view === 'board' ? 'بچه‌ها: تخته' : 'بچه‌ها: همه'}</small>
                    </button>
                )}
                {fs && (
                    <button type="button" className="oc-fs-x" onClick={toggleFs}>⤫ خروج از تمام‌صفحه</button>
                )}
                <button type="button" className="oc-cb oc-only-m oc-only-land" onClick={() => setSheet(true)}>
                    <span>💬</span><small>گفت‌وگو{unread > 0 ? ` (${fa(unread)})` : ''}</small>
                </button>
            </nav>

            {more && isMod && (
                <div className="oc-more" role="dialog" aria-label="ابزارهای معلم">
                    <div className="oc-more-h"><b>⚙️ ابزارهای کلاس</b><button type="button" onClick={() => setMore(false)} aria-label="بستن">×</button></div>
                    <div className="oc-more-g">
                        <b>⏱️ تایمر</b>
                        <div className="oc-chips">
                            {[1, 2, 3, 5, 10].map((m) => <button key={m} type="button" onClick={() => control('timer', m * 60)}>{fa(m)} دقیقه</button>)}
                            <button type="button" onClick={() => control('timer', 0)}>✕ خاموش</button>
                        </div>
                    </div>
                    <div className="oc-more-g">
                        <b>🎛️ کنترلِ کلاس</b>
                        <div className="oc-chips">
                            <button type="button" onClick={() => control('chat', !(st?.settings?.chat !== false))}>{st?.settings?.chat !== false ? '🔒 بستنِ گفت‌وگو' : '💬 باز کردنِ گفت‌وگو'}</button>
                            <button type="button" onClick={() => control('lock', !st?.settings?.locked)}>{st?.settings?.locked ? '🔓 باز کردنِ در' : '🔐 قفلِ ورودِ تازه'}</button>
                            <button type="button" onClick={() => control('mute_all')}>🔇 بی‌صدا کردنِ همه</button>
                            <button type="button" onClick={() => control('lower_hands')}>✋ پایین آوردنِ دست‌ها</button>
                            <button type="button" onClick={() => control('stage', stage === 'board' ? 'screen' : 'board')}>{stage === 'board' ? '🖥️ نمایشِ صفحه‌ی اشتراکی' : '📝 نمایشِ تخته'}</button>
                            <button type="button" onClick={() => control('kidview', st?.settings?.kid_view === 'board' ? 'normal' : 'board')}>{st?.settings?.kid_view === 'board' ? '👀 بچه‌ها: همه‌ی صحنه' : '🎯 بچه‌ها: فقط تخته'}</button>
                            <button type="button" onClick={() => setReport(true)}>📋 گزارشِ حضور</button>
                        </div>
                    </div>
                    <div className="oc-more-g">
                        <b>📢 پیامِ بالای صفحه</b>
                        <form className="oc-send" onSubmit={(e) => { e.preventDefault(); control('announce', e.target.elements.a.value); e.target.reset(); }}>
                            <input name="a" maxLength={140} placeholder="مثلاً: کتاب‌ها را باز کنید، صفحه‌ی ۲۳" />
                            <button type="submit">➤</button>
                        </form>
                        {st?.settings?.announce && <button type="button" className="oc-link" onClick={() => control('announce', '')}>✕ برداشتنِ پیام</button>}
                    </div>
                    <small className="oc-note">{media === 'livekit' ? 'حالتِ سرورِ رسانه: مناسبِ کلاس‌های بزرگ.' : `حالتِ داخلی: صدای شما برای ${fa(link.connected)} نفر فرستاده می‌شود؛ کیفیتِ تصویر خودکار با تعدادِ نفرات تنظیم می‌شود.`}</small>
                </div>
            )}

            {endOpen && (
                <div className="oc-modal" role="dialog" aria-label="پایانِ کلاس">
                    <div className="oc-modal-box small">
                        <Mascot mood="happy" size={80} />
                        <h3>کلاس تمام شود؟</h3>
                        <p>همه از کلاس خارج می‌شوند و امتیازِ حضور به بچه‌هایی که بیشترِ کلاس بودند داده می‌شود.</p>
                        <div className="oc-row"><button type="button" className="oc-go danger" onClick={endClass}>⏹ بله، تمام شود</button><button type="button" className="oc-back" onClick={() => setEndOpen(false)}>ادامه‌ی کلاس</button></div>
                    </div>
                </div>
            )}
            {report && <ReportModal url={R('report')} onClose={() => setReport(false)} />}
        </div>
    );
}
