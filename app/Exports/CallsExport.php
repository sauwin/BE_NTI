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
        $query = Call::with('program');

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['program_type'])) {
            $programType = $this->filters['program_type'];
            
            $query->whereHas('program', function ($q) use ($programType) {
                $q->where('code', 'program_' . $programType);
            });
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
        $programLabel = '—';
        if ($call->program) {
            if ($call->program->code === 'program_a') {
                $programLabel = 'Program A';
            } elseif ($call->program->code === 'program_b') {
                $programLabel = 'Program B';
            } else {
                $programLabel = $call->program->title ?? $call->program->name ?? '—';
            }
        }

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