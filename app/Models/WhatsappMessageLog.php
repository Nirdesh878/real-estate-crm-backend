<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessageLog extends Model
{
    protected $table = 'whatsapp_message_logs';

    protected $fillable = [
        'lead_id',
        'phone',
        'template_name',
        'api_response',
        'delivery_status',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}

