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
    Schema::table('event_items', function (Blueprint $table) {
        $table->string('transaction_id')->nullable()->after('qr_token'); // أضيفي العمود بحالة nullable لكي لا يتسبب بمشاكل للبيانات القديمة
    });
}

public function down(): void
{
    Schema::table('event_items', function (Blueprint $table) {
        $table->dropColumn('transaction_id');
    });
}
};
