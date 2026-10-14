<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** یک فیلمِ ضبط‌شده از کلاسِ آنلاین (هر کلاس می‌تواند چند ضبط داشته باشد). */
class OnlineRecording extends Model
{
    protected $fillable = ['room_id', 'school_id', 'teacher_id', 'path', 'size', 'duration_ms'];

    public function room(): BelongsTo { return $this->belongsTo(OnlineRoom::class, 'room_id'); }

    public static function ready(): bool
    {
        return \App\Support\DbSchema::hasTable('online_recordings');
    }

    /** داده‌ی فهرست برای صفحه‌ها. */
    public function card(): array
    {
        return [
            'id' => $this->id, 'room_id' => $this->room_id,
            'size_mb' => round($this->size / 1048576, 1),
            'minutes' => $this->duration_ms ? max(1, (int) round($this->duration_ms / 60000)) : null,
            'at' => $this->created_at ? \App\Support\Jalali::format($this->created_at) . ' ساعت ' . \App\Support\Jalali::fa($this->created_at->format('H:i')) : null,
            'url' => route('online.recording', [$this->room_id, 'rec' => $this->id]),
        ];
    }
}
