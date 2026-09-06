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
    Schema::create('package_services', function (Blueprint $table) {
        $table->id();
        $table->foreignId('provider_service_id')->constrained('provider_services')->onDelete('cascade'); // الخدمة المحددة للمزود
        $table->foreignId('package_id')->constrained('packages')->onDelete('cascade'); // الباقة التابعة لها
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_services');
    }
};
