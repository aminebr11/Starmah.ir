import { router } from '@inertiajs/react';
import { handleWellbeing, inLiveClass, dismissBreak } from './wellbeing';

/**
 * ضربانِ حضور — هر دقیقه، فقط وقتی کاربر وارد شده و صفحه جلوی چشمش است.
 * «آنلاین‌بودن» و «زمانِ فعال» در گزارشِ بازدیدها از همین می‌آید؛
 * برای دانش‌آموز پاسخ، وضعیتِ «سلامتِ دیجیتال» (استراحت/سقفِ زمان) را هم دارد.
 */
let authed = false;
let timer = null;

function beat() {
    if (!authed || document.visibilityState !== 'visible') return;
    // در کلاسِ آنلاین زمان شمرده نمی‌شود (درس است، نه بازی) و یادآوریِ استراحت نمی‌آید
    window.axios?.post('/presence', inLiveClass() ? { in_class: 1 } : {}).then((r) => { if (r?.data?.wellbeing) handleWellbeing(r.data.wellbeing); }).catch(() => {});
}

export function initPresence(initialPage) {
    authed = !!initialPage?.props?.auth?.user;
    router.on('navigate', (e) => { authed = !!e.detail?.page?.props?.auth?.user; if (inLiveClass()) dismissBreak(); });
    if (timer) clearInterval(timer);
    timer = setInterval(beat, 60000);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') beat(); });
}
