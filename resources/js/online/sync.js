import axios from 'axios';

/**
 * حلقه‌ی همگام‌سازیِ کلاس با سرور (بدونِ وب‌سوکت؛ روی هاستِ اشتراکی کار می‌کند).
 * - عادی هر ۲ ثانیه؛ هنگامِ اتصال/تبادلِ پیام هر ۰٫۹ ثانیه؛ زبانه‌ی پنهان هر ۵ ثانیه.
 * - سرور فقط وقتی چیزی عوض شده (version) داده‌ی کامل می‌فرستد.
 */
export default class RoomSync {
    constructor(url, onData, onError) {
        this.url = url;
        this.onData = onData;
        this.onError = onError;
        this.v = 0;
        this.msg = 0;
        this.ms = null;
        this.fastUntil = 0;
        this.timer = null;
        this.running = false;
        this.busy = false;
        this.fails = 0;
    }

    start() {
        if (this.running) return;
        this.running = true;
        this.tick();
    }

    stop() {
        this.running = false;
        clearTimeout(this.timer);
    }

    /** چند ثانیه سریع‌تر همگام شو (پس از فرستادنِ پیامِ اتصال یا کارِ کاربر). */
    fast(ms = 6000) {
        this.fastUntil = Math.max(this.fastUntil, Date.now() + ms);
        if (this.running && !this.busy) { clearTimeout(this.timer); this.timer = setTimeout(() => this.tick(), 120); }
    }

    /** همین حالا همگام شو. */
    now() {
        if (this.running && !this.busy) { clearTimeout(this.timer); this.tick(); }
    }

    async tick() {
        if (!this.running) return;
        this.busy = true;
        try {
            const { data } = await axios.post(this.url, { v: this.v, msg: this.msg, ms: this.ms }, { timeout: 15000 });
            this.fails = 0;
            if (data.v) this.v = data.v;
            const msgs = data.state?.messages;
            if (msgs?.length) this.msg = Math.max(this.msg, ...msgs.map((m) => m.id));
            if (data.signals?.length) this.fast();
            this.onData?.(data);
        } catch (e) {
            this.fails += 1;
            this.onError?.(e, this.fails);
        } finally {
            this.busy = false;
        }
        if (!this.running) return;
        const hidden = typeof document !== 'undefined' && document.hidden;
        const wait = this.fails ? Math.min(8000, 1500 * this.fails) : hidden ? 5000 : Date.now() < this.fastUntil ? 900 : 2000;
        this.timer = setTimeout(() => this.tick(), wait);
    }
}
