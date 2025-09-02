<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WebhookSubscription extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id','url','secret','events'];

    protected $casts = [
        'events' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}


