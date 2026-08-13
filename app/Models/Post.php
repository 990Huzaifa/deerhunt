<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'image',
        'feed_images',
        'share_count',
        'like_count',
        'comment_count',
        'score',
        'analysis',
        'is_delete',
        'is_trophy',
        'is_public',
        'is_private',
        'ref_id',
        'antler_points',
        'measurements',
        'deer_age_estimate',
        'estimated_age',
        'growth_projection',
        'years_age',
        'comment',
        'caption',
        'state',
        'county',
        'harvest_type',

        'created_at'
    ];

    public function reports()
    {
        // 'reportable' is the name of the polymorphic relation in the Report model
        return $this->morphMany(Report::class, 'reportable');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
