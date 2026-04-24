<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetaLead;
use App\Services\MetaLeadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaLeadsController extends Controller
{
    // Admin helper: pull recent leads from Meta and save them.
    public function pull(Request $request, MetaLeadService $meta)
    {
        $request->validate([
            'since' => ['nullable', 'string'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'pages' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $hours = (int) ($request->input('hours') ?? 48);
        $limit = (int) ($request->input('limit') ?? 50);
        $pages = (int) ($request->input('pages') ?? 3);

        $since = null;
        if ($request->filled('since')) {
            try {
                $since = Carbon::parse((string) $request->input('since'));
            } catch (\Throwable) {
                $since = null;
            }
        } else {
            $since = now()->subHours($hours);
        }

        $result = $meta->pullRecentLeads($since, $limit, $pages);

        return response()->json([
            'ok' => true,
            'since' => $since ? $since->toISOString() : null,
            ...$result,
        ]);
    }

    // Admin helper: refresh leads on-demand from UI.
    public function refresh(Request $request, MetaLeadService $meta)
    {
        $request->validate([
            'hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'pages' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $hours = (int) ($request->input('hours') ?? 168);
        $limit = (int) ($request->input('limit') ?? 50);
        $pages = (int) ($request->input('pages') ?? 3);

        $since = now()->subHours($hours);

        try {
            Log::info('meta.refresh.start', [
                'user_id' => optional($request->user())->id,
                'hours' => $hours,
                'limit' => $limit,
                'pages' => $pages,
            ]);

            $result = $meta->pullRecentLeads($since, $limit, $pages);

            Log::info('meta.refresh.done', [
                'user_id' => optional($request->user())->id,
                'forms_checked' => count($result['forms'] ?? []),
                'leads_fetched' => (int) ($result['fetched'] ?? 0),
                'inserted' => (int) ($result['inserted'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
                'errors' => is_array($result['errors'] ?? null) ? count($result['errors']) : null,
            ]);

            return response()->json([
                'success' => true,
                'window_hours' => $hours,
                'since' => $since->toISOString(),
                'forms_checked' => count($result['forms'] ?? []),
                'leads_fetched' => (int) ($result['fetched'] ?? 0),
                'inserted' => (int) ($result['inserted'] ?? 0),
                'inserted_lead_ids' => $result['inserted_lead_ids'] ?? [],
                'inserted_lead_ids_truncated' => (bool) ($result['inserted_lead_ids_truncated'] ?? false),
                'skipped' => (int) ($result['skipped'] ?? 0),
                'out_of_window' => (int) ($result['out_of_window'] ?? 0),
                'missing_id' => (int) ($result['missing_id'] ?? 0),
                'errors' => $result['errors'] ?? [],
                'last_refreshed_at' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('meta.refresh.failed', [
                'user_id' => optional($request->user())->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'window_hours' => $hours,
                'forms_checked' => 0,
                'leads_fetched' => 0,
                'inserted' => 0,
                'skipped' => 0,
                'out_of_window' => 0,
                'missing_id' => 0,
                'errors' => [
                    ['message' => $e->getMessage()],
                ],
            ], 500);
        }
    }

    // Admin helper: list page lead forms (for debugging/config).
    public function forms(Request $request, MetaLeadService $meta)
    {
        $pageId = (string) config('meta.page_id', '');
        if (! $pageId) {
            return response()->json([
                'message' => 'META_PAGE_ID is not configured.',
            ], 422);
        }

        return response()->json([
            'page_id' => $pageId,
            'forms' => $meta->listLeadgenForms($pageId),
        ]);
    }

    // Admin helper: quick health/status.
    public function status(Request $request, MetaLeadService $meta)
    {
        $pageId = (string) config('meta.page_id', '');

        $tokenOk = false;
        $tokenError = null;
        $formsCount = null;

        try {
            $info = $meta->tokenInfo();
            $tokenOk = isset($info['id']) || isset($info['name']);

            if ($pageId) {
                $forms = $meta->listLeadgenForms($pageId);
                $formsCount = count($forms);
            }
        } catch (\Throwable $e) {
            $tokenOk = false;
            $tokenError = $e->getMessage();
        }

        $lastSyncedAt = MetaLead::query()->max('synced_at');
        $lastCreatedTime = MetaLead::query()->max('created_time');

        return response()->json([
            'ok' => true,
            'graph_version' => (string) config('meta.graph_version', ''),
            'page_id' => $pageId ?: null,
            'form_ids_configured' => (array) config('meta.form_ids', []),
            'token_configured' => (bool) config('meta.access_token'),
            'token_ok' => $tokenOk,
            'token_error' => $tokenError,
            'forms_count' => $formsCount,
            'last_synced_at' => $lastSyncedAt ? Carbon::parse((string) $lastSyncedAt)->toISOString() : null,
            'last_lead_created_time' => $lastCreatedTime ? Carbon::parse((string) $lastCreatedTime)->toISOString() : null,
        ]);
    }
}
