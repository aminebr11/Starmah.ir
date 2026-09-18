<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جا بازکردن برای محیط‌های بازیِ بزرگ.
 *
 * ستون‌های تخته از نوعِ TEXT بودند؛ TEXT در MySQL سقفِ ۶۵٬۵۳۵ «بایت»
 * دارد، نه نویسه — و هر حرفِ فارسی در UTF-8 دو بایت می‌گیرد. پس یک
 * صحنه‌ی بزرگ با توضیحاتِ فارسی خیلی زودتر از چیزی که به نظر می‌رسد
 * به سقف می‌خورد و ذخیره بی‌صدا ناقص می‌شد.
 *
 * MEDIUMTEXT سقف را به ۱۶ مگابایت می‌برد؛ با اعتبارسنجیِ ۲۰۰٬۰۰۰ نویسه
 * در کنترلر، حتی بدترین حالتِ فارسی (۴۰۰ کیلوبایت) هم راحت جا می‌شود.
 *
 * روی SQLite این تغییر بی‌اثر است (TEXT آنجا سقف ندارد) ولی مهاجرت
 * بدونِ خطا رد می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('game_templates')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // change() روی MySQL امن‌تر از تغییرِ مستقیمِ SQL نیست؛ همین‌جا
            // صریح می‌نویسیم تا دقیقاً نوعِ دلخواه بنشیند.
            Schema::getConnection()->statement(
                'ALTER TABLE `game_templates` MODIFY `board_html` MEDIUMTEXT NULL, MODIFY `board_css` MEDIUMTEXT NULL'
            );

            return;
        }

        Schema::table('game_templates', function (Blueprint $table) {
            $table->mediumText('board_html')->nullable()->change();
            $table->mediumText('board_css')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('game_templates')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement(
                'ALTER TABLE `game_templates` MODIFY `board_html` TEXT NULL, MODIFY `board_css` TEXT NULL'
            );

            return;
        }

        Schema::table('game_templates', function (Blueprint $table) {
            $table->text('board_html')->nullable()->change();
            $table->text('board_css')->nullable()->change();
        });
    }
};
