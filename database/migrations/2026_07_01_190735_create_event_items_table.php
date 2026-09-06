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
{ Schema::create('event_items', function (Blueprint $table) {
$table->id();
$table->foreignId('event_id')->constrained('events')->onDelete('cascade');
$table->foreignId('provider_service_id')->constrained('provider_services');
$table->foreignId('package_id')->nullable()->constrained('packages'); // الحزمة اختيارية
$table->time('start_time');
$table->time('end_time')->nullable();      // البيانات
$table->decimal('price', 10, 2);
$table->string('payment_status')->default('pending'); // pending, paid, etc.
$table->json('option')->nullable();     
$table->string('status')->default('pending')->change();   
$table->integer('quantity')->nullable();
     
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_items');
    }
};