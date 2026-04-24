<?php

return [
    'base_url' => rtrim((string) env('EMOVUR_BASE_URL', ''), '/'),
    'api_key' => env('EMOVUR_API_KEY'),
    'send_path' => (string) env('EMOVUR_SEND_PATH', '/send-template'),

    // Most providers use Bearer tokens. If Emovur uses a different scheme,
    // update the service or add additional config.
    'timeout_seconds' => (int) env('EMOVUR_TIMEOUT_SECONDS', 20),

    // Number formatting (WhatsApp requires country code)
    'default_country_code' => (string) env('WHATSAPP_DEFAULT_COUNTRY_CODE', '91'),
];
