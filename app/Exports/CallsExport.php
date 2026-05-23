<?php

namespace App\Exports;

use App\Models\Call;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CallsExport implements FromQuery, WithHeadings, WithMapping
{
    public function query()
    {
        return Call::query();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Call name',
            'Program ID',
            'Status',
            'Opening date',
            'Deadline',
            'Min. team size',
            'Max. team size'
        ];
    }

    public function map($call): array
    {
        return [
            $call->id,
            $call->name,
            $call->program_id,
            $call->status,
            $call->opens_at,
            $call->deadline_at,
            $call->min_team_size,
            $call->max_team_size,
        ];
    }
}