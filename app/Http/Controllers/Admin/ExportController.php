<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exports\ApplicationsExport;
use App\Exports\UsersExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    public function applications(Request $request)
    {
        $filters = $request->except(['page', 'format']);
        $format = strtolower($request->query('format', 'xlsx')); 
        $this->logAudit('export_applications', $filters, $request);
        $fileName = 'applications_export_' . now()->format('Y_m_d_His') . '.' . $format;
        
        $writerType = match ($format) {
            'csv' => \Maatwebsite\Excel\Excel::CSV,
            'pdf' => \Maatwebsite\Excel\Excel::DOMPDF,
            default => \Maatwebsite\Excel\Excel::XLSX,
        };

        return Excel::download(new ApplicationsExport($filters), $fileName, $writerType);
    }

    public function users(Request $request)
    {

        $filters = $request->except('page');

        $this->logAudit('export_users', $filters, $request);

        $fileName = 'users_export_' . now()->format('Y_m_d_His') . '.csv';

        return Excel::download(new UsersExport($filters), $fileName, \Maatwebsite\Excel\Excel::CSV);
    }

    private function logAudit(string $action, array $filters, Request $request): void
    {
        DB::table('audit')->insert([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'object' => 'export',
            'details' => json_encode(['filters' => $filters]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}