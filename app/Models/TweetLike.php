<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TweetLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'tweet_id',
        'user_id',
    ];
}
