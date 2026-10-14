/**
 * رسانه با سرورِ LiveKit (وقتی ادمینِ کل تنظیمش کرده باشد) — برای کلاس‌های بزرگ.
 * همان رابطِ mesh.js را دارد تا صفحه‌ی کلاس فرقی نبیند.
 * کتابخانه فقط همین‌جا و فقط در این حالت بارگذاری می‌شود (صفحه سبک می‌ماند).
 */
export default async function createLk(opts) {
    const { url, token, isHost, hostId, onTrack, onData, onPeer, onSpeaking } = opts;
    const LK = await import('livekit-client');
    const { Room, RoomEvent, Track, ConnectionState } = LK;
    const enc = new TextEncoder();
    const dec = new TextDecoder();
    const pubs = { mic: null, cam: null, screen: null };
    const remoteAudio = new Map();

    const room = new Room({
        adaptiveStream: true,
        dynacast: true,
        publishDefaults: {
            simulcast: true,
            videoSimulcastLayers: [LK.VideoPresets.h180, LK.VideoPresets.h360],
            screenShareEncoding: { maxBitrate: 1_500_000, maxFramerate: 10 },
            dtx: true,
        },
    });

    const slot = (source) => (source === Track.Source.Camera ? 'cam'
        : source === Track.Source.ScreenShare ? 'screen'
            : 'audio');

    room.on(RoomEvent.TrackSubscribed, (track, pub, participant) => {
        const uid = Number(participant.identity);
        const s = slot(pub.source);
        if (s === 'audio') remoteAudio.set(`${uid}:${pub.trackSid}`, track.mediaStreamTrack);
        onTrack?.(uid, s, track.mediaStreamTrack, pub.trackSid);
    });
    room.on(RoomEvent.TrackUnsubscribed, (track, pub, participant) => {
        const uid = Number(participant.identity);
        remoteAudio.delete(`${uid}:${pub.trackSid}`);
        onTrack?.(uid, slot(pub.source), null, pub.trackSid);
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

    async function publish(key, track, source) {
        if (pubs[key]) { await room.localParticipant.unpublishTrack(pubs[key], false).catch(() => {}); pubs[key] = null; }
        if (track) {
            await room.localParticipant.publishTrack(track, { source }).catch((e) => console.warn('[online] publish', e));
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
        setScreen: (t) => (isHost ? publish('screen', t, Track.Source.ScreenShare) : Promise.resolve()),
        setSpeakers() {},
        relayCam() {},
        broadcast(msg) { send(msg, isHost ? undefined : [String(hostId)]); },
        sendTo(uid, msg) { send(msg, [String(uid)]); },
        remoteAudioTracks() { return [...remoteAudio.values()]; },
        resumeAudio() { room.startAudio?.().catch(() => {}); },
        close() { room.disconnect().catch(() => {}); },
    };
}
