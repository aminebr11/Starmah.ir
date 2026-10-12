<?php

namespace Tests\Feature\Assistant;

use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** «ماهو»: نمای من متناسب با نقش، بسته‌بودن توسطِ مدیر، و پاسخِ گفت‌وگو. */
class AssistantTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_hello_shows_only_own_numbers_for_each_role(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher, $classroom, $student] = $this->makeClass($school);
        $admin = $this->makeUser($school, Roles::SCHOOL_ADMIN);

        $st = $this->actingAs($student)->getJson(route('assistant.hello'))->assertOk()->json();
        $this->assertSame(['امتیاز', 'سطح'], array_slice(array_column($st['stats'], 'label'), 0, 2));
        $this->assertContains('رتبه در کلاس', array_column($st['stats'], 'label'));
        $this->assertNotContains('دانش‌آموز', array_column($st['stats'], 'label'));

        $te = $this->actingAs($teacher)->getJson(route('assistant.hello'))->assertOk()->json();
        $labels = array_column($te['stats'], 'label');
        $this->assertContains('دانش‌آموز', $labels);
        $this->assertSame('۱', $te['stats'][array_search('دانش‌آموز', $labels)]['value']);

        $ad = $this->actingAs($admin)->getJson(route('assistant.hello'))->assertOk()->json();
        $this->assertContains('معلم', array_column($ad['stats'], 'label'));
        $this->assertArrayHasKey('ai', $ad);
    }

    public function test_school_can_close_the_assistant_for_students(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [, , $student] = $this->makeClass($school);
        $school->forceFill(['assistant_students' => false])->save();

        $this->actingAs($student)->getJson(route('assistant.hello'))->assertForbidden();
        $this->actingAs($student)->postJson(route('assistant.chat'), ['message' => 'سلام'])->assertForbidden();
    }

    public function test_chat_answers_from_own_data_without_ai_key(): void
    {
        $this->seedRoles();
        [, , $student] = $this->makeClass($this->makeSchool());

        $res = $this->actingAs($student)->postJson(route('assistant.chat'), ['message' => 'گزارش تحلیلی بده', 'history' => []])->assertOk()->json();
        $this->assertSame('local', $res['mode']);
        $this->assertStringContainsString($student->name, $res['reply']);
    }
}
