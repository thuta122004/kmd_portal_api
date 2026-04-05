<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SubjectController;
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
});
