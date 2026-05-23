<?php

namespace App\Exports;

use App\Models\Application;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ApplicationsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        return Application::query()
            ->when(!empty($this->filters['status']), function ($query) {
                $query->where('status', $this->filters['status']);
            })
            ->when(!empty($this->filters['search']), function ($query) {
                $searchTerm = trim($this->filters['search']);
                $query->where('title', 'like', '%' . $searchTerm . '%');
            });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Názov projektu',
            'Status',
            'Dátum vytvorenia',
        ];
    }

    public function map($application): array
    {
        return [
            $application->id,
            $application->title,
            $application->status,
            $application->created_at->format('Y-m-d H:i:s'),
        ];
    }
}