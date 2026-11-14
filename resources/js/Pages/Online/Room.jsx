import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import Mascot from '@/Components/Mascot';
import { toast } from '@/lib/toast';
import Board, { preloadImg, W as BW, H as BH } from '@/online/Board';
import RoomSync from '@/online/sync';
import createMesh from '@/online/mesh';
import ClassRecorder from '@/online/recorder';
import { importSlides, openPdf, renderPdfPage } from '@/online/slides';
import BookPanel from '@/online/books';
import { fa, Vid, DragBox, AudioSink, Clock, Countdown, Chat, People, PollPanel, PollCard, ReportModal, HandCards, CamViewer } from '@/online/parts';
import '@/../css/online.css';

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
const OFFICE = /\.(pptx?|ppsx?|odp|docx?|odt|rtf)$/i;
const LAYOUTS = [
    ['board_cam', '📝🎥', 'تخته + دوربین', 'تخته بزرگ، تصویرِ معلم کوچک و جابه‌جاشدنی'],
    ['board', '📝', 'فقط تخته / فایل', 'بچه‌ها فقط تخته و فایلِ درس را تمام‌صفحه می‌بینند'],
    ['cam', '🎥', 'فقط دوربین', 'تصویرِ معلم بزرگ وسطِ صفحه'],
    ['screen', '🖥️', 'اشتراکِ صفحه', 'نمایشِ صفحه‌ی رایانه‌ی معلم'],
];
const BACK = /back|rear|environment|پشت/i;
const FRONT = /front|user|facetime|integrated|webcam|hd camera|جلو/i;
const pref = (k) => { try { return localStorage.getItem(k) || ''; } catch { return ''; } };
const savePref = (k, v) => { try { if (v) localStorage.setItem(k, v); else localStorage.removeItem(k); } catch { /* حالتِ خصوصی */ } };
/** گرفتنِ دوربین/میک: اول دستگاهِ انتخاب‌شده؛ اگر نبود (جدا شده)، پیش‌فرض. */
async function grab(kind, base, id) {
    const want = (extra) => navigator.mediaDevices.getUserMedia({ [kind]: { ...base, ...extra } });
    if (id) {
        try { return await want({ deviceId: { exact: id } }); } catch (e) { if (e?.name === 'NotAllowedError') throw e; }
    }
    return want(kind === 'video' ? { facingMode: 'user' } : {});
}
const BGS = [['blank', '⬜ ساده'], ['lines', '📒 خط‌دار'], ['grid', '🔲 شطرنجی'], ['dots', '⠿ نقطه‌ای'], ['chalk', '🟩 تخته‌سیاه']];

/** این دستگاه چه چیزهایی را پشتیبانی می‌کند؟ (روی گوشی اشتراکِ صفحه/پخشِ فیلم/ضبط معمولاً نیست → دکمه‌اش نشان داده نمی‌شود) */
const CAN = (() => {
    if (typeof navigator === 'undefined' || typeof document === 'undefined') return { share: false, film: false, rec: false };
    const v = document.createElement('video');
    return { share: !!navigator.mediaDevices?.getDisplayMedia, film: !!(v.captureStream || v.mozCaptureStream), rec: ClassRecorder.supported() };
})();

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
    const [liveMs, setLiveMs] = useState({});      // uid → { ms, at }: وضعیتِ میک/دوربینِ تازه از کانالِ داده
    const [layoutOpen, setLayoutOpen] = useState(false);
    const [linkErr, setLinkErr] = useState(null);
    const [devs, setDevs] = useState({ cams: [], mics: [] });   // دوربین‌ها و میکروفون‌های این دستگاه
    const [devOpen, setDevOpen] = useState(false);   // دلیلِ وصل نشدنِ صدا/تصویر (برای عیب‌یابی)
    const [busy, setBusy] = useState(null);
    const [job, setJob] = useState(null);   // کارِ پشتِ صحنه (بقیه‌ی صفحه‌های فایل) — جلوی کارِ معلم را نمی‌گیرد
    const [booksOpen, setBooksOpen] = useState(false);
    const [play, setPlay] = useState(null);         // فیلم/آهنگی که معلم برای همه پخش می‌کند: { name, kind, url }
    const [bookGo, setBookGo] = useState('');
    const [rec, setRec] = useState(null);
    const [skew, setSkew] = useState(0);
    const [endOpen, setEndOpen] = useState(false);
    const [leaveOpen, setLeaveOpen] = useState(false);
    const [viewer, setViewer] = useState(null);   // معلم: شناسه‌ی کسی که تصویرش تمام‌صفحه باز است
    const [wait, setWait] = useState(null);

    const tr = useRef(null);          // ابزارِ رسانه (mesh یا livekit)
    const sync = useRef(null);
    const board = useRef(null);
    const stRef = useRef(null);
    const mineRef = useRef(null);
    const loaded = useRef({ page: -1, version: -1 });
    const saveT = useRef(null);
    // ── رساندنِ مطمئنِ تخته ──
    const lastHb = useRef(0);                 // آخرین «ضربانِ تخته» از معلم (نشانه‌ی سالم بودنِ کانالِ زنده)
    const loadingPg = useRef(null);           // صفحه‌ای که همین حالا از سرور خوانده می‌شود
    const loadSeq = useRef(0);
    const pendMsgs = useRef([]);              // پیام‌های تخته که پیش از آماده شدنِ صفحه رسیده‌اند
    const ownOps = useRef([]);                // نوشته‌های تازه‌ی خودم (تا هنگامِ ترمیم گم نشوند)
    const ownOpAt = useRef(0);
    const ownViewAt = useRef(0);
    const repair = useRef({ at: 0, n: 0, t: null });
    const snapBuf = useRef(null);
    const needers = useRef(new Map());        // معلم: چه کسانی نسخه‌ی کاملِ تخته می‌خواهند
    const needT = useRef(null);
    const savedIds = useRef(new Set());       // معلم: خط‌هایی که روی سرور ذخیره شده‌اند
    const saveChain = useRef(Promise.resolve());
    const recRef = useRef(null);
    const screenEl = useRef(null);
    const camEl = useRef(null);
    const lastReact = useRef(0);
    const helloAt = useRef(0);
    const joinedAt = useRef(0);
    const playEl = useRef(null);
    const playTracks = useRef({ video: null, audio: null, stream: null });
    const playRef = useRef(null);
    playRef.current = play;
    const bookDoc = useRef(null);            // { id, doc } — PDFِ کتابِ باز روی رایانه‌ی معلم
    const bookRendering = useRef(new Set()); // صفحه‌هایی که در حالِ ساختنِ تصویرشان هستیم
    const localRef = useRef(local);
    localRef.current = local;

    const page = st?.settings?.page ?? 0;
    const media = st?.media || room.media;
    const people = st?.people ?? [];
    const pages = st?.pages ?? [];
    // کتابِ باز: صفحه‌های ۱۰۰۰ به بعد صفحه‌های کتاب‌اند (۱۰۰۰ = صفحه‌ی ۱)
    const book = st?.settings?.book || null;
    const inBook = !!book && page >= 1000;
    const bookNo = inBook ? page - 999 : 0;
    const bookPending = inBook && (pages.find((x) => x.page === page)?.bg ?? 'blank') === 'blank';
    const boardPages = pages.filter((x) => x.page < 1000);
    const pageCount = Math.max(boardPages.length, page < 1000 ? page + 1 : 1, 1);
    const stage = st?.settings?.stage ?? 'board';
    const hostTiles = tiles[room.teacher_id] || {};
    const canDraw = isHost || !!mine?.can_draw;
    const kidBoard = !isMod && st?.settings?.kid_view === 'board' && stage === 'board';   // بچه‌ها فقط تخته را می‌بینند
    // «فقط دوربین» یا «اشتراکِ صفحه/فیلم» هم برای بچه‌ها کلِ صفحه‌ی گوشی را می‌گیرد
    const kidMedia = !isMod && (stage === 'cam' || stage === 'screen');
    const kidFull = kidBoard || kidMedia;
    /** تمام‌صفحه‌ی واقعی (آیفون فقط برای ویدیو اجازه می‌دهد). */
    const kidGoFull = () => {
        const v = document.querySelector('.oc-screen video');
        try {
            if (document.fullscreenElement) { document.exitFullscreen?.(); return; }
            if (document.documentElement.requestFullscreen) { document.documentElement.requestFullscreen().catch(() => v?.webkitEnterFullscreen?.()); return; }
            if (v?.webkitEnterFullscreen) { v.webkitEnterFullscreen(); return; }
        } catch { /* */ }
        toast('گوشی را افقی کن تا بزرگ‌تر ببینی.', 'info');
    };
    const layout = stage === 'screen' ? 'screen' : stage === 'cam' ? 'cam' : st?.settings?.kid_view === 'board' ? 'board' : 'board_cam';
    /** وضعیتِ رسانه‌ی هر نفر: ready+mic+cam+scr (تازه‌ترین: کانالِ داده؛ وگرنه سرور). */
    const msOf = (uid) => {
        const l = liveMs[uid];
        if (l && Date.now() - l.at < 15000) return l.ms;
        return String(people.find((p) => p.id === uid)?.ms || '');
    };
    const camOn = (uid) => (uid === me.id ? !!local.cam : msOf(uid).includes('+cam'));
    const spotId = st?.settings?.spotlight || null;
    // تصویرِ اصلیِ کلاس برای دانش‌آموز: دوربینِ معلم (یا دانش‌آموزی که معلم نمایش داده)
    const mainCam = isHost
        ? (spotId && media === 'livekit' ? tiles[spotId]?.cam : local.cam) || null
        : (media === 'livekit' && spotId && tiles[spotId]?.cam && camOn(spotId)) ? tiles[spotId].cam
            : (hostTiles.cam && media !== 'livekit' && (spotId ? camOn(spotId) : camOn(room.teacher_id))) ? hostTiles.cam
                : (hostTiles.cam && media === 'livekit' && camOn(room.teacher_id)) ? hostTiles.cam : null;
    const mainCamLabel = spotId ? `👁️ ${people.find((p) => p.id === spotId)?.name || ''}` : isHost ? 'من' : `👩‍🏫 ${room.teacher}`;
    const speakingNames = people.filter((p) => speaking.includes(p.id)).map((p) => p.name);
    // همه‌ی دوربین‌های روشن (برای «سینمای دوربین»ِ معلم)
    const camList = [
        ...(local.cam ? [{ id: me.id, name: 'من', track: local.cam, mirror: true }] : []),
        ...Object.entries(tiles).map(([uid, t]) => ({ id: Number(uid), track: t.cam })).filter((c) => c.id !== me.id && c.track && camOn(c.id))
            .map((c) => ({ ...c, name: people.find((p) => p.id === c.id)?.name || (c.id === room.teacher_id ? room.teacher : '') }))
            .sort((a, b) => (a.id === room.teacher_id ? -1 : b.id === room.teacher_id ? 1 : String(a.name).localeCompare(String(b.name), 'fa'))),
    ];
    const toggleFs = () => {
        const next = !fs;
        setFs(next);
        try {
            if (next) document.documentElement.requestFullscreen?.()?.catch?.(() => {});
            else if (document.fullscreenElement) document.exitFullscreen?.();
        } catch { /* مرورگر اجازه نداد؛ حالتِ تمام‌صفحه‌ی خودِ صفحه کافی است */ }
    };

    // ── تخته ─────────────────────────────────────────────────────────
    /** کانالِ زنده‌ی تخته سالم است؟ (معلم همیشه؛ دانش‌آموز فقط وقتی ضربانِ تخته‌ی معلم می‌رسد) */
    const chanLive = useCallback(() => !!tr.current?.dataOpen() && (isHost || Date.now() - lastHb.current < 9000), [isHost]);

    /** پیامِ تخته را روی تخته اعمال کن. */
    const applyBoardMsg = useCallback((msg) => {
        if (msg.t === 'op' && msg.op) board.current?.add(msg.op);
        else if (msg.t === 'undo') board.current?.undo(msg.id);
        else if (msg.t === 'bg') { board.current?.setBg(msg.bg); loaded.current.bg = msg.bg; }
    }, []);

    const loadPage = useCallback(async (p, merge = false, tries = 0) => {
        // همان صفحه همین حالا در راه است → درخواستِ تکراری نفرست
        if (loadingPg.current && loadingPg.current.page === p && Date.now() - loadingPg.current.at < 8000 && !tries) return;
        const my = ++loadSeq.current;
        loadingPg.current = { page: p, at: Date.now() };
        try {
            const { data } = await axios.get(R('board'), { params: { page: p }, timeout: 15000 });
            if (my !== loadSeq.current) return;   // درخواستِ تازه‌تری جایش را گرفته
            loadingPg.current = null;
            let ops = Array.isArray(data.ops) ? data.ops : [];
            const have = new Set(ops.map((o) => o.id));
            if (merge && board.current) {
                // معلم: کارهای هنوز ذخیره‌نشده را نگه می‌دارد تا با نوشته‌ی بچه پاک نشوند؛
                // ولی خطی که قبلاً ذخیره بود و حالا روی سرور نیست یعنی دانش‌آموز پاکش کرده → برداشته می‌شود.
                const before = board.current.ops();
                const mine = new Set(before.map((o) => o.id));
                const keep = before.filter((o) => !have.has(o.id) && !savedIds.current.has(o.id));
                const gone = before.filter((o) => !have.has(o.id) && savedIds.current.has(o.id));
                const fresh = ops.filter((o) => !mine.has(o.id));
                ops = [...ops, ...keep];
                // آنچه از راهِ سرور آمده برای بچه‌هایی که کانالِ زنده دارند هم پخش شود
                fresh.slice(-200).forEach((op) => tr.current?.broadcast({ t: 'op', page: p, op }));
                gone.slice(-200).forEach((op) => tr.current?.broadcast({ t: 'undo', page: p, id: op.id }));
            } else if (!isHost && board.current && loaded.current.page === p) {
                // دانش‌آموز: نوشته‌ی چند ثانیه‌ی اخیرِ خودش که هنوز به سرور نرسیده، نپرد
                const now = Date.now();
                ops = [...ops, ...ownOps.current.filter((x) => x.page === p && now - x.at < 6000 && !have.has(x.op.id)).map((x) => x.op)];
            }
            savedIds.current = have;
            loaded.current = { page: p, version: data.version, bg: data.bg };
            board.current?.load(ops, data.bg);
            // پیام‌هایی که وسطِ بارگذاری رسیده بودند (معلم همان لحظه شروع به نوشتن کرده)
            if (!isHost) {
                const now = Date.now();
                const mineNow = pendMsgs.current.filter((x) => x.msg.page === p && now - x.at < 20000);
                pendMsgs.current = pendMsgs.current.filter((x) => x.msg.page !== p && now - x.at < 20000);
                mineNow.forEach((x) => applyBoardMsg(x.msg));
            }
            // بزرگ‌نماییِ ذخیره‌شده‌ی همین صفحه (برای کسی که تازه آمده)؛ صفحه‌ی دیگر از اندازه‌ی کامل
            const v = stRef.current?.settings?.view;
            board.current?.setView(v && v.page === p ? v : null);
            viewApplied.current = v && v.page === p ? JSON.stringify(v) : '';
        } catch {
            if (my !== loadSeq.current) return;
            loadingPg.current = null;
            // سرور جواب نداد: خودش دوباره تلاش می‌کند (قبلاً تا تغییرِ بعدیِ کلاس، تخته خالی می‌ماند)
            const want = stRef.current?.settings?.page;
            if (tries < 6 && (want === undefined || want === p)) setTimeout(() => { if (loadSeq.current === my) loadPage(p, merge, tries + 1); }, Math.min(6000, 900 * (tries + 1)));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const saveBoard = useCallback((now = false) => {
        if (!isHost) return null;
        const run = async (retry = false) => {
            const p = loaded.current.page;
            if (p < 0 || !board.current) return;
            const ops = board.current.ops().slice();
            try {
                const { data } = await axios.post(R('board.save'), { page: p, bg: board.current.bg(), ops: JSON.stringify(ops), base: loaded.current.version });
                if (loaded.current.page === p) { loaded.current.version = data.version; savedIds.current = new Set(ops.map((o) => o.id)); }
            } catch (e) {
                // یک دانش‌آموز همین‌وقت چیزی نوشته: اول نسخه‌ی تازه، بعد ذخیره‌ی دوباره
                if (e?.response?.status === 409 && !retry) { await loadPage(p, true, 1); return run(true); }
                // سرور جواب نداد → کمی بعد دوباره (تخته از کانالِ زنده به بچه‌ها رسیده است)
                if (!retry && loaded.current.page === p && !saveT.current) saveT.current = setTimeout(() => { saveT.current = null; queue(); }, 3000);
            }
        };
        // ذخیره‌ها پشتِ سرِ هم (نه هم‌زمان) تا با خودشان تداخل نکنند
        const queue = () => { saveChain.current = saveChain.current.then(() => run()).catch(() => {}); return saveChain.current; };
        if (now) { clearTimeout(saveT.current); saveT.current = null; return queue(); }
        // هنگامِ نوشتنِ پیوسته هم هر ~۱ ثانیه ذخیره می‌شود (قبلاً تا مکثِ معلم صبر می‌کرد و بچه‌های بدونِ کانالِ زنده عقب می‌ماندند)
        if (!saveT.current) saveT.current = setTimeout(() => { saveT.current = null; queue(); }, 900);
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
        const direct = chanLive();
        if (!isHost) {
            const now = Date.now();
            ownOps.current = [...ownOps.current.filter((x) => now - x.at < 8000 && x.op.id !== op.id), ...(op.k === '__undo' ? [] : [{ page: p, op, at: now }])];
            ownOpAt.current = now;
        }
        if (op.k === '__undo') {
            send({ t: 'undo', page: p, id: op.id });
            if (!isHost && !direct) boardOpSend({ page: p, kind: 'undo', id: op.id });
        } else {
            send({ t: 'op', page: p, op });
            if (!isHost && !direct) boardOpSend({ page: p, kind: 'op', op });
        }
        saveBoard();
    }, [send, saveBoard, boardOpSend, chanLive]);
    const onStroke = useCallback((s) => send({ t: 'stroke', page: loaded.current.page, ...s }), [send]);
    const onPtr = useCallback((x, y) => send({ t: 'ptr', x, y }), [send]);
    /** بزرگ‌نمایی/جابه‌جاییِ تخته (معلم یا دانش‌آموزِ مجاز) → برای همه. معلم آن را ذخیره هم می‌کند. */
    const viewApplied = useRef('');
    const observersSeen = useRef(new Set());
    const viewSaveT = useRef(null);
    const saveView = useCallback((v) => {
        clearTimeout(viewSaveT.current);
        viewSaveT.current = setTimeout(() => { axios.post(R('control'), { action: 'view', value: v }).catch(() => {}); }, 700);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
    const onView = useCallback((v) => {
        const msg = { t: 'view', page: loaded.current.page, z: v.z, x: v.x, y: v.y };
        ownViewAt.current = Date.now();
        viewApplied.current = JSON.stringify({ page: msg.page, z: v.z, x: v.x, y: v.y });
        send(msg);
        if (isHost) saveView({ page: msg.page, z: v.z, x: v.x, y: v.y });
    }, [send, isHost, saveView]);

    /**
     * «ضربانِ تخته»: معلم هر ~۲٫۵ ثانیه امضای کوتاهِ تخته‌اش را می‌فرستد. پیام‌های مطمئن به‌ترتیب می‌رسند،
     * پس اگر امضای دانش‌آموز فرق داشت یعنی واقعاً چیزی جا مانده (قطعیِ کوتاه، ورودِ دیرهنگام…) و همان لحظه ترمیم می‌شود.
     */
    const onHeartbeat = (msg) => {
        lastHb.current = Date.now();
        const b = board.current;
        if (!b || phaseRef.current !== 'in') return;
        if (msg.page !== loaded.current.page) {
            if (msg.page !== stRef.current?.settings?.page) sync.current?.now();   // معلم ورق زده و من هنوز خبر ندارم
            return;
        }
        if (loadingPg.current) return;
        if (msg.bg && msg.bg !== b.bg()) { b.setBg(msg.bg); loaded.current.bg = msg.bg; }
        if (Date.now() - ownViewAt.current > 3000) {
            const v = msg.v ? { z: msg.v[0], x: msg.v[1], y: msg.v[2] } : { z: 1, x: 0, y: 0 };
            const cur = b.view();
            if (Math.abs(cur.z - v.z) > 0.01 || Math.abs(cur.x - v.x) > 2 || Math.abs(cur.y - v.y) > 2) b.setView(v);
        }
        if (Date.now() - ownOpAt.current < 3500) return;   // نوشته‌ی خودم هنوز در راهِ معلم است
        if (b.sig() === msg.sig) { repair.current.n = 0; clearTimeout(repair.current.t); return; }
        const r = repair.current; const now = Date.now();
        if (now - r.at < [0, 3500, 7000, 14000, 30000][Math.min(r.n, 4)]) return;
        r.at = now; r.n += 1;
        const pg = msg.page;
        tr.current?.sendTo(room.teacher_id, { t: 'need', page: pg });
        // اگر نسخه‌ی کامل از کانالِ زنده نرسید، از سرور بخوان
        clearTimeout(r.t);
        r.t = setTimeout(() => { if (loaded.current.page === pg) loadPage(pg, false, 1); }, 3200);
    };

    /** نسخه‌ی کاملِ تخته از معلم (چند تکه). */
    const onSnap = (msg) => {
        if (msg.via === 'server') { clearTimeout(repair.current.t); if (msg.page === loaded.current.page) loadPage(msg.page, false, 1); return; }
        let buf = snapBuf.current;
        if (!buf || buf.sid !== msg.sid) buf = snapBuf.current = { sid: msg.sid, parts: [], got: 0 };
        if (buf.parts[msg.i] === undefined) { buf.parts[msg.i] = msg.ops || []; buf.got += 1; }
        if (buf.got < msg.of) return;
        snapBuf.current = null;
        clearTimeout(repair.current.t);
        if (msg.page !== loaded.current.page || !board.current) return;
        let ops = buf.parts.flat();
        const have = new Set(ops.map((o) => o.id));
        const now = Date.now();
        ops = [...ops, ...ownOps.current.filter((x) => x.page === msg.page && now - x.at < 6000 && !have.has(x.op.id)).map((x) => x.op)];
        board.current.load(ops, msg.bg || board.current.bg());
        loaded.current.bg = msg.bg || loaded.current.bg;
    };

    /** معلم: نسخه‌ی کاملِ تخته برای کسانی که خواسته‌اند — تکه‌تکه تا هر پیام سبک بماند. */
    const sendSnap = () => {
        const b = board.current; const t = tr.current;
        const now = Date.now();
        const ids = [...needers.current.entries()].filter(([, at]) => now - at < 2000).map(([uid]) => uid);
        for (const [uid, at] of needers.current) if (now - at > 60000) needers.current.delete(uid);
        const p = loaded.current.page;
        if (!b || !t || !ids.length || p < 0) return;
        const many = (m) => (t.sendMany ? t.sendMany(ids, m) : ids.forEach((u) => t.sendTo(u, m)));
        const chunks = [[]]; let size = 0; let big = false;
        for (const o of b.ops()) {
            const l = JSON.stringify(o).length;
            if (l > 12000) big = true;
            if (size + l > 7000 && chunks[chunks.length - 1].length) { chunks.push([]); size = 0; }
            chunks[chunks.length - 1].push(o); size += l;
        }
        if (big || chunks.length > 80) {
            // تخته‌ی خیلی سنگین: از سرور خوانده شود (اول ذخیره، بعد خبر)
            Promise.resolve(saveBoard(true)).then(() => many({ t: 'snap', page: p, via: 'server' }));
            return;
        }
        const sid = Math.random().toString(36).slice(2, 8);
        chunks.forEach((ops, i) => many({ t: 'snap', page: p, sid, i, of: chunks.length, bg: b.bg(), ops }));
    };

    /** پیام از کانالِ داده. */
    const onData = useCallback((from, msg) => {
        if (!msg || typeof msg !== 'object') return;
        const curPage = loaded.current.page;
        const sender = stRef.current?.people?.find((p) => p.id === from);
        const fromHost = from === room.teacher_id;
        // ── ترمیمِ تخته ──
        if (msg.t === 'need') {
            // دانش‌آموزی فهمیده تخته‌اش با معلم یکی نیست → نسخه‌ی کامل را برایش بفرست (چند نفر با هم، یک‌جا)
            if (!isHost || msg.page !== curPage || !sender) return;
            const last = needers.current.get(from) || 0;
            if (Date.now() - last < 2500) return;
            needers.current.set(from, Date.now());
            clearTimeout(needT.current);
            needT.current = setTimeout(sendSnap, 250);
            return;
        }
        if (msg.t === 'hb') { if (!isHost && fromHost) onHeartbeat(msg); return; }
        if (msg.t === 'snap') { if (!isHost && fromHost) onSnap(msg); return; }
        // معلم: نوشته‌ی دانش‌آموزی که اجازه‌ی تخته دارد را ثبت و برای بقیه پخش کن
        if (isHost && ['op', 'stroke', 'undo', 'view'].includes(msg.t)) {
            if (!sender?.can_draw || msg.page !== curPage) return;
            if (msg.t === 'view') {
                board.current?.setView(msg);
                send(msg, from);
                saveView({ page: msg.page, z: msg.z, x: msg.x, y: msg.y });
                return;
            }
            if (msg.t === 'op' && msg.op) { board.current?.add({ ...msg.op, by: from }); saveBoard(); }
            if (msg.t === 'undo') { board.current?.undo(msg.id); saveBoard(); }
            if (msg.t === 'stroke') board.current?.stroke(msg);
            send(msg, from);
            return;
        }
        if (msg.t === 'ms' && typeof msg.ms === 'string') {
            setLiveMs((m) => ({ ...m, [from]: { ms: msg.ms.slice(0, 40), at: Date.now() } }));
            return;
        }
        if (msg.t === 'speak') { if (!isHost && fromHost) setSpeaking(Array.isArray(msg.uids) ? msg.uids : []); return; }
        if (msg.t === 'react') {
            // معلم شکلک‌های بچه‌ها را خاموش کرده: نه نمایش، نه پخش برای بقیه
            if (!fromHost && sender?.role === 'student' && stRef.current?.settings?.reactions === false) return;
            showReact(msg.e, msg.name || sender?.name);
            if (isHost) send({ t: 'react', e: msg.e, name: sender?.name }, from);
            return;
        }
        if (!fromHost && !isHost) return;
        if (msg.t === 'op' || msg.t === 'undo' || msg.t === 'bg') {
            if (msg.page === curPage && !(loadingPg.current && loadingPg.current.page === msg.page)) applyBoardMsg(msg);
            else if (!isHost) {
                // صفحه هنوز آماده نیست (معلم تازه ورق زده یا من تازه آمده‌ام) → نگه دار و بعد از آماده شدن اعمال کن؛
                // قبلاً این پیام‌ها دور ریخته می‌شد و تخته‌ی دانش‌آموز «سفید» می‌ماند.
                pendMsgs.current.push({ at: Date.now(), msg });
                if (pendMsgs.current.length > 400) pendMsgs.current.splice(0, pendMsgs.current.length - 400);
                if (msg.page !== stRef.current?.settings?.page) sync.current?.now();
            }
        } else if (msg.t === 'stroke' && msg.page === curPage) board.current?.stroke(msg);
        else if (msg.t === 'ptr') board.current?.pointer(msg.x, msg.y);
        else if (msg.t === 'view' && msg.page === curPage) { board.current?.setView(msg); viewApplied.current = JSON.stringify({ page: msg.page, z: msg.z, x: msg.x, y: msg.y }); }
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
            // تصویرِ دو صفحه‌ی بعد از پیش بارگذاری شود تا ورقِ فایلِ درس فوری عوض شود
            for (const x of s.pages) {
                if (x.page > pg && x.page <= pg + 2 && typeof x.bg === 'string' && x.bg.startsWith('/')) preloadImg(x.bg);
            }
            const live = chanLive();
            if (loaded.current.page !== pg) loadPage(pg);
            else if (isHost && ver > loaded.current.version) loadPage(pg, true);
            // بدونِ کانالِ زنده (یا وقتی ضربانِ تخته‌ی معلم نمی‌رسد): تخته از سرور می‌آید و همگام‌سازی تندتر می‌شود
            else if (!isHost && ver > loaded.current.version && !live) { loadPage(pg); sync.current?.fast(5000); }
            if (!isHost && loaded.current.page === pg && !loadingPg.current) {
                // بزرگ‌نمایی از راهِ سرور (برای کسی که اتصالِ مستقیم ندارد)
                if (!live && s.settings.view && s.settings.view.page === pg) {
                    const vs = JSON.stringify(s.settings.view);
                    if (vs !== viewApplied.current) { viewApplied.current = vs; board.current?.setView(s.settings.view); }
                }
                // زمینه‌ی صفحه (اسلاید/صفحه‌ی کتاب) — همیشه بررسی می‌شود؛ قبلاً اگر معلم یک بار بزرگ‌نمایی کرده بود
                // این بررسی از کار می‌افتاد و تصویرِ صفحه‌ی تازه‌آماده‌شده به دانش‌آموز نمی‌رسید.
                const nbg = s.pages.find((x) => x.page === pg)?.bg;
                const cur = board.current?.bg();
                if (nbg && cur && nbg !== cur && (!live || cur === 'blank')) { board.current?.setBg(nbg); loaded.current.bg = nbg; }
            }
            // معلم: ورودِ ناظم/مدیر به کلاس اطلاع داده شود
            if (isHost) {
                const obs = s.people.filter((p) => p.role === 'observer' && p.online);
                for (const o of obs) if (!observersSeen.current.has(o.id)) toast(`🛡️ ${o.name} (ناظر) وارد کلاس شد.`, 'info');
                observersSeen.current = new Set(obs.map((o) => o.id));
            }
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
            onTrack: (uid, slot, track, sid, old) => setTiles((t) => {
                // پایانِ track قدیمی، track تازه‌ای را که زودتر رسیده پاک نکند
                if (!track && old && t[uid]?.[slot] && t[uid][slot] !== old) return t;
                return { ...t, [uid]: { ...(t[uid] || {}), [slot]: track } };
            }),
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
                // توکن و کتابخانه هم‌زمان (کتابخانه از لابی پیش‌بارگذاری شده است)
                const [{ data }, lk] = await Promise.all([axios.get(R('token')), import('@/online/lk')]);
                const createLk = lk.default;
                tr.current = await createLk({ ...common, url: data.url, token: data.token });
            } else {
                tr.current = createMesh({ ...common, ice: config.ice, budget: config.budget, signal: (to, kind, payload) => { sync.current?.fast(); return axios.post(R('signal'), { to, kind, payload }); } });
                if (!isHost) { helloAt.current = Date.now(); tr.current.hello(); }
            }
        } catch (e) {
            console.warn('[online] media', e);
            setLinkErr(String(e?.response?.data?.message || e?.message || e).slice(0, 160));
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

    // ── سرعتِ ورود: تا بچه راهنمای لابی را می‌خواند، کتابخانه‌ی رسانه و اتصال به سرورِ رسانه آماده می‌شود
    useEffect(() => {
        if ((room.media || '') !== 'livekit') return;
        import('@/online/lk').catch(() => {});
        import('livekit-client').catch(() => {});
        if (config.lk_origin && !document.querySelector(`link[data-oc-pre="${config.lk_origin}"]`)) {
            const l = document.createElement('link');
            l.rel = 'preconnect'; l.href = config.lk_origin; l.crossOrigin = 'anonymous'; l.dataset.ocPre = config.lk_origin;
            document.head.appendChild(l);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // ── گوشی: صفحه وسطِ کلاس خاموش نشود (بچه‌ای که فقط تخته را نگاه می‌کند دست به صفحه نمی‌زند)
    useEffect(() => {
        if (phase !== 'in' || !navigator.wakeLock?.request) return undefined;
        let lock = null;
        let gone = false;
        const get = () => { if (document.visibilityState === 'visible') navigator.wakeLock.request('screen').then((l) => { if (gone) l.release().catch(() => {}); else lock = l; }).catch(() => {}); };
        get();
        document.addEventListener('visibilitychange', get);
        return () => { gone = true; document.removeEventListener('visibilitychange', get); lock?.release?.().catch(() => {}); };
    }, [phase]);

    // ── برگشتن از برنامه‌ی دیگر (تماس، پیام): همان لحظه همگام شو و صدا را دوباره راه بینداز
    useEffect(() => {
        if (phase !== 'in') return undefined;
        const back = () => {
            if (document.visibilityState !== 'visible') return;
            sync.current?.now();
            tr.current?.resumeAudio?.();
            document.querySelectorAll('audio[data-oc-audio]').forEach((a) => a.play().catch(() => setAudioBlocked(true)));
            if (!isHost && tr.current && !tr.current.connected() && Date.now() - helloAt.current > 4000) { helloAt.current = Date.now(); tr.current.hello(); }
        };
        const online = () => { toast('🌐 اینترنت برگشت؛ در حالِ اتصالِ دوباره…', 'info'); back(); };
        document.addEventListener('visibilitychange', back);
        window.addEventListener('online', online);
        return () => { document.removeEventListener('visibilitychange', back); window.removeEventListener('online', online); };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [phase]);

    // ── گوشی: وقتی صفحه‌کلید باز می‌شود، کلاس به اندازه‌ی فضای دیدنی جمع شود (کادرِ پیام زیرِ صفحه‌کلید نماند)
    useEffect(() => {
        const vv = window.visualViewport;
        if (phase !== 'in' || !vv) return undefined;
        const root = document.documentElement;
        const fit = () => {
            const kb = window.innerHeight - vv.height > 120;   // صفحه‌کلید باز است
            if (kb) { root.style.setProperty('--oc-vvh', `${Math.round(vv.height)}px`); root.style.setProperty('--oc-vvt', `${Math.round(vv.offsetTop)}px`); root.classList.add('oc-kb'); }
            else { root.style.removeProperty('--oc-vvh'); root.style.removeProperty('--oc-vvt'); root.classList.remove('oc-kb'); }
        };
        vv.addEventListener('resize', fit);
        vv.addEventListener('scroll', fit);
        return () => { vv.removeEventListener('resize', fit); vv.removeEventListener('scroll', fit); root.style.removeProperty('--oc-vvh'); root.style.removeProperty('--oc-vvt'); root.classList.remove('oc-kb'); };
    }, [phase]);

    // با Esc یا دکمه‌ی برگشتِ گوشی از تمام‌صفحه‌ی مرورگر بیرون آمد → حالتِ تمام‌صفحه‌ی تخته هم بسته شود
    useEffect(() => {
        const ch = () => { if (!document.fullscreenElement) setFs(false); };
        document.addEventListener('fullscreenchange', ch);
        return () => document.removeEventListener('fullscreenchange', ch);
    }, []);

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

    // وضعیتِ میک/دوربین/صفحه را فوری به بقیه بگو (کانالِ داده) و در سرور هم ثبت کن؛
    // با خاموش شدنِ دوربین، آخرین تصویر روی صفحه‌ی بچه‌ها نمی‌ماند.
    useEffect(() => {
        if (phase !== 'in' || !sync.current) return;
        const base = String(sync.current.ms || 'ready').startsWith('nortc') ? 'nortc' : 'ready';
        const ms = base + (local.mic ? '+mic' : '') + (local.cam ? '+cam' : '') + (local.screen ? '+scr' : '');
        if (sync.current.ms === ms) return;
        sync.current.ms = ms;
        sync.current.now();
        send({ t: 'ms', ms });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [local, phase]);
    // «فقط دوربین» با سرورِ رسانه: تصویرِ معلم با بالاترین کیفیت
    useEffect(() => { if (!isHost) tr.current?.setFocus?.(stage === 'cam'); }, [stage, isHost, tiles]);
    // هر چند ثانیه یک بار: وضعیتِ خودم برای کسی که تازه وصل شده
    useEffect(() => {
        if (phase !== 'in') return undefined;
        const t = setInterval(() => { if (sync.current?.ms) send({ t: 'ms', ms: sync.current.ms }); }, 10000);
        return () => clearInterval(t);
    }, [phase, send]);

    // ── میکروفون، دوربین، اشتراکِ صفحه ────────────────────────────────
    const toggleMic = async (on = !localRef.current.mic) => {
        if (on) {
            if (!isMod && !mineRef.current?.can_speak) { toast('✋ اول دست بالا کن تا معلم اجازه‌ی صحبت بدهد.', 'info'); return; }
            try {
                const s = await grab('audio', { echoCancellation: true, noiseSuppression: true, autoGainControl: true }, pref('oc-mic'));
                const t = s.getAudioTracks()[0];
                await tr.current?.setMic(t);
                setLocal((l) => ({ ...l, mic: t }));
                listDevices();
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
                // با سرورِ رسانه، تصویرِ معلم HD گرفته می‌شود (سرور برای هر دستگاه کیفیتِ مناسب را می‌فرستد)
                const hd = media === 'livekit' && isHost;
                const base = { width: { ideal: hd ? 1280 : 640 }, height: { ideal: hd ? 720 : 360 }, frameRate: { ideal: hd ? 24 : 20, max: 24 } };
                let t = (await grab('video', base, pref('oc-cam'))).getVideoTracks()[0];
                // لپ‌تاپ/تبلت‌هایی که پیش‌فرضشان دوربینِ پشت است: بارِ اول خودکار دوربینِ جلو
                if (!pref('oc-cam') && BACK.test(t.label)) {
                    const list = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'videoinput');
                    const front = list.find((d) => FRONT.test(d.label) && !BACK.test(d.label)) || list.find((d) => d.deviceId !== t.getSettings().deviceId && !BACK.test(d.label));
                    if (front) {
                        try { const n = (await grab('video', base, front.deviceId)).getVideoTracks()[0]; t.stop(); t = n; } catch { /* همان قبلی */ }
                    }
                }
                listDevices();
                await tr.current?.setCam(t);
                setLocal((l) => ({ ...l, cam: t }));
            } catch { toast('اجازه‌ی دوربین داده نشد.'); }
        } else {
            localRef.current.cam?.stop();
            await tr.current?.setCam(null);
            setLocal((l) => ({ ...l, cam: null }));
        }
    };
    /** فهرستِ دوربین‌ها و میکروفون‌ها (نام‌ها فقط پس از اجازه معلوم است). */
    const listDevices = async () => {
        try {
            const l = await navigator.mediaDevices.enumerateDevices();
            setDevs({ cams: l.filter((d) => d.kind === 'videoinput' && d.deviceId), mics: l.filter((d) => d.kind === 'audioinput' && d.deviceId && d.deviceId !== 'communications') });
        } catch { /* */ }
    };
    useEffect(() => {
        const md = navigator.mediaDevices;
        if (!md?.addEventListener) return undefined;
        md.addEventListener('devicechange', listDevices);
        return () => md.removeEventListener('devicechange', listDevices);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
    /** تعویضِ دوربین یا میکروفون وسطِ کلاس (انتخاب روی همین دستگاه می‌ماند). */
    const switchDevice = async (kind, id) => {
        setDevOpen(false);
        if (kind === 'video') {
            savePref('oc-cam', id);
            const old = localRef.current.cam;
            if (!old) return;
            const hd = media === 'livekit' && isHost;
            const base = { width: { ideal: hd ? 1280 : 640 }, height: { ideal: hd ? 720 : 360 }, frameRate: { ideal: hd ? 24 : 20, max: 24 } };
            try {
                let t;
                // اول دوربینِ تازه، بعد بستنِ قبلی (بی‌وقفه)؛ اگر دستگاه دو دوربین را با هم باز نکرد، اول قبلی را می‌بندیم
                try { t = (await grab('video', base, id)).getVideoTracks()[0]; } catch { old.stop(); t = (await grab('video', base, id)).getVideoTracks()[0]; }
                await tr.current?.setCam(t);
                old.stop();
                setLocal((l) => ({ ...l, cam: t }));
            } catch {
                toast('این دوربین باز نشد؛ دوربینِ دیگری را انتخاب کنید.');
                await tr.current?.setCam(null);
                setLocal((l) => ({ ...l, cam: null }));
            }
        } else {
            savePref('oc-mic', id);
            const old = localRef.current.mic;
            if (!old) return;
            try {
                const t = (await grab('audio', { echoCancellation: true, noiseSuppression: true, autoGainControl: true }, id)).getAudioTracks()[0];
                await tr.current?.setMic(t);
                old.stop();
                setLocal((l) => ({ ...l, mic: t }));
            } catch { toast('این میکروفون باز نشد.'); }
        }
    };

    // ── 🎬 پخشِ فیلم/آهنگ برای همه ─────────────────────────────────────
    // فایل روی رایانه‌ی معلم پخش می‌شود و تصویر و صدایش زنده برای همه فرستاده می‌شود (هم‌زمان، بدونِ بارگذاری)
    const startPlay = async (file) => {
        if (!file) return;
        const probe = document.createElement('video');
        if (!probe.captureStream && !probe.mozCaptureStream) { toast('پخشِ فیلم برای همه روی این مرورگر ممکن نیست؛ از کروم یا اج روی رایانه استفاده کنید.'); return; }
        await stopPlay(true);
        if (localRef.current.screen) {
            localRef.current.screen.stop();
            await tr.current?.setScreen(null);
            setLocal((l) => ({ ...l, screen: null }));
        }
        const kind = /^audio\//.test(file.type) || /\.(mp3|m4a|aac|ogg|oga|wav|flac|opus|wma)$/i.test(file.name) ? 'audio' : 'video';
        setPlay({ name: file.name.replace(/\.[^.]+$/, '').slice(0, 100), kind, url: URL.createObjectURL(file) });
    };
    /** با شروعِ پخش: تصویر و صدای همان پخش‌کننده برای همه فرستاده می‌شود. */
    const capturePlay = async () => {
        const el = playEl.current;
        if (!el || playTracks.current.stream) return;
        let stream;
        try { stream = el.captureStream ? el.captureStream() : el.mozCaptureStream(); } catch (e) { console.warn('[online] capture', e); toast('پخش برای همه آغاز نشد.'); return; }
        playTracks.current.stream = stream;
        const hook = async () => {
            const vt = stream.getVideoTracks()[0] || null;
            const at = stream.getAudioTracks()[0] || null;
            if (vt && vt !== playTracks.current.video) {
                vt.contentHint = 'motion';
                playTracks.current.video = vt;
                await tr.current?.setScreen(vt, true);
                setLocal((l) => ({ ...l, screen: vt }));
            }
            if (at && at !== playTracks.current.audio) {
                playTracks.current.audio = at;
                await tr.current?.setMediaAudio?.(at);
            }
        };
        stream.addEventListener('addtrack', hook);
        await hook();
        const p = playRef.current;
        if (p) await control('playing', { name: p.name, kind: p.kind });
        if (playTracks.current.video) control('layout', 'screen');
    };
    const stopPlay = async (silent = false) => {
        const pt = playTracks.current;
        if (pt.video) { await tr.current?.setScreen(null); setLocal((l) => ({ ...l, screen: null })); }
        if (pt.audio) await tr.current?.setMediaAudio?.(null);
        pt.stream?.getTracks().forEach((t) => t.stop());
        playTracks.current = { video: null, audio: null, stream: null };
        try { playEl.current?.pause(); } catch { /* */ }
        const had = playRef.current;
        if (had?.url) URL.revokeObjectURL(had.url);
        setPlay(null);
        if (had && !silent) {
            await control('playing', null);
            if (stRef.current?.settings?.stage === 'screen') control('layout', 'board_cam');
        }
    };

    const toggleScreen = async () => {
        if (playRef.current) { await stopPlay(); return; }
        if (localRef.current.screen) {
            localRef.current.screen.stop();
            await tr.current?.setScreen(null);
            setLocal((l) => ({ ...l, screen: null }));
            control('stage', 'board');
            return;
        }
        if (!navigator.mediaDevices?.getDisplayMedia) { toast('اشتراکِ صفحه روی این دستگاه پشتیبانی نمی‌شود (از رایانه استفاده کنید).'); return; }
        try {
            const s = await navigator.mediaDevices.getDisplayMedia({ video: { frameRate: { ideal: media === 'livekit' ? 15 : 10, max: 15 } }, audio: false });
            const t = s.getVideoTracks()[0];
            t.contentHint = 'detail';
            t.addEventListener('ended', () => { tr.current?.setScreen(null); setLocal((l) => ({ ...l, screen: null })); control('stage', 'board'); });
            await tr.current?.setScreen(t);
            setLocal((l) => ({ ...l, screen: t }));
            control('stage', 'screen');
        } catch { /* لغو شد */ }
    };

    // ── فرمان‌ها ──────────────────────────────────────────────────────
    const control = async (action, value, user, perm, extra) => {
        try {
            const { data } = await axios.post(R('control'), { action, value, user, perm, ...(extra || {}) });
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
        if (job) { toast('کمی صبر کنید تا همه‌ی صفحه‌های فایل اضافه شود.', 'info'); return; }
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
    // ── 📚 کتابِ درسی ─────────────────────────────────────────────────
    const bookDocFor = async (b) => {
        if (bookDoc.current?.id === b.id) return bookDoc.current.doc;
        const doc = await openPdf(R('book.file', b.id));
        bookDoc.current = { id: b.id, doc };
        return doc;
    };
    const openBook = async (b) => {
        setBooksOpen(false);
        setBusy(`📖 در حالِ باز کردنِ «${b.title}»…`);
        try {
            await saveBoard(true);
            const doc = await bookDocFor(b);
            await axios.post(R('book.open', b.id), { pages: doc.numPages });
            loaded.current = { page: -1, version: -1 };
            sync.current?.now();
            send({ t: 'sync' });
        } catch (e) {
            console.warn('[online] book', e);
            toast(e?.response?.data?.message || 'کتاب باز نشد؛ دوباره امتحان کنید.');
        } finally { setBusy(null); }
    };
    const closeBook = async () => {
        await saveBoard(true);
        loaded.current = { page: -1, version: -1 };
        await control('book_close');
    };
    const goBookPage = (n) => {
        if (!book || !Number.isFinite(n)) return;
        goPage(999 + Math.max(1, Math.min(book.pages || 9999, Math.round(n))));
    };
    /** ذخیره‌ی صفحه‌ی فعلیِ تخته (با زمینه و نوشته‌ها) به‌صورتِ عکس روی رایانه. */
    const savePng = () => {
        // کلِ صفحه با اندازه‌ی واقعی (حتی وقتی بزرگ‌نمایی شده)
        let c = board.current?.snapshot?.();
        if (!c) {
            c = document.createElement('canvas'); c.width = BW; c.height = BH;
            const g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, BW, BH);
            for (const cv of board.current?.canvases() || []) if (cv?.width) g.drawImage(cv, 0, 0, BW, BH);
        }
        c.toBlob((blob) => {
            if (!blob) { toast('عکس ساخته نشد.'); return; }
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            const name = inBook ? `${book.title}-صفحه-${bookNo}` : `${room.title}-تخته-${page + 1}`;
            a.download = `${name.replace(/[\\/:*?"<>|]+/g, ' ').trim()}.png`;
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 4000);
        }, 'image/png');
    };

    const uploadSlides = async (file) => {
        if (!file) return;
        if (job) { toast('فایلِ قبلی هنوز در حالِ اضافه شدن است؛ کمی صبر کنید.', 'info'); return; }
        setBusy('در حالِ آماده کردنِ اسلایدها…');
        try {
            await saveBoard(true);
            // پاورپوینت/ورد: سرور آن را PDF می‌کند؛ بعد مثلِ PDF صفحه‌به‌صفحه تصویرِ سبک می‌شود
            if (OFFICE.test(file.name)) {
                setBusy('در حالِ تبدیلِ فایل به PDF… (چند ثانیه)');
                const fd = new FormData();
                fd.append('file', file);
                let pdf;
                try {
                    const res = await axios.post(R('convert'), fd, {
                        responseType: 'blob', timeout: 200000,
                        onUploadProgress: (e) => e.total && setBusy(`ارسالِ فایل… ${fa(Math.round((e.loaded / e.total) * 100))}٪`),
                    });
                    pdf = res.data;
                } catch (e) {
                    let m = null;
                    try { m = JSON.parse(await e?.response?.data?.text?.())?.message; } catch { /* */ }
                    toast(m || 'فایل تبدیل نشد. در پاورپوینت از «File ← Save As ← PDF» آن را PDF کنید و همان را بفرستید.');
                    return;
                }
                file = new File([pdf], file.name.replace(/\.[^.]+$/, '') + '.pdf', { type: 'application/pdf' });
            }
            const start = pageCount;
            // صفحه‌ی اول همین‌که آماده شد برای همه نمایش داده می‌شود؛ بقیه پشتِ صحنه اضافه می‌شوند
            const urls = await importSlides(file, R('asset.upload'), (i, n) => (i < 1 ? setBusy(`آماده کردنِ صفحه‌ی اول از ${fa(n)}…`) : setJob(`صفحه‌ی ${fa(Math.min(i + 1, n))} از ${fa(n)} در حالِ آماده شدن…`)),
                async (url, i) => {
                    await axios.post(R('board.save'), { page: start + i, bg: url, ops: '[]' });
                    if (i === 0) {
                        loaded.current = { page: -1, version: -1 };
                        await control('page', start);
                        setBusy(null);
                    }
                });
            toast(`🖼️ ${fa(urls.length)} صفحه به تخته اضافه شد.`, 'success');
        } catch (e) {
            console.warn(e);
            toast(e?.response?.data?.message || 'فایل آماده نشد. عکس (JPG/PNG)، PDF یا پاورپوینت بفرستید.');
        } finally { setBusy(null); setJob(null); }
    };

    const reactOn = isMod || st?.settings?.reactions !== false;
    const react = (e) => {
        if (!isMod && stRef.current?.settings?.reactions === false) return;
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
    const deletePoll = (id) => axios.delete(R('poll.destroy', id)).then(() => { setPollHidden(null); sync.current?.now(); send({ t: 'sync' }); }).catch(() => toast('پاک نشد.'));

    /** تغییرِ فوری روی صفحه (پیش از پاسخِ سرور) تا دکمه‌ها بی‌درنگ واکنش نشان دهند. */
    const patchPerson = (uid, patch) => {
        const upd = (st0) => (st0 ? { ...st0, people: st0.people.map((x) => (uid === null ? (x.role === 'student' ? { ...x, ...patch } : x) : x.id === uid ? { ...x, ...patch } : x)) } : st0);
        stRef.current = upd(stRef.current);
        setSt(upd);
    };
    /** دسترسیِ یک دانش‌آموز. keep: دستش بالا بماند (از کارتِ «دستِ بالا» چند دسترسی پشتِ هم داده می‌شود). */
    const perm = (uid, p, v, keep = false) => {
        patchPerson(uid, p === 'all' ? { can_speak: v, can_video: v, can_draw: v } : { [p]: v, ...(p === 'can_speak' && v && !keep ? { hand: null } : {}) });
        return control('perm', v, uid, p, keep ? { keep_hand: true } : null);
    };
    const lowerHand = (uid) => { patchPerson(uid, { hand: null }); return control('lower_hands', null, uid); };
    const allAct = (a, v) => {
        if (a === 'revoke_all') patchPerson(null, { can_speak: false, can_video: false, can_draw: false });
        if (a === 'lower_hands') patchPerson(null, { hand: null });
        return control(a, v);
    };
    const star = async (uid) => { const d = await control('star', null, uid); if (d) { showReact('⭐'); toast(d.xp ? `⭐ ستاره داده شد (+${fa(d.xp)} امتیاز)` : '⭐ ستاره داده شد', 'success'); } };
    const kick = (p) => { if (confirm(`«${p.name}» از کلاس بیرون گذاشته شود؟`)) control('kick', null, p.id); };
    const spot = async (uid) => {
        await control('spotlight', null, uid);
        if (media !== 'livekit') tr.current?.relayCam(uid ? tiles[uid]?.cam : null);
    };

    /** چیدمانِ صفحه‌ی کلاس برای همه: تخته+دوربین، فقط تخته/فایل، فقط دوربین، اشتراکِ صفحه. */
    const setLayout = async (v) => {
        setLayoutOpen(false);
        if (v === 'screen' && !localRef.current.screen) { await toggleScreen(); return; }
        // فیلمِ در حالِ پخش با عوض شدنِ چیدمان قطع نشود
        if (v !== 'screen' && localRef.current.screen && !playRef.current) {
            localRef.current.screen.stop();
            await tr.current?.setScreen(null);
            setLocal((l) => ({ ...l, screen: null }));
        }
        if (v === 'cam' && !localRef.current.cam) await toggleCam(true);
        await control('layout', v);
    };

    // ── ضبط ──────────────────────────────────────────────────────────
    const toggleRec = async () => {
        if (recRef.current) {
            setBusy('در حالِ ذخیره‌ی فیلم…');
            const res = await recRef.current.stop();
            recRef.current = null; setRec(null); setBusy(null);
            control('recording', false);
            if (res?.saved) toast(res.failed ? '🎬 فیلم ذخیره شد، ولی بخشی از آن به‌خاطرِ اینترنت بارگذاری نشد.' : '🎬 فیلمِ کلاس ذخیره شد؛ در صفحه‌ی «کلاس‌های آنلاین» ← «فیلم‌های کلاس» هست.', res.failed ? 'info' : 'success');
            else toast('فیلم ذخیره نشد (بارگذاری به سرور انجام نشد). اینترنت را بررسی کنید و دوباره ضبط کنید.');
            return;
        }
        if (!ClassRecorder.supported()) { toast('این مرورگر ضبط را پشتیبانی نمی‌کند (کروم یا اج روی رایانه).'); return; }
        const r = new ClassRecorder({
            uploadUrl: R('record'),
            finishUrl: R('record.finish'),
            getStage: () => ({ mode: localRef.current.screen && stRef.current?.settings?.stage === 'screen' ? 'screen' : stRef.current?.settings?.stage === 'cam' ? 'cam' : 'board',
                canvases: board.current?.canvases() || [], screen: screenEl.current, cam: camEl.current }),
            getAudioTracks: () => [localRef.current.mic, playTracks.current.audio, ...(tr.current?.remoteAudioTracks() || [])].filter(Boolean),
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

    // تصویرِ بزرگ‌شده با بهترین کیفیت گرفته شود؛ با بستن به حالتِ کم‌مصرف برگردد
    useEffect(() => {
        if (viewer === null || viewer === me.id) return undefined;
        tr.current?.setBig?.(viewer, true);
        return () => tr.current?.setBig?.(viewer, false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [viewer]);

    // دسترسیِ آزمایشی برای عیب‌یابی (فقط با oc-debug در حافظه‌ی مرورگر)
    useEffect(() => {
        try { if (window.localStorage.getItem('oc-debug')) window.__ocDbg = { board, tr, loaded, lastHb, sync }; } catch { /* */ }
    }, []);

    // معلم: ضربانِ تخته (امضای محتوا + زمینه + بزرگ‌نمایی) برای ترمیمِ خودکارِ تخته‌ی بچه‌ها
    useEffect(() => {
        if (!isHost || phase !== 'in') return undefined;
        const id = setInterval(() => {
            const b = board.current; const p = loaded.current.page;
            if (!b || p < 0 || !tr.current || loadingPg.current) return;
            const v = b.view();
            tr.current.broadcast({ t: 'hb', page: p, sig: b.sig(), bg: b.bg(), v: v.z > 1.001 ? [v.z, v.x, v.y] : 0 });
        }, 2500);
        return () => clearInterval(id);
    }, [isHost, phase]);

    // تخته پس از نمایشِ صفحه‌ی کلاس بارگذاری شود (پیش از آن بوم وجود ندارد)
    useEffect(() => {
        if (phase === 'in' && stRef.current) { loaded.current = { page: -1, version: -1 }; loadPage(stRef.current.settings.page); }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [phase]);

    // معلم: تصویرِ صفحه‌ی فعلیِ کتاب (و دو صفحه‌ی بعد) را یک‌بار از PDF می‌سازد؛ بعد برای همه فوری است
    useEffect(() => {
        if (!isHost || !inBook || !book || phase !== 'in') return undefined;
        let stop = false;
        (async () => {
            const last = Math.min(bookNo + 2, book.pages || bookNo + 2);
            for (let i = bookNo; i <= last; i += 1) {
                if (stop && i !== bookNo) break;
                const entry = pages.find((x) => x.page === 999 + i);
                if (entry && entry.bg && entry.bg !== 'blank') continue;
                const key = `${book.id}:${i}`;
                if (bookRendering.current.has(key)) continue;
                bookRendering.current.add(key);
                try {
                    const doc = await bookDocFor(book);
                    const blob = await renderPdfPage(doc, i);
                    const fd = new FormData();
                    fd.append('page', String(i));
                    fd.append('file', new File([blob], `p${i}.jpg`, { type: 'image/jpeg' }));
                    const { data } = await axios.post(R('book.image', book.id), fd, { timeout: 120000 });
                    if (i === bookNo && data?.bg && loaded.current.page === 999 + i) {
                        // همه همین حالا تصویرِ صفحه را ببینند
                        board.current?.setBg(data.bg);
                        loaded.current.bg = data.bg;
                        send({ t: 'bg', page: 999 + i, bg: data.bg });
                    }
                    if (i === bookNo) sync.current?.now();
                } catch (e) {
                    bookRendering.current.delete(key);
                    console.warn('[online] book page', e);
                    if (i === bookNo) toast('صفحه‌ی کتاب آماده نشد؛ یک بار صفحه را عوض کنید و برگردید.');
                    break;
                }
            }
        })();
        return () => { stop = true; };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isHost, inBook, bookNo, book?.id, book?.pages, pages, phase]);

    // پنلِ گفت‌وگو باز شد → خوانده‌شده
    useEffect(() => { if (panel === 'chat' && (sheet || window.innerWidth > 900 || window.matchMedia('(orientation: portrait)').matches)) setUnread(0); }, [panel, sheet, msgs.length]);

    // ── صداها ────────────────────────────────────────────────────────
    // صدای هر نفر + صدای فیلم/آهنگِ معلم (با LiveKit جدا می‌آید)
    const audioTracks = useMemo(() => Object.entries(tiles).flatMap(([uid, t]) => (Number(uid) === me.id ? []
        : [t.audio && [uid, t.audio], t.saudio && [`${uid}:s`, t.saudio]].filter(Boolean))), [tiles, me.id]);
    // فیلمِ معلم: گوشی‌ها نسخه‌ی سبک‌تر (LiveKit)
    useEffect(() => { if (!isHost) tr.current?.setMovie?.(st?.settings?.playing?.kind === 'video'); }, [isHost, st?.settings?.playing?.kind, tiles]);

    const poll = st?.poll;
    const showPollCard = !isMod && poll && pollHidden !== `${poll.id}:${poll.status}` && (poll.status === 'open' || (poll.status === 'shown' && poll.mine !== null));
    const hands = people.filter((p) => p.online && p.hand).length;
    // فهرستِ نفرات جلوی چشمِ معلم است؟ (آن‌وقت کارتِ شناورِ «دستِ بالا» تکراری است و جلوی فهرست را می‌گیرد)
    const peopleShown = panel === 'people' && (sheet || (typeof window !== 'undefined' && (window.innerWidth > 900 || window.matchMedia('(orientation: portrait)').matches)));

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
                {panel === 'people' && <People people={people} me={me.id} isMod={isMod} isHost={isHost} tiles={tiles} speaking={speaking} msOf={msOf} onPerm={perm} onStar={star} onKick={kick} onSpot={spot}
                    spotlight={st?.settings?.spotlight} onAll={allAct} openMics={!!st?.settings?.open_mics} onLower={lowerHand} onCam={isMod ? setViewer : undefined} />}
                {panel === 'poll' && <PollPanel poll={poll} isMod={isMod} onCreate={newPoll} onClose={closePoll} onDelete={deletePoll} />}
            </div>
        </aside>
    );

    return (
        <div className={`oc ${isHost ? 'is-host' : ''} ${canDraw && stage === 'board' ? 'has-tools' : ''} ${kidFull ? 'kid-board' : ''} ${kidMedia ? 'kid-media' : ''} ${fs ? 'oc-fs' : ''}`} dir="rtl">
            <Head title={room.title} />
            {audioTracks.map(([uid, t]) => <AudioSink key={`${uid}:${t.id}`} track={t} muted={mute} onBlocked={() => setAudioBlocked(true)} />)}

            <header className="oc-top">
                <button type="button" className="oc-ib" onClick={() => setLeaveOpen(true)} title="خروج" aria-label="خروج از کلاس">→</button>
                <div className="oc-title">
                    <b>{st?.title || room.title}</b>
                    <span><i className="oc-live" />زنده · <Clock from={st?.started_at} skew={skew} /></span>
                </div>
                <Countdown end={st?.settings?.timer_end} skew={skew} />
                {st?.settings?.recording && <span className="oc-rec">⏺ ضبط</span>}
                <span className={`oc-link ${link.state === 'failed' && !isHost ? 'bad' : ''}`} title={linkBadge}><i>{linkBadge.split(' ')[0]}</i><em>{linkBadge.slice(linkBadge.indexOf(' ') + 1)}</em></span>
                {!isHost && link.state === 'failed' && <button type="button" className="oc-ib" onClick={() => { helloAt.current = Date.now(); joinedAt.current = Date.now(); setLink((l) => ({ ...l, state: 'connecting' })); tr.current?.hello(); }} title="تلاشِ دوباره">↻</button>}
                <span className="oc-count">👥 {fa(people.filter((p) => p.online).length)}</span>
                {isMod && <button type="button" className="oc-end" onClick={() => setEndOpen(true)}>⏹ پایانِ کلاس</button>}
            </header>

            {st?.settings?.announce && <div className="oc-announce">📢 {st.settings.announce}</div>}
            {audioBlocked && <button type="button" className="oc-unmute" onClick={unlockAudio}>🔊 برای شنیدنِ صدای کلاس این‌جا را بزن</button>}
            {!isHost && (link.state === 'failed' || link.state === 'nortc') && (
                <div className="oc-announce oc-weak">
                    {link.state === 'nortc' ? 'این مرورگر صدا و تصویرِ کلاس را پشتیبانی نمی‌کند (کروم یا سافاریِ به‌روز را امتحان کن)؛ تخته، گفت‌وگو و سؤال‌ها کار می‌کند.' : 'صدا و تصویر وصل نشد؛ تخته، گفت‌وگو و سؤال‌ها کار می‌کند. دکمه‌ی ↻ را بزن یا اینترنت را عوض کن.'}
                    <a href={route('online.check')} target="_blank" rel="noreferrer">🔍 آزمایشِ اتصال</a>
                    {linkErr && <small>{media === 'livekit' ? 'LiveKit' : 'P2P'}: {linkErr}</small>}
                </div>
            )}

            <div className="oc-main">
                <section className="oc-stage-wrap">
                    <div className={`oc-stage stage-${stage}`}>
                        <div className="oc-board" style={{ display: stage !== 'board' ? 'none' : undefined }}>
                            <Board ref={board} canDraw={canDraw} isHost={isHost} me={me.id} onOp={onOp} onStroke={onStroke} onPtr={onPtr} onView={onView} />
                        </div>
                        {stage === 'screen' && (
                            <div className="oc-screen">
                                {isHost ? (local.screen ? <Vid track={local.screen} fit="contain" onEl={(el) => { screenEl.current = el; }} /> : <div className="oc-empty">اشتراکِ صفحه متوقف شد.</div>)
                                    : hostTiles.screen ? <Vid track={hostTiles.screen} fit="contain" /> : <div className="oc-empty">🖥️ معلم صفحه‌اش را نشان می‌دهد…</div>}
                            </div>
                        )}
                        {stage === 'cam' && (
                            <div className="oc-screen oc-camstage">
                                {mainCam
                                    ? <Vid key={mainCam.id} track={mainCam} fit="contain" mirror={isHost && !spotId} className={speaking.includes(room.teacher_id) ? 'spk' : ''} label={mainCamLabel} onEl={isHost ? (el) => { camEl.current = el; } : undefined} />
                                    : <div className="oc-empty">📷 {isHost ? 'دوربینِ شما خاموش است؛ دکمه‌ی دوربین را بزنید.' : 'دوربینِ معلم الان خاموش است…'}</div>}
                            </div>
                        )}
                        {/* دوربین‌ها: جابه‌جاشدنی با کشیدن، کوچک/بزرگ و جمع‌شدنی */}
                        <DragBox id="pips" className="oc-pips" onTap={isMod ? setViewer : undefined}>
                            {isHost && local.cam && stage !== 'cam' && <Vid key="me" track={local.cam} mirror className="oc-pip" label="من" cam={isMod ? me.id : undefined} onEl={(el) => { camEl.current = el; }} />}
                            {!isHost && mainCam && stage !== 'cam' && <Vid key="main" track={mainCam} className={`oc-pip ${speaking.includes(room.teacher_id) ? 'spk' : ''}`} label={mainCamLabel} cam={isMod ? (spotId || room.teacher_id) : undefined} />}
                            {!isHost && local.cam && <Vid key="me" track={local.cam} mirror className="oc-pip small" label="من" />}
                            {media === 'livekit' && Object.entries(tiles).filter(([uid, t]) => Number(uid) !== room.teacher_id && Number(uid) !== me.id && Number(uid) !== spotId && t.cam && camOn(Number(uid)))
                                .slice(0, 6).map(([uid, t]) => <Vid key={uid} track={t.cam} className={`oc-pip small ${speaking.includes(Number(uid)) ? 'spk' : ''}`} label={people.find((p) => p.id === Number(uid))?.name} cam={isMod ? Number(uid) : undefined} />)}
                        </DragBox>
                        {kidFull && <button type="button" className="oc-kid-fs" onClick={kidGoFull} aria-label="تمام‌صفحه">⛶</button>}
                        {kidMedia && <div className="oc-rotate-hint" aria-hidden="true">🔄 برای تصویرِ بزرگ‌تر گوشی را افقی کن</div>}
                        {st?.settings?.playing && (
                            <div className="oc-play-chip" aria-live="polite">{st.settings.playing.kind === 'audio' ? '🎵' : '🎬'} در حالِ پخش: {st.settings.playing.name}</div>
                        )}
                        {speakingNames.length > 0 && (
                            <div className="oc-spk-chip" aria-live="polite">🎙️ {speakingNames.join('، ')} {speakingNames.length > 1 ? 'صحبت می‌کنند' : 'صحبت می‌کند'}</div>
                        )}
                        <div className="oc-reacts" aria-hidden="true">
                            {reacts.map((r) => <span key={r.id} style={{ right: `${r.x}%` }}>{r.e}{r.name && <small>{r.name}</small>}</span>)}
                        </div>
                        {showPollCard && <PollCard poll={poll} onAnswer={answer} onDismiss={() => setPollHidden(`${poll.id}:${poll.status}`)} />}
                        {busy && <div className="oc-busy"><Mascot mood="think" size={64} /><span>{busy}</span></div>}
                        {!busy && job && <div className="oc-job">⏳ {job}</div>}
                        {!busy && bookPending && stage === 'board' && <div className="oc-book-wait">📖 صفحه‌ی {fa(bookNo)} در حالِ آماده شدن…</div>}
                    </div>

                    {isHost && stage === 'board' && !inBook && (
                        <div className="oc-pages">
                            <button type="button" onClick={() => goPage(page - 1)} disabled={page <= 0} aria-label="صفحه‌ی قبل">◀</button>
                            <span>صفحه‌ی {fa(page + 1)} از {fa(pageCount)}</span>
                            <button type="button" onClick={() => goPage(page + 1)} disabled={page >= pageCount - 1} aria-label="صفحه‌ی بعد">▶</button>
                            <button type="button" onClick={() => addPage('blank')}>➕ صفحه</button>
                            <select aria-label="زمینه‌ی صفحه" onChange={(e) => { setBg(e.target.value); e.target.value = ''; }} value="">
                                <option value="" disabled>🎨 زمینه</option>
                                {BGS.map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                            </select>
                            <label className="oc-file" title="عکس، PDF، پاورپوینت یا ورد؛ بعد با قلم روی آن بنویسید">📂 فایلِ درس<input type="file" accept="image/*,application/pdf,.pdf,.ppt,.pptx,.pps,.ppsx,.odp,.doc,.docx,.odt,.rtf" onChange={(e) => { uploadSlides(e.target.files?.[0]); e.target.value = ''; }} /></label>
                            <button type="button" className="oc-book-btn" onClick={() => setBooksOpen(true)} title="کتاب‌های درسیِ من (روی سرور)">📚 کتاب{book ? `: ${book.title}` : ''}</button>
                            <button type="button" onClick={removePage} disabled={pageCount <= 1} aria-label="حذفِ صفحه">🗑️</button>
                            <button type="button" onClick={savePng} title="ذخیره‌ی این صفحه به‌صورتِ عکس روی رایانه">💾 عکس</button>
                            <button type="button" onClick={toggleFs} title="تخته را تمام‌صفحه کن">⛶ تمام‌صفحه</button>
                        </div>
                    )}
                    {isHost && stage === 'board' && inBook && (
                        <div className="oc-pages oc-pages-book">
                            <button type="button" onClick={() => goBookPage(bookNo - 1)} disabled={bookNo <= 1} aria-label="صفحه‌ی قبلِ کتاب">◀</button>
                            <span className="oc-book-t">📖 {book.title}</span>
                            <form className="oc-book-go-f" onSubmit={(e) => { e.preventDefault(); goBookPage(Number(String(bookGo).replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))); setBookGo(''); }}>
                                صفحه‌ی <input inputMode="numeric" aria-label="رفتن به صفحه" value={bookGo} placeholder={fa(bookNo)} onChange={(e) => setBookGo(e.target.value)} />{book.pages ? ` از ${fa(book.pages)}` : ''}
                            </form>
                            <button type="button" onClick={() => goBookPage(bookNo + 1)} disabled={!!book.pages && bookNo >= book.pages} aria-label="صفحه‌ی بعدِ کتاب">▶</button>
                            <button type="button" onClick={() => setBooksOpen(true)}>📚 کتاب‌ها</button>
                            <button type="button" onClick={savePng} title="ذخیره‌ی این صفحه با نوشته‌ها به‌صورتِ عکس">💾 عکس</button>
                            <button type="button" onClick={toggleFs} title="تمام‌صفحه">⛶</button>
                            <button type="button" className="oc-book-close" onClick={closeBook}>📕 بستنِ کتاب</button>
                        </div>
                    )}
                    {!isHost && stage === 'board' && (
                        <div className="oc-pages ro">
                            {inBook ? `📖 ${book.title} · صفحه‌ی ${fa(bookNo)}${book.pages ? ` از ${fa(book.pages)}` : ''}` : `صفحه‌ی ${fa(page + 1)} از ${fa(pageCount)}`}
                            {canDraw && ' · ✏️ می‌توانی بنویسی'}
                        </div>
                    )}
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
                {((local.cam && devs.cams.length > 1) || (local.mic && devs.mics.length > 1)) && (
                    <button type="button" className={`oc-cb ${devOpen ? 'on' : ''}`} onClick={() => { listDevices(); setDevOpen((o) => !o); }} title="انتخابِ دوربین و میکروفون">
                        <span>🔄</span><small>{local.cam && devs.cams.length > 1 ? 'تعویضِ دوربین' : 'میکروفون'}</small>
                    </button>
                )}
                {devOpen && (
                    <div className="oc-layouts oc-devs" role="menu" aria-label="دوربین و میکروفون">
                        {local.cam && devs.cams.length > 1 && (
                            <>
                                <b>📷 کدام دوربین؟</b>
                                {devs.cams.map((d, i) => {
                                    const on = local.cam?.getSettings?.().deviceId === d.deviceId;
                                    return (
                                        <button key={d.deviceId} type="button" role="menuitemradio" aria-checked={on} className={on ? 'on' : ''} onClick={() => switchDevice('video', d.deviceId)}>
                                            <span>{BACK.test(d.label) ? '🔙' : '🤳'}</span><span><b>{d.label || `دوربینِ ${fa(i + 1)}`}</b>{on && <small>الان روشن است</small>}</span>
                                        </button>
                                    );
                                })}
                            </>
                        )}
                        {local.mic && devs.mics.length > 1 && (
                            <>
                                <b>🎤 کدام میکروفون؟</b>
                                {devs.mics.map((d, i) => {
                                    const on = local.mic?.getSettings?.().deviceId === d.deviceId;
                                    return (
                                        <button key={d.deviceId} type="button" role="menuitemradio" aria-checked={on} className={on ? 'on' : ''} onClick={() => switchDevice('audio', d.deviceId)}>
                                            <span>🎙️</span><span><b>{d.label || `میکروفونِ ${fa(i + 1)}`}</b>{on && <small>الان روشن است</small>}</span>
                                        </button>
                                    );
                                })}
                            </>
                        )}
                    </div>
                )}
                {isHost && (CAN.share || (local.screen && !play)) && (
                    <button type="button" className={`oc-cb ${local.screen && !play ? 'on' : ''}`} onClick={toggleScreen}>
                        <span>🖥️</span><small>{local.screen && !play ? 'توقفِ اشتراک' : 'اشتراکِ صفحه'}</small>
                    </button>
                )}
                {isHost && (play
                    ? (
                        <button type="button" className="oc-cb rec" onClick={() => stopPlay()}>
                            <span>⏹</span><small>پایانِ پخش</small>
                        </button>
                    ) : CAN.film && (
                        <label className="oc-cb oc-cb-file" title="پخشِ فیلم یا آهنگ از رایانه‌ی شما برای همه">
                            <span>🎬</span><small>فیلم/آهنگ</small>
                            <input type="file" accept="video/*,audio/*,.mkv,.mp3,.m4a,.wav,.ogg" onChange={(e) => { startPlay(e.target.files?.[0]); e.target.value = ''; }} />
                        </label>
                    ))}
                {!isMod && (
                    <button type="button" className={`oc-cb hand ${mine?.hand ? 'on' : ''}`} onClick={hand}>
                        <span>✋</span><small>{mine?.hand ? 'پایین بیاور' : 'دست بالا'}</small>
                    </button>
                )}
                {reactOn && (
                    <>
                        <div className={`oc-reactbar ${reactOpen ? 'open' : ''}`}>
                            {REACTIONS.map((e) => <button key={e} type="button" onClick={() => { react(e); setReactOpen(false); }} aria-label={`واکنش ${e}`}>{e}</button>)}
                        </div>
                        <button type="button" className={`oc-cb oc-only-n ${reactOpen ? 'on' : ''}`} onClick={() => setReactOpen((o) => !o)}>
                            <span>😀</span><small>واکنش</small>
                        </button>
                    </>
                )}
                <button type="button" className={`oc-cb ${mute ? 'off' : ''}`} onClick={() => setMute((m) => !m)}>
                    <span>{mute ? '🔈' : '🔊'}</span><small>{mute ? 'صدا بسته' : 'صدا'}</small>
                </button>
                {isHost && config.recording && (CAN.rec || rec) && (
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
                    <button type="button" className={`oc-cb oc-cb-layout ${layoutOpen ? 'on' : ''}`} onClick={() => setLayoutOpen((o) => !o)} title="بچه‌ها چه چیزی را ببینند">
                        <span>{LAYOUTS.find((l) => l[0] === layout)?.[1]}</span><small>چیدمان</small>
                    </button>
                )}
                {isHost && layoutOpen && (
                    <div className="oc-layouts" role="menu" aria-label="چیدمانِ صفحه">
                        <b>بچه‌ها چه ببینند؟</b>
                        {LAYOUTS.filter(([k]) => k !== 'screen' || CAN.share || layout === 'screen').map(([k, ic, t, d]) => (
                            <button key={k} type="button" role="menuitemradio" aria-checked={layout === k} className={layout === k ? 'on' : ''} onClick={() => setLayout(k)}>
                                <span>{ic}</span><span><b>{t}</b><small>{d}</small></span>
                            </button>
                        ))}
                    </div>
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
                        <form className="oc-timer-f" onSubmit={(e) => {
                            e.preventDefault();
                            const num = (v) => Number(String(v || '0').replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d))) || 0;
                            const sec = num(e.target.elements.m.value) * 60 + num(e.target.elements.s.value);
                            if (sec <= 0) { toast('زمان را وارد کنید.'); return; }
                            control('timer', Math.min(sec, 3 * 3600));
                            e.target.reset();
                        }}>
                            <span>⌨️ دلخواه:</span>
                            <input name="m" inputMode="numeric" maxLength={3} placeholder="دقیقه" aria-label="دقیقه" />
                            <b>:</b>
                            <input name="s" inputMode="numeric" maxLength={2} placeholder="ثانیه" aria-label="ثانیه" />
                            <button type="submit">▶ شروع</button>
                        </form>
                    </div>
                    <div className="oc-more-g">
                        <b>🎛️ کنترلِ کلاس</b>
                        <div className="oc-chips">
                            <button type="button" onClick={() => control('chat', !(st?.settings?.chat !== false))}>{st?.settings?.chat !== false ? '🔒 بستنِ گفت‌وگو' : '💬 باز کردنِ گفت‌وگو'}</button>
                            <button type="button" onClick={() => control('lock', !st?.settings?.locked)}>{st?.settings?.locked ? '🔓 باز کردنِ در' : '🔐 قفلِ ورودِ تازه'}</button>
                            <button type="button" onClick={() => control('mute_all')}>🔇 بی‌صدا کردنِ همه</button>
                            <button type="button" onClick={() => control('lower_hands')}>✋ پایین آوردنِ دست‌ها</button>
                            <button type="button" className={st?.settings?.reactions === false ? '' : 'on'} onClick={() => control('reactions', st?.settings?.reactions === false)}
                                title="شکلک‌های احساسیِ بچه‌ها (👏❤️😂…)">{st?.settings?.reactions === false ? '😶 شکلکِ بچه‌ها: خاموش' : '😀 شکلکِ بچه‌ها: روشن'}</button>
                            <button type="button" onClick={() => setReport(true)}>📋 گزارشِ حضور</button>
                        </div>
                    </div>
                    <div className="oc-more-g">
                        <b>🧩 چیدمانِ صفحه برای بچه‌ها</b>
                        <div className="oc-chips">
                            {LAYOUTS.filter(([k]) => k !== 'screen' || CAN.share || layout === 'screen').map(([k, ic, t]) => <button key={k} type="button" className={layout === k ? 'on' : ''} onClick={() => setLayout(k)} disabled={!isHost}>{ic} {t}</button>)}
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
                    {isHost && !(CAN.share && CAN.film && CAN.rec) && <small className="oc-note">📱 روی این دستگاه {[!CAN.share && 'اشتراکِ صفحه', !CAN.film && 'پخشِ فیلم', !CAN.rec && 'ضبط'].filter(Boolean).join('، ')} پشتیبانی نمی‌شود؛ برای این‌ها از کروم یا اج روی رایانه واردِ کلاس شوید.</small>}
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
            {/* بیرون از نوارِ پایین: آن نوار در تمام‌صفحه پنهان است و این دکمه هم با آن پنهان می‌شد */}
            {fs && <button type="button" className="oc-fs-x" onClick={toggleFs}>⤫ خروج از تمام‌صفحه</button>}
            {isMod && phase === 'in' && viewer === null && !peopleShown && <HandCards people={people} onPerm={perm} onLower={lowerHand} onMore={() => { setPanel('people'); setSheet(true); }} />}
            {isMod && viewer !== null && (
                <CamViewer cams={camList} id={viewer} onChange={setViewer} onClose={() => setViewer(null)} people={people} isMod={isMod} isHost={isHost}
                    onPerm={perm} onStar={star} onSpot={spot} spotlight={st?.settings?.spotlight} speaking={speaking} msOf={msOf} />
            )}
            {leaveOpen && (
                <div className="oc-modal" role="dialog" aria-label="خروج از کلاس" onClick={(e) => { if (e.target === e.currentTarget) setLeaveOpen(false); }}>
                    <div className="oc-modal-box small">
                        <Mascot mood="idle" size={72} />
                        <h3>{isHost ? 'از کلاس بیرون می‌روید؟' : 'از کلاس بیرون می‌روی؟'}</h3>
                        <p>{isHost ? 'کلاس برای بچه‌ها باز می‌ماند و می‌توانید دوباره برگردید. برای تمام کردنِ کلاس «⏹ پایانِ کلاس» را بزنید.' : 'کلاس هنوز ادامه دارد؛ هر وقت خواستی می‌توانی دوباره برگردی.'}</p>
                        <div className="oc-row"><button type="button" className="oc-go" onClick={() => setLeaveOpen(false)}>🎥 می‌مانم</button><button type="button" className="oc-back" onClick={leave}>خروج</button></div>
                    </div>
                </div>
            )}
            {report && <ReportModal url={R('report')} onClose={() => setReport(false)} />}
            {isHost && play && (
                <div className={`oc-player-mini ${play.kind}`}>
                    <div className="oc-player-mini-h">
                        <b>{play.kind === 'audio' ? '🎵' : '🎬'} {play.name}</b>
                        <button type="button" onClick={() => stopPlay()} aria-label="پایانِ پخش">⏹</button>
                    </div>
                    {play.kind === 'audio'
                        ? <audio ref={playEl} src={play.url} controls autoPlay onPlaying={capturePlay} />
                        : <video ref={playEl} src={play.url} controls autoPlay playsInline onPlaying={capturePlay} />}
                    <small>با دکمه‌های همین‌جا مکث، جلو و عقب کنید؛ برای همه هم‌زمان پخش می‌شود.</small>
                </div>
            )}
            {booksOpen && isHost && (
                <BookPanel listUrl={R('books')} uploadUrl={R('books.upload')} deleteUrl={(id) => R('book.destroy', id)} current={book?.id}
                    onOpen={openBook} onClose={() => setBooksOpen(false)} />
            )}
        </div>
    );
}
