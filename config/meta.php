<?php

return [
    'verify_token' => env('META_VERIFY_TOKEN'),
    'access_token' => env('META_ACCESS_TOKEN'),
    'app_secret' => env('META_APP_SECRET'),

    'graph_version' => env('META_GRAPH_VERSION', 'v25.0'),
    'api_base' => env('META_API_BASE', 'https://graph.facebook.com'),

    // If you don't want to manually maintain form IDs, set `META_PAGE_ID`
    // and the system will resolve active lead forms automatically.
    'page_id' => env('META_PAGE_ID'),
    // Comma-separated list of form ids (optional). Example: "123,456"
    'form_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('META_FORM_IDS', ''))))),

    // For debugging you can log webhook + Graph API payloads (contains PII).
    'log_payloads' => (bool) env('META_LOG_PAYLOADS', false),

    // Optional: also sync Meta leads into the CRM `leads` table (recommended).
    'sync_to_crm_leads' => (bool) env('META_SYNC_TO_CRM_LEADS', true),

    // Optional: auto-pull latest leads when an admin opens the app (GET /api/user).
    // Helpful for local development when webhooks can't reach localhost.
    'auto_pull_on_user' => (bool) env('META_AUTO_PULL_ON_USER', false),
    'pull_min_interval_seconds' => (int) env('META_PULL_MIN_INTERVAL_SECONDS', 300),
];
