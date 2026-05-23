<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use App\Exports\ApplicationsExport;
use App\Exports\CallsExport;

class ExportController extends Controller
{
    public function exportUsers(Request $request)
    {
        $filters = $request->only(['search', 'role']);
        $format = strtolower($request->query('format', 'xlsx'));

        [$excelFormat, $extension] = $this->resolveFormat($format);

        AuditService::log('export', 'users', [
            'format' => $format,
            'filters' => $filters,
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

        [$excelFormat, $extension] = $this->resolveFormat($format);

        AuditService::log('export', 'applications', [
            'format' => $format,
            'filters' => $filters,
        ]);

        $fileName = 'applications_export_' . now()->format('Y_m_d_His') . '.' . $extension;

        return Excel::download(new ApplicationsExport($filters), $fileName, $excelFormat);
    }

    public function exportCalls(Request $request)
    {
        $format = strtolower($request->query('format', 'xlsx'));

        [$excelFormat, $extension] = $this->resolveFormat($format);

        AuditService::log('export', 'calls', [
            'format' => $format,
        ]);

        $fileName = 'calls_export_' . now()->format('Y_m_d_His') . '.' . $extension;

        return Excel::download(new CallsExport(), $fileName, $excelFormat);
    }

    /**
     * Resolve Maatwebsite format constant and file extension from a format string.
     * @return array{0: string, 1: string}
     */
    private function resolveFormat(string $format): array
    {
        return match ($format) {
            'csv'  => [\Maatwebsite\Excel\Excel::CSV,  'csv'],
            default => [\Maatwebsite\Excel\Excel::XLSX, 'xlsx'],
        };
    }
}