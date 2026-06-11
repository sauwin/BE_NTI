<?php

namespace App\Exports;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class CompanyExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Organization::query();

        if (!empty($this->filters['search_name'])) {
            $query->where('name', 'like', "%{$this->filters['search_name']}%");
        }

        if (!empty($this->filters['search_number'])) {
            $query->where('registration_number', 'like', "%{$this->filters['search_number']}%");
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Company Name',
            'Registration Number',
            'Website',
            'Status',
            'Created At',
            'Updated At',
        ];
    }

    /**
     * @param Organization $company
     */
    public function map($company): array
    {
        return [
            $company->id,
            $company->name,
            $company->registration_number,
            $company->website,
            $company->status,
            $company->created_at ? $company->created_at->format('Y-m-d H:i:s') : '',
            $company->updated_at ? $company->updated_at->format('Y-m-d H:i:s') : '',
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