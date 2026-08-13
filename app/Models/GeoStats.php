<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeoStats extends Model
{
    use HasFactory;

    protected $fillable = [
        'state',
        'county',
        
        'no_of_posts',
        'average_score',
        'highest_score',
        'scored_posts_count',
        'time_window',
    ];
}
