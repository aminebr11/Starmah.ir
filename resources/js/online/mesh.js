/**
 * رسانه‌ی «داخلی» کلاسِ آنلاین — بدونِ سرورِ رسانه‌ی جدا.
 *
 * ساختار ستاره‌ای است: هر دانش‌آموز فقط به معلم وصل است (نه به همه).
 *  - معلم صدای خودش و صدای بچه‌هایی که اجازه‌ی صحبت دارند را در مرورگرش «میکس»
 *    می‌کند و برای هر نفر یک صدا می‌فرستد؛ پس همه صدای هم را می‌شنوند، ولی
 *    هر گوشی فقط یک اتصال دارد (سبک و کم‌مصرف).
 *  - کیفیتِ تصویر بر اساسِ تعدادِ نفرات خودکار پایین/بالا می‌شود تا اینترنتِ معلم
 *    کم نیاورد (سقف را ادمینِ کل تعیین می‌کند).
 *  - تخته، نشانگر و واکنش‌ها از «کانالِ داده»ی همین اتصال می‌روند (بی‌درنگ).
 * پیام‌های اتصال (offer/answer) از راهِ همگام‌سازیِ سرور رد و بدل می‌شوند؛
 * برای کم‌کردنِ رفت‌وبرگشت، نامزدهای ICE داخلِ همان پیام جمع می‌شوند.
 */

const SLOTS = ['audio', 'cam', 'screen'];

function waitIce(pc, ms = 2500) {
    if (pc.iceGatheringState === 'complete') return Promise.resolve();
    return new Promise((resolve) => {
        const done = () => { clearTimeout(t); pc.removeEventListener('icegatheringstatechange', check); pc.removeEventListener('icecandidate', cand); resolve(); };
        const check = () => { if (pc.iceGatheringState === 'complete') done(); };
        const cand = (e) => { if (!e.candidate) done(); };
        const t = setTimeout(done, ms);
        pc.addEventListener('icegatheringstatechange', check);
        pc.addEventListener('icecandidate', cand);
    });
}

function setEncoding(sender, enc) {
    if (!sender?.track) return;
    try {
        const p = sender.getParameters();
        if (!p.encodings || !p.encodings.length) p.encodings = [{}];
        Object.assign(p.encodings[0], enc);
        sender.setParameters(p).catch(() => {});
    } catch { /* مرورگرِ قدیمی */ }
}

export default function createMesh(opts) {
    const { me, isHost, hostId, ice = [], budget = 3000, signal, onTrack, onData, onPeer, onSpeaking } = opts;
    const config = { iceServers: ice, bundlePolicy: 'max-bundle' };
    const peers = new Map();          // معلم: uid → peer | دانش‌آموز: hostId → peer
    const local = { mic: null, cam: null, screen: null };
    let speakers = new Set();
    let relayed = null;          // تصویرِ دانش‌آموزی که معلم برای همه نمایش می‌دهد
    let counter = 0;
    let closed = false;

    // ── میکسرِ صدا (فقط معلم) ─────────────────────────────────────────
    let ctx = null; let micGain = null; let micSrc = null; let micAn = null;
    let spkTimer = null; let lastSpk = '';
    const remotes = new Map();        // uid → { src, gain, el }
    /** تراز صدا (۰ تا ۱) از روی یک AnalyserNode. */
    function level(an) {
        const buf = new Uint8Array(an.fftSize);
        an.getByteTimeDomainData(buf);
        let s = 0;
        for (const v of buf) { const x = (v - 128) / 128; s += x * x; }
        return Math.sqrt(s / buf.length);
    }
    /** معلم هر ۲۵۰ میلی‌ثانیه می‌سنجد چه کسی حرف می‌زند؛ فقط وقتی فهرست عوض شود خبر می‌دهد. */
    function startSpeakers() {
        if (spkTimer || !isHost) return;
        spkTimer = setInterval(() => {
            const now = [];
            for (const [uid, r] of remotes) if (r.an && level(r.an) > 0.02) now.push(uid);
            if (micAn && level(micAn) > 0.02) now.push(me);
            const key = now.join(',');
            if (key !== lastSpk) { lastSpk = key; onSpeaking?.(now); }
        }, 250);
    }
    function audio() {
        if (!ctx) {
            const AC = window.AudioContext || window.webkitAudioContext;
            ctx = new AC();
            micGain = ctx.createGain();
            startSpeakers();
        }
        if (ctx.state === 'suspended') ctx.resume().catch(() => {});
        return ctx;
    }
    function rewire() {
        if (!ctx) return;
        for (const [uid, r] of remotes) {
            try { r.gain.disconnect(); } catch { /* */ }
            if (!speakers.has(uid)) continue;
            for (const [pid, peer] of peers) if (pid !== uid && peer.dest) r.gain.connect(peer.dest);
        }
    }
    function setRemoteAudio(uid, track) {
        const old = remotes.get(uid);
        if (old) { try { old.src.disconnect(); old.gain.disconnect(); old.an?.disconnect(); } catch { /* */ } old.el.srcObject = null; remotes.delete(uid); }
        if (!track || !isHost) return;
        audio();
        const stream = new MediaStream([track]);
        // کروم صدای WebRTC را فقط وقتی به WebAudio می‌دهد که به یک عنصرِ صوتی هم وصل باشد
        const el = new Audio(); el.muted = true; el.srcObject = stream; el.play().catch(() => {});
        const src = ctx.createMediaStreamSource(stream);
        const gain = ctx.createGain();
        const an = ctx.createAnalyser();      // فقط برای تشخیصِ گوینده؛ به خروجی وصل نیست
        an.fftSize = 256;
        src.connect(gain);
        src.connect(an);
        remotes.set(uid, { src, gain, el, an });
        rewire();
    }

    // ── کیفیتِ تصویرِ معلم بر اساسِ تعدادِ نفرات ─────────────────────────
    let adaptTimer = null;
    function adapt() {
        clearTimeout(adaptTimer);
        adaptTimer = setTimeout(() => {
            const live = [...peers.values()].filter((p) => p.state === 'connected');
            const n = Math.max(1, live.length);
            const video = Math.max(80 * n, budget - n * 40);
            const both = local.cam && local.screen;
            const scr = Math.round((both ? 0.72 : 1) * video / n);
            const cam = Math.round((both ? 0.28 : 1) * video / n);
            for (const p of live) {
                setEncoding(p.tx?.cam?.sender, {
                    maxBitrate: Math.max(50, Math.min(cam, 900)) * 1000,
                    scaleResolutionDownBy: cam >= 500 ? 1 : cam >= 250 ? 2 : cam >= 120 ? 3 : 4,
                    maxFramerate: cam >= 250 ? 20 : 12,
                });
                setEncoding(p.tx?.screen?.sender, {
                    maxBitrate: Math.max(80, Math.min(scr, 2500)) * 1000,
                    scaleResolutionDownBy: scr >= 700 ? 1 : scr >= 300 ? 1.5 : 2,
                    maxFramerate: scr >= 400 ? 12 : 6,
                });
            }
        }, 400);
    }

    // ── کانالِ داده ─────────────────────────────────────────────────────
    function wireDc(uid, peer, dc) {
        peer.dc = dc;
        dc.onopen = () => onPeer?.(uid, peer.state, true);
        dc.onclose = () => onPeer?.(uid, peer.state, false);
        dc.onmessage = (e) => { try { onData?.(uid, JSON.parse(e.data)); } catch { /* */ } };
    }

    function slotOf(pc, transceiver) {
        return SLOTS[pc.getTransceivers().indexOf(transceiver)] ?? null;
    }

    function drop(uid) {
        const p = peers.get(uid);
        if (!p) return;
        peers.delete(uid);
        try { p.dc?.close(); } catch { /* */ }
        try { p.pc.close(); } catch { /* */ }
        if (p.dest) try { micGain?.disconnect(p.dest); } catch { /* */ }
        setRemoteAudio(uid, null);
        for (const s of SLOTS) onTrack?.(uid, s, null);
        onPeer?.(uid, 'closed', false);
    }

    function watch(uid, peer) {
        const pc = peer.pc;
        pc.onconnectionstatechange = () => {
            peer.state = pc.connectionState;
            if (peer.state === 'connected') { peer.failedAt = 0; adapt(); }
            if (peer.state === 'failed') peer.failedAt = Date.now();
            onPeer?.(uid, peer.state, peer.dc?.readyState === 'open');
        };
        pc.ontrack = (e) => {
            const slot = slotOf(pc, e.transceiver);
            if (!slot) return;
            if (isHost && slot === 'audio') setRemoteAudio(uid, e.track);
            onTrack?.(uid, slot, e.track);
        };
    }

    // ── معلم: ساختنِ اتصال برای یک دانش‌آموز ─────────────────────────────
    async function offer(uid) {
        if (closed) return;
        drop(uid);
        audio();
        const pc = new RTCPeerConnection(config);
        const n = ++counter;
        const peer = { uid, pc, state: 'new', n, offeredAt: Date.now(), failedAt: 0 };
        peers.set(uid, peer);
        peer.dest = ctx.createMediaStreamDestination();
        micGain.connect(peer.dest);
        peer.tx = {
            audio: pc.addTransceiver(peer.dest.stream.getAudioTracks()[0], { direction: 'sendrecv' }),
            cam: pc.addTransceiver(local.cam || 'video', { direction: 'sendrecv' }),
            screen: pc.addTransceiver(local.screen || 'video', { direction: 'sendonly' }),
        };
        rewire();
        watch(uid, peer);
        wireDc(uid, peer, pc.createDataChannel('room', { ordered: true }));
        try {
            await pc.setLocalDescription(await pc.createOffer());
            await waitIce(pc);
            if (peers.get(uid) !== peer) return;
            await signal(uid, 'offer', JSON.stringify({ sdp: pc.localDescription.sdp, n }));
        } catch (e) {
            console.warn('[online] offer failed', e);
            peer.failedAt = Date.now();
        }
    }

    // ── دانش‌آموز: پاسخ به پیشنهادِ معلم ───────────────────────────────
    async function answer(from, { sdp, n }) {
        drop(from);
        const pc = new RTCPeerConnection(config);
        const peer = { uid: from, pc, state: 'new', n };
        peers.set(from, peer);
        watch(from, peer);
        pc.ondatachannel = (e) => wireDc(from, peer, e.channel);
        await pc.setRemoteDescription({ type: 'offer', sdp });
        const t = pc.getTransceivers();
        peer.tx = { audio: t[0], cam: t[1], screen: t[2] };
        try { t[0].direction = 'sendrecv'; t[1].direction = 'sendrecv'; t[2].direction = 'recvonly'; } catch { /* */ }
        if (local.mic) await t[0].sender.replaceTrack(local.mic);
        if (relayed || local.cam) await t[1].sender.replaceTrack(relayed || local.cam);
        await pc.setLocalDescription(await pc.createAnswer());
        await waitIce(pc);
        if (peers.get(from) !== peer) return;
        await signal(from, 'answer', JSON.stringify({ sdp: pc.localDescription.sdp, n }));
    }

    return {
        mode: 'builtin',

        /** پیام‌های اتصال از همگام‌سازی. */
        async handleSignal({ from, kind, payload }) {
            if (closed) return;
            try {
                const data = payload ? JSON.parse(payload) : {};
                if (isHost) {
                    if (kind === 'hello') await offer(from);
                    else if (kind === 'answer') {
                        const p = peers.get(from);
                        if (p && p.n === data.n && p.pc.signalingState === 'have-local-offer') await p.pc.setRemoteDescription({ type: 'answer', sdp: data.sdp });
                    } else if (kind === 'bye') drop(from);
                } else if (from === hostId) {
                    if (kind === 'offer') await answer(from, data);
                    else if (kind === 'bye') drop(from);
                }
            } catch (e) {
                console.warn('[online] signal', kind, e);
            }
        },

        /** معلم: برای هر نفرِ آنلاین اتصال بساز/ترمیم کن؛ برای رفته‌ها ببند. */
        ensure(people) {
            if (!isHost || closed) return;
            const want = new Set();
            for (const p of people) {
                if (p.id === me || !p.online || !String(p.ms || '').startsWith('ready')) continue;
                want.add(p.id);
                const peer = peers.get(p.id);
                const stale = peer && (peer.state === 'failed' || peer.state === 'closed'
                    || (peer.state !== 'connected' && Date.now() - peer.offeredAt > 15000));
                if (!peer || stale) offer(p.id);
            }
            for (const uid of [...peers.keys()]) if (!want.has(uid)) drop(uid);
        },

        /** دانش‌آموز: «من آماده‌ام، به من وصل شو». */
        hello() {
            if (!isHost) signal(hostId, 'hello', null).catch(() => {});
        },

        connected(uid = hostId) {
            const p = peers.get(uid);
            return p?.state === 'connected';
        },

        dataOpen(uid = hostId) {
            return peers.get(uid)?.dc?.readyState === 'open';
        },

        stats() {
            const all = [...peers.values()];
            return { total: all.length, connected: all.filter((p) => p.state === 'connected').length };
        },

        async setMic(track) {
            local.mic = track;
            if (isHost) {
                audio();
                if (micSrc) { try { micSrc.disconnect(); micAn?.disconnect(); } catch { /* */ } micSrc = null; micAn = null; }
                if (track) {
                    micSrc = ctx.createMediaStreamSource(new MediaStream([track]));
                    micSrc.connect(micGain);
                    micAn = ctx.createAnalyser(); micAn.fftSize = 256; micSrc.connect(micAn);
                }
            } else {
                for (const p of peers.values()) await p.tx?.audio?.sender.replaceTrack(track).catch(() => {});
            }
        },

        async setCam(track) {
            local.cam = track;
            // وقتی تصویرِ یک دانش‌آموز برای همه نمایش داده می‌شود، روشن/خاموش کردنِ دوربینِ معلم آن را قطع نکند
            for (const p of peers.values()) await p.tx?.cam?.sender.replaceTrack(relayed || track).catch(() => {});
            adapt();
        },

        async setScreen(track) {
            if (!isHost) return;
            local.screen = track;
            for (const p of peers.values()) await p.tx?.screen?.sender.replaceTrack(track).catch(() => {});
            adapt();
        },

        /** معلم: چه کسانی اجازه‌ی صحبت دارند (صدایشان برای همه پخش می‌شود). */
        setSpeakers(ids) {
            speakers = new Set(ids);
            rewire();
        },

        /** معلم: نمایشِ تصویرِ یک دانش‌آموز برای همه (به جای دوربینِ خودم). */
        async relayCam(track) {
            relayed = track || null;
            for (const p of peers.values()) await p.tx?.cam?.sender.replaceTrack(track || local.cam).catch(() => {});
            adapt();
        },

        /** پیام برای همه (معلم) یا برای معلم (دانش‌آموز). */
        broadcast(msg, except) {
            const s = JSON.stringify(msg);
            for (const [uid, p] of peers) if (uid !== except && p.dc?.readyState === 'open') { try { p.dc.send(s); } catch { /* */ } }
        },

        sendTo(uid, msg) {
            const p = peers.get(uid);
            if (p?.dc?.readyState === 'open') { try { p.dc.send(JSON.stringify(msg)); } catch { /* */ } }
        },

        /** همه‌ی صداهای رسیده (برای ضبط). */
        remoteAudioTracks() {
            return [...remotes.values()].map((r) => r.el.srcObject?.getAudioTracks()[0]).filter(Boolean);
        },

        resumeAudio() { if (ctx?.state === 'suspended') ctx.resume().catch(() => {}); },

        close() {
            closed = true;
            for (const uid of [...peers.keys()]) {
                if (isHost || uid === hostId) signal(uid, 'bye', null).catch(() => {});
                drop(uid);
            }
            clearTimeout(adaptTimer);
            clearInterval(spkTimer); spkTimer = null;
            try { ctx?.close(); } catch { /* */ }
        },
    };
}
