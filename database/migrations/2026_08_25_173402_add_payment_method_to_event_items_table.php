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
    Schema::table('event_items', function (Blueprint $table) {
        $table->string('payment_method')->nullable(); // أو النوع المناسب حسب بياناتك
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_items', function (Blueprint $table) {
            //
        });
    }
};
