<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetaLead;
use App\SyncMetaLeadJob;
use App\Services\MetaLeadService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class MetaLeadWebhookController extends Controller
{
    public function verify(Request $request)
    {
        // Meta sends `hub.mode`, `hub.verify_token`, `hub.challenge`.
        // Some proxies/frameworks may surface them as underscores.
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ((string) $mode === 'subscribe' && $token && (string) $token === (string) config('meta.verify_token')) {
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

        if ((bool) config('meta.log_payloads')) {
            Log::info('meta.webhook.received', ['payload' => $payload]);
        } else {
            Log::info('meta.webhook.received', [
                'object' => Arr::get($payload, 'object'),
                'entry_count' => is_array(Arr::get($payload, 'entry')) ? count((array) Arr::get($payload, 'entry')) : null,
            ]);
        }

        $entries = Arr::get($payload, 'entry', []);
        foreach ($entries as $entry) {
            $pageId = (string) (Arr::get($entry, 'id') ?? Arr::get($entry, 'page_id') ?? '');
            $changes = Arr::get($entry, 'changes', []);
            foreach ($changes as $change) {
                if ((string) Arr::get($change, 'field') !== 'leadgen') {
                    continue;
                }

                $value = Arr::get($change, 'value', []);
                $leadId = (string) Arr::get($value, 'leadgen_id', '');
                if (! $leadId) continue;

                $context = [
                    'page_id' => $pageId ?: (string) (Arr::get($value, 'page_id') ?? ''),
                    'form_id' => (string) (Arr::get($value, 'form_id') ?? ''),
                    'ad_id' => (string) (Arr::get($value, 'ad_id') ?? ''),
                    'adgroup_id' => (string) (Arr::get($value, 'adgroup_id') ?? ''),
                    'campaign_id' => (string) (Arr::get($value, 'campaign_id') ?? ''),
                    'created_time' => (string) (Arr::get($value, 'created_time') ?? ''),
                ];

                Log::info('meta.webhook.leadgen', [
                    'leadgen_id' => $leadId,
                    'page_id' => $context['page_id'] ?? null,
                    'form_id' => $context['form_id'] ?? null,
                ]);

                // Duplicate protection: if we already synced this lead, skip re-fetching.
                $already = MetaLead::query()->where('leadgen_id', $leadId)->exists();
                if ($already) {
                    Log::info('meta.webhook.duplicate_skipped', ['leadgen_id' => $leadId]);
                    continue;
                }

                // Fetch+save is deferred to keep webhook response fast.
                try {
                    SyncMetaLeadJob::dispatch($leadId, $context)->afterResponse();
                } catch (\Throwable $e) {
                    Log::error('meta.webhook.dispatch_failed', [
                        'leadgen_id' => $leadId,
                        'message' => $e->getMessage(),
                    ]);

                    // Fallback to sync (still safe to run multiple times due to upserts).
                    try {
                        $meta->syncLeadgenId($leadId, $context);
                    } catch (\Throwable $e2) {
                        Log::error('meta.webhook.sync_failed', [
                            'leadgen_id' => $leadId,
                            'message' => $e2->getMessage(),
                        ]);
                    }
                }
            }
        }

        return response()->json(['ok' => true], 200);
    }

    private function verifySignature(Request $request): bool
    {
        $appSecret = (string) config('meta.app_secret');
        if (! $appSecret) {
            // Allow if not configured (dev), but recommended to set.
            return true;
        }

        $sigHeader = $request->header('X-Hub-Signature-256');
        if (! $sigHeader || ! str_starts_with($sigHeader, 'sha256=')) return false;

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $sigHeader);
    }

    // Admin/test helper: manually fetch + store a lead without waiting for a webhook.
    public function testSync(Request $request, MetaLeadService $meta, string $leadgenId)
    {
        $saved = $meta->syncLeadgenId($leadgenId, [
            'page_id' => (string) $request->query('page_id', ''),
            'form_id' => (string) $request->query('form_id', ''),
        ]);

        return response()->json([
            'meta_lead' => $saved,
        ]);
    }
}
