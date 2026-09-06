<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'username'          => 'admin',                     
            'email'             => 'admin@gmail.com',
            'phone'             => '0912345678',                
            'email_verified_at' => now(),
            'role'              => 'admin',                     
            'password'          => Hash::make('admin123$%'), 
            'id_img_front'      => 'defaults/admin_front.jpg',    
            'id_img_back'       => 'defaults/admin_back.jpg',    
            'remember_token'    => Str::random(10),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (User $user) {
            // إنشاء محفظة للأدمن تلقائياً بمجرد إنشاء المستخدم
            \App\Models\Wallet::firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0.00]
            );
        });
    }

    public function withToken()
    {
        return $this->afterCreating(function ($user) {
            $user->createToken('admin-token')->plainTextToken;
        });
    }
}
