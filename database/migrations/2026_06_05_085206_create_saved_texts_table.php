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
    Schema::create('saved_texts', function (Blueprint $table) {
        $table->id();
        $table->text('user_speech');      // النص يلي حكاه المستخدم واستخرجه الريأكت
        $table->text('ai_response')->nullable(); // الرد النصي يلي رح يتولد بعد المقارنة
        $table->string('audio_response_path')->nullable(); // مسار ملف الرد الصوتي الثاني
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_texts');
    }
};
