# 🌟 ستاره ماه ۲.۰

پلتفرم آموزشی **گیمیفای‌شده و شخصی‌سازی‌شده بر اساس علاقه‌ی کودک** — به‌صورت SaaS برای فروش به مدارس (B2B2C).

> هر دانش‌آموز دنیای آموزشی خودش را دارد: عاشق فوتبال در قالب لیگ و گل یاد می‌گیرد، عاشق ماشین در قالب گرنپری و نیترو — اما محتوای آموزشی یکی است.

## 🧱 استک فنی

| لایه | فناوری |
|---|---|
| بک‌اند | Laravel 13 (PHP 8.3+) |
| فرانت‌اند | React + Inertia.js + Tailwind (به‌صورت PWA) |
| احراز هویت | Laravel Sanctum + Breeze + ورود با موبایل |
| نقش‌ها | Spatie Laravel Permission |
| دیتابیس | MySQL (cPanel) / SQLite (توسعه‌ی محلی) |

## 🏛️ معماری

- **چندمستأجری (Multi-tenant):** هر مدرسه یک مستأجر با `school_id`. جداسازی خودکار داده با trait `BelongsToSchool` و Global Scope.
- **موتور تم سه‌لایه** (`app/Services/ThemeEngine.php`) — قلب تمایز محصول:
  1. **Skin** — توکن‌های بصری (رنگ، ماسکوت) در `themes.skin`
  2. **Narrative** — واژگان گیمیفیکیشن (گل/نیترو، لیگ/گرنپری) در `themes.narrative`
  3. **Content** — روکش داستانی سؤال‌های خنثی با لیست اسم‌ها در `themes.content_pools`
- **محتوای خنثی نسبت به تم:** `Subject → Topic → Skill → Question`. سؤال‌ها قالب‌محورند (`{team}`, `{n}` ...) و موتور تم در زمان نمایش روکش می‌زند.

نقش‌ها: `super_admin`، `school_admin`، `teacher`، `student`، `parent`.

## 🚀 راه‌اندازی محلی

```bash
composer install
npm install --legacy-peer-deps
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed   # دیتابیس + داده‌ی نمونه
npm run build                       # یا: npm run dev
php artisan serve
```

### کاربران نمونه (رمز همه: `password`)
| نقش | موبایل | تم |
|---|---|---|
| دانش‌آموز (آرمین) | `09120000010` | فوتبال ⚽ |
| دانش‌آموز (کیان) | `09120000012` | ماشین 🏎️ |
| معلم | `09120000002` | — |
| مدیر مدرسه | `09120000001` | — |
| سوپرادمین | `09120000000` | — |

## 🌐 دیپلوی روی cPanel

1. در `.env`، `DB_CONNECTION=mysql` و اطلاعات دیتابیس cPanel را تنظیم کنید.
2. `composer install --no-dev --optimize-autoloader` و `npm run build` (محلی یا روی سرور).
3. ریشه‌ی دامنه را به پوشه‌ی `public/` اشاره دهید (Document Root).
4. `php artisan migrate --force` و در صورت نیاز seed.
5. اسرار (کلید AI، پیامک، پرداخت) فقط در `.env` — هرگز کامیت نشوند.

## 📁 ساختار

- `app/Services/ThemeEngine.php` — موتور تم
- `app/Models/Concerns/BelongsToSchool.php` — چندمستأجری
- `database/seeders/` — نقش‌ها، تم‌ها (فوتبال/ماشین)، ریاضی چهارم، داده‌ی نمونه
- `resources/js/Pages/Student/Dashboard.jsx` — داشبورد تم‌دار
- `demo/` — پروتوتایپ طراحی اولیه (HTML)
- `docs/PRODUCT_PLAN.md` — طرح جامع محصول
- `legacy/` — نسخه‌ی ۱ (مرجع مهاجرت)
