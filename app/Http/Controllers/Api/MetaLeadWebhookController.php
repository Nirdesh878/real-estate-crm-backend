<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\MetaLeadService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MetaLeadWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token && $token === env('META_VERIFY_TOKEN')) {
            return response((string) $challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request, MetaLeadService $meta)
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->all();

        $entries = Arr::get($payload, 'entry', []);
        foreach ($entries as $entry) {
            $changes = Arr::get($entry, 'changes', []);
            foreach ($changes as $change) {
                $value = Arr::get($change, 'value', []);
                $leadId = (string) Arr::get($value, 'leadgen_id', '');
                if (! $leadId) continue;

                $existing = Lead::query()->where('meta_lead_id', $leadId)->first();
                if ($existing) {
                    continue;
                }

                $leadData = null;
                try {
                    $leadData = $meta->fetchLead($leadId);
                } catch (\Throwable $e) {
                    // store minimal lead from webhook if fetch fails
                }

                $fieldData = Arr::get($leadData ?? [], 'field_data', []);
                $mapped = $this->mapFields($fieldData);

                $metaAdId = (string) (Arr::get($leadData ?? $value, 'ad_id') ?? '');
                $metaAdsetId = (string) (Arr::get($leadData ?? $value, 'adgroup_id') ?? '');
                $metaCampaignId = (string) (Arr::get($leadData ?? $value, 'campaign_id') ?? '');
                $metaFormId = (string) (Arr::get($leadData ?? $value, 'form_id') ?? Arr::get($value, 'form_id') ?? '');
                $metaPageId = (string) (Arr::get($value, 'page_id') ?? '');

                $fetchNames = (bool) env('META_FETCH_NAMES', false);

                $lead = Lead::create([
                    'platform' => 'meta',
                    'lead_source' => 'meta_lead_form',
                    'meta_lead_id' => $leadId,
                    'meta_form_id' => $metaFormId ?: null,
                    'meta_ad_id' => $metaAdId ?: null,
                    'meta_adset_id' => $metaAdsetId ?: null,
                    'meta_campaign_id' => $metaCampaignId ?: null,
                    'meta_page_id' => $metaPageId ?: null,

                    'campaign_name' => $fetchNames && $metaCampaignId ? $meta->fetchName($metaCampaignId) : null,
                    'ad_set_name' => $fetchNames && $metaAdsetId ? $meta->fetchName($metaAdsetId) : null,
                    'ad_name' => $fetchNames && $metaAdId ? $meta->fetchName($metaAdId) : null,
                    'lead_form_name' => $fetchNames && $metaFormId ? $meta->fetchName($metaFormId) : null,

                    'name' => $mapped['name'] ?? null,
                    'phone' => $mapped['phone'] ?? null,
                    'email' => $mapped['email'] ?? null,
                    'city' => $mapped['city'] ?? null,

                    'budget' => $mapped['budget'] ?? null,
                    'plot_size' => $mapped['plot_size'] ?? null,
                    'purpose' => $mapped['purpose'] ?? null,
                    'timeline_to_buy' => $mapped['timeline_to_buy'] ?? null,
                    'loan_required' => array_key_exists('loan_required', $mapped) ? (bool) $mapped['loan_required'] : null,

                    'qualification' => $mapped['qualification'] ?? null,
                    'raw_payload' => $payload,
                    'status' => 'new',
                ]);
            }
        }

        return response()->noContent();
    }

    private function verifySignature(Request $request): bool
    {
        $appSecret = env('META_APP_SECRET');
        if (! $appSecret) {
            // Allow if not configured (dev), but recommended to set.
            return true;
        }

        $sigHeader = $request->header('X-Hub-Signature-256');
        if (! $sigHeader || ! str_starts_with($sigHeader, 'sha256=')) return false;

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $sigHeader);
    }

    private function mapFields(array $fieldData): array
    {
        $out = [
            'qualification' => [],
        ];

        foreach ($fieldData as $item) {
            $name = (string) Arr::get($item, 'name', '');
            $values = Arr::get($item, 'values', []);
            $value = is_array($values) && isset($values[0]) ? (string) $values[0] : null;
            if (! $name) continue;

            $normalized = strtolower(trim($name));

            $out['qualification'][$normalized] = $values;

            if (in_array($normalized, ['full_name', 'name'], true)) $out['name'] = $value;
            if (in_array($normalized, ['phone_number', 'phone'], true)) $out['phone'] = $value;
            if (in_array($normalized, ['email'], true)) $out['email'] = $value;
            if (in_array($normalized, ['city'], true)) $out['city'] = $value;

            if (str_contains($normalized, 'budget')) {
                $out['budget'] = $value ? (float) preg_replace('/[^0-9.]/', '', $value) : null;
            }
            if (str_contains($normalized, 'plot')) $out['plot_size'] = $value;
            if (str_contains($normalized, 'investment') || str_contains($normalized, 'self')) $out['purpose'] = $value;
            if (str_contains($normalized, 'timeline')) $out['timeline_to_buy'] = $value;
            if (str_contains($normalized, 'loan')) {
                if ($value === null) continue;
                $out['loan_required'] = in_array(strtolower($value), ['yes', 'true', '1'], true);
            }
        }

        return $out;
    }
}