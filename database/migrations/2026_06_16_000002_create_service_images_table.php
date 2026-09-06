<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('service_images', function (Blueprint $table) {
            $table->id();
            // التصحيح: الربط مع جدول خدمات المزود وليس الخدمة العامة
            $table->foreignId('provider_service_id')->constrained('provider_services')->onDelete('cascade'); 
            $table->string('image_path');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('title')->nullable();
            $table->timestamps();

        });
    }

   
    public function down(): void
    {
        Schema::dropIfExists('service_images');
    }
};