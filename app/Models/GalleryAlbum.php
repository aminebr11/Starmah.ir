<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** آلبومِ گالری — پوشه‌ای از عکس‌های کلاس با یک عکسِ کاور. */
class GalleryAlbum extends Model
{
    use BelongsToSchool;

    public const THEMES = ['sunset', 'ocean', 'forest', 'candy', 'night', 'sky'];

    protected $fillable = [
        'school_id', 'teacher_id', 'classroom_id', 'title', 'description',
        'cover_id', 'theme', 'publish_at', 'is_visible', 'notified_at',
    ];

    protected $casts = [
        'publish_at' => 'datetime',
        'notified_at' => 'datetime',
        'is_visible' => 'boolean',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(ClassContent::class, 'album_id')->where('type', 'gallery')->orderBy('created_at')->orderBy('id');
    }

    public function cover(): BelongsTo { return $this->belongsTo(ClassContent::class, 'cover_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }

    public function isLive(): bool
    {
        return $this->is_visible !== false && ($this->publish_at === null || $this->publish_at->lessThanOrEqualTo(now()));
    }
}
