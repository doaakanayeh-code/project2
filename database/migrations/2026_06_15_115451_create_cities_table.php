<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // 👈 هذا السطر مهم جداً لتشغيل الحقن التلقائي

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. إنشاء هيكل جدول المدن
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم المدينة
            $table->timestamps();
        });

        // 2. حقن المدن السورية تلقائياً وثابتاً 🚀
        DB::table('cities')->insert([
            ['id' => 1, 'name' => 'دمشق', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'حلب', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'حمص', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'اللاذقية', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'طرطوس', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'حماة', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};