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

        // Receipt / booking template fields
        'receipt_no',
        'receipt_date',
        'customer_code',
        'payment_against',
        'cheque_no',
        'bank_name',
        'transaction_description',
        'transaction_amount',
        'amount_in_words',
        'receipt_notes',
        'meta_lead_id',
        'meta_form_id',
        'meta_ad_id',
        'meta_adset_id',
        'meta_campaign_id',
        'meta_page_id',
        'name',
        'phone',
        'whatsapp_eligible',
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
        'transaction_amount' => 'decimal:2',
        'loan_required' => 'bool',
        'whatsapp_eligible' => 'bool',
        'qualification' => 'array',
        'raw_payload' => 'array',
        'follow_up_at' => 'datetime',
        'receipt_date' => 'date',
    ];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
