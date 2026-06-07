<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LecturerController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SectionAssignmentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TimetableController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes (Bearer Token Required)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Subjects
    Route::patch('subjects/{id}/toggle', [SubjectController::class, 'toggleStatus']);
    Route::apiResource('subjects', SubjectController::class);

    // Sections
    Route::patch('sections/{id}/toggle', [SectionController::class, 'toggleStatus']);
    Route::apiResource('sections', SectionController::class);

    // Lecturers
    Route::patch('lecturers/{id}/toggle', [LecturerController::class, 'toggleStatus']);
    Route::apiResource('lecturers', LecturerController::class);

    // Section Assignments
    Route::patch('section-assignments/{id}/toggle', [SectionAssignmentController::class, 'toggleStatus']);
    Route::apiResource('section-assignments', SectionAssignmentController::class);

    // Timetables
    Route::patch('timetables/{id}/toggle', [TimetableController::class, 'toggleStatus']);
    Route::apiResource('timetables', TimetableController::class);
});
