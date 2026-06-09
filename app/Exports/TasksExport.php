<?php

namespace App\Exports;

use App\Models\Task;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class TasksExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Task::with(['organization', 'productOwner', 'call'])
            ->whereHas('call', fn($q) => $q->where('program', 'b'));

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('organization', fn($o) => $o->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('updated_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Title',
            'Organization',
            'Status',
            'Budget (€)',
            'Product Owner',
            'Deadline',
            'Created At',
        ];
    }

    public function map($task): array
    {
        $po = $task->productOwner;
        $poName = $po
            ? trim(($po->first_name ?? '') . ' ' . ($po->last_name ?? '')) ?: $po->email
            : '';

        return [
            $task->id,
            $task->title,
            $task->organization->name ?? '',
            $task->status,
            $task->budget !== null ? number_format((float) $task->budget, 2, '.', '') : '',
            $poName,
            $task->call?->deadline_at ? \Carbon\Carbon::parse($task->call->deadline_at)->format('Y-m-d') : '',
            $task->created_at ? $task->created_at->format('Y-m-d H:i:s') : '',
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'use_bom'         => true,
            'output_encoding' => 'UTF-8',
        ];
    }
}