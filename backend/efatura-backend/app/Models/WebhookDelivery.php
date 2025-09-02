<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id','webhook_subscription_id','event','payload','status','attempt_count','last_attempt_at','response_status','response_body'
    ];

    protected $casts = [
        'payload' => 'array',
        'last_attempt_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}


