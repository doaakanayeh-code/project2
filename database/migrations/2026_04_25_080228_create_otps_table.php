<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('otps', function (Blueprint $table) {
            $table->id();

            // إيميل وهاتف nullable لدعم التحقق الديناميكي المشترك
            // إضافة index لتسريع عملية الـ Where() عند البحث عن الكود
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable()->index();

            $table->string('otp');
            $table->boolean('used')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};