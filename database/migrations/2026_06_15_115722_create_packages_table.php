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
    Schema::create('packages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // المزود الذي أنشأ الباقة
        $table->string('name'); // اسم الباقة (مثل: باقة الأعراس الملكية)
        $table->text('description')->nullable(); // وصف الباقة وميزاتها
        $table->decimal('price', 10, 2); // سعر الباقة الإجمالي
        $table->string('status')->default('active'); // حالة الباقة (متاحة، منتهية...)
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
