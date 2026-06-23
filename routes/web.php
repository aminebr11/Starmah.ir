<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\SchoolApprovalController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RegistrationController;
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
    Route::get('/schools', [SchoolApprovalController::class, 'index'])->name('schools');
    Route::post('/schools/requests/{schoolRequest}/approve', [SchoolApprovalController::class, 'approve'])->name('schools.approve');
    Route::post('/schools/requests/{schoolRequest}/reject', [SchoolApprovalController::class, 'reject'])->name('schools.reject');
});

/* ---------------- مدیر مدرسه ---------------- */
Route::middleware(['auth', 'role:school_admin'])->prefix('school')->name('school.')->group(function () {
    Route::get('/teachers', [SchoolTeacherController::class, 'index'])->name('teachers');
    Route::post('/teachers', [SchoolTeacherController::class, 'store'])->name('teachers.store');
});

/* ---------------- دانش‌آموز ---------------- */
Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('/world', [ThemeController::class, 'index'])->name('world.choose');
    Route::post('/world', [ThemeController::class, 'update'])->name('world.update');

    Route::get('/practice/{skill?}', [PracticeController::class, 'start'])->name('practice.start');
    Route::post('/practice/submit', [PracticeController::class, 'submit'])->name('practice.submit');

    Route::get('/progress', ProgressController::class)->name('progress');
    Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
});

/* ---------------- معلم ---------------- */
Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/', [TeacherDashboardController::class, 'index'])->name('dashboard');
    Route::get('/class/{classroom}', [TeacherDashboardController::class, 'show'])->name('classroom');
    Route::get('/assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
    Route::post('/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    Route::post('/discipline', [DisciplineController::class, 'store'])->name('discipline.store');
});

/* ---------------- مدیر مدرسه / سوپرادمین ---------------- */
Route::middleware(['auth', 'role:school_admin|super_admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'overview'])->name('admin.overview');
});

/* ---------------- مشترک ---------------- */
Route::middleware('auth')->group(function () {
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
