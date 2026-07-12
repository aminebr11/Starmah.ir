<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** یک بازیِ آموزشیِ ساخته‌شده توسط معلم. */
class EduGame extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'teacher_id', 'template_key', 'theme_id', 'title', 'description',
        'subject', 'grade', 'difficulty', 'cover_path', 'status', 'publish_at', 'close_at', 'rules', 'version',
    ];

    protected $casts = [
        'rules' => 'array', 'publish_at' => 'datetime', 'close_at' => 'datetime',
    ];

    public function questions(): HasMany { return $this->hasMany(EduGameQuestion::class)->orderBy('sort'); }
    public function targets(): HasMany { return $this->hasMany(EduGameTarget::class); }
    public function attempts(): HasMany { return $this->hasMany(EduGameAttempt::class); }
    public function theme(): BelongsTo { return $this->belongsTo(Theme::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function template(): BelongsTo { return $this->belongsTo(GameTemplate::class, 'template_key', 'key'); }

    /** آیا هم‌اکنون برای بازی در دسترس است؟ */
    public function isLive(): bool
    {
        if ($this->status !== 'published') return false;
        $now = now();
        if ($this->publish_at && $now->lessThan($this->publish_at)) return false;
        if ($this->close_at && $now->greaterThan($this->close_at)) return false;
        return true;
    }
}
