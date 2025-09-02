<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id','plan_id','status','current_period_start','current_period_end',
    ];

    protected $casts = [
        'current_period_start' => 'date',
        'current_period_end' => 'date',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function invoices(): HasMany { return $this->hasMany(SubscriptionInvoice::class); }
}


