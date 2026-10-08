<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** کاربرگِ پرشده که دانش‌آموز برای معلم می‌فرستد (عکس/فایل). */
class WorksheetSubmission extends Model
{
    protected $fillable = ['worksheet_id', 'student_id', 'file_path', 'note', 'submitted_at', 'downloaded_at', 'download_xp', 'submit_xp',
        'grade', 'grade_xp', 'feedback', 'marked_path', 'graded_at', 'graded_by'];

    protected $casts = [
        'submitted_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'download_xp' => 'boolean',
        'submit_xp' => 'boolean',
        'graded_at' => 'datetime',
        'grade_xp' => 'integer',
    ];

    /** نمره‌های توصیفیِ تصحیح و امتیازِ پیش‌فرضِ هر کدام (معلم می‌تواند امتیاز را عوض کند). */
    public const GRADES = ['عالی' => 20, 'خیلی خوب' => 15, 'خوب' => 10, 'قابل قبول' => 6, 'نیاز به تلاش' => 3];

    public const MAX_XP = 50;

    /** منبعِ امتیازِ تصحیح در دفترِ امتیاز (جدا از امتیازِ «ارسال»). */
    public const GRADE_SOURCE = 'worksheet_grade';

    public static function isPdf(?string $path): bool
    {
        return $path && str_ends_with(strtolower($path), '.pdf');
    }

    /** دادهِ مشترکِ نمایش (معلم و دانش‌آموز). فایل از مسیرِ خودِ سایت می‌آید، نه لینکِ مستقیم. */
    public function viewData(): array
    {
        $v = $this->updated_at?->timestamp ?? 0;

        return [
            'id' => $this->id,
            'url' => $this->file_path ? route('worksheet.file', [$this->id, 'file']) . '?v=' . $v : null,
            'marked_url' => $this->marked_path ? route('worksheet.file', [$this->id, 'marked']) . '?v=' . $v : null,
            'pdf' => self::isPdf($this->file_path),
            'note' => $this->note,
            'date' => \App\Support\Jalali::format($this->submitted_at ?? $this->created_at, true),
            'grade' => $this->grade,
            'xp' => (int) ($this->grade_xp ?? 0),
            'feedback' => $this->feedback,
            'graded' => (bool) $this->graded_at,
            'graded_at' => $this->graded_at ? \App\Support\Jalali::format($this->graded_at, true) : null,
        ];
    }

    public function worksheet(): BelongsTo { return $this->belongsTo(Worksheet::class); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
