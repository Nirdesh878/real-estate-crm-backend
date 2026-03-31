<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MstLeadStatus extends Model
{
    protected $table = 'mst_lead_status';

    protected $fillable = ['key', 'label', 'sort', 'is_active'];

    protected $casts = [
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];
}
