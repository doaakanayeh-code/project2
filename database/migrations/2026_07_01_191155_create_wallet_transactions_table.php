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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            // المحفظة صاحبة الحركة (قد تكون لزبون أو لمزود خدمات)
            $table->foreignId('wallet_id')
                ->constrained()
                ->cascadeOnDelete();

            // ربط المعاملة بالفعالية الكلية (nullable لأن شحن المحفظة أو السحب لا يرتبط بفعالية)
            $table->foreignId('event_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // ربط المعاملة بالخدمة المنفردة (مهم جداً لتوزيع أرباح المزودين أو الـ Refund المنفرد)
            $table->foreignId('event_item_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // معرف المعاملة الخارجي (رقم دفع Stripe أو البنك) - بدون unique لسلامة التوزيع المشترك
            $table->string('transaction_id')->nullable();

            // القيمة المالية للحركة
            $table->decimal('amount', 12, 2);

            // نوع الحركة المالية داخل النظام
            $table->enum('action_type', [
                'deposit',      // شحن
                'payment',      // دفع
                'refund',       // استرجاع
                'earning',      // أرباح مزود
                'commission',   // عمولة المنصة
                'withdraw'      // سحب كاش
            ]);

            // حالة المعاملة
            $table->enum('status', [
                'pending',
                'completed',
                'failed'
            ])->default('completed');

            // طريقة الدفع المستخدمة (stripe, wallet, etc.)
            $table->string('payment_method')->nullable();

            // تفاصيل أو وصف الحركة المالية للأرشفة والتقارير
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};