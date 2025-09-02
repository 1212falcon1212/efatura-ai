<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'organization_id', 'code', 'percent_off', 'active',
    ];
    protected $casts = [
        'percent_off' => 'float',
        'active' => 'boolean',
    ];
}


