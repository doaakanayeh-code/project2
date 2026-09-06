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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('guest_name');
        $table->string('code')->unique(); // الرمز الفريد للباركود
        $table->boolean('is_scanned')->default(false); // الحالة (دخل/لم يدخل)
        $table->timestamp('scanned_at')->nullable(); // وقت الدخول الفعلي
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
