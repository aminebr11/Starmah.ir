import DatePickerImport from 'react-multi-date-picker';
import DateObjectImport from 'react-date-object';
import persianImport from 'react-date-object/calendars/persian';
import persianFaImport from 'react-date-object/locales/persian_fa';
import gregorianImport from 'react-date-object/calendars/gregorian';
import gregorianEnImport from 'react-date-object/locales/gregorian_en';
import TimePickerImport from 'react-multi-date-picker/plugins/time_picker';

// رفعِ ناسازگاری interop ماژول‌های CJS (گاهی default دوبار پیچیده می‌شود)
const unwrap = (m) => (m && m.default) ? m.default : m;
const DatePicker = unwrap(DatePickerImport);
const DateObject = unwrap(DateObjectImport);
const persian = unwrap(persianImport);
const persian_fa = unwrap(persianFaImport);
const gregorian = unwrap(gregorianImport);
const gregorian_en = unwrap(gregorianEnImport);
const TimePicker = unwrap(TimePickerImport);

/**
 * انتخابگر تاریخ شمسی — نمایش فارسی/هجری‌شمسی، خروجی میلادی برای دیتابیس.
 *
 * props:
 *  - value: رشته‌ی میلادی 'YYYY-MM-DD' (یا 'YYYY-MM-DD HH:mm')
 *  - onChange: (str) => void  با مقدار میلادی
 *  - withTime: اگر true، انتخاب ساعت فعال و خروجی 'YYYY-MM-DD HH:mm'
 */
export default function JalaliDatePicker({ value, onChange, withTime = false, placeholder = 'انتخاب تاریخ', className = 'input', disabled = false, minDate, maxDate }) {
    let display = '';
    if (value) {
        try {
            display = new DateObject({ date: value, calendar: gregorian, locale: gregorian_en }).convert(persian, persian_fa);
        } catch (e) { display = ''; }
    }

    const handle = (d) => {
        if (!d) { onChange(''); return; }
        const g = d.convert(gregorian, gregorian_en);
        const p2 = (n) => String(n).padStart(2, '0');
        let out = `${g.year}-${p2(g.month.number)}-${p2(g.day)}`;
        if (withTime) out += ` ${p2(g.hour || 0)}:${p2(g.minute || 0)}`;
        onChange(out);
    };

    return (
        <DatePicker
            calendar={persian}
            locale={persian_fa}
            calendarPosition="bottom-right"
            value={display}
            onChange={handle}
            disabled={disabled}
            format={withTime ? 'YYYY/MM/DD HH:mm' : 'YYYY/MM/DD'}
            plugins={withTime ? [<TimePicker key="tp" position="bottom" hideSeconds />] : []}
            minDate={minDate}
            maxDate={maxDate}
            inputClass={className}
            placeholder={placeholder}
            containerStyle={{ width: '100%' }}
            editable={false}
        />
    );
}
