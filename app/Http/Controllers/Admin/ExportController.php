<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use App\Exports\ApplicationsExport;
use App\Exports\CallsExport;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    public function exportUsers(Request $request)
    {
        $filters = $request->only(['search', 'role']);
        $format = strtolower($request->query('format', 'xlsx'));
        $excelFormat = $format === 'xlsx' ? \Maatwebsite\Excel\Excel::XLSX : \Maatwebsite\Excel\Excel::CSV;
        $extension = $format === 'xlsx' ? 'xlsx' : 'xlsx';

        DB::table('audit')->insert([
            'user_id' => auth()->id(),
            'action' => 'export',
            'object' => 'users',
            'details' => json_encode([
                'filters' => $filters,
                'format' => $format,
                'admin_email' => auth()->user()->email ?? 'unknown',
            ]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        $fileName = 'users_export_' . now()->format('Y_m_d_His') . '.' . $extension;

        return Excel::download(
            new UsersExport($filters['search'] ?? null, $filters['role'] ?? null), 
            $fileName, 
            $excelFormat
        );
    }

    public function exportApplications(Request $request)
    {
        $filters = $request->only(['search', 'status', 'format', 'call_id']);
        $format = strtolower($request->query('format', 'xlsx'));
        $excelFormat = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
        $extension = $format === 'csv' ? 'csv' : 'xlsx';

        DB::table('audit')->insert([
            'user_id' => auth()->id(),
            'action' => 'export',
            'object' => 'applications',
            'details' => json_encode([
                'filters' => $filters,
                'format' => $format,
                'admin_email' => auth()->user()->email ?? 'unknown',
            ]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        $fileName = 'applications_export_' . now()->format('Y_m_d_His') . '.' . $extension;

        return Excel::download(new ApplicationsExport($filters), $fileName, $excelFormat);
    }

    public function exportCalls(Request $request)
    {
        $format = strtolower($request->query('format', 'xlsx'));
        $excelFormat = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
        $extension = $format === 'csv' ? 'csv' : 'xlsx';

        \Illuminate\Support\Facades\DB::table('audit')->insert([
            'user_id' => auth()->id(),
            'action' => 'export',
            'object' => 'calls',
            'details' => json_encode([
                'format' => $format,
                'admin_email' => auth()->user()->email ?? 'unknown',
            ]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        $fileName = 'calls_export_' . now()->format('Y_m_d_His') . '.' . $extension;

        return Excel::download(new \App\Exports\CallsExport(), $fileName, $excelFormat);
    }
}