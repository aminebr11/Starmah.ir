<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** انواعِ مأموریت: quiz (سؤالِ بانک) | podcast | worksheet | game. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('missions') && ! Schema::hasColumn('missions', 'type')) {
            Schema::table('missions', function (Blueprint $table) {
                $table->string('type', 20)->default('quiz')->after('title');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('missions') && Schema::hasColumn('missions', 'type')) {
            Schema::table('missions', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
