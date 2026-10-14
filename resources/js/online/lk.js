/**
 * رسانه با سرورِ LiveKit (وقتی ادمینِ کل تنظیمش کرده باشد) — برای کلاس‌های بزرگ.
 * همان رابطِ mesh.js را دارد تا صفحه‌ی کلاس فرقی نبیند.
 * کتابخانه فقط همین‌جا و فقط در این حالت بارگذاری می‌شود (صفحه سبک می‌ماند).
 */
export default async function createLk(opts) {
    const { url, token, isHost, hostId, onTrack, onData, onPeer, onSpeaking } = opts;
    const LK = await import('livekit-client');
    const { Room, RoomEvent, Track, ConnectionState, VideoQuality, AudioPresets } = LK;
    const enc = new TextEncoder();
    const dec = new TextDecoder();
    const pubs = { mic: null, cam: null, screen: null, saudio: null };
    let movie = false;   // فیلمِ معلم در حالِ پخش: گوشی‌ها لایه‌ی ۳۶۰ را بگیرند
    const remoteAudio = new Map();

    // adaptiveStream خاموش است: تصویرها را خودمان در <video> نشان می‌دهیم (نه با attach)،
    // و با آن روشن، برگشتن از برنامه‌ی دیگر تصویر را متوقف نگه می‌داشت. کیفیت را خودمان می‌گوییم.
    const room = new Room({
        adaptiveStream: false,
        dynacast: true,
        disconnectOnPageLeave: true,
        reconnectPolicy: { nextRetryDelayInMs: (ctx) => (ctx.retryCount > 30 ? null : Math.min(5000, 300 * (ctx.retryCount + 1))) },
        publishDefaults: {
            simulcast: true,
            videoSimulcastLayers: [LK.VideoPresets.h180, LK.VideoPresets.h360],
            screenShareEncoding: { maxBitrate: 2_500_000, maxFramerate: 15 },
            dtx: true,
            red: true,
            stopMicTrackOnMute: false,
        },
    });
    // گوشی: تصویرِ معلم ۳۶۰p (کم‌مصرف و روان)؛ رایانه: ۷۲۰p. تصویرِ کوچکِ بچه‌ها: ۱۸۰p.
    const wide = () => (typeof window !== 'undefined' ? Math.max(window.innerWidth, window.innerHeight) * (window.devicePixelRatio > 1.5 ? 1.2 : 1) : 1280) >= 1100;
    const qualityFor = (uid, source) => (source === Track.Source.ScreenShare ? (movie && !wide() ? VideoQuality.LOW : VideoQuality.HIGH)
        : uid === hostId ? (wide() ? VideoQuality.HIGH : VideoQuality.MEDIUM)
            : VideoQuality.LOW);

    const slot = (source) => (source === Track.Source.Camera ? 'cam'
        : source === Track.Source.ScreenShare ? 'screen'
            : source === Track.Source.ScreenShareAudio ? 'saudio'
                : 'audio');

    room.on(RoomEvent.TrackSubscribed, (track, pub, participant) => {
        const uid = Number(participant.identity);
        const s = slot(pub.source);
        if (s === 'cam' || s === 'screen') { try { pub.setVideoQuality(qualityFor(uid, pub.source)); } catch { /* */ } }
        if (s === 'audio' || s === 'saudio') remoteAudio.set(`${uid}:${pub.trackSid}`, track.mediaStreamTrack);
        onTrack?.(uid, s, track.mediaStreamTrack, pub.trackSid);
    });
    room.on(RoomEvent.TrackUnsubscribed, (track, pub, participant) => {
        const uid = Number(participant.identity);
        remoteAudio.delete(`${uid}:${pub.trackSid}`);
        // track قدیمی هم فرستاده می‌شود تا اگر تازه‌اش زودتر رسیده، پاک نشود
        onTrack?.(uid, slot(pub.source), null, pub.trackSid, track?.mediaStreamTrack);
    });
    room.on(RoomEvent.DataReceived, (payload, participant) => {
        try { onData?.(Number(participant?.identity), JSON.parse(dec.decode(payload))); } catch { /* */ }
    });
    room.on(RoomEvent.ConnectionStateChanged, (s) => {
        const state = s === ConnectionState.Connected ? 'connected' : s === ConnectionState.Reconnecting ? 'connecting' : s === ConnectionState.Disconnected ? 'failed' : 'connecting';
        onPeer?.(hostId, state, state === 'connected');
    });
    room.on(RoomEvent.ParticipantConnected, () => onPeer?.(hostId, 'connected', true));
    room.on(RoomEvent.ActiveSpeakersChanged, (speakers) => onSpeaking?.(speakers.map((s) => Number(s.identity)).filter(Boolean)));

    await room.connect(url, token, { autoSubscribe: true });
    onPeer?.(hostId, 'connected', true);

    async function publish(key, track, source, extra = {}) {
        // تعویضِ میکروفون: همان انتشار، فقط track عوض می‌شود (بی‌وقفه). دوربین دوباره منتشر می‌شود
        // تا simulcast با اندازه‌ی دوربینِ تازه از نو ساخته شود.
        if (pubs[key] && track && source === Track.Source.Microphone) {
            const lp = room.localParticipant.getTrackPublication(source);
            if (lp?.track?.replaceTrack) {
                try {
                    await lp.track.replaceTrack(track, true);
                    // اگر میکروفونِ قبلی زودتر بسته شده بود، LiveKit آن را «بی‌صدا» علامت زده است
                    if (lp.isMuted) await lp.unmute();
                    setTimeout(() => { if (lp.isMuted && pubs[key] === track) lp.unmute().catch(() => {}); }, 800);
                    pubs[key] = track;
                    return;
                } catch (e) { console.warn('[online] replace', e); }
            }
        }
        if (pubs[key]) { await room.localParticipant.unpublishTrack(pubs[key], false).catch(() => {}); pubs[key] = null; }
        if (track) {
            const o = { source, ...extra };
            if (source === Track.Source.Microphone) o.audioPreset = isHost ? AudioPresets.music : AudioPresets.speech;
            // صدای فیلم/آهنگ: استریو با کیفیتِ بالا، بدونِ قطع‌کردنِ سکوت
            if (source === Track.Source.ScreenShareAudio) { o.audioPreset = AudioPresets.musicHighQualityStereo; o.dtx = false; o.red = false; o.forceStereo = true; }
            if (source === Track.Source.Camera) {
                o.videoEncoding = isHost ? { maxBitrate: 1_500_000, maxFramerate: 24 } : { maxBitrate: 500_000, maxFramerate: 20 };
                o.videoSimulcastLayers = isHost ? [LK.VideoPresets.h180, LK.VideoPresets.h360] : [LK.VideoPresets.h180];
            }
            await room.localParticipant.publishTrack(track, o).catch((e) => console.warn('[online] publish', e));
            pubs[key] = track;
        }
    }

    const send = (msg, identities) => {
        const lossy = msg.t === 'ptr' || msg.t === 'stroke';
        room.localParticipant.publishData(enc.encode(JSON.stringify(msg)), { reliable: !lossy, destinationIdentities: identities }).catch(() => {});
    };

    return {
        mode: 'livekit',
        handleSignal() {},
        ensure() {},
        hello() {},
        connected() { return room.state === ConnectionState.Connected; },
        dataOpen() { return room.state === ConnectionState.Connected; },
        stats() { return { total: room.numParticipants - 1, connected: room.numParticipants - 1 }; },
        setMic: (t) => publish('mic', t, Track.Source.Microphone),
        setCam: (t) => publish('cam', t, Track.Source.Camera),
        setScreen: (t, isMotion = false) => (isHost ? publish('screen', t, Track.Source.ScreenShare, isMotion
            // فیلم: ۳۰ فریم و یک لایه‌ی سبکِ ۳۶۰ برای گوشی‌ها
            ? { screenShareEncoding: { maxBitrate: 2_500_000, maxFramerate: 30 }, screenShareSimulcastLayers: [new LK.VideoPreset(640, 360, 650_000, 24)] }
            : {}) : Promise.resolve()),
        setMediaAudio: (t) => (isHost ? publish('saudio', t, Track.Source.ScreenShareAudio) : Promise.resolve()),
        /** فیلمِ معلم در حالِ پخش است: گوشی‌ها نسخه‌ی سبک‌تر را بگیرند (کم‌مصرف و روان). */
        setMovie(on) {
            movie = !!on;
            for (const p of room.remoteParticipants.values()) {
                if (Number(p.identity) !== hostId) continue;
                for (const pub of p.videoTrackPublications.values()) {
                    if (pub.source === Track.Source.ScreenShare) { try { pub.setVideoQuality(qualityFor(hostId, pub.source)); } catch { /* */ } }
                }
            }
        },
        setSpeakers() {},
        relayCam() {},
        broadcast(msg) { send(msg, isHost ? undefined : [String(hostId)]); },
        sendTo(uid, msg) { send(msg, [String(uid)]); },
        remoteAudioTracks() { return [...remoteAudio.values()]; },
        resumeAudio() { room.startAudio?.().catch(() => {}); },
        /** «فقط دوربین»: تصویرِ معلم با بیشترین کیفیت؛ برگشت به حالتِ عادی. */
        setFocus(camBig) {
            for (const p of room.remoteParticipants.values()) {
                if (Number(p.identity) !== hostId) continue;
                for (const pub of p.videoTrackPublications.values()) {
                    if (pub.source === Track.Source.Camera) { try { pub.setVideoQuality(camBig ? VideoQuality.HIGH : qualityFor(hostId, pub.source)); } catch { /* */ } }
                }
            }
        },
        close() { room.disconnect().catch(() => {}); },
    };
}
