<?php

namespace Tests\Feature\Learning;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** «بخوان برایم» و «متنِ درشت». */
class A11yTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_large_text_defaults_on_for_early_grades_and_can_be_saved(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [, , $second] = $this->makeClass($school, 'دوم');
        [, , $fourth] = $this->makeClass($school, 'چهارم');

        $this->actingAs($second)->get(route('missions'))->assertInertia(fn ($p) => $p->where('a11y.largeText', true)->where('a11y.readAloud', true));
        $this->actingAs($fourth)->get(route('missions'))->assertInertia(fn ($p) => $p->where('a11y.largeText', false));

        $this->postJson(route('a11y.update'), ['largeText' => true])->assertOk()->assertJson(['largeText' => true]);
        $this->get(route('missions'))->assertInertia(fn ($p) => $p->where('a11y.largeText', true));
    }
}
