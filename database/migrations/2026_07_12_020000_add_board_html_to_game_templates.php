<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** تم‌ساز HTML برای ادمین: تختهٔ سفارشیِ هر قالب بازی (HTML + CSS) با جای‌گیرها. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('game_templates', 'board_html')) {
                $table->text('board_html')->nullable()->after('config');
            }
            if (! Schema::hasColumn('game_templates', 'board_css')) {
                $table->text('board_css')->nullable()->after('board_html');
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_templates', function (Blueprint $table) {
            foreach (['board_html', 'board_css'] as $c) {
                if (Schema::hasColumn('game_templates', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
