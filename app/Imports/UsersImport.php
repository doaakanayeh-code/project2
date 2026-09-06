<?php

namespace App\Imports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; 
use Illuminate\Support\Facades\Hash;

class UsersImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
   public function model(array $row)
{
    return User::firstOrCreate(
        [
            'email' => $row['email'], 
        ],
        [
            'username'     => $row['username'],
            'phone'        => $row['phone'] ?? null,
            'id_img_front' => $row['id_img_front'] ?? null,
            'id_img_back'  => $row['id_img_back'] ?? null,
            'password'     => Hash::make($row['password'] ?? '12345678'),
            'role'         => $row['role'] ?? 'user',
            'ocr_text'     => $row['ocr_text'] ?? null,
            'is_blocked'   => $row['is_blocked'] ?? false,
        ]
    );
}
}