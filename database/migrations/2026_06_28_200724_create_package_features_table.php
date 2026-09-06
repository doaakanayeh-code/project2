<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('package_features', function (Blueprint $table) {
        $table->id();
        
        // يربط المعرف الخاص بالباقة
        $table->foreignId('package_id')->constrained('packages')->onDelete('cascade');
        // يربط المعرف الخاص بالميزة (من الجدول الذي أرسلتيه لي)
        $table->foreignId('feature_id')->constrained('features')->onDelete('cascade');
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_features');
    }
};
