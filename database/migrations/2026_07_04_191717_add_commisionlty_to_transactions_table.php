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
    Schema::table('transactions', function (Blueprint $table) {
        // إضافة عمود النسبة المئوية للعمولة
        $table->decimal('commission_percentage', 5, 2)->default(10)->after('admin_commission');
    });
}

public function down(): void
{
    Schema::table('transactions', function (Blueprint $table) {
        // حذف العمود في حال التراجع عن العملية
        $table->dropColumn('commission_percentage');
    });
}
};