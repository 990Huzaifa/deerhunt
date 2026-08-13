<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Premium extends Model
{
    use HasFactory;

    protected $table = 'premiums';

    protected $fillable = [
        'user_id',
        'plan',
        'platform',
        'status',
        'renewal_period',
        'transaction_id',
        'expires_at',
        'canceled_at',
    ];
}
