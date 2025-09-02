<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'customer_id', 'external_id', 'status', 'type', 'issue_date',
        'customer', 'items', 'totals', 'kolaysoft_ref', 'xml', 'ettn', 'metadata',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'customer' => 'array',
        'items' => 'array',
        'totals' => 'array',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'kolaysoft_ref',
    ];

    protected $appends = [
        'providerRef',
    ];

    public function getProviderRefAttribute(): ?string
    {
        return $this->attributes['kolaysoft_ref'] ?? null;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the customer that owns the invoice.
     */
    public function customerRecord(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
