<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provider_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('set null');
            $table->decimal('price', 10, 2);
            
            // ✅ إضافة عمود rating (متوسط التقييمات)
            $table->decimal('rating', 3, 2)->default(0);
            
            // ✅ إضافة عمود review_count (عدد التقييمات)
            $table->integer('review_count')->default(0);
            
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->json('features')->nullable();
            $table->string('video_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_services');
    }
};