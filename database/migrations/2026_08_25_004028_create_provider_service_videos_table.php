<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| جدول provider_service_videos
|--------------------------------------------------------------------------
| بيسمح لكل خدمة (ProviderService) يكون إلها أكتر من فيديو واحد.
| الفيديو الأساسي بضل محفوظ بعمود video_path داخل provider_services متل ما هو
| (نفس المنطق يلي شغّال حالياً بكل الأماكن التانية بالمشروع)، وهاد الجدول
| بيستخدم بس للفيديوهات "الإضافية" يلي بدها تترافق مع نفس الخدمة.
|
| ملاحظة: انسخ هاد الملف لمجلد database/migrations بمشروعك، وشغّل:
|   php artisan migrate
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_service_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_service_id')
                  ->constrained('provider_services')
                  ->onDelete('cascade');
            $table->string('video_path');
            $table->string('title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_service_videos');
    }
};