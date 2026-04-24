<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmovurWhatsappService
{
    /**
     * Send a WhatsApp template message via Emovur.
     *
     * NOTE: The exact payload fields depend on your Emovur account/API.
     * If Emovur requires different keys, update this method accordingly.
     *
     * @param  array<string,mixed>  $variables
     * @return array{status:int, ok:bool, body:array|string|null}
     */
    public function sendTemplate(string $phoneE164, string $templateName, array $variables = []): array
    {
        $baseUrl = (string) config('emovur.base_url');
        $apiKey = (string) config('emovur.api_key');
        $timeout = (int) config('emovur.timeout_seconds', 20);

        if (! $baseUrl || ! $apiKey) {
            throw new \RuntimeException('Emovur API is not configured (EMOVUR_BASE_URL / EMOVUR_API_KEY).');
        }

        $sendPath = (string) config('emovur.send_path', '/send-template');
        if ($sendPath === '') $sendPath = '/send-template';
        if (! str_starts_with($sendPath, '/')) $sendPath = '/'.$sendPath;

        $url = "{$baseUrl}{$sendPath}";

        $payload = [
            'to' => $phoneE164,
            'template_name' => $templateName,
            'variables' => $variables,
        ];

        $response = Http::timeout($timeout)
            ->retry(1, 250)
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        $body = null;
        try {
            $body = $response->json();
        } catch (\Throwable) {
            $body = $response->body();
        }

        $ok = $response->successful();

        Log::info('emovur.whatsapp.send', [
            'to' => $phoneE164,
            'template' => $templateName,
            'status' => $response->status(),
            'ok' => $ok,
        ]);

        if (! $ok) {
            Log::warning('emovur.whatsapp.send_failed', [
                'to' => $phoneE164,
                'template' => $templateName,
                'status' => $response->status(),
                'body' => $body,
            ]);
        }

        return [
            'status' => $response->status(),
            'ok' => $ok,
            'body' => $body,
        ];
    }

    /**
     * Normalize phone to E.164-ish for WhatsApp (best-effort).
     */
    public function normalizePhone(?string $phoneRaw): ?string
    {
        $phoneRaw = trim((string) ($phoneRaw ?? ''));
        if ($phoneRaw === '') return null;

        $digits = preg_replace('/[^0-9+]/', '', $phoneRaw) ?? $phoneRaw;

        // If already includes + and has enough digits, keep.
        if (str_starts_with($digits, '+')) {
            $onlyDigits = preg_replace('/[^0-9]/', '', $digits) ?? '';
            return strlen($onlyDigits) >= 10 ? $digits : null;
        }

        $onlyDigits = preg_replace('/[^0-9]/', '', $digits) ?? '';
        if (strlen($onlyDigits) < 10) return null;

        // If 10-digit local number, prefix default country code.
        $defaultCc = (string) config('emovur.default_country_code', '91');
        if (strlen($onlyDigits) === 10) {
            return '+'.$defaultCc.$onlyDigits;
        }

        // If it already includes country code (e.g. 91XXXXXXXXXX), prefix +.
        return '+'.$onlyDigits;
    }
}
