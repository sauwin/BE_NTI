<?php

use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ReportingController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\CallOrganizationController;
use App\Http\Controllers\OrganizationMembershipController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\FaqItemController;
use App\Http\Controllers\MentorProfileController;
use App\Http\Controllers\MentorshipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\StudentProfileController;
use App\Mail\RegistrationSubmit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,15');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,15');

Route::apiResource('articles', ArticleController::class);

Route::get('/calls/active/{program_type?}', [CallController::class, 'active']);
Route::get('/faq-items', [FaqItemController::class, 'index']);
Route::get('/programs/b/tasks', [CallOrganizationController::class, 'publicTasks']);

Route::get('/email/continueRegistration/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = User::findOrFail($id);
    if (! hash_equals(sha1($user->email), $hash)) {
        return response()->json(['message' => 'Invalid verification link'], 403);
    }
    if (! $request->hasValidSignature()) {
        return response()->json(['message' => 'Link expired'], 403);
    }

    $superAdmin = User::join('user_roles', 'users.id', '=', 'user_roles.user_id')
        ->join('roles', 'user_roles.role_id', '=', 'roles.id')
        ->where('roles.slug', 'super_admin')
        ->select('users.id')
        ->first();

    if (! $superAdmin) {
        return response()->json(['message' => 'System error: no super admin found'], 500);
    }

    DB::transaction(function () use ($user, $superAdmin) {
        $user->update(['email_verified_at' => now(), 'status' => 'active']);
        DB::table('user_roles')
            ->where('user_id', $user->id)
            ->update([
                'granted_by' => $superAdmin->id,
                'granted_at' => now(),
            ]);
    });

    return redirect('http://localhost:5173/verified');
})->name('verification.verify');

Route::post('/email/resend', function (Request $request) {
    $user = $request->user();
    if ($user->email_verified_at) {
        return response()->json(['message' => 'Already verified'], 400);
    }
    Mail::to($user->email)->queue(new RegistrationSubmit($user));

    return response()->json(['message' => 'Verification email sent']);
})->middleware(['auth:sanctum', 'throttle:3,1']);

Route::post('/auth/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:3,15');
Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:3,15');
Route::post('/auth/verify-reset-token', [PasswordResetController::class, 'verify'])->middleware('throttle:5,15');

// Authenticated
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/auth/role-status', [AuthController::class, 'roleStatus']);

    // Documents
    Route::post('/documents/upload', [DocumentController::class, 'upload']);
    Route::get('/documents/{id}/download', [DocumentController::class, 'download']);
    Route::get('/documents/{id}/preview', [DocumentController::class, 'preview']);

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
    Route::get('/admin/logs', [AdminController::class, 'logs']);
    Route::post('/admin/approve/{userId}', [AdminController::class, 'approveRole']);
    Route::post('/admin/block/{userId}', [AdminController::class, 'blockUser']);
    Route::post('/admin/unblock/{userId}', [AdminController::class, 'unblockUser']);
    Route::post('/admin/users/{userId}/roles', [AdminController::class, 'assignRole']);
    Route::delete('/admin/users/{userId}/roles', [AdminController::class, 'removeRole']);

    // Роути для фірми (керування власними завданнями/викликами)
    Route::get('/company/tasks', [CallOrganizationController::class, 'index']);
    Route::get('/company/{companyId}/tasks', [CallOrganizationController::class, 'byOrganization']);
    Route::post('/company/tasks', [CallOrganizationController::class, 'store']);
    Route::put('/company/tasks/{id}', [CallOrganizationController::class, 'update']);
    Route::delete('/company/tasks/{id}', [CallOrganizationController::class, 'destroy']);

    // Company members
    Route::get('/company/members/pending', [OrganizationMembershipController::class, 'pendingMembers']);
    Route::post('/company/members/{userId}/approve', [OrganizationMembershipController::class, 'approveMember']);
    Route::post('/company/members/{userId}/reject', [OrganizationMembershipController::class, 'rejectMember']);

    // Super Admin Only
    Route::middleware('super_admin')->group(function () {
        Route::post('/admin/create-admin', [AdminController::class, 'createAdmin']);
        Route::delete('/admin/users/{userId}', [AdminController::class, 'deleteUser']);

        Route::post('/admin/users/{userId}/reset-password', [AdminController::class, 'resetAdminPassword']);
    });

    // Admin Only
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/programs', [ProgramController::class, 'index']);
        Route::post('/programs', [ProgramController::class, 'store']);
        Route::get('/programs/{program}', [ProgramController::class, 'show']);
        Route::put('/programs/{program}', [ProgramController::class, 'update']);
        Route::delete('/programs/{program}', [ProgramController::class, 'destroy']);

        Route::get('/documents', [DocumentController::class, 'index']);

        Route::get('/calls', [CallController::class, 'index']);
        Route::post('/calls', [CallController::class, 'store']);
        Route::get('/calls/{id}', [CallController::class, 'show']);
        Route::put('/calls/{id}', [CallController::class, 'update']);
        Route::delete('/calls/{id}', [CallController::class, 'destroy']);
        Route::patch('/calls/{id}/status', [CallController::class, 'updateStatus']);
        Route::get('/reporting/dashboard-stats', [ReportingController::class, 'dashboardStats']);
    });
});
