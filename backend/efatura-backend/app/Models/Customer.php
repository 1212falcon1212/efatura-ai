<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'name', 'surname', 'tckn_vkn', 'email', 'phone',
        'city', 'district', 'street_address', 'tax_office', 'urn'
    ];

    protected $casts = [
        // 'address' artık JSON değil, bu yüzden cast'e gerek yok.
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
