<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TweetShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'tweet_id',
        'user_id',
    ];
}
