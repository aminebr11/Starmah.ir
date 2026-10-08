<?php

namespace Tests\Concerns;

use App\Models\Classroom;
use App\Models\Mission;
use App\Models\School;
use App\Models\SmartQuestionBank;
use App\Models\User;
use App\Support\Roles;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** ساختِ یک مدرسه‌ی کوچک برای تست: معلم، کلاس، دانش‌آموز و سؤالِ بانک. */
trait BuildsSchool
{
    protected function seedRoles(): void
    {
        $this->seed(RoleSeeder::class);
    }

    protected function makeSchool(string $slug = 'test-school'): School
    {
        return School::create(['name' => 'دبستان ' . $slug, 'slug' => $slug, 'status' => 'active', 'plan' => 'pro', 'seats' => 50]);
    }

    protected function makeUser(?School $school, string $role, array $extra = []): User
    {
        $u = User::create(array_merge([
            'school_id' => $school?->id, 'name' => 'کاربر ' . Str::random(4),
            'phone' => '0912' . random_int(1000000, 9999999), 'password' => Hash::make('password'),
        ], $extra));
        $u->assignRole($role);

        return $u;
    }

    /** @return array{0:User,1:Classroom,2:User} [teacher, classroom, student] */
    protected function makeClass(School $school, string $grade = 'چهارم'): array
    {
        $teacher = $this->makeUser($school, Roles::TEACHER);
        $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'کلاس ' . Str::random(3), 'teacher_id' => $teacher->id, 'grade' => $grade, 'join_code' => Str::upper(Str::random(6))]);
        $student = $this->addStudent($school, $classroom, $grade);

        return [$teacher, $classroom, $student];
    }

    protected function addStudent(School $school, Classroom $classroom, string $grade = 'چهارم'): User
    {
        $s = $this->makeUser($school, Roles::STUDENT, ['grade' => $grade]);
        $classroom->students()->attach($s->id, ['joined_at' => now()]);

        return $s;
    }

    /** @return array<int,SmartQuestionBank> */
    protected function makeQuestions(User $teacher, string $topic, int $n = 3, array $extra = []): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = SmartQuestionBank::withoutGlobalScopes()->create(array_merge([
                'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'scope' => 'school', 'type' => 'mc',
                'prompt' => "سؤالِ {$topic} شماره‌ی {$i} " . Str::random(5), 'grade' => 'چهارم', 'subject' => 'ریاضی', 'topic' => $topic,
                'choices' => [['value' => "درست{$i}", 'correct' => true], ['value' => "غلطالف{$i}", 'correct' => false], ['value' => "غلطب{$i}", 'correct' => false], ['value' => "غلطج{$i}", 'correct' => false]],
                'hint' => 'راهنمای ' . $topic, 'explanation' => 'توضیحِ ' . $topic, 'approval' => 'approved',
            ], $extra));
        }

        return $out;
    }

    protected function makeMission(User $teacher, Classroom $classroom, array $questions, array $extra = []): Mission
    {
        return Mission::create(array_merge([
            'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id,
            'title' => 'مأموریتِ تست', 'type' => 'quiz', 'subject' => 'ریاضی',
            'question_ids' => collect($questions)->pluck('id')->all(), 'question_count' => count($questions),
            'pass_percent' => 60, 'xp_reward' => 20, 'is_active' => true, 'repeat_mode' => 'daily',
        ], $extra));
    }
}
