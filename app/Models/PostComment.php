<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class PostComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'comment',
        'is_delete',
    ];


    // 1. Relationship to the User who made the comment
    public function user(): BelongsTo
    {
        // Assuming your User model is App\Models\User
        return $this->belongsTo(User::class);
    }

    // 2. Relationship for Replies (We will call it 'replies' in the code)
    public function replies(): HasMany
    {
        return $this->hasMany(PostComment::class, 'parent_id', 'id')->where('is_delete', false);
    }

    // --- Accessors for Flattening User Data ---

    public function getUsernameAttribute(): ?string
    {
        // Check if the 'user' relationship has been loaded before accessing it
        return $this->user ? $this->user->username : null;
    }

    public function getAvatarAttribute(): ?string
    {
        return $this->user ? $this->user->avatar : null;
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
