<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\MentorshipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\StudentProfileController;
use App\Mail\RegistrationSubmit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::apiResource('articles', ArticleController::class);

Route::get('/calls/active/{program_type}', [CallController::class, 'active']);

// Do not "optimize import" here, it breaks verification
Route::get('/email/continueRegistration/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = User::findOrFail($id);
    if (! hash_equals(sha1($user->email), $hash)) {
        return response()->json(['message' => 'Invalid verification link'], 403);
    }
    if (! $request->hasValidSignature()) {
        return response()->json(['message' => 'Link expired'], 403);
    }
    $user->update(['email_verified_at' => now(), 'status' => 'active']);

    return redirect('http://localhost:5173/verified');
})->name('verification.verify');

Route::post('/email/resend', function (Request $request) {
    $user = $request->user();
    if ($user->email_verified_at) {
        return response()->json(['message' => 'Already verified'], 400);
    }
    Mail::to($user->email)->send(new RegistrationSubmit($user));

    return response()->json(['message' => 'Verification email sent']);
})->middleware(['auth:sanctum', 'throttle:3,1']);

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::post('/documents/upload', [DocumentController::class, 'upload']);

    Route::post('/applications', [ApplicationController::class, 'store']);
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::get('/applications/{id}', [ApplicationController::class, 'show']);
    Route::patch('/applications/{id}', [ApplicationController::class, 'update']);
    Route::delete('/applications/{id}', [ApplicationController::class, 'destroy']);
    Route::patch('/applications/{id}/status', [ApplicationController::class, 'updateStatus']);
    Route::get('/applications/{id}/documents', [ApplicationController::class, 'documents']);

    Route::post('/profile/student', [StudentProfileController::class, 'store']);
    Route::get('/profile/student', [StudentProfileController::class, 'show']);

    Route::post('/mentorships', [MentorshipController::class, 'assign']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::post('/drafts', [DraftController::class, 'store']);
    Route::get('/drafts/{program_type}', [DraftController::class, 'show']);

    Route::get('/onboarding/status', [OnboardingController::class, 'status']);
    Route::post('/onboarding', [OnboardingController::class, 'store']);
});
