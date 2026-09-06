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
    Schema::table('transactions', function (Blueprint $table) {
        // إضافة الأعمدة الجديدة
        $table->decimal('refund_amount', 10, 2)->default(0)->after('amount');
        $table->decimal('penalty_amount', 10, 2)->default(0)->after('refund_amount');
    });
}

public function down()
{
    Schema::table('transactions', function (Blueprint $table) {
        // حذف الأعمدة في حال عمل rollback
        $table->dropColumn(['refund_amount', 'penalty_amount']);
    });
}
};