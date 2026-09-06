<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_services', function (Blueprint $table) {
            if (!Schema::hasColumn('provider_services', 'rating')) {
                $table->decimal('rating', 3, 2)->default(0)->after('price');
            }
            if (!Schema::hasColumn('provider_services', 'review_count')) {
                $table->integer('review_count')->default(0)->after('rating');
            }
        });
    }

    public function down(): void
    {
        Schema::table('provider_services', function (Blueprint $table) {
            $table->dropColumn(['rating', 'review_count']);
        });
    }
};