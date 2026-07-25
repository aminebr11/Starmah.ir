<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * چهار طرحِ پیش‌فرضِ فروش. از firstOrCreate استفاده می‌شود تا قیمت‌های
 * ویرایش‌شده‌ی ادمین در اجرای دوباره بازنویسی نشوند.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'key' => 'trial', 'name' => 'آزمایشی', 'description' => 'برای آشنایی و امتحانِ سامانه',
                'price' => 0, 'period_label' => '۱۴ روزه', 'duration_days' => 14,
                'max_classes' => 2, 'max_students_per_class' => 30, 'highlighted' => false, 'sort' => 1,
                'features' => ['۲ کلاس', 'تا ۳۰ دانش‌آموز در هر کلاس', 'بازی و مأموریت', 'گزارشِ پایه', 'پشتیبانیِ ایمیلی'],
            ],
            [
                'key' => 'basic', 'name' => 'پایه', 'description' => 'مناسبِ مدارسِ کوچک',
                'price' => 3900000, 'period_label' => 'سالانه', 'duration_days' => 365,
                'max_classes' => 8, 'max_students_per_class' => 35, 'highlighted' => false, 'sort' => 2,
                'features' => ['۸ کلاس', 'تا ۳۵ دانش‌آموز در هر کلاس', 'همه‌ی بازی‌ها و آزمون‌ها', 'گزارش‌های تحلیلی', 'ارتباط با والدین', 'پشتیبانیِ تلفنی'],
            ],
            [
                'key' => 'pro', 'name' => 'حرفه‌ای', 'description' => 'انتخابِ اکثرِ مدارس',
                'price' => 7900000, 'period_label' => 'سالانه', 'duration_days' => 365,
                'max_classes' => 25, 'max_students_per_class' => 40, 'highlighted' => true, 'sort' => 3,
                'features' => ['۲۵ کلاس', 'تا ۴۰ دانش‌آموز در هر کلاس', 'آزمونِ هوشمند (AI)', 'گزارش‌های BI پیشرفته', 'بخشِ محرمانه‌ی والدین', 'خروجی‌های چاپی', 'پشتیبانیِ اولویت‌دار'],
            ],
            [
                'key' => 'enterprise', 'name' => 'سازمانی', 'description' => 'برای مجتمع‌ها و مدارسِ بزرگ',
                'price' => 14900000, 'period_label' => 'سالانه', 'duration_days' => 365,
                'max_classes' => null, 'max_students_per_class' => null, 'highlighted' => false, 'sort' => 4,
                'features' => ['کلاسِ نامحدود', 'دانش‌آموزِ نامحدود', 'همه‌ی امکاناتِ حرفه‌ای', 'برندینگِ اختصاصی (لوگو)', 'مدیرِ حسابِ اختصاصی', 'پشتیبانیِ ۲۴/۷'],
            ],
        ];

        foreach ($plans as $p) {
            $plan = Plan::firstOrCreate(['key' => $p['key']], $p + ['is_active' => true]);
            // بک‌فیلِ غیرِمخرب برای طرح‌های قدیمی: فقط فیلدهای نمایشیِ خالی پر می‌شوند (قیمت دست‌نخورده)
            $fill = [];
            if (empty($plan->features)) $fill['features'] = $p['features'];
            if (empty($plan->period_label)) $fill['period_label'] = $p['period_label'];
            if ($plan->highlighted === false && $p['highlighted']) $fill['highlighted'] = true;
            if ($fill) $plan->update($fill);
        }
    }
}
