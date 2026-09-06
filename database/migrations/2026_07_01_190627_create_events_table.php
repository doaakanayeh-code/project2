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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            
            // ربط الفعالية بالمستخدم (صاحب الفعالية)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            $table->string('name'); 
            $table->decimal('budget', 15, 2)->nullable(); 
            
            // وقت وتاريخ الحفل العام
            $table->dateTime('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            
            $table->string('status')->default('upcoming'); // مثلاً: upcoming, finished, cancelled
            
            // الإحداثيات والموقع
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();

            // نوع الفعالية والمبالغ
            $table->enum('type', [
                'event',
                'standalone'
            ])->default('event');

            $table->decimal('total_amount', 12, 2)->default(0); // إجمالي المبلغ
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};