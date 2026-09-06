<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_items', function (Blueprint $table) {
            $table->json('proposed_changes')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('event_items', function (Blueprint $table) {
            $table->dropColumn('proposed_changes');
        });
    }
};