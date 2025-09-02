<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_organization_id',
        'payment_id',
        'credits',
        'status', // reserved | cancelled | consumed
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}


