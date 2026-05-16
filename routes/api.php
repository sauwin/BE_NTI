<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\FaqItemController;
use App\Http\Controllers\MentorshipController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\MentorProfileController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
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
Route::get('/faq-items', [FaqItemController::class, 'index']);

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

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/auth/role-status', [AuthController::class, 'roleStatus']);

    // Documents
    Route::post('/documents/upload', [DocumentController::class, 'upload']);

    // Applications
    Route::post('/applications', [ApplicationController::class, 'store']);
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::get('/applications/{id}', [ApplicationController::class, 'show']);
    Route::patch('/applications/{id}', [ApplicationController::class, 'update']);
    Route::delete('/applications/{id}', [ApplicationController::class, 'destroy']);
    Route::patch('/applications/{id}/status', [ApplicationController::class, 'updateStatus']);
    Route::get('/applications/{id}/documents', [ApplicationController::class, 'documents']);

    Route::post('/profile/student', [StudentProfileController::class, 'store']);
    Route::get('/profile/student', [StudentProfileController::class, 'show']);

    // Mentorships
    Route::post('/mentorships', [MentorshipController::class, 'assign']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Drafts
    Route::post('/drafts', [DraftController::class, 'store']);
    Route::get('/drafts/{program_type}', [DraftController::class, 'show']);

    // Profiles
    Route::get('/profile', [StudentProfileController::class, 'show']);
    Route::put('/profile', [StudentProfileController::class, 'update']);
    Route::get('/mentor-profile', [MentorProfileController::class, 'show']);
    Route::put('/mentor-profile', [MentorProfileController::class, 'update']);
    Route::get('/company-profile', [OrganizationController::class, 'show']);
    Route::put('/company-profile', [OrganizationController::class, 'update']);

    // FAQ items
    Route::post('/faq-items', [FaqItemController::class, 'store']);
    Route::put('/faq-items/{faqItem}', [FaqItemController::class, 'update']);
    Route::delete('/faq-items/{faqItem}', [FaqItemController::class, 'destroy']);

    // Admin (nti_admin can see users/approvals)
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::get('/admin/users/{id}', [AdminController::class, 'showUser']);
    Route::get('/admin/approvals', [AdminController::class, 'pendingApprovals']);
    Route::post('/admin/approve/{userId}', [AdminController::class, 'approveRole']);
    Route::post('/admin/block/{userId}', [AdminController::class, 'blockUser']);
    Route::post('/admin/users/{userId}/roles', [AdminController::class, 'assignRole']);
    Route::delete('/admin/users/{userId}/roles', [AdminController::class, 'removeRole']);

    // Super Admin Only
    Route::middleware('super_admin')->group(function () {
        Route::post('/admin/create-admin', [AdminController::class, 'createAdmin']);
        Route::delete('/admin/users/{userId}', [AdminController::class, 'deleteUser']);
    });
});