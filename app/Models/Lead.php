<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $table = 'leads';

    protected $fillable = [
        'platform',
        'lead_source',
        'campaign_name',
        'ad_set_name',
        'ad_name',
        'lead_form_name',
        'source_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'meta_lead_id',
        'meta_form_id',
        'meta_ad_id',
        'meta_adset_id',
        'meta_campaign_id',
        'meta_page_id',
        'name',
        'phone',
        'email',
        'city',
        'budget',
        'plot_size',
        'purpose',
        'timeline_to_buy',
        'loan_required',
        'status',
        'assigned_user_id',
        'follow_up_at',
        'notes',
        'qualification',
        'raw_payload',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'loan_required' => 'bool',
        'qualification' => 'array',
        'raw_payload' => 'array',
        'follow_up_at' => 'datetime',
    ];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}