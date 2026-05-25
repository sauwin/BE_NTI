<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\AdminApplicationService;
use Illuminate\Http\Request;

class ApplicationManagementController extends Controller
{
    protected $adminService;

    public function __construct(AdminApplicationService $adminService)
    {
        $this->adminService = $adminService;
    }

    public function index(Request $request)
    {
        $applications = Application::with(['studentProfile.user', 'team', 'call'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->program, fn($q, $program) => $q->where('program_type', $program))
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($applications);
    }
}