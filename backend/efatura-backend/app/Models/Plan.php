<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = ['code','name','price_try','limits','active'];
    protected $casts = ['limits' => 'array','active' => 'boolean'];
}


