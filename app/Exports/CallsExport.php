<?php

namespace App\Exports;

use App\Models\Call;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CallsExport implements FromQuery, WithHeadings, WithMapping
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Call::query();

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['program_type'])) {
            $programType = $this->filters['program_type'];
            $query->where('program', $programType);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Call name',
            'Program',
            'Status',
            'Opening date',
            'Deadline',
            'Min. team size',
            'Max. team size'
        ];
    }

    public function map($call): array
    {
        $programLabel = match($call->program ?? null) {
            'a' => 'Program A',
            'b' => 'Program B',
            default => '—',
        };

        return [
            $call->id,
            $call->name,
            $programLabel,
            strtoupper($call->status), 
            $call->opens_at,
            $call->deadline_at,
            $call->min_team_size,
            $call->max_team_size ?? '∞',
        ];
    }
}