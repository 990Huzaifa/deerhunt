<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditsWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'free_credits',
        'paid_credits',
        'unlimited_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
