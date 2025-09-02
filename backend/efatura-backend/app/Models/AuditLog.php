<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id','user_id','action','entity_type','entity_id','context',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}


