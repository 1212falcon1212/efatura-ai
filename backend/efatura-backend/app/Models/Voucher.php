<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id','external_id','status','type','issue_date','customer','items','totals','provider_ref','xml','ettn','destination_email'
    ];

    protected $casts = [
        'customer' => 'array',
        'items' => 'array',
        'totals' => 'array',
        'issue_date' => 'date',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}

