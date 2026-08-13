<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditsTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'credits',
        'type',
        'source',
        'ref',
    ];
}
