<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    use HasFactory;

    protected $fillable = ['subscription_id','number','amount_try','issue_date','status'];
    protected $casts = ['issue_date' => 'date'];

    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
}


