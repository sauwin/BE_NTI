<?php

namespace App\Exports;

use App\Models\Application;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ApplicationsExport implements FromQuery, WithHeadings, WithMapping
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Application::with(['call', 'studentProfile.user', 'team']);

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->whereHas('studentProfile.user', function($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%");
                })->orWhereHas('team', function($teamQuery) use ($search) {
                    $teamQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['call_id'])) {
            $query->where('call_id', $this->filters['call_id']);
        }

        return $query;
    }

    public function headings(): array
    {
        return ['ID', 'Call', 'Applicant', 'Team', 'Status', 'Date Created'];
    }

    public function map($app): array
    {
        return [
            $app->id,
            $app->call->name ?? 'Unknown Call',
            $app->studentProfile->user->name ?? 'N/A',
            $app->team->name ?? 'Individual',
            $app->status,
            $app->created_at ? $app->created_at->format('Y-m-d H:i:s') : 'N/A',
        ];
    }
}