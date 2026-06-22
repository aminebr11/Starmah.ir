<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Seeder;

/**
 * ریاضی چهارم — محتوای خنثی نسبت به تم.
 * سؤال‌ها قالب‌محورند: جای‌خالی {team}/{unit} توسط موتور تم پر می‌شود،
 * پس یک سؤال در همه‌ی دنیاها (فوتبال، ماشین، ...) کار می‌کند.
 */
class CurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $math = Subject::updateOrCreate(
            ['slug' => 'math-4', 'school_id' => null],
            ['name' => 'ریاضی', 'grade' => 'چهارم', 'icon' => '🧮', 'sort' => 1]
        );

        $topics = [
            'add-sub' => ['name' => 'جمع و تفریق', 'skills' => [
                'add-2digit' => ['name' => 'جمع دو رقمی', 'q' => [
                    ['tpl' => '{hero} در بازی اول {n} {unit} و در بازی دوم {k} {unit} به‌دست آورد. مجموع چند {unit}؟',
                     'vars' => ['n' => [10, 40], 'k' => [10, 40]], 'expr' => 'n+k', 'xp' => 10],
                ]],
                'sub-2digit' => ['name' => 'تفریق دو رقمی', 'q' => [
                    ['tpl' => 'تیم {team} {n} {unit} داشت و {k} {unit} از دست داد. چند {unit} باقی ماند؟',
                     'vars' => ['n' => [20, 60], 'k' => [1, 19]], 'expr' => 'n-k', 'xp' => 10],
                ]],
            ]],
            'multiply' => ['name' => 'ضرب', 'skills' => [
                'mul-1digit' => ['name' => 'ضرب یک‌رقمی', 'q' => [
                    ['tpl' => 'تیم {team} در {n} بازی، در هر بازی {k} {unit} زد. در مجموع چند {unit}؟',
                     'vars' => ['n' => [2, 9], 'k' => [2, 9]], 'expr' => 'n*k', 'xp' => 15],
                ]],
                'mul-2x1' => ['name' => 'ضرب دو‌رقمی در یک‌رقمی', 'q' => [
                    ['tpl' => 'هر {hero} {k} {unit} دارد. {n} نفر چند {unit} دارند؟',
                     'vars' => ['n' => [11, 20], 'k' => [2, 9]], 'expr' => 'n*k', 'xp' => 20],
                ]],
            ]],
            'fractions' => ['name' => 'کسرها', 'skills' => [
                'frac-add' => ['name' => 'مقایسه و جمع کسر ساده', 'q' => [
                    ['tpl' => '{n} {unit} از {k} {unit} برداشته شد. چند {unit} باقی ماند؟',
                     'vars' => ['n' => [1, 5], 'k' => [6, 12]], 'expr' => 'k-n', 'xp' => 15],
                ]],
            ]],
            'geometry' => ['name' => 'هندسه', 'skills' => [
                'perimeter' => ['name' => 'محیط چندضلعی', 'q' => [
                    ['tpl' => 'زمین به شکل مربعی است که هر ضلع {n} متر است. محیط آن چند متر است؟',
                     'vars' => ['n' => [3, 20]], 'expr' => 'n*4', 'xp' => 15],
                ]],
            ]],
        ];

        $tSort = 0;
        foreach ($topics as $tSlug => $t) {
            $topic = Topic::updateOrCreate(
                ['subject_id' => $math->id, 'slug' => $tSlug],
                ['name' => $t['name'], 'sort' => ++$tSort]
            );

            $sSort = 0;
            foreach ($t['skills'] as $sSlug => $s) {
                $skill = Skill::updateOrCreate(
                    ['topic_id' => $topic->id, 'slug' => $sSlug],
                    ['name' => $s['name'], 'sort' => ++$sSort]
                );

                foreach ($s['q'] as $q) {
                    Question::updateOrCreate(
                        ['skill_id' => $skill->id, 'template' => $q['tpl']],
                        [
                            'type'        => 'mcq',
                            'difficulty'  => 2,
                            'variables'   => $q['vars'],
                            'answer_expr' => $q['expr'],
                            'xp'          => $q['xp'],
                        ]
                    );
                }
            }
        }
    }
}
