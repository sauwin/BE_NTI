<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        return User::query()
            ->when(!empty($this->filters['role']), function ($query) {
                $query->whereHas('roles', function ($q) {
                    $q->where('name', $this->filters['role']);
                });
            });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Meno',
            'E-mail',
            'Dátum registrácie',
        ];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->created_at->format('Y-m-d H:i:s'),
        ];
    }
}