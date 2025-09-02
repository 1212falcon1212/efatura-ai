<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeadLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'reference_id', 'queue', 'error', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}


