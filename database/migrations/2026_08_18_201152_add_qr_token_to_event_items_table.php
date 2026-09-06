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
    if (!Schema::hasColumn('event_items', 'qr_token')) {
        Schema::table('event_items', function (Blueprint $table) {
            $table->string('qr_token')->nullable()->after('status');
        });
    }
}

public function down(): void
{
    Schema::table('event_items', function (Blueprint $table) {
        $table->dropColumn('qr_token');
    });
}
};
