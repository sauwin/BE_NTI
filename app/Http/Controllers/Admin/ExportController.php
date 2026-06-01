<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use App\Exports\ApplicationsExport;
use App\Exports\CallsExport;
use App\Exports\BulkNotificationCampaignsExport;

/**
 * @tags Admin Management
 * Endpoints for generating administrative data exports, facilitating the conversion of system entities (Users, Applications, Calls, and Notification Campaigns) into portable spreadsheet formats (XLSX/CSV) with integrated audit logging for compliance and reporting.
 */
class ExportController extends Controller
{

    /**
     * Export users
     */
    public function exportUsers(Request $request)
    {
        $filters = $request->only(['search', 'role', 'status']);
        $format = strtolower($request->query('format', 'xlsx'));

        [$excelFormat, $extension] = $this->resolveFormat($format);

        AuditService::log('export', 'users', [
            'format' => $format,
            'filters' => $filters,
        ]);

        $fileName = 'users_export_' . now()->format('Y_m_d_His') . '.' . $extension;

        return Excel::download(
            new UsersExport($filters['search'] ?? null, $filters['role'] ?? null, $filters['status'] ?? null),
            $fileName,
            $excelFormat
        );
    }

    /**
     * Export applications
     */
    public function exportApplications(Request $request)
    {
        $filters = $request->only(['search', 'status', 'call_id', 'program_type']);
        $format = strtolower($request->query('format', 'xlsx'));

        [$excelFormat, $extension] = $this->resolveFormat($format);

        AuditService::log('export', 'applications', [
            'format' => $format,
            'filters' => $filters,
        ]);

        $fileName = 'applications_export_' . now()->format('Y_m_d_His') . '.' . $extension;

        return Excel::download(
            new ApplicationsExport($filters), 
            $fileName, 
            $excelFormat
        );
    }

    /**
     * Export calls
     */
    public function exportCalls(Request $request)
    {
        $filters = $request->only(['status', 'program_type']);
        $format = strtolower($request->query('format', 'xlsx'));

        [$excelFormat, $extension] = $this->resolveFormat($format);

        AuditService::log('export', 'calls', [
            'format' => $format,
            'filters' => $filters,
        ]);

        $fileName = 'calls_export_' . now()->format('Y_m_d_His') . '.' . $extension;

        return Excel::download(
            new CallsExport($filters), 
            $fileName, 
            $excelFormat
        );
    }

    /**
     * Export notifications
     */
    public function exportNotifications(Request $request)
    {
        $filters = $request->only(['subject', 'recipient_group', 'sender_id', 'date_from', 'date_to']);
        $format = strtolower($request->query('format', 'xlsx'));

        [$excelFormat, $extension] = $this->resolveFormat($format);

        AuditService::log('export', 'bulk_notifications', [
            'format' => $format,
            'filters' => $filters,
        ]);

        $fileName = 'bulk_notifications_export_' . now()->format('Y_m_d_His') . '.' . $extension;

        return Excel::download(
            new BulkNotificationCampaignsExport($filters),
            $fileName,
            $excelFormat
        );
    }

    /**
     * Resolve format for export
     * @return array{0: string, 1: string}
     */
    private function resolveFormat(string $format): array
    {
        return match ($format) {
            'csv'  => [\Maatwebsite\Excel\Excel::CSV, 'csv'],
            default => [\Maatwebsite\Excel\Excel::XLSX, 'xlsx'],
        };
    }
}