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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username'); // اسم المستخدم

            // جعل الإيميل والهاتف nullable لأن المستخدم قد يختار أحدهما فقط عند التسجيل الديناميكي
            $table->string('email')->unique()->nullable();
            $table->string('phone')->unique()->nullable();

            $table->string('id_img_front')->nullable(); 
            $table->string('id_img_back')->nullable();  
            $table->boolean('is_blocked')->default(false); 

            $table->string('password');
            
            // تم إلغاء التعليق وتفعيل الحقل لحل مشكلة الـ Register والـ Login فوراُ
            $table->string('status')->default('pending'); 

            $table->enum('role', ['user', 'provider', 'admin'])->default('user')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->softDeletes();
            $table->string('avatar')->nullable();

            // توحيد الحقول لتطابق الـ User Model تماماً وتجنب خطأ تعارض الأسماء
            $table->string('google_id')->nullable();
           // $table->string('google_token')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};