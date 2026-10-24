<?php

namespace Tests\Feature\Learning;

use App\Models\Grade;
use App\Models\GradeColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** دفترِ نمره بی‌درنگ باز می‌شود؛ تحلیلِ اثر در تسلط جدا (با تأخیر) می‌رسد و همان نتیجه را دارد. */
class GradebookFastLoadTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_gradebook_defers_mastery_impact_and_groups_by_month(): void
    {
        $this->seedRoles();
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $cols = [];
        foreach ([18, 15, 19, 12] as $i => $score) {
            $col = GradeColumn::create([
                'school_id' => $teacher->school_id, 'classroom_id' => $classroom->id, 'teacher_id' => $teacher->id,
                'title' => 'ارزیابی ' . $i, 'type' => 'numeric', 'score_type' => 'numeric', 'lesson' => 'ریاضی', 'topic' => 'کسرها',
                'max' => 20, 'graded_at' => now()->subDays($i * 10),
            ]);
            Grade::create(['grade_column_id' => $col->id, 'student_id' => $student->id, 'score' => $score]);
            $cols[] = $col;
        }

        $this->actingAs($teacher)->get(route('teacher.gradebook'))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Teacher/Gradebook')
                ->has('activities', 4)
                ->missing('impact')                                   // صفحه منتظرِ تحلیل نمی‌ماند
                ->where('activities.0.ym', fn ($v) => (bool) preg_match('/^\d{4}-\d{2}$/', (string) $v))
                ->where('activities.0.month', fn ($v) => is_string($v) && $v !== '')
                ->loadDeferredProps(fn ($d) => $d
                    ->has('impact.' . $cols[0]->id . '.students.' . $student->id)
                    ->where('impact.' . $cols[0]->id . '.subject', 'ریاضی')));
    }
}
