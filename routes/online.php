<?php

use App\Http\Controllers\Online\BookController;
use App\Http\Controllers\Online\CheckController;
use App\Http\Controllers\Online\ManageController;
use App\Http\Controllers\Online\RoomController;
use Illuminate\Support\Facades\Route;

/*
| «🎥 کلاسِ آنلاین» — شبیهِ اسکای‌روم.
| ادمینِ کل: دسترسیِ مدرسه‌ها و سرورِ رسانه · مدیرِ مدرسه: اجازه‌ی معلم‌ها، نظارت و پایانِ کلاس
| معلم: ساخت و برگزاری · دانش‌آموز: ورود · اتاق: یک صفحه برای همه با نقشِ تعیین‌شده توسطِ سرور
*/

Route::middleware(['auth', 'role:super_admin'])->prefix('admin/online')->name('admin.')->group(function () {
    Route::get('/', [ManageController::class, 'admin'])->name('online');
    Route::put('/settings', [ManageController::class, 'adminSettings'])->name('online.settings');
    Route::post('/test-convert', [ManageController::class, 'adminTestConvert'])->name('online.test-convert');
    Route::put('/schools/{school}', [ManageController::class, 'adminSchool'])->name('online.school');
});

Route::middleware(['auth', 'role:school_admin'])->prefix('school/online')->name('school.')->group(function () {
    Route::get('/', [ManageController::class, 'school'])->name('online');
    Route::post('/grant', [ManageController::class, 'grant'])->name('online.grant');
});

Route::middleware(['auth', 'role:teacher'])->prefix('teacher/online')->name('teacher.')->group(function () {
    Route::get('/', [ManageController::class, 'teacher'])->name('online');
    Route::post('/', [ManageController::class, 'store'])->name('online.store');
    Route::put('/{room}', [ManageController::class, 'update'])->name('online.update');
    Route::delete('/{room}', [ManageController::class, 'destroy'])->name('online.destroy');
});

Route::middleware(['auth', 'role:student'])->get('/online', [ManageController::class, 'student'])->name('online.list');

// آزمایشِ اتصال روی همین دستگاه (برای همه‌ی نقش‌ها)
Route::middleware(['auth'])->group(function () {
    Route::get('/online-check', [CheckController::class, 'show'])->name('online.check');
    Route::post('/online-check/server', [CheckController::class, 'server'])->middleware('throttle:online-act')->name('online.check.server');
});

Route::middleware(['auth'])->prefix('online/{room}')->name('online.')->group(function () {
    Route::get('/', [RoomController::class, 'show'])->name('room');
    Route::post('/join', [RoomController::class, 'join'])->middleware('throttle:online-act')->name('join');
    Route::post('/sync', [RoomController::class, 'sync'])->middleware('throttle:online-sync')->name('sync');
    Route::post('/leave', [RoomController::class, 'leave'])->name('leave');
    Route::post('/signal', [RoomController::class, 'signal'])->middleware('throttle:online-signal')->name('signal');
    Route::post('/chat', [RoomController::class, 'chat'])->middleware('throttle:online-chat')->name('chat');
    Route::delete('/chat/{message}', [RoomController::class, 'hideMessage'])->name('chat.hide');
    Route::post('/hand', [RoomController::class, 'hand'])->middleware('throttle:online-act')->name('hand');
    Route::post('/control', [RoomController::class, 'control'])->middleware('throttle:online-act')->name('control');
    Route::get('/board', [RoomController::class, 'boardGet'])->name('board');
    Route::post('/board-op', [RoomController::class, 'boardOp'])->middleware('throttle:online-act')->name('board.op');
    Route::post('/board', [RoomController::class, 'boardSave'])->middleware('throttle:online-act')->name('board.save');
    Route::post('/convert', [RoomController::class, 'convert'])->middleware('throttle:online-act')->name('convert');
    // 📚 کتاب‌های درسیِ معلم
    Route::get('/books', [BookController::class, 'index'])->name('books');
    Route::post('/books/upload', [BookController::class, 'upload'])->middleware('throttle:online-act')->name('books.upload');
    Route::get('/books/{book}/file', [BookController::class, 'file'])->name('book.file');
    Route::post('/books/{book}/open', [BookController::class, 'open'])->middleware('throttle:online-act')->name('book.open');
    Route::post('/books/{book}/image', [BookController::class, 'image'])->middleware('throttle:online-act')->name('book.image');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('book.destroy');
    Route::get('/book-img/{book}/{page}', [BookController::class, 'pageImage'])->whereNumber('page')->name('book.img');
    Route::post('/asset', [RoomController::class, 'assetUpload'])->middleware('throttle:online-act')->name('asset.upload');
    Route::get('/asset/{name}', [RoomController::class, 'asset'])->name('asset');
    Route::post('/poll', [RoomController::class, 'pollCreate'])->name('poll');
    Route::post('/poll/{poll}/answer', [RoomController::class, 'pollAnswer'])->name('poll.answer');
    Route::post('/poll/{poll}/close', [RoomController::class, 'pollClose'])->name('poll.close');
    Route::post('/record', [RoomController::class, 'recordChunk'])->middleware('throttle:online-act')->name('record');
    Route::post('/record/finish', [RoomController::class, 'recordFinish'])->name('record.finish');
    Route::get('/recording', [RoomController::class, 'recording'])->name('recording');
    Route::get('/token', [RoomController::class, 'token'])->name('token');
    Route::get('/report', [RoomController::class, 'report'])->name('report');
    Route::post('/recording-share', [ManageController::class, 'recording'])->name('recording.share');
    Route::post('/end', [ManageController::class, 'endRoom'])->name('end');
});
