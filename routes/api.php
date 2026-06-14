<?php

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
    Route::patch('lecturers/{id}/toggle', [LecturerController::class, 'toggleStatus']);
    Route::apiResource('lecturers', LecturerController::class);

    // Students
    Route::patch('students/{id}/toggle', [StudentController::class, 'toggleStatus']);
    Route::apiResource('students', StudentController::class);

    // Guardians
    Route::post('guardians/{guardianId}/attach-student', [GuardianController::class, 'attachStudent']);
    Route::patch('guardians/{id}/toggle', [GuardianController::class, 'toggleStatus']);
    Route::apiResource('guardians', GuardianController::class);

    // Section Assignments
    Route::patch('section-assignments/{id}/toggle', [SectionAssignmentController::class, 'toggleStatus']);
    Route::apiResource('section-assignments', SectionAssignmentController::class);

    // Timetables
    Route::patch('timetables/{id}/toggle', [TimetableController::class, 'toggleStatus']);
    Route::apiResource('timetables', TimetableController::class);

    // Enrolments
    Route::patch('enrolments/{id}/toggle', [EnrolmentController::class, 'toggleStatus']);
    Route::apiResource('enrolments', EnrolmentController::class);
});
