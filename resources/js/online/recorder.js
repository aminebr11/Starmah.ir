import axios from 'axios';
import { W, H } from './Board';

/**
 * ضبطِ کلاس روی مرورگرِ معلم: تصویرِ صحنه (تخته یا صفحه‌ی اشتراکی + دوربینِ معلم)
 * با صدای معلم و بچه‌ها. هر ۱۰ ثانیه یک تکه بارگذاری می‌شود تا در پایان فایلِ
 * بزرگی منتظرِ بارگذاری نماند و اگر صفحه بسته شد، ضبط تا همان‌جا محفوظ بماند.
 */
export default class ClassRecorder {
    constructor({ uploadUrl, finishUrl, getStage, getAudioTracks, onState }) {
        this.uploadUrl = uploadUrl;
        this.finishUrl = finishUrl;
        this.getStage = getStage;            // () => { mode: board|screen|cam, canvases:[], screen:HTMLVideoElement|null, cam:HTMLVideoElement|null }
        this.getAudioTracks = getAudioTracks; // () => MediaStreamTrack[]
        this.onState = onState;
        this.seq = 0;
        this.queue = Promise.resolve();
    }

    static supported() {
        return typeof MediaRecorder !== 'undefined' && !!HTMLCanvasElement.prototype.captureStream;
    }

    start() {
        const c = document.createElement('canvas');
        c.width = 1280; c.height = 720;
        this.canvas = c;
        const g = c.getContext('2d');
        const draw = () => {
            const s = this.getStage();
            g.fillStyle = '#fff'; g.fillRect(0, 0, 1280, 720);
            if (s.mode === 'cam' && s.cam?.videoWidth) {
                const v = s.cam; const k = Math.min(1280 / v.videoWidth, 720 / v.videoHeight);
                g.fillStyle = '#120d2b'; g.fillRect(0, 0, 1280, 720);
                g.drawImage(v, (1280 - v.videoWidth * k) / 2, (720 - v.videoHeight * k) / 2, v.videoWidth * k, v.videoHeight * k);
                return;
            }
            if (s.mode === 'screen' && s.screen?.videoWidth) {
                const v = s.screen; const k = Math.min(1280 / v.videoWidth, 720 / v.videoHeight);
                g.fillStyle = '#111'; g.fillRect(0, 0, 1280, 720);
                g.drawImage(v, (1280 - v.videoWidth * k) / 2, (720 - v.videoHeight * k) / 2, v.videoWidth * k, v.videoHeight * k);
            } else {
                for (const cv of s.canvases || []) if (cv?.width) g.drawImage(cv, 0, 0, 1280, (1280 * H) / W);
            }
            if (s.cam?.videoWidth) {
                const r = 92; const x = 1280 - r - 22; const y = 720 - r - 22;
                g.save(); g.beginPath(); g.arc(x, y, r, 0, Math.PI * 2); g.clip();
                const v = s.cam; const k = Math.max((2 * r) / v.videoWidth, (2 * r) / v.videoHeight);
                g.drawImage(v, x - (v.videoWidth * k) / 2, y - (v.videoHeight * k) / 2, v.videoWidth * k, v.videoHeight * k);
                g.restore();
                g.lineWidth = 5; g.strokeStyle = '#fff'; g.beginPath(); g.arc(x, y, r, 0, Math.PI * 2); g.stroke();
            }
        };
        draw();
        this.timer = setInterval(draw, 100);

        const AC = window.AudioContext || window.webkitAudioContext;
        this.ac = new AC();
        this.dest = this.ac.createMediaStreamDestination();
        this.sources = new Map();
        this.mixAudio();
        this.mixTimer = setInterval(() => this.mixAudio(), 3000);

        const stream = new MediaStream([...c.captureStream(10).getVideoTracks(), ...this.dest.stream.getAudioTracks()]);
        const types = ['video/webm;codecs=vp8,opus', 'video/webm;codecs=vp9,opus', 'video/webm'];
        const mimeType = types.find((t) => MediaRecorder.isTypeSupported?.(t)) || '';
        this.rec = new MediaRecorder(stream, { mimeType, videoBitsPerSecond: 700_000, audioBitsPerSecond: 64_000 });
        this.rec.ondataavailable = (e) => { if (e.data?.size) this.push(e.data); };
        this.rec.onstop = () => this.onState?.('stopped');
        this.started = Date.now();
        this.rec.start(10_000);
        this.onState?.('recording');
    }

    /** صداهای تازه (کسی که تازه اجازه‌ی صحبت گرفته) به ضبط اضافه شوند. */
    mixAudio() {
        const tracks = this.getAudioTracks().filter((t) => t && t.readyState === 'live');
        const ids = new Set(tracks.map((t) => t.id));
        for (const [id, src] of this.sources) if (!ids.has(id)) { try { src.disconnect(); } catch { /* */ } this.sources.delete(id); }
        for (const t of tracks) {
            if (this.sources.has(t.id)) continue;
            const src = this.ac.createMediaStreamSource(new MediaStream([t]));
            src.connect(this.dest);
            this.sources.set(t.id, src);
        }
    }

    push(blob) {
        const seq = this.seq++;
        this.queue = this.queue.then(async () => {
            const fd = new FormData();
            fd.append('seq', String(seq));
            fd.append('chunk', new File([blob], `part-${seq}.webm`, { type: 'video/webm' }));
            for (let i = 0; i < 3; i += 1) {
                try { await axios.post(this.uploadUrl, fd, { timeout: 120000 }); return; } catch { await new Promise((r) => setTimeout(r, 2000 * (i + 1))); }
            }
            this.onState?.('error');
        });
    }

    async stop() {
        if (!this.rec) return;
        await new Promise((res) => { this.rec.addEventListener('stop', res, { once: true }); try { this.rec.stop(); } catch { res(); } });
        clearInterval(this.timer); clearInterval(this.mixTimer);
        try { await this.ac.close(); } catch { /* */ }
        await this.queue;
        this.rec = null;
        if (this.finishUrl) await axios.post(this.finishUrl, { duration_ms: Date.now() - this.started }).catch(() => {});
    }
}
