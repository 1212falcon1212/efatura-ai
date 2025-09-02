<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'subscription_invoice_id',
        'provider',
        'amount_try',
        'currency',
        'status',
        'provider_txn_id',
        'request_json',
        'response_json',
        'error',
    ];

    protected $casts = [
        'request_json' => 'array',
        'response_json' => 'array',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function subscriptionInvoice(): BelongsTo { return $this->belongsTo(SubscriptionInvoice::class); }
}



