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
        // أضفنا العمود وجعلناه nullable لأنه سيكون فارغاً قبل الدفع
        $table->string('qr_token')->nullable()->after('payment_status'); 
    });
}

public function down()
{
    Schema::table('event_items', function (Blueprint $table) {
        $table->dropColumn('qr_token');
    });
}
};