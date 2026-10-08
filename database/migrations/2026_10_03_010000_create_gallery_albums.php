<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * آلبوم‌های گالری: چند عکس زیرِ یک «پوشه» با یک عکسِ کاور.
 * هر عکس همچنان یک ردیفِ class_contents (type=gallery) است با album_id.
 * عکس‌های قدیمیِ بدونِ آلبوم را برنامه خودش (GalleryAlbums::adopt) دسته‌بندی می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gallery_albums')) {
            Schema::create('gallery_albums', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('school_id')->nullable()->index();
                $t->unsignedBigInteger('teacher_id')->index();
                $t->unsignedBigInteger('classroom_id')->nullable()->index();
                $t->string('title', 150);
                $t->text('description')->nullable();
                $t->unsignedBigInteger('cover_id')->nullable();
                $t->string('theme', 20)->nullable();
                $t->timestamp('publish_at')->nullable();
                $t->boolean('is_visible')->default(true);
                $t->timestamp('notified_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasColumn('class_contents', 'album_id')) {
            Schema::table('class_contents', function (Blueprint $t) {
                $t->unsignedBigInteger('album_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('class_contents', 'album_id')) {
            Schema::table('class_contents', function (Blueprint $t) {
                $t->dropIndex(['album_id']);
                $t->dropColumn('album_id');
            });
        }
        Schema::dropIfExists('gallery_albums');
    }
};
