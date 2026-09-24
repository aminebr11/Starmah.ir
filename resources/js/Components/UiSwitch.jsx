import { usePage } from '@inertiajs/react';
import { setUi } from '@/lib/ui';

/** کلیدِ جابه‌جاییِ طرح: «خمیرماه» ⇄ طرحِ قبلی. */
export default function UiSwitch({ compact = false, className = '' }) {
    const { ui = 'classic' } = usePage().props;
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
