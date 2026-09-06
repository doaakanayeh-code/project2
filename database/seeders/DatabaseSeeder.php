<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
//    public function run(): void
// {
//     // 1. تعبئة المدن (لازم قبل المواقع)
//     \App\Models\City::create(['name' => 'دمشق']);

//     // 2. تعبئة الخدمات الأساسية
//     \App\Models\Service::create(['name' => 'hall', 'service_type' => 'hall']);
    
//     // 3. تعبئة المواقع
//     \App\Models\Location::create(['city_id' => 1, 'address_name' => 'حي المزة - بناء رقم 5']);

//     // 4. تعبئة بيانات الخدمات المقدمة (هنا نستخدم الـ Factory)
//     \App\Models\ProviderService::factory()->count(10)->create();
// }


    public function run(): void
    {
        \Database\Factories\AdminFactory::new()->withToken()->create();
        // User::factory(10)->create();

       User::factory()->create([
    'username' => 'Test User', // التعديل هنا
    'email' => 'test@example.com',
]);



$this->call([
    DemoDataSeeder::class,
]);
    }



}
