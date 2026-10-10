<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** آهنگِ معلم برای «🏆 مسابقه‌ی زنده» — یک بار آپلود می‌شود و در مسابقه‌های بعدی هم انتخاب‌شدنی است. */
class LiveTrack extends Model
{
    protected $fillable = ['school_id', 'teacher_id', 'title', 'path', 'size'];

    public static function ready(): bool
    {
        return \App\Support\DbSchema::hasTable('live_tracks') && \App\Support\DbSchema::hasColumn('live_contests', 'music_track_id');
    }

    public function data(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'url' => route('teacher.live.track', $this->id),
            'size' => round($this->size / 1048576, 1)];
    }
}
