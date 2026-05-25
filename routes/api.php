<?php
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\CallEvaluatorController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\FaqItemController;
use App\Http\Controllers\MentorProfileController;
use App\Http\Controllers\MentorshipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationMembershipController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ReportingController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\CallTaskController;
use App\Mail\RegistrationSubmit;
use App\Http\Controllers\Admin\BulkNotificationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:3,15');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,15');

Route::apiResource('articles', ArticleController::class);

Route::get('/calls/active/{program_type?}', [CallController::class, 'active']);
Route::get('/faq-items', [FaqItemController::class, 'index']);
Route::get('/programs/b/tasks', [TaskController::class, 'publicTasks']);

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

    //Programs
    Route::get('/programs', [ProgramController::class, 'index']);

    //Tasks
    Route::get('/tasks/{id}', [TaskController::class, 'show']);

    //Сalls
    Route::get('/calls/{id}', [CallController::class, 'show']);

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/auth/role-status', [AuthController::class, 'roleStatus']);

    // Teams
    Route::apiResource('teams', TeamController::class);
    Route::post('teams/{team}/invite', [TeamController::class, 'invite'])->middleware('throttle:10,1');
    Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
    Route::get('user/invitations', [TeamController::class, 'myInvitations']);
    Route::post('/teams/{team}/invitation/respond', [TeamController::class, 'respondToInvitation']);

    // Documents
    Route::post('/documents/upload', [DocumentController::class, 'upload'])->middleware('throttle:20,1');
    Route::get('/documents/{id}/download', [DocumentController::class, 'download']);
    Route::get('/documents/{id}/preview', [DocumentController::class, 'preview']);

    Route::delete('/applications/{applicationId}/documents/{type}', [DocumentController::class, 'deleteApplicationDocument']);
    Route::delete('/tasks/{taskId}/documents/{type}', [DocumentController::class, 'deleteTaskDocument']);

    // Applications
    Route::post('/applications', [ApplicationController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::get('/applications/{id}', [ApplicationController::class, 'show']);
    Route::patch('/applications/{id}', [ApplicationController::class, 'update'])->middleware('throttle:10,1');
    Route::delete('/applications/{id}', [ApplicationController::class, 'destroy'])->middleware('throttle:10,1');
    Route::patch('/applications/{id}/status', [ApplicationController::class, 'updateStatus'])->middleware('throttle:10,1');
    Route::get('/applications/{id}/documents', [ApplicationController::class, 'documents']);

    // Milestones
    Route::get('/applications/{id}/milestones', [MilestoneController::class, 'index']);
    Route::post('/applications/{id}/milestones', [MilestoneController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/milestones/{id}', [MilestoneController::class, 'show']);
    Route::patch('/milestones/{id}', [MilestoneController::class, 'update'])->middleware('throttle:10,1');
    Route::post('/milestones/{id}/documents', [MilestoneController::class, 'uploadDocument'])->middleware('throttle:20,1');

    // Evaluations
    Route::get('/evaluations', [EvaluationController::class, 'index'])->middleware('throttle:30,1');
    Route::post('/evaluations', [EvaluationController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/evaluations/{id}', [EvaluationController::class, 'show']);
    Route::patch('/evaluations/{id}', [EvaluationController::class, 'update'])->middleware('throttle:10,1');

    Route::post('/profile/student', [StudentProfileController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/profile/student', [StudentProfileController::class, 'show']);

    // Mentorships
    Route::get('/mentorships', [MentorshipController::class, 'index'])->middleware('throttle:10,1');
    Route::get('/mentorships/{id}', [MentorshipController::class, 'show'])->middleware('throttle:10,1');
    Route::post('/mentorships/{id}/consultations', [MentorshipController::class, 'logConsultation'])->middleware('throttle:10,1');
    Route::get('/mentorships/{id}/consultations', [MentorshipController::class, 'consultations'])->middleware('throttle:10,1');
    Route::post('/mentorships/assign', [MentorshipController::class, 'assign'])->middleware('throttle:10,1');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // GDPR
    Route::post('/gdpr/consent', [GdprController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/gdpr/consents', [GdprController::class, 'index']);
    Route::post('/gdpr/export', [GdprController::class, 'export'])->middleware('throttle:3,60');
    Route::delete('/gdpr/account', [GdprController::class, 'anonymize'])->middleware('throttle:1,60');

    // Drafts
    Route::post('/drafts', [DraftController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/drafts/{program_type}', [DraftController::class, 'show']);

    // Profiles
    Route::get('/profile', [StudentProfileController::class, 'show']);
    Route::put('/profile', [StudentProfileController::class, 'update'])->middleware('throttle:10,1');
    Route::get('/mentor-profile', [MentorProfileController::class, 'show']);
    Route::put('/mentor-profile', [MentorProfileController::class, 'update'])->middleware('throttle:10,1');
    Route::get('/company-profile', [OrganizationController::class, 'show']);
    Route::put('/company-profile', [OrganizationController::class, 'update'])->middleware('throttle:10,1');

    // FAQ items
    Route::post('/faq-items', [FaqItemController::class, 'store'])->middleware('throttle:10,1');
    Route::put('/faq-items/{faqItem}', [FaqItemController::class, 'update']);
    Route::delete('/faq-items/{faqItem}', [FaqItemController::class, 'destroy']);

    // Admin (nti_admin can see users/approvals)
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::get('/admin/users/{id}', [AdminController::class, 'showUser']);
    Route::get('/admin/approvals', [AdminController::class, 'pendingApprovals']);
    Route::get('/admin/logs', [AdminController::class, 'logs']);
    Route::post('/admin/approve/{userId}', [AdminController::class, 'approveRole'])->middleware('throttle:10,1');
    Route::post('/admin/block/{userId}', [AdminController::class, 'blockUser'])->middleware('throttle:10,1');
    Route::post('/admin/unblock/{userId}', [AdminController::class, 'unblockUser'])->middleware('throttle:10,1');
    Route::post('/admin/users/{userId}/roles', [AdminController::class, 'assignRole'])->middleware('throttle:10,1');
    Route::delete('/admin/users/{userId}/roles', [AdminController::class, 'removeRole'])->middleware('throttle:10,1');
    Route::get('/admin/mentorships', [MentorshipController::class, 'adminIndex'])->middleware('throttle:10,1');
    Route::delete('/mentorships/{id}', [MentorshipController::class, 'destroy'])->middleware('throttle:10,1');

    // Organization
    Route::get('/company/tasks', [TaskController::class, 'index']);
    Route::get('/company/{companyId}/tasks', [TaskController::class, 'byOrganization']);
    Route::post('/company/tasks', [TaskController::class, 'store'])->middleware('throttle:10,1');
    Route::put('/company/tasks/{id}', [TaskController::class, 'update'])->middleware('throttle:10,1');
    Route::delete('/company/tasks/{id}', [TaskController::class, 'destroy'])->middleware('throttle:10,1');
    Route::post('/calls-with-tasks', [CallTaskController::class, 'storeCallWithTask'])->middleware('throttle:10,1');

    // Company members
    Route::get('/company/members/pending', [OrganizationMembershipController::class, 'pendingMembers']);
    Route::post('/company/members/{userId}/approve', [OrganizationMembershipController::class, 'approveMember'])->middleware('throttle:10,1');
    Route::post('/company/members/{userId}/reject', [OrganizationMembershipController::class, 'rejectMember'])->middleware('throttle:10,1');
    Route::get('/company/members/active', [OrganizationMembershipController::class, 'activeMembers']);
    Route::post('/company/members/{userId}/kick', [OrganizationMembershipController::class, 'kickMember'])->middleware('throttle:10,1');

    Route::get('/admin/admin-users', [AdminController::class, 'adminUsers']);
    Route::delete('/company/members/{userId}/kick', [OrganizationMembershipController::class, 'kickMember']);

    Route::get('/applications/{id}/history', [ApplicationController::class, 'getHistory']);
    Route::get('/applications/{id}/revision-request', [ApplicationController::class, 'getRevisionRequest']);

    // Super Admin Only
    Route::middleware('super_admin')->group(function () {
        Route::post('/admin/create-admin', [AdminController::class, 'createAdmin']);
        Route::delete('/admin/users/{userId}', [AdminController::class, 'deleteUser']);
        Route::post('/admin/users/{userId}/reset-password', [AdminController::class, 'resetAdminPassword']);
    });

    // Admin Only
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::post('/notifications/bulk', [BulkNotificationController::class, 'send'])->middleware('throttle:5,1');
        Route::get('/notifications/history', [BulkNotificationController::class, 'history']);
        Route::get('/programs', [ProgramController::class, 'index']);
        Route::post('/programs', [ProgramController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/programs/{program}', [ProgramController::class, 'show']);
        Route::put('/programs/{program}', [ProgramController::class, 'update'])->middleware('throttle:10,1');
        Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])->middleware('throttle:10,1');

        // Керування заявками (Applications Management)
        Route::get('/applications', [ApplicationController::class, 'adminIndex']); // Список з пагінацією та фільтрами
        Route::get('/applications/{id}', [ApplicationController::class, 'adminShow']); // Детальний перегляд (документи, оцінки)
        Route::patch('/applications/{id}/status', [ApplicationController::class, 'updateStatus'])->middleware('throttle:10,1'); // Зміна статусу адміном

        Route::get('/documents', [DocumentController::class, 'index']);
        Route::get('/calls', [CallController::class, 'index']);
        Route::post('/calls', [CallController::class, 'store'])->middleware('throttle:10,1');
        Route::put('/calls/{id}', [CallController::class, 'update'])->middleware('throttle:10,1');
        Route::delete('/calls/{id}', [CallController::class, 'destroy'])->middleware('throttle:10,1');
        Route::patch('/calls/{id}/status', [CallController::class, 'updateStatus'])->middleware('throttle:10,1');
        Route::get('/reporting/dashboard-stats', [ReportingController::class, 'dashboardStats']);

        // Call Evaluators
        Route::get('/calls/{id}/evaluators', [CallEvaluatorController::class, 'index']);
        Route::post('/calls/{id}/evaluators', [CallEvaluatorController::class, 'assign'])->middleware('throttle:10,1');
        Route::delete('/calls/{id}/evaluators/{userId}', [CallEvaluatorController::class, 'remove'])->middleware('throttle:10,1');

        // export
        Route::get('/export/applications', [ExportController::class, 'exportApplications']);
        Route::get('/export/users', [ExportController::class, 'exportUsers']);
        Route::get('/export/calls', [ExportController::class, 'exportCalls']);
        Route::get('/export/notifications', [ExportController::class, 'exportNotifications']);
        Route::put('/admin/calls/{call}', [CallController::class, 'update']);

        Route::post('/applications/{id}/revision-request', [ApplicationController::class, 'createRevisionRequest']);
    });
});
