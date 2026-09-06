<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return User::all();
    }

  
    public function headings(): array
    {
        return [
            'Username',
            'Email',
            'Phone',
            'Role',
            'Status',
        ];
    }

   
    public function map($user): array
    {
        $status = 'Active'; // الحالة الافتراضية

    if ($user->deleted_at !== null) {
        $status = 'Deleted'; // محذوف
    } elseif ($user->is_blocked == 1) {
        $status = 'Blocked'; // محظور
    }

        return [
            $user->username,
            $user->email,
            $user->phone ?? '-', 
            $user->role,
            $status,
        ];
    }
}