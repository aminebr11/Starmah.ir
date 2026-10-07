import '../css/app.css';
import '../css/site.css';
import '../css/home3d.css';
import '../css/smart-exams.css';
import '../css/theme-clay.css';
import '../css/visits.css';
import '../css/hub.css';
import '../css/schedule-kids.css';
import '../css/mastery.css';
import '../css/gradebook.css';
import '../css/sort.css';
import '../css/points-trend.css';
import '../css/gallery.css';
import '../css/ai-center.css';
import '../css/points-center.css';
import './bootstrap';
// پیش از Inertia: «بازگشت» گوشی اول پنجره‌های باز را می‌بندد
import './lib/overlayBack';
import { initPwa } from './lib/pwa';
import { initUi, syncUi } from './lib/ui';
import { router } from '@inertiajs/react';
import { initToasts } from './lib/toast';
import { initPresence } from './lib/presence';

initUi();
// داخلِ اپِ اندروید window.print کاری نمی‌کند؛ همه‌ی دکمه‌های چاپ از پلِ اپ استفاده کنند
if (typeof window !== 'undefined' && window.StarmahApp?.print) {
    window.print = () => window.StarmahApp.print(document.title);
}
router.on('navigate', (e) => syncUi(e.detail?.page?.props?.ui));

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        initPresence(props.initialPage);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// نصب‌پذیریِ اپ + به‌روزرسانیِ خودکار با هر انتشارِ تازه
initPwa();
initToasts();
