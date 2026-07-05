<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EnrolmentController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\LecturerController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SectionAssignmentController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes (Bearer Token Required)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Users
    Route::patch('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
    Route::put('users/{id}/password', [UserController::class, 'updatePassword']);
    Route::patch('users/{id}/toggle', [UserController::class, 'toggleStatus']);
    Route::apiResource('users', UserController::class);

    // Subjects
    Route::patch('subjects/{id}/toggle', [SubjectController::class, 'toggleStatus']);
    Route::apiResource('subjects', SubjectController::class);

    // Sections
    Route::get('sections/{id}/students', [SectionController::class, 'students']);
    Route::patch('sections/{id}/toggle', [SectionController::class, 'toggleStatus']);
    Route::apiResource('sections', SectionController::class);

    // Lecturers
    Route::apiResource('lecturers', LecturerController::class);

    // Students
    Route::get('/sections/{sectionId}/attendance-summary', [AttendanceController::class, 'getSectionAttendanceSummary']);
    Route::get('/students/{studentId}/attendance-report/{sectionId}', [AttendanceController::class, 'getStudentAttendanceReport']);
    Route::apiResource('students', StudentController::class);

    // Guardians
    Route::delete('guardians/{guardianId}/detach-student/{studentId}', [GuardianController::class, 'detachStudent']);
    Route::post('guardians/{guardianId}/attach-student', [GuardianController::class, 'attachStudent']);
    Route::apiResource('guardians', GuardianController::class);

    // Section Assignments
    Route::patch('section-assignments/{id}/toggle', [SectionAssignmentController::class, 'toggleStatus']);
    Route::apiResource('section-assignments', SectionAssignmentController::class);

    // Timetables
    Route::get('timetables/attendance-sheet', [TimetableController::class, 'getTimetableWithAttendanceByDate']);
    Route::patch('timetables/{id}/toggle', [TimetableController::class, 'toggleStatus']);
    Route::apiResource('timetables', TimetableController::class);

    // Enrolments
    Route::patch('enrolments/{id}/toggle', [EnrolmentController::class, 'toggleStatus']);
    Route::apiResource('enrolments', EnrolmentController::class);

    // Attendances
    Route::post('/attendances/refresh', [AttendanceController::class, 'refreshAbsences']);
    Route::patch('attendances/{id}/toggle', [AttendanceController::class, 'toggleStatus']);
    Route::apiResource('attendances', AttendanceController::class);
});
