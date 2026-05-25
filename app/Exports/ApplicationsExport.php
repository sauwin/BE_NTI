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
            $search = trim($this->filters['search']);
            $query->where(function($q) use ($search) {
                $q->whereHas('studentProfile.user', function($userQuery) use ($search) {
                    $userQuery->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%");
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

        if (!empty($this->filters['program_type'])) {
            $query->where('program_type', strtolower($this->filters['program_type']));
        }

        return $query;
    }

    public function headings(): array
    {
        return ['ID', 'Call', 'Applicant Name', 'Applicant Email', 'Team', 'Status', 'Date Created'];
    }

    public function map($app): array
    {
        $name = 'N/A';
        $email = 'N/A';
        
        if ($app->studentProfile && $app->studentProfile->user) {
            $name = trim($app->studentProfile->user->first_name . ' ' . $app->studentProfile->user->last_name);
            $email = $app->studentProfile->user->email ?? 'N/A';
        }

        return [
            $app->id,
            $app->call->name ?? 'Unknown Call',
            $name ?: 'N/A',
            $email,
            $app->team ? $app->team->name : 'Individual',
            $app->status,
            $app->created_at ? $app->created_at->format('Y-m-d H:i:s') : 'N/A',
        ];
    }
}