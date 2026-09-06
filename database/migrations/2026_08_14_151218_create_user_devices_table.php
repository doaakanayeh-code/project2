<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  
   public function up()
{
    Schema::create('user_devices', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade'); // ربط الجهاز بالمستخدم
        $table->string('device_token'); // التوكن الخاص بـ FCM
        $table->string('device_type')->nullable(); // نوع الجهاز (اختياري)
        $table->timestamps();
    });
}

  
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};