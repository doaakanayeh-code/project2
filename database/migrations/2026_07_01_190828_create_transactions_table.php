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
      Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    
    // العلاقات
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('event_item_id')->constrained('event_items')->onDelete('cascade');
    
    // تفاصيل الدفع
    $table->decimal('amount', 12, 2);
    $table->decimal('admin_commission', 10, 2)->default(0); // عمولة الأدمن المضافة
    $table->decimal('provider_amount', 10, 2)->default(0);  // صافي المزوّد المضافة
    $table->string('payment_method'); // مثلاً: credit_card, wallet, paypal
    $table->string('reference_number')->nullable(); // رقم المرجع من بوابة الدفع
    $table->string('status'); // paid, pending, failed
    $table->string('type'); // purchase, refund, deposit
    $table->string('pdf_url')->nullable(); // رابط الفاتورة
    
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};