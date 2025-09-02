<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id','name','sku','unit','vat_rate','unit_price','currency','metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'vat_rate' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
