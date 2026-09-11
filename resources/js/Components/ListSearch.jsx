/**
 * جعبه‌ی جست‌وجوی فهرست‌ها — یک‌جا تعریف شده تا در همه‌ی صفحاتی که
 * فهرستِ دانش‌آموز، معلم یا مدرسه دارند یکسان باشد.
 *
 * props: value, onChange(string), placeholder
 */
export default function ListSearch({ value, onChange, placeholder = 'جست‌وجو…', autoFocus = false }) {
    return (
        <div className="list-search">
            <span className="ic" aria-hidden="true">🔍</span>
            <input
                type="search"
                value={value}
                autoFocus={autoFocus}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                aria-label={placeholder}
            />
            {value && (
                <button type="button" className="clear" onClick={() => onChange('')} aria-label="پاک‌کردنِ جست‌وجو">
                    ✕
                </button>
            )}
        </div>
    );
}

/**
 * نرمال‌سازیِ متنِ فارسی برای جست‌وجو.
 * «ي/ی»، «ك/ک»، انواعِ الف و نیم‌فاصله را یکسان می‌کند تا جست‌وجو
 * به شکلِ نوشتنِ کاربر حساس نباشد.
 */
export function normalizeFa(s) {
    return String(s ?? '')
        .replace(/[يی]/g, 'ی')
        .replace(/[كک]/g, 'ک')
        .replace(/[أإآا]/g, 'ا')
        .replace(/‌/g, ' ')
        .toLowerCase()
        .trim();
}
