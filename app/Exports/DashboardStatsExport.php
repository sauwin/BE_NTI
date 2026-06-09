<?php

namespace App\Exports;

use App\Models\User;
use App\Models\Call;
use App\Models\Application;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Support\Collection;

class DashboardStatsExport implements FromCollection, WithHeadings, WithMapping, WithCustomCsvSettings
{
    public function collection(): Collection
    {
        $totalUsers = User::count();
        $students = User::whereHas('roles', fn($q) => $q->where('slug', 'student'))->count();
        $companyOwners = User::whereHas('roles', fn($q) => $q->where('slug', 'company'))->count();
        $admins = User::whereHas('roles', fn($q) => $q->where('slug', 'nti_admin'))->count();
        $contentEditors = User::whereHas('roles', fn($q) => $q->where('slug', 'content_editor'))->count();
        $evaluators = User::whereHas('roles', fn($q) => $q->where('slug', 'evaluator'))->count();
        $mentors = User::whereHas('roles', fn($q) => $q->where('slug', 'mentor'))->count();

        $totalCalls = Call::count();
        $openCalls  = Call::where('status', 'open')->count();

        $totalApplications = Application::count();
        $applicationSubmitted = Application::where('status', 'submitted')->count();
        $applicationActive = Application::where('status', 'active')->count();
        $applicationClosed = Application::where('status', 'closed')->count();

        return collect([
            ['metric' => 'total_users', 'value' => $totalUsers],
            ['metric' => 'students', 'value' => $students],
            ['metric' => 'company_owners', 'value' => $companyOwners],
            ['metric' => 'admins', 'value' => $admins],
            ['metric' => 'content_editors', 'value' => $contentEditors],
            ['metric' => 'evaluators', 'value' => $evaluators],
            ['metric' => 'mentors', 'value' => $mentors],
            ['metric' => 'total_calls', 'value' => $totalCalls],
            ['metric' => 'open_calls', 'value' => $openCalls],
            ['metric' => 'total_applications','value' => $totalApplications],
            ['metric' => 'application_submitted', 'value' => $applicationSubmitted],
            ['metric' => 'application_active', 'value' => $applicationActive],
            ['metric' => 'application_closed', 'value' => $applicationClosed],
        ]);
    }

    public function headings(): array
    {
        return ['Metric', 'Value', 'Exported At'];
    }

    public function map($row): array
    {
        return [
            $row['metric'],
            $row['value'],
            now()->format('Y-m-d H:i:s'),
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'use_bom' => true,
            'output_encoding' => 'UTF-8',
        ];
    }
}