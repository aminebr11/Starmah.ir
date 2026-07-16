<?php

use App\Http\Controllers\Admin\PlatformController;
use App\Http\Controllers\Admin\SchoolApprovalController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SchoolAdmin\SchoolDashboardController;
use App\Http\Controllers\SchoolAdmin\TeacherController as SchoolTeacherController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\Teacher\AssignmentController;
use App\Http\Controllers\Teacher\DisciplineController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', WelcomeController::class)->name('welcome');

// صفحه‌ی درباره‌ی معلم/کلاس — عمومی
Route::get('/about', fn () => Inertia::render('About'))->name('about');

// نقطه‌ی ورود مشترک — بر اساس نقش هدایت می‌شود
Route::get('/dashboard', HomeController::class)->middleware('auth')->name('dashboard');

/* ---------------- ثبت‌نام (عمومی) ---------------- */
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegistrationController::class, 'choice'])->name('register');
    Route::get('/register/school', [RegistrationController::class, 'schoolForm'])->name('register.school');
    Route::post('/register/school', [RegistrationController::class, 'schoolStore'])->name('register.school.store');
    Route::get('/register/thanks', [RegistrationController::class, 'thanks'])->name('register.thanks');
    Route::get('/register/student', [RegistrationController::class, 'studentForm'])->name('register.student');
    Route::post('/register/student', [RegistrationController::class, 'studentStore'])->name('register.student.store');
});

/* ---------------- ادمین کل (سوپرادمین) ---------------- */
Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [PlatformController::class, 'overview'])->name('overview');
    Route::get('/schools', [SchoolApprovalController::class, 'index'])->name('schools');
    Route::get('/schools/{school}/manage', [\App\Http\Controllers\Admin\SchoolManageController::class, 'show'])->name('schools.manage');
    Route::put('/schools/{school}', [\App\Http\Controllers\Admin\SchoolManageController::class, 'update'])->name('schools.update');
    Route::post('/schools/requests/{schoolRequest}/approve', [SchoolApprovalController::class, 'approve'])->name('schools.approve');
    Route::post('/schools/requests/{schoolRequest}/reject', [SchoolApprovalController::class, 'reject'])->name('schools.reject');
    Route::post('/schools/{school}/plan', [SchoolApprovalController::class, 'updatePlan'])->name('schools.plan');
    // طرح‌های اشتراک
    Route::get('/plans', [\App\Http\Controllers\Admin\PlanController::class, 'index'])->name('plans');
    Route::post('/plans', [\App\Http\Controllers\Admin\PlanController::class, 'store'])->name('plans.store');
    Route::put('/plans/{plan}', [\App\Http\Controllers\Admin\PlanController::class, 'update'])->name('plans.update');
    Route::post('/plans/{plan}/toggle', [\App\Http\Controllers\Admin\PlanController::class, 'toggle'])->name('plans.toggle');
    Route::delete('/plans/{plan}', [\App\Http\Controllers\Admin\PlanController::class, 'destroy'])->name('plans.destroy');
    // دروس/کتاب‌های مقاطع
    Route::get('/curriculum', [\App\Http\Controllers\Admin\CurriculumController::class, 'index'])->name('curriculum');
    Route::post('/curriculum', [\App\Http\Controllers\Admin\CurriculumController::class, 'store'])->name('curriculum.store');
    Route::put('/curriculum/{curriculumBook}', [\App\Http\Controllers\Admin\CurriculumController::class, 'update'])->name('curriculum.update');
    Route::delete('/curriculum/{curriculumBook}', [\App\Http\Controllers\Admin\CurriculumController::class, 'destroy'])->name('curriculum.destroy');
    Route::get('/themes', [PlatformController::class, 'themes'])->name('themes');
    Route::post('/themes', [PlatformController::class, 'storeTheme'])->name('themes.store');
    Route::post('/themes/{theme}/toggle', [PlatformController::class, 'toggleTheme'])->name('themes.toggle');
    Route::post('/themes/{theme}/header', [PlatformController::class, 'uploadHeader'])->name('themes.header');
    // قالب‌های بازی (مکانیک‌ها)
    Route::get('/game-templates', [\App\Http\Controllers\Admin\GameTemplateController::class, 'index'])->name('game-templates');
    Route::post('/game-templates', [\App\Http\Controllers\Admin\GameTemplateController::class, 'store'])->name('game-templates.store');
    Route::post('/game-templates/{gameTemplate}/toggle', [\App\Http\Controllers\Admin\GameTemplateController::class, 'toggle'])->name('game-templates.toggle');
    Route::put('/game-templates/{gameTemplate}', [\App\Http\Controllers\Admin\GameTemplateController::class, 'update'])->name('game-templates.update');
    Route::delete('/game-templates/{gameTemplate}', [\App\Http\Controllers\Admin\GameTemplateController::class, 'destroy'])->name('game-templates.destroy');
    // آزمایشگاه هوشمند آزمون — پرچم‌ها و دامنه (بدون گیت؛ ادمین همیشه دسترسی دارد)
    Route::get('/smart-lab', [\App\Http\Controllers\Admin\SmartLabController::class, 'index'])->name('smart-lab');
    Route::post('/smart-lab', [\App\Http\Controllers\Admin\SmartLabController::class, 'update'])->name('smart-lab.update');
    Route::get('/reports', [PlatformController::class, 'reports'])->name('reports');
    Route::get('/settings', [PlatformController::class, 'settings'])->name('settings');
    Route::post('/settings', [PlatformController::class, 'storeSettings'])->name('settings.store');
});

/* ---------------- مدیر مدرسه ---------------- */
Route::middleware(['auth', 'role:school_admin'])->prefix('school')->name('school.')->group(function () {
    Route::get('/', [SchoolDashboardController::class, 'overview'])->name('overview');
    Route::get('/teachers', [SchoolTeacherController::class, 'index'])->name('teachers');
    Route::post('/teachers', [SchoolTeacherController::class, 'store'])->name('teachers.store');
    Route::get('/students', [SchoolDashboardController::class, 'students'])->name('students');
    Route::get('/announcements', [SchoolDashboardController::class, 'announcements'])->name('announcements');
    Route::post('/announcements', [SchoolDashboardController::class, 'storeAnnouncement'])->name('announcements.store');
    Route::put('/announcements/{announcement}', [SchoolDashboardController::class, 'updateAnnouncement'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [SchoolDashboardController::class, 'destroyAnnouncement'])->name('announcements.destroy');
    Route::post('/announcements/ai', [SchoolDashboardController::class, 'aiAnnouncement'])->name('announcements.ai');
    Route::get('/schedule', [\App\Http\Controllers\ScheduleController::class, 'manage'])->name('schedule');
    Route::post('/schedule', [\App\Http\Controllers\ScheduleController::class, 'store'])->name('schedule.store');
    Route::delete('/schedule/{scheduleEntry}', [\App\Http\Controllers\ScheduleController::class, 'destroy'])->name('schedule.destroy');
    Route::get('/schedule-overview', [\App\Http\Controllers\ScheduleController::class, 'schoolView'])->name('schedule.overview');
    Route::get('/reports', [SchoolDashboardController::class, 'reports'])->name('reports');
    // ثبت حضور و غیاب توسط مدیر مدرسه (همه‌ی کلاس‌ها)
    Route::get('/attendance', [\App\Http\Controllers\AttendanceController::class, 'record'])->name('attendance');
    Route::post('/attendance', [\App\Http\Controllers\AttendanceController::class, 'store'])->name('attendance.store');
    Route::delete('/attendance/day', [\App\Http\Controllers\AttendanceController::class, 'destroyDay'])->name('attendance.day.destroy');
    Route::delete('/attendance/one', [\App\Http\Controllers\AttendanceController::class, 'destroyOne'])->name('attendance.one.destroy');
    Route::get('/attendance/monthly-sheet', [\App\Http\Controllers\AttendanceController::class, 'monthlySheet'])->name('attendance.monthly');
    Route::get('/attendance-report', [\App\Http\Controllers\AttendanceReportController::class, 'index'])->name('attendance.report');
    Route::get('/exam-reports', [\App\Http\Controllers\SchoolAdmin\ExamOverviewController::class, 'index'])->name('exam.reports');
});

/* ---------------- دانش‌آموز ---------------- */
Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('/world', [ThemeController::class, 'index'])->name('world.choose');
    Route::post('/world', [ThemeController::class, 'update'])->name('world.update');

    Route::get('/practice/{skill?}', [PracticeController::class, 'start'])->name('practice.start');
    Route::post('/practice/submit', [PracticeController::class, 'submit'])->name('practice.submit');

    Route::get('/progress', ProgressController::class)->name('progress');
    Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
    Route::get('/schedule', [\App\Http\Controllers\ScheduleController::class, 'studentView'])->name('schedule');
    Route::get('/my-discipline', [\App\Http\Controllers\StudentDisciplineController::class, 'index'])->name('my.discipline');
    Route::get('/my-grades', [\App\Http\Controllers\StudentGradesController::class, 'index'])->name('my.grades');
    // کارت‌های صفحه‌ی خانه: محتوای کلاس، تکالیف، فعالیت‌ها، گزارش‌ها
    Route::get('/class-content', [\App\Http\Controllers\Student\StudentHubController::class, 'content'])->name('my.content');
    Route::post('/class-content/{classContent}/progress', [\App\Http\Controllers\Student\StudentHubController::class, 'contentProgress'])->name('my.content.progress');
    Route::get('/homework', [\App\Http\Controllers\Student\StudentHubController::class, 'homework'])->name('my.homework');
    Route::get('/my-activities', [\App\Http\Controllers\Student\StudentHubController::class, 'activities'])->name('my.activities');
    Route::get('/my-reports', \App\Http\Controllers\Student\StudentReportController::class)->name('my.reports');

    Route::get('/exams', [ExamController::class, 'index'])->name('exams');
    Route::get('/exams/{assignment}/take', [ExamController::class, 'take'])->name('exams.take');
    Route::post('/exams/{assignment}/submit', [ExamController::class, 'submit'])->name('exams.submit');

    // بازی‌های دانش‌آموز (نسخه‌ی ساده‌ی قبلی)
    Route::get('/games', [\App\Http\Controllers\Student\GameController::class, 'index'])->name('games');
    Route::get('/games/{classActivity}/play', [\App\Http\Controllers\Student\GameController::class, 'play'])->name('games.play');
    Route::post('/games/{classActivity}/submit', [\App\Http\Controllers\Student\GameController::class, 'submit'])->name('games.submit');

    // دنیای بازی‌های آموزشی
    Route::get('/game-world', [\App\Http\Controllers\Student\EduGameWorldController::class, 'world'])->name('gameworld');
    Route::get('/game-world/{eduGame}/play', [\App\Http\Controllers\Student\EduGameWorldController::class, 'play'])->name('gameworld.play');
    Route::post('/game-world/{eduGame}/progress', [\App\Http\Controllers\Student\EduGameWorldController::class, 'progress'])->name('gameworld.progress');
    Route::post('/game-world/{eduGame}/finish', [\App\Http\Controllers\Student\EduGameWorldController::class, 'finish'])->name('gameworld.finish');

    // آزمایشگاه هوشمند — سمت دانش‌آموز (آزمایشی)
    Route::middleware('smartlab')->prefix('student/smart-exams')->name('student.smart.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Student\SmartExamController::class, 'index'])->name('index');
        Route::get('/my-performance', [\App\Http\Controllers\Student\SmartExamController::class, 'performance'])->name('performance');
        Route::get('/{smartExam}/take', [\App\Http\Controllers\Student\SmartExamController::class, 'take'])->name('take');
        Route::post('/{smartExam}/save', [\App\Http\Controllers\Student\SmartExamController::class, 'save'])->name('save');
        Route::post('/{smartExam}/submit', [\App\Http\Controllers\Student\SmartExamController::class, 'submit'])->name('submit');
        Route::get('/{smartExam}/result/{attempt}', [\App\Http\Controllers\Student\SmartExamController::class, 'result'])->name('result');
    });
});

/* ---------------- معلم ---------------- */
Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/', [TeacherDashboardController::class, 'index'])->name('dashboard');
    Route::post('/dismiss-alarm', [TeacherDashboardController::class, 'dismissAlarm'])->name('dismiss-alarm');
    // حضور و غیاب
    Route::get('/attendance', [\App\Http\Controllers\AttendanceController::class, 'record'])->name('attendance');
    Route::post('/attendance', [\App\Http\Controllers\AttendanceController::class, 'store'])->name('attendance.store');
    Route::delete('/attendance/day', [\App\Http\Controllers\AttendanceController::class, 'destroyDay'])->name('attendance.day.destroy');
    Route::delete('/attendance/one', [\App\Http\Controllers\AttendanceController::class, 'destroyOne'])->name('attendance.one.destroy');
    Route::get('/attendance/monthly-sheet', [\App\Http\Controllers\AttendanceController::class, 'monthlySheet'])->name('attendance.monthly');
    Route::get('/attendance-report', [\App\Http\Controllers\AttendanceReportController::class, 'index'])->name('attendance.report');
    Route::get('/class/{classroom}', [TeacherDashboardController::class, 'show'])->name('classroom');
    Route::get('/students', [TeacherDashboardController::class, 'myClass'])->name('students');
    Route::post('/students/{user}/team', [TeacherDashboardController::class, 'setTeam'])->name('students.team');
    Route::get('/activities', [\App\Http\Controllers\Teacher\ActivityController::class, 'index'])->name('activities');
    Route::post('/activities', [\App\Http\Controllers\Teacher\ActivityController::class, 'store'])->name('activities.store');
    Route::post('/activities/{classActivity}/award', [\App\Http\Controllers\Teacher\ActivityController::class, 'award'])->name('activities.award');
    // بازی‌ساز (بانک بازی، انتشار، XP)
    Route::get('/games', [\App\Http\Controllers\Teacher\GameController::class, 'index'])->name('games');
    Route::post('/games/ai', [\App\Http\Controllers\Teacher\GameController::class, 'aiGenerate'])->name('games.ai');
    Route::post('/games', [\App\Http\Controllers\Teacher\GameController::class, 'store'])->name('games.store');
    Route::put('/games/{classActivity}', [\App\Http\Controllers\Teacher\GameController::class, 'update'])->name('games.update');
    Route::post('/games/{classActivity}/toggle', [\App\Http\Controllers\Teacher\GameController::class, 'toggle'])->name('games.toggle');
    Route::post('/games/{classActivity}/duplicate', [\App\Http\Controllers\Teacher\GameController::class, 'duplicate'])->name('games.duplicate');
    Route::delete('/games/{classActivity}', [\App\Http\Controllers\Teacher\GameController::class, 'destroy'])->name('games.destroy');
    Route::get('/gradebook', [\App\Http\Controllers\Teacher\GradebookController::class, 'index'])->name('gradebook');
    Route::post('/gradebook/activities', [\App\Http\Controllers\Teacher\GradebookController::class, 'storeActivity'])->name('gradebook.activities');
    Route::post('/gradebook/columns/{gradeColumn}/grades', [\App\Http\Controllers\Teacher\GradebookController::class, 'saveGrades'])->name('gradebook.grades');
    Route::delete('/gradebook/columns/{gradeColumn}', [\App\Http\Controllers\Teacher\GradebookController::class, 'destroyColumn'])->name('gradebook.columns.destroy');
    Route::get('/discipline', [DisciplineController::class, 'index'])->name('discipline');
    Route::post('/discipline/topics', [DisciplineController::class, 'storeTopic'])->name('discipline.topics');
    Route::delete('/discipline/topics/{disciplineTopic}', [DisciplineController::class, 'destroyTopic'])->name('discipline.topics.destroy');
    Route::post('/discipline/record', [DisciplineController::class, 'record'])->name('discipline.record');
    // محتوای کلاس: جزوه/فایل، پادکست، گالری، تکلیف (بارگذاری واقعی)
    Route::get('/materials', [\App\Http\Controllers\Teacher\ClassContentController::class, 'index'])->name('materials');
    Route::post('/materials', [\App\Http\Controllers\Teacher\ClassContentController::class, 'store'])->name('materials.store');
    Route::post('/materials/{classContent}', [\App\Http\Controllers\Teacher\ClassContentController::class, 'update'])->name('materials.update');
    Route::delete('/materials/{classContent}', [\App\Http\Controllers\Teacher\ClassContentController::class, 'destroy'])->name('materials.destroy');
    Route::get('/reports', [TeacherDashboardController::class, 'reports'])->name('reports');
    Route::get('/schedule', [\App\Http\Controllers\ScheduleController::class, 'manage'])->name('schedule');
    Route::post('/schedule', [\App\Http\Controllers\ScheduleController::class, 'store'])->name('schedule.store');
    Route::delete('/schedule/{scheduleEntry}', [\App\Http\Controllers\ScheduleController::class, 'destroy'])->name('schedule.destroy');
    Route::get('/assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
    Route::post('/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    // کاربرگ‌سازِ هوشمند + بانک کاربرگ‌ها
    Route::get('/worksheets', [\App\Http\Controllers\Teacher\WorksheetController::class, 'index'])->name('worksheets');
    Route::get('/worksheets/create', [\App\Http\Controllers\Teacher\WorksheetController::class, 'create'])->name('worksheets.create');
    Route::post('/worksheets/ai', [\App\Http\Controllers\Teacher\WorksheetController::class, 'ai'])->name('worksheets.ai');
    Route::post('/worksheets', [\App\Http\Controllers\Teacher\WorksheetController::class, 'store'])->name('worksheets.store');
    Route::get('/worksheets/{worksheet}', [\App\Http\Controllers\Teacher\WorksheetController::class, 'show'])->name('worksheets.show');
    Route::delete('/worksheets/{worksheet}', [\App\Http\Controllers\Teacher\WorksheetController::class, 'destroy'])->name('worksheets.destroy');
    // آزمون‌ساز (دستی + AI)
    Route::get('/exams', [\App\Http\Controllers\Teacher\ExamBuilderController::class, 'index'])->name('exams');
    Route::post('/exams/generate', [\App\Http\Controllers\Teacher\ExamBuilderController::class, 'generate'])->name('exams.generate');
    Route::post('/exams', [\App\Http\Controllers\Teacher\ExamBuilderController::class, 'store'])->name('exams.store');
    Route::put('/exams/{assignment}', [\App\Http\Controllers\Teacher\ExamBuilderController::class, 'update'])->name('exams.update');
    Route::delete('/exams/{assignment}', [\App\Http\Controllers\Teacher\ExamBuilderController::class, 'destroy'])->name('exams.destroy');
    Route::get('/exams/{assignment}/report', [\App\Http\Controllers\Teacher\ExamBuilderController::class, 'report'])->name('exams.report');
    Route::post('/exams/{assignment}/grade-descriptive', [\App\Http\Controllers\Teacher\ExamBuilderController::class, 'gradeDescriptive'])->name('exams.grade');
    Route::post('/exam-bank', [\App\Http\Controllers\Teacher\ExamBuilderController::class, 'saveToBank'])->name('exams.bank.store');
    Route::delete('/exam-bank/{examQuestion}', [\App\Http\Controllers\Teacher\ExamBuilderController::class, 'deleteFromBank'])->name('exams.bank.destroy');
    // تنظیم مرحله‌ها (سطوح پیشرفت)
    Route::get('/levels', [\App\Http\Controllers\Teacher\LevelSettingsController::class, 'edit'])->name('levels');
    Route::post('/levels', [\App\Http\Controllers\Teacher\LevelSettingsController::class, 'update'])->name('levels.update');

    // آزمایشگاه هوشمند آزمون (آزمایشی — پشتِ Feature Flag)
    Route::middleware('smartlab')->prefix('smart-exams')->name('smart.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Teacher\SmartExamController::class, 'lab'])->name('lab');
        Route::get('/{smartExam}/edit', [\App\Http\Controllers\Teacher\SmartExamController::class, 'edit'])->name('edit');
        Route::post('/', [\App\Http\Controllers\Teacher\SmartExamController::class, 'store'])->name('store');
        Route::put('/{smartExam}', [\App\Http\Controllers\Teacher\SmartExamController::class, 'update'])->name('update');
        Route::post('/{smartExam}/status', [\App\Http\Controllers\Teacher\SmartExamController::class, 'status'])->name('status');
        Route::delete('/{smartExam}', [\App\Http\Controllers\Teacher\SmartExamController::class, 'destroy'])->name('destroy');
        Route::get('/{smartExam}/report', [\App\Http\Controllers\Teacher\SmartExamController::class, 'report'])->name('report');
        Route::post('/{smartExam}/build-game', [\App\Http\Controllers\Teacher\SmartExamController::class, 'buildGame'])->name('buildgame');
        Route::post('/ai/generate', [\App\Http\Controllers\Teacher\SmartExamController::class, 'aiGenerate'])->name('ai');
        Route::get('/bank/list', [\App\Http\Controllers\Teacher\SmartExamController::class, 'bank'])->name('bank');
        Route::post('/bank', [\App\Http\Controllers\Teacher\SmartExamController::class, 'bankStore'])->name('bank.store');
        Route::delete('/bank/{question}', [\App\Http\Controllers\Teacher\SmartExamController::class, 'bankDestroy'])->name('bank.destroy');
    });
    // استودیوی ساخت بازی (دنیای بازی‌های آموزشی)
    Route::get('/studio', [\App\Http\Controllers\Teacher\EduGameController::class, 'index'])->name('studio');
    Route::post('/studio/ai', [\App\Http\Controllers\Teacher\EduGameController::class, 'aiGenerate'])->name('studio.ai');
    Route::get('/studio/bank', [\App\Http\Controllers\Teacher\EduGameController::class, 'bankQuestions'])->name('studio.bank');
    Route::get('/studio/{eduGame}/edit', [\App\Http\Controllers\Teacher\EduGameController::class, 'show'])->name('studio.edit');
    Route::post('/studio', [\App\Http\Controllers\Teacher\EduGameController::class, 'store'])->name('studio.store');
    Route::put('/studio/{eduGame}', [\App\Http\Controllers\Teacher\EduGameController::class, 'update'])->name('studio.update');
    Route::post('/studio/{eduGame}/status', [\App\Http\Controllers\Teacher\EduGameController::class, 'status'])->name('studio.status');
    Route::post('/studio/{eduGame}/duplicate', [\App\Http\Controllers\Teacher\EduGameController::class, 'duplicate'])->name('studio.duplicate');
    Route::delete('/studio/{eduGame}', [\App\Http\Controllers\Teacher\EduGameController::class, 'destroy'])->name('studio.destroy');
    Route::get('/studio/{eduGame}/report', [\App\Http\Controllers\Teacher\EduGameController::class, 'report'])->name('studio.report');
});

/* ---------------- مشترک ---------------- */
Route::middleware('auth')->group(function () {
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');

    // کارتابل اعلان‌ها/پیام‌ها (معلم و دانش‌آموز)
    Route::get('/notices', \App\Http\Controllers\NoticeController::class)->name('notices');

    // بانک سؤالاتِ حرفه‌ای — ادمین کل (کلِ بانک) + مدیر مدرسه (بانکِ مدرسه)
    Route::middleware('role:super_admin|school_admin')->prefix('question-bank')->name('bank.')->group(function () {
        Route::get('/', [\App\Http\Controllers\QuestionBankController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\QuestionBankController::class, 'store'])->name('store');
        Route::put('/{question}', [\App\Http\Controllers\QuestionBankController::class, 'update'])->name('update');
        Route::delete('/{question}', [\App\Http\Controllers\QuestionBankController::class, 'destroy'])->name('destroy');
        Route::post('/ai', [\App\Http\Controllers\QuestionBankController::class, 'ai'])->name('ai');
        Route::post('/share', [\App\Http\Controllers\QuestionBankController::class, 'share'])->name('share');
    });

    // مدیریت کاربران و کلاس‌ها (کنترل دسترسی نقش‌محور داخل کنترلر)
    Route::put('/manage/users/{user}', [\App\Http\Controllers\ManagementController::class, 'updateUser'])->name('manage.users.update');
    Route::delete('/manage/users/{user}', [\App\Http\Controllers\ManagementController::class, 'destroyUser'])->name('manage.users.destroy');
    Route::put('/manage/classrooms/{classroom}', [\App\Http\Controllers\ManagementController::class, 'updateClassroom'])->name('manage.classrooms.update');
    Route::delete('/manage/classrooms/{classroom}', [\App\Http\Controllers\ManagementController::class, 'destroyClassroom'])->name('manage.classrooms.destroy');
    Route::post('/manage/users/{user}/move', [\App\Http\Controllers\ManagementController::class, 'moveStudent'])->name('manage.users.move');
    Route::post('/manage/classrooms/{classroom}/teacher', [\App\Http\Controllers\ManagementController::class, 'reassignTeacher'])->name('manage.classrooms.teacher');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // تغییر اجباری رمز در اولین ورود
    Route::get('/force-password', [\App\Http\Controllers\ForcePasswordController::class, 'show'])->name('password.force');
    Route::post('/force-password', [\App\Http\Controllers\ForcePasswordController::class, 'update'])->name('password.force.update');
});

require __DIR__.'/auth.php';
