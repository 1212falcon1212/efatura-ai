<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Despatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id','external_id','status','mode','issue_date','sender','receiver','items','totals','provider_ref','xml','ettn'
    ];

    protected $casts = [
        'sender' => 'array',
        'receiver' => 'array',
        'items' => 'array',
        'totals' => 'array',
        'issue_date' => 'date',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}

