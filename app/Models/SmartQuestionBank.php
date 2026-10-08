<?php
namespace App\Models;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SmartQuestionBank extends Model
{
    use BelongsToSchool;
    protected $table = 'smart_question_bank';
    protected $fillable = [
        'school_id', 'teacher_id', 'scope', 'level', 'type', 'prompt', 'choices', 'answer', 'explanation',
        'grade', 'subject', 'lesson_no', 'book', 'chapter', 'topic', 'goal', 'difficulty', 'points', 'time_limit',
        'tags', 'media_path', 'source', 'approval', 'used_count', 'correct_pct', 'version',
        'chapter_id', 'bloom', 'hint', 'fingerprint', 'objective_id',
    ];

    /** فیلدهایی که «هدفِ درسیِ» سؤال را تعیین می‌کنند. */
    public const OBJECTIVE_FIELDS = ['grade', 'subject', 'book', 'chapter_id', 'chapter', 'lesson_no', 'topic'];

    protected static function booted(): void
    {
        // هدفِ درسی همیشه با دسته‌بندیِ سؤال هم‌خوان می‌ماند (مرورِ فاصله‌دار و گزارشِ تسلط به آن تکیه دارند)
        static::saving(function (self $q) {
            static $ready = null;
            // اگر به‌روزرسانیِ کد پیش از اجرای مایگریشن رسیده باشد، ذخیره‌ی سؤال نباید بشکند
            $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('smart_question_bank', 'objective_id');
            if (! $ready) {
                return;
            }
            if (! $q->objective_id || $q->isDirty(self::OBJECTIVE_FIELDS)) {
                $q->objective_id = \App\Support\Objectives::idFor($q);
            }
        });
    }
    protected $casts = ['choices' => 'array', 'answer' => 'array'];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
}
