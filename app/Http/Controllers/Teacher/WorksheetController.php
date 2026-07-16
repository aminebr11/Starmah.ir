<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Worksheet;
use App\Services\SmartExamAiService;
use App\Support\Jalali;
use App\Support\Roles;
use App\Support\WorksheetAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * کاربرگ‌سازِ هوشمند — معلم مشخصاتِ کاربرگ و تم را می‌دهد،
 * هوش مصنوعی سؤال پیشنهاد می‌دهد، و یک تصویرِ جذابِ HTML/SVG از کاربرگ ساخته و در بانک ذخیره می‌شود.
 */
class WorksheetController extends Controller
{
    /** تم‌های تصویری کاربرگ — هماهنگ با دنیاهای بازی. */
    private const THEMES = [
        'stars'   => ['label' => 'ستاره ماه 🌙', 'flavor' => 'ماجراجویی فضایی و ستاره‌ها', 'p1' => '#3d7bf0', 'p2' => '#7a5cf0', 'bg' => '#0d1b3e', 'ic' => ['⭐', '🌙', '✨', '☄️', '🚀']],
        'pitch'   => ['label' => 'شلیک آتشین ⚽', 'flavor' => 'فوتبال و ورزش', 'p1' => '#2bb673', 'p2' => '#0f9d58', 'bg' => '#0b3d2e', 'ic' => ['⚽', '🥅', '🏆', '👟', '🔥']],
        'blocks'  => ['label' => 'کریپر ماینکرفت 🟩', 'flavor' => 'دنیای مکعبی و ساخت‌وساز', 'p1' => '#4caf50', 'p2' => '#2e7d32', 'bg' => '#1b3a1e', 'ic' => ['🟩', '⛏️', '💎', '🧱', '🌳']],
        'speed'   => ['label' => 'سوپر اسپید 🏎️', 'flavor' => 'مسابقه و سرعت', 'p1' => '#e8862e', 'p2' => '#d64545', 'bg' => '#3a1c0b', 'ic' => ['🏎️', '🏁', '💨', '🔥', '🏆']],
        'classic' => ['label' => 'کلاسیک 📘', 'flavor' => '', 'p1' => '#3d7bf0', 'p2' => '#2555c0', 'bg' => '#16264f', 'ic' => ['📘', '✏️', '📐', '🍎', '⭐']],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $items = WorksheetAccess::visibleQuery($user)->latest()->get()
            ->map(fn (Worksheet $w) => [
                'id'       => $w->id,
                'title'    => $w->title,
                'subject'  => $w->subject,
                'grade'    => $w->grade,
                'theme'    => $w->theme,
                'scope'    => $w->scope,
                'count'    => is_array($w->questions) ? count($w->questions) : 0,
                'mine'     => $w->teacher_id === $user->id,
                'can_edit' => WorksheetAccess::canEdit($user, $w),
                'date'     => Jalali::format($w->created_at),
            ]);

        return Inertia::render('Teacher/Worksheets', [
            'items'  => $items->values(),
            'themes' => collect(self::THEMES)->map(fn ($t, $k) => ['key' => $k, 'label' => $t['label']])->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Teacher/WorksheetCreate', [
            'themes' => collect(self::THEMES)->map(fn ($t, $k) => ['key' => $k, 'label' => $t['label']])->values(),
        ]);
    }

    /** پیشنهادِ سؤال با هوش مصنوعی (بر اساس مشخصات + تم). */
    public function ai(Request $request, SmartExamAiService $ai): JsonResponse
    {
        $data = $request->validate([
            'subject'    => ['nullable', 'string', 'max:120'],
            'grade'      => ['nullable', 'string', 'max:60'],
            'topic'      => ['nullable', 'string', 'max:160'],
            'goal'       => ['nullable', 'string', 'max:300'],
            'theme'      => ['nullable', 'string', 'max:30'],
            'count'      => ['nullable', 'integer', 'min:1', 'max:20'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'type'       => ['nullable', 'in:mc,tf,desc,blank'],
            'sample'     => ['nullable', 'boolean'],
        ]);

        $theme = self::THEMES[$data['theme'] ?? 'classic'] ?? self::THEMES['classic'];

        $res = $ai->generate([
            'subject'    => $data['subject'] ?? '',
            'topic'      => $data['topic'] ?? ($data['subject'] ?? ''),
            'grade'      => $data['grade'] ?? 'چهارم',
            'goal'       => $data['goal'] ?? '',
            'count'      => $data['count'] ?? 6,
            'difficulty' => $data['difficulty'] ?? 'medium',
            'type'       => $data['type'] ?? 'mc',
            'flavor'     => $theme['flavor'],
            'sample'     => (bool) ($data['sample'] ?? false),
            'school_id'  => $request->user()->school_id,
            'teacher_id' => $request->user()->id,
        ]);

        return response()->json($res);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'      => ['required', 'string', 'max:180'],
            'subject'    => ['nullable', 'string', 'max:120'],
            'grade'      => ['nullable', 'string', 'max:60'],
            'theme'      => ['required', 'string', 'max:30'],
            'spec'       => ['nullable', 'string', 'max:2000'],
            'questions'  => ['required', 'array', 'min:1'],
            'questions.*.prompt' => ['required', 'string'],
        ]);

        $user = $request->user();
        $themeKey = array_key_exists($data['theme'], self::THEMES) ? $data['theme'] : 'classic';
        $scope = $user->hasRole(Roles::SUPER_ADMIN) ? 'global' : 'school';

        $html = $this->renderWorksheet($data['title'], $data['subject'] ?? '', $data['grade'] ?? '', $themeKey, $data['questions']);

        Worksheet::create([
            'school_id'   => $user->school_id,
            'teacher_id'  => $user->id,
            'scope'       => $scope,
            'title'       => $data['title'],
            'subject'     => $data['subject'] ?? null,
            'grade'       => $data['grade'] ?? null,
            'theme'       => $themeKey,
            'spec'        => $data['spec'] ?? null,
            'questions'   => array_map(fn ($q) => [
                'prompt'  => (string) ($q['prompt'] ?? ''),
                'type'    => $q['type'] ?? 'mc',
                'choices' => $q['choices'] ?? [],
                'answer'  => $q['answer'] ?? null,
            ], $data['questions']),
            'render_html' => $html,
        ]);

        return redirect()->route('teacher.worksheets')->with('flash', 'کاربرگ ساخته و در بانک ذخیره شد ✅');
    }

    /** نمایش/چاپِ تصویرِ کاربرگ (HTML مستقل). */
    public function show(Request $request, Worksheet $worksheet): Response
    {
        abort_unless(WorksheetAccess::visibleQuery($request->user())->whereKey($worksheet->id)->exists(), 403);

        return Inertia::render('Teacher/WorksheetView', [
            'worksheet' => [
                'id'    => $worksheet->id,
                'title' => $worksheet->title,
                'html'  => $worksheet->render_html,
                'date'  => Jalali::format($worksheet->created_at),
            ],
        ]);
    }

    public function destroy(Request $request, Worksheet $worksheet): RedirectResponse
    {
        abort_unless(WorksheetAccess::canEdit($request->user(), $worksheet), 403);
        $worksheet->delete();

        return back()->with('flash', 'کاربرگ حذف شد ✅');
    }

    /** ساختِ تصویرِ جذابِ HTML/SVG از کاربرگ (بجای API تصویرسازِ واقعی). قابل چاپ و دانلود. */
    private function renderWorksheet(string $title, string $subject, string $grade, string $themeKey, array $questions): string
    {
        $t = self::THEMES[$themeKey] ?? self::THEMES['classic'];
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $fa = fn ($n) => strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);

        // تزئیناتِ شناورِ ایموجیِ تم
        $floats = '';
        $positions = [[6, 8], [90, 12], [12, 88], [88, 86], [50, 5], [50, 94], [4, 50], [95, 48]];
        foreach ($positions as $i => [$x, $y]) {
            $ic = $t['ic'][$i % count($t['ic'])];
            $floats .= "<span style=\"position:absolute;left:{$x}%;top:{$y}%;font-size:26px;opacity:.28;transform:translate(-50%,-50%)\">{$ic}</span>";
        }

        $rows = '';
        foreach (array_values($questions) as $idx => $q) {
            $n = $fa($idx + 1);
            $prompt = $e($q['prompt'] ?? '');
            $type = $q['type'] ?? 'mc';
            $body = '';
            if (in_array($type, ['mc', 'tf'], true) && ! empty($q['choices'])) {
                $body .= '<div style="display:flex;flex-wrap:wrap;gap:10px 22px;margin-top:8px">';
                foreach ($q['choices'] as $ci => $c) {
                    $lbl = $e($c['value'] ?? '');
                    $letters = ['الف', 'ب', 'ج', 'د'];
                    $L = $letters[$ci] ?? ($ci + 1);
                    $body .= "<span style=\"display:inline-flex;align-items:center;gap:7px;font-size:14px\"><span style=\"display:inline-flex;width:22px;height:22px;border:2px solid {$t['p1']};border-radius:50%\"></span><b style=\"color:{$t['p2']}\">{$L})</b> {$lbl}</span>";
                }
                $body .= '</div>';
            } else {
                // تشریحی/جای‌خالی — خطوط پاسخ
                $body .= '<div style="margin-top:10px">'
                    . '<div style="border-bottom:2px dotted #c9d3e6;height:22px"></div>'
                    . '<div style="border-bottom:2px dotted #c9d3e6;height:22px;margin-top:6px"></div>'
                    . '</div>';
            }
            $rows .= "<div style=\"background:#fff;border:2px solid #eef2fa;border-inline-start:6px solid {$t['p1']};border-radius:14px;padding:14px 16px;margin-bottom:14px;box-shadow:0 4px 14px -10px rgba(0,0,0,.3)\">"
                . "<div style=\"display:flex;gap:10px;align-items:flex-start\">"
                . "<span style=\"flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:10px;background:linear-gradient(135deg,{$t['p1']},{$t['p2']});color:#fff;font-weight:900\">{$n}</span>"
                . "<div style=\"flex:1\"><div style=\"font-weight:800;font-size:15px;color:#1b2742;line-height:1.9\">{$prompt}</div>{$body}</div></div></div>";
        }

        $subjectLine = trim($subject . ($grade ? ' — پایه‌ی ' . $grade : ''));

        return '<div style="font-family:Vazirmatn,Tahoma,sans-serif;direction:rtl;max-width:820px;margin:0 auto;background:#f4f7fd;border-radius:22px;overflow:hidden;box-shadow:0 20px 50px -24px rgba(0,0,0,.5)">'
            . "<div style=\"position:relative;background:linear-gradient(135deg,{$t['p1']},{$t['p2']});color:#fff;padding:26px 24px;overflow:hidden\">"
            . $floats
            . "<div style=\"position:relative\"><div style=\"font-size:12px;opacity:.85;letter-spacing:1px\">کاربرگ آموزشی · ستاره ماه</div>"
            . "<div style=\"font-size:24px;font-weight:900;margin-top:4px\">" . $e($title) . '</div>'
            . ($subjectLine ? "<div style=\"font-size:14px;opacity:.92;margin-top:4px\">" . $e($subjectLine) . '</div>' : '')
            . '<div style="display:flex;gap:20px;margin-top:14px;font-size:13px;opacity:.95">'
            . '<span>👤 نام: ....................</span><span>🏫 کلاس: ..............</span><span>📅 تاریخ: ..............</span>'
            . '</div></div></div>'
            . '<div style="padding:22px 20px">' . $rows . '</div>'
            . "<div style=\"text-align:center;padding:14px;color:{$t['p2']};font-weight:800;font-size:13px\">🌟 آفرین! تو می‌تونی 🌟</div>"
            . '</div>';
    }
}
