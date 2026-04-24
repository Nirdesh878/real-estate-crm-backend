<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\WhatsappMessageLog;
use App\SendWhatsappTemplateJob;
use App\Services\EmovurWhatsappService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappController extends Controller
{
    /**
     * Send a WhatsApp template message to recently refreshed Meta leads.
     * This endpoint is admin-only and rate-limited.
     */
    public function sendRefreshCampaign(Request $request, EmovurWhatsappService $emovur)
    {
        $request->validate([
            'hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'template_name' => ['required', 'string', 'max:100'],
            'lead_ids' => ['nullable', 'array', 'max:500'],
            'lead_ids.*' => ['integer', 'distinct'],
        ]);

        $hours = (int) ($request->input('hours') ?? 168);
        $templateName = (string) $request->input('template_name');
        $since = now()->subHours($hours);
        $leadIds = $request->input('lead_ids');
        $leadIds = is_array($leadIds) ? array_values(array_unique(array_map('intval', $leadIds))) : [];

        Log::info('whatsapp.refresh_campaign.start', [
            'user_id' => optional($request->user())->id,
            'hours' => $hours,
            'template' => $templateName,
            'lead_ids_count' => count($leadIds) ?: null,
        ]);

        $eligibleQuery = Lead::query()->orderByDesc('id');

        if (count($leadIds)) {
            // Only send to the explicit list (newly inserted leads from refresh).
            $eligibleQuery->whereIn('id', $leadIds);
        } else {
            // Back-compat: send to recently created Meta leads in window.
            $eligibleQuery
                ->join('meta_leads', 'meta_leads.leadgen_id', '=', 'leads.meta_lead_id')
                ->where('meta_leads.created_time', '>=', $since)
                ->select('leads.*')
                ->distinct();
        }

        $eligibleQuery
            ->whereNotNull('phone')
            ->where('whatsapp_eligible', true);

        $queued = 0;
        $skippedDuplicate = 0;
        $skippedInvalidPhone = 0;
        $errors = [];
        $errorLimit = 25;

        $eligibleQuery->chunkById(200, function ($leads) use (
            $templateName,
            $emovur,
            &$queued,
            &$skippedDuplicate,
            &$skippedInvalidPhone,
            &$errors,
            $errorLimit,
        ) {
            $leadIdList = $leads->pluck('id')->map(fn ($v) => (int) $v)->all();
            $alreadySent = WhatsappMessageLog::query()
                ->where('template_name', $templateName)
                ->whereIn('lead_id', $leadIdList)
                ->pluck('lead_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $alreadySentMap = array_fill_keys($alreadySent, true);

            foreach ($leads as $lead) {
                if (isset($alreadySentMap[(int) $lead->id])) {
                    $skippedDuplicate++;
                    continue;
                }

                $phone = $emovur->normalizePhone($lead->phone);
                if (! $phone) {
                    $skippedInvalidPhone++;
                    continue;
                }

                try {
                    // Create log row first (unique constraint prevents duplicates).
                    $logRow = WhatsappMessageLog::create([
                        'lead_id' => (int) $lead->id,
                        'phone' => $phone,
                        'template_name' => $templateName,
                        'delivery_status' => 'queued',
                        'api_response' => null,
                        'sent_at' => null,
                    ]);

                    // Queue send (requires queue worker running in production).
                    SendWhatsappTemplateJob::dispatch((int) $logRow->id);
                    $queued++;
                } catch (QueryException $e) {
                    // Unique constraint hit => already queued/sent.
                    $sqlState = (string) ($e->errorInfo[0] ?? '');
                    if ($sqlState === '23000') {
                        $skippedDuplicate++;
                        continue;
                    }

                    Log::error('whatsapp.refresh_campaign.db_failed', [
                        'lead_id' => (int) $lead->id,
                        'message' => $e->getMessage(),
                    ]);

                    if (count($errors) < $errorLimit) {
                        $errors[] = [
                            'lead_id' => (int) $lead->id,
                            'message' => $e->getMessage(),
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::error('whatsapp.refresh_campaign.queue_failed', [
                        'lead_id' => (int) $lead->id,
                        'message' => $e->getMessage(),
                    ]);

                    if (count($errors) < $errorLimit) {
                        $errors[] = [
                            'lead_id' => (int) $lead->id,
                            'message' => $e->getMessage(),
                        ];
                    }
                }
            }
        }, 'leads.id');

        Log::info('whatsapp.refresh_campaign.done', [
            'user_id' => optional($request->user())->id,
            'queued' => $queued,
            'skipped_duplicate' => $skippedDuplicate,
            'skipped_invalid_phone' => $skippedInvalidPhone,
            'errors' => count($errors),
        ]);

        return response()->json([
            'success' => true,
            'template_name' => $templateName,
            'window_hours' => $hours,
            'since' => $since->toISOString(),
            'queued' => $queued,
            'skipped' => $skippedDuplicate,
            'skipped_invalid_phone' => $skippedInvalidPhone,
            'errors' => $errors,
        ]);
    }
}
