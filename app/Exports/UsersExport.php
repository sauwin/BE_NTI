<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class UsersExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    protected $search;
    protected $role;
    protected $status;

    public function __construct($search = null, $role = null, $status = null)
    {
        $this->search = $search;
        $this->role = $role;
        $this->status = $status;
    }

    public function query()
    {
        $query = User::query()->with('roles');

        if ($this->search) {
            $query->where(function (Builder $q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                  ->orWhere('last_name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        if ($this->role) {
            $query->whereHas('roles', function (Builder $q) {
                $q->where('slug', $this->role);
            });
        }

        if ($this->status) {
            $query->where(function (Builder $q) {
                if ($this->status === 'active') {
                    $q->where('status', 'active');
                } elseif ($this->status === 'blocked') {
                    $q->where('status', 'blocked');
                }
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'First Name',
            'Last Name',
            'Email',
            'Roles',
            'Status',
            'Language Preference',
            'Organization ID',
            'Role In Org',
            'Email Verified At',
            'Created At',
            'Updated At',
            'Password Hash'
        ];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->first_name,
            $user->last_name,
            $user->email,
            $user->roles->pluck('slug')->implode(', '), 
            $user->status,
            $user->language_preference,
            $user->organization_id,
            $user->role_in_org,
            $user->email_verified_at ? $user->email_verified_at->format('Y-m-d H:i:s') : '',
            $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : '',
            $user->updated_at ? $user->updated_at->format('Y-m-d H:i:s') : '',
            $user->password,
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