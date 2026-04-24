<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaLead extends Model
{
    protected $table = 'meta_leads';

    protected $fillable = [
        'leadgen_id',
        'form_id',
        'page_id',
        'ad_id',
        'adgroup_id',
        'campaign_id',
        'created_time',
        'raw_json',
        'full_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'city',
        'state',
        'zip_code',
        'country',
        'job_title',
        'company_name',
        'custom_fields_json',
        'synced_at',
    ];

    protected $casts = [
        'created_time' => 'datetime',
        'custom_fields_json' => 'array',
        'synced_at' => 'datetime',
    ];
}

