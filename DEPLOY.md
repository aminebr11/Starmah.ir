# 🚀 راهنمای نصب روی cPanel — ستاره ماه ۲.۰

این بسته شامل **فرانت‌اند + بک‌اند + دیتابیس** است و طوری آماده شده که **بدون نیاز به Composer یا Node** روی هاست اجرا شود (پوشه‌ی `vendor/` و فایل‌های build‌شده‌ی `public/build/` از قبل درون بسته هستند).

---

## گام ۱ — آپلود فایل‌ها
۱. کل محتوای زیپ را در یک پوشه‌ی **بیرون از** `public_html` آپلود کنید — مثلاً `~/starmah`.
۲. محتوای پوشه‌ی `public/` را داخل `public_html` (یا Document Root دامنه) قرار دهید.

> **روش ساده‌تر (پیشنهادی):** اگر می‌توانید Document Root دامنه را تغییر دهید، آن را مستقیماً به پوشه‌ی `starmah/public` اشاره دهید. در این صورت نیازی به جابه‌جایی نیست.

اگر `public` را جدا کردید، در `public/index.php` مسیر دو `require` را به محل واقعی پوشه‌ی پروژه اصلاح کنید:
```php
require __DIR__.'/../starmah/vendor/autoload.php';
$app = require_once __DIR__.'/../starmah/bootstrap/app.php';
```

## گام ۲ — دیتابیس
در cPanel یک دیتابیس MySQL و یک کاربر بسازید و کاربر را به دیتابیس متصل کنید.

**دو راه برای ساخت جدول‌ها:**

- **الف) با phpMyAdmin (بدون ترمینال):** فایل `database/starmah_mysql.sql` را در phpMyAdmin → Import بارگذاری کنید. همه‌ی جدول‌ها و داده‌ی نمونه ساخته می‌شوند.
- **ب) با ترمینال (دقیق‌تر):** `php artisan migrate --force --seed`

## گام ۳ — فایل `.env`
فایل `.env` را باز کنید و این موارد را تنظیم کنید:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://starmah.ir

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=نام_دیتابیس_cpanel
DB_USERNAME=کاربر_cpanel
DB_PASSWORD=رمز
```
سپس کلید اپ را بسازید (یک‌بار):
```bash
php artisan key:generate
php artisan config:cache
```
> اگر ترمینال ندارید، فایل `.env` از قبل یک `APP_KEY` دارد؛ فقط اطلاعات دیتابیس را پر کنید.

## گام ۴ — دسترسی‌ها
پوشه‌های `storage/` و `bootstrap/cache/` باید نوشتنی باشند (۷۵۵ یا ۷۷۵).

## گام ۵ — کلیدهای سرویس (اختیاری، برای امکانات کامل)
در `.env` در صورت نیاز پر کنید: `ANTHROPIC_API_KEY` (تولید محتوای AI)، `KAVENEGAR_API_KEY` (پیامک)، `ZARINPAL_MERCHANT_ID` (پرداخت).

---

## ورود آزمایشی (رمز همه: `password`)
| نقش | موبایل |
|---|---|
| دانش‌آموز (فوتبال) | `09120000010` |
| دانش‌آموز (ماشین) | `09120000012` |
| معلم | `09120000002` |
| مدیر مدرسه | `09120000001` |

> پس از اطمینان از کارکرد، حتماً کاربران نمونه را حذف یا رمزها را عوض کنید.

## نکات PHP
- نسخه‌ی PHP را روی **۸.۳** یا بالاتر بگذارید (cPanel → MultiPHP Manager).
- اکستنشن‌های لازم: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` (معمولاً فعال‌اند).
