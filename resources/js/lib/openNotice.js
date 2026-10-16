import { router } from '@inertiajs/react';

/**
 * کلیک روی اعلانِ پیشخوان — مثلِ زنگوله: اول «مطالعه شد» ثبت می‌شود (تا از
 * خلاصه‌ی پیشخوان، زنگوله و شمارنده برود)، بعد به مقصد می‌رویم.
 */
export default function openNotice(n, e) {
    e?.preventDefault();
    const visit = () => router.visit(n.href || '/notices');
    if (n.read) return visit();
    return router.post(route('notices.read', n.id), {}, { preserveScroll: true, preserveState: true, onFinish: visit });
}
