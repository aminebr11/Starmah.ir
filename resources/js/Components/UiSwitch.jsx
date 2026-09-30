import { usePage } from '@inertiajs/react';
import { setUi } from '@/lib/ui';

/** کلیدِ پیش‌نمایشِ طرح برای ادمینِ کل: «خمیرماه» ⇄ طرحِ قبلی. */
export default function UiSwitch({ compact = false, className = '' }) {
    const { ui = 'clay', auth } = usePage().props;
    // طرح را ادمینِ کل برای هر مدرسه تعیین می‌کند؛ کلید فقط برای پیش‌نمایشِ خودِ او
    if (!(auth?.roles ?? []).includes('super_admin')) return null;
    const clay = ui === 'clay';
    return (
        <button type="button" className={`ui-switch ${clay ? 'on' : ''} ${className}`}
            onClick={() => setUi(clay ? 'classic' : 'clay')}
            title={clay ? 'بازگشت به طرحِ قبلی' : 'امتحانِ طرحِ تازه‌ی «خمیرماه»'}>
            <span className="ui-switch-dot" aria-hidden="true">{clay ? '🌙' : '🎨'}</span>
            {!compact && <span>{clay ? 'طرحِ قبلی' : 'طرحِ خمیرماه'}</span>}
        </button>
    );
}
