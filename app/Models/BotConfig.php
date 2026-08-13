<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'daily_like_limit',
        'daily_comment_limit',
        'likes_done_today',
        'comments_done_today',
        'total_actions_today',
        'last_active_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
