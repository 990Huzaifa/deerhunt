<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'credits_per_month',
        'total_credits',
        'released_credits',
        'plan',
        'platform',
        'status',
        'renewal_period',
        'transaction_id',
        'expires_at',
        'canceled_at',
        'last_released_at'
    ];
}
