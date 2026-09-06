<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedText extends Model
{
    //
    protected $fillable = [
        'user_speech', 
        'ai_response', 
        'audio_response_path'
    ];
}
