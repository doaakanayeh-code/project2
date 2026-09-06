<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; 

return new class extends Migration
{
    public function up(): void
    {
        // 1. إنشاء هيكل الجدول مع تعديل اسم العمود ونوعه
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name'); 
            $table->enum('service_type', [ // 👈 تم تغيير الاسم إلى service_type ليطابق الكنترولر
                'hall',          
                'photographer',  
                'cake',          
                'decoration',     // 👈 تم تعديلها إلى decoration لتطابق التحقق (Validation)
                'music',         
                'public_event'   
            ]);
            $table->timestamps();
        });

        // 2. حقن الخدمات الستة بالبيانات المطابقة الجديدة 🚀
        DB::table('services')->insert([
            ['id' => 1, 'name' => 'صالات ومناسبات', 'service_type' => 'hall', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'خدمات تصوير', 'service_type' => 'photographer', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'كيك وحلويات', 'service_type' => 'cake', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'تنسيق وزينة وحفلات', 'service_type' => 'decoration', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'فرق موسيقية ودي جي', 'service_type' => 'music', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'حفلات عامة ومهرجانات', 'service_type' => 'public_event', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};