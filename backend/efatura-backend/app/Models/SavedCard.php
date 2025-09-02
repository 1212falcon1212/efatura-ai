<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'provider',
        'token',
        'masked_pan',
        'holder_name',
        'exp_month',
        'exp_year',
        'customer_code',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}


