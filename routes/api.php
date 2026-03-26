<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LessonLiveController;
use App\Http\Controllers\Manager\ClassController;
use App\Http\Controllers\Manager\AttendanceAnalyticsController;
use App\Http\Controllers\Manager\FeeController;
use App\Http\Controllers\Manager\GuidanceController;
use App\Http\Controllers\Manager\ExamPeriodController;
use App\Http\Controllers\Manager\LessonSummaryController;
use App\Http\Controllers\Manager\LevelController;
use App\Http\Controllers\Manager\PapersWorkController;
use App\Http\Controllers\Manager\QuizController;
use App\Http\Controllers\Manager\ReportController as ManagerReportController;
use App\Http\Controllers\Manager\SettingController;
use App\Http\Controllers\Manager\TeacherTrackingController;
use App\Http\Controllers\Manager\PaymentController;
use App\Http\Controllers\Manager\ResultController;
use App\Http\Controllers\Manager\StudentController;
use App\Http\Controllers\Manager\SubjectController;
use App\Http\Controllers\Manager\TeacherController;
use App\Http\Controllers\Manager\TeacherTimeTableController;
use App\Http\Controllers\Manager\TrackTeacherController;
use App\Http\Controllers\Student\ActivityController;
use App\Http\Controllers\Student\AttendanceController as StudentAttendanceController;
use App\Http\Controllers\Student\LessonController as StudentLessonController;
use App\Http\Controllers\Student\MonthlyTestController as StudentMonthlyTestController;
use App\Http\Controllers\Student\PapersController;
use App\Http\Controllers\Student\GuidanceController as StudentGuidanceController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\ResultController as StudentResultController;
use App\Http\Controllers\Student\SubjectController as StudentSubjectController;
use App\Http\Controllers\Student\TimeTableController as StudentTimeTableController;
use App\Http\Controllers\Teacher\AttendanceController;
use App\Http\Controllers\Teacher\LessonController as TeacherLessonController;
use App\Http\Controllers\Teacher\LiveStreamController;
use App\Http\Controllers\Teacher\MarkController;
use App\Http\Controllers\Teacher\MonthlyTestController;
use App\Http\Controllers\Teacher\StudentController as TeacherStudentController;
use App\Http\Controllers\Teacher\SubjectController as TeacherSubjectController;
use App\Http\Controllers\Teacher\ReportController as TeacherReportController;
use App\Http\Controllers\Teacher\PapersWorkController as TeacherPapersWorkController;
use App\Http\Controllers\Teacher\TimeTableController;
use App\Http\Controllers\Teacher\ProfileController as TeacherProfileController;
use App\Http\Controllers\Streaming\CloudflareWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/manager/students/public', [StudentController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::get('/manager/teachers/public', [TeacherController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::get('/manager/lesson-summaries', [LessonSummaryController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::get('/manager/quizzes', [QuizController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::get('/manager/papers-work', [PapersWorkController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::get('/manager/reports', [ManagerReportController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::get('/manager/teacher-tracking', [TeacherTrackingController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::get('/manager/settings', [SettingController::class, 'show'])->withoutMiddleware('auth:sanctum');
Route::get('/manager/attendance/daily-summary', [AttendanceAnalyticsController::class, 'dailySummary'])->withoutMiddleware('auth:sanctum');
Route::get('/manager/payments/analytics', [PaymentController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::post('/manager/settings', [SettingController::class, 'store'])->withoutMiddleware('auth:sanctum');

Route::apiResource('manager/levels', LevelController::class)->only(['index', 'store', 'update', 'destroy'])->withoutMiddleware('auth:sanctum');
Route::apiResource('manager/subjects', SubjectController::class)->only(['index', 'store', 'update', 'destroy'])->withoutMiddleware('auth:sanctum');
Route::apiResource('manager/fees', FeeController::class)->only(['index', 'store', 'update'])->withoutMiddleware('auth:sanctum');
Route::apiResource('manager/students', StudentController::class)->only(['index', 'store', 'update', 'destroy'])->withoutMiddleware('auth:sanctum');
Route::apiResource('manager/teachers', TeacherController::class)->only(['index', 'store', 'update', 'destroy'])->withoutMiddleware('auth:sanctum');
Route::apiResource('manager/teacher-time-table', TeacherTimeTableController::class)->only(['index', 'store', 'update'])->withoutMiddleware('auth:sanctum');
Route::apiResource('manager/guidance', GuidanceController::class)->only(['index', 'store', 'destroy'])->withoutMiddleware('auth:sanctum');
Route::apiResource('manager/exams-period', ExamPeriodController::class)->only(['index', 'store'])->withoutMiddleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('manager')->group(function () {
        Route::apiResource('teachers', TeacherController::class)->except(['index', 'store', 'update', 'destroy']);
        Route::apiResource('students', StudentController::class)->except(['index', 'store', 'update', 'destroy']);
        Route::apiResource('classes', ClassController::class);
        Route::apiResource('fees', FeeController::class)->except(['index', 'store', 'update']);
        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('track-teachers', [TrackTeacherController::class, 'index']);
        Route::get('results', [ResultController::class, 'index']);
    });

    Route::prefix('teacher')->group(function () {
        Route::get('profile', [TeacherProfileController::class, 'show']);
        Route::post('lessons/{lesson}/start-live-youtube', [LessonLiveController::class, 'startLive']);
        Route::get('lessons/{lesson}/obs', [TeacherLessonController::class, 'obs']);
        Route::get('subjects', [TeacherSubjectController::class, 'index']);
        Route::get('subjects/{subject}', [TeacherSubjectController::class, 'show']);
        Route::get('students', [TeacherStudentController::class, 'index']);
        Route::get('papers-work', [TeacherPapersWorkController::class, 'index']);
        Route::post('papers-work', [TeacherPapersWorkController::class, 'store']);
        Route::post('reports', [TeacherReportController::class, 'store']);
        Route::get('timetable', [TimeTableController::class, 'index']);
        Route::apiResource('lessons', TeacherLessonController::class);
        Route::post('lessons/{lesson}/start-live', [LiveStreamController::class, 'start']);
        Route::get('lessons/{lesson}/insights', [TeacherLessonController::class, 'insights']);
        Route::get('attendance/summary', [AttendanceController::class, 'summary']);
        Route::apiResource('attendance', AttendanceController::class)->only(['index', 'store']);
        Route::apiResource('marks', MarkController::class);
        Route::apiResource('monthly-tests', MonthlyTestController::class);
    });

    Route::prefix('student')->group(function () {
        Route::get('profile', [StudentProfileController::class, 'show']);
        Route::post('lessons/{lesson}/attendance', [StudentAttendanceController::class, 'store']);
        Route::get('attendance/overall', [StudentAttendanceController::class, 'overall']);
        Route::get('timetable', [StudentTimeTableController::class, 'index']);
        Route::get('subjects', [StudentSubjectController::class, 'index']);
        Route::get('subjects/{subject}', [StudentSubjectController::class, 'show']);
        Route::get('lessons', [StudentLessonController::class, 'index']);
        Route::get('lessons/{lesson}', [StudentLessonController::class, 'show']);
        Route::get('papers', [PapersController::class, 'index']);
        Route::get('papers/{paper}', [PapersController::class, 'show']);
        Route::apiResource('monthly-tests', StudentMonthlyTestController::class)->only(['index', 'show', 'store']);
        Route::get('activities', [ActivityController::class, 'index']);
        Route::get('guidance', [StudentGuidanceController::class, 'index']);
        Route::get('results', [StudentResultController::class, 'index']);
    });
});

Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/auth/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');

Route::post('/cloudflare/webhook', [CloudflareWebhookController::class, 'handle']);
