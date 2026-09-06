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
    Schema::create('locations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('city_id')->constrained('cities')->onDelete('cascade'); // ربط الموقع بالمدينة
        $table->string('address_name'); // اسم العنوان أو الحي تفصيلياً
        $table->decimal('latitude', 10, 8)->nullable(); // خط العرض للخريطة
        $table->decimal('longitude', 11, 8)->nullable(); // خط الطول للخريطة
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
