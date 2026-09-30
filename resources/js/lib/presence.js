import { router } from '@inertiajs/react';

/**
 * ضربانِ حضور — هر دقیقه، فقط وقتی کاربر وارد شده و صفحه جلوی چشمش است.
 * «آنلاین‌بودن» و «زمانِ فعال» در گزارشِ بازدیدها از همین می‌آید.
 */
let authed = false;
let timer = null;

function beat() {
    if (!authed || document.visibilityState !== 'visible') return;
    window.axios?.post('/presence').catch(() => {});
}

export function initPresence(initialPage) {
    authed = !!initialPage?.props?.auth?.user;
    router.on('navigate', (e) => { authed = !!e.detail?.page?.props?.auth?.user; });
    if (timer) clearInterval(timer);
    timer = setInterval(beat, 60000);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') beat(); });
}
