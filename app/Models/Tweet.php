<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tweet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'caption',
        'state',
        'images',
        'harvest_type',
        'score_data',
        'like_count',
        'comment_count',
        'share_count',
        'is_public',
    ];

}
