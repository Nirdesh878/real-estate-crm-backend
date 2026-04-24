<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\MetaLead;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaLeadService
{
    /**
     * Quick token check (Graph API /me).
     *
     * @return array<string,mixed>
     */
    public function tokenInfo(): array
    {
        return $this->graphGet('me', ['fields' => 'id,name'], 15);
    }

    /**
     * List lead forms on a page (Graph API).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listLeadgenForms(string $pageId): array
    {
        $json = $this->graphGet("{$pageId}/leadgen_forms", [
            'fields' => 'id,name,status,locale',
            'limit' => 200,
        ]);

        $data = Arr::get($json, 'data', []);
        return is_array($data) ? $data : [];
    }

    /**
     * Fetch full lead details from the Graph API.
     *
     * @return array<string,mixed>
     */
    public function fetchLead(string $leadgenId): array
    {
        $json = $this->graphGet($leadgenId, [
            'fields' => 'created_time,field_data,ad_id,adgroup_id,campaign_id,form_id',
        ]);

        if ((bool) config('meta.log_payloads')) {
            Log::info('meta.graph.lead.response', [
                'leadgen_id' => $leadgenId,
                'response' => $json,
            ]);
        }

        return $json;
    }

    public function fetchName(string $objectId): ?string
    {
        try {
            $json = $this->graphGet($objectId, ['fields' => 'name'], 15);
        } catch (\Throwable) {
            return null;
        }

        $name = $json['name'] ?? null;
        return $name ? (string) $name : null;
    }

    /**
     * Upsert a Meta lead into `meta_leads`, optionally also into CRM `leads`.
     *
     * @param  array<string,mixed>  $context
     */
    public function syncLeadgenId(string $leadgenId, array $context = []): MetaLead
    {
        $lead = $this->fetchLead($leadgenId);
        return $this->upsertFromGraphLead($lead, $context);
    }

    /**
     * Pull recent leads from configured form IDs (or discover via page id) and upsert them.
     *
     * Useful during local development when webhooks cannot reach localhost.
     *
     * @return array{forms: array<int,string>, fetched: int, inserted: int, inserted_lead_ids: array<int,int>, inserted_lead_ids_truncated: bool, skipped: int, out_of_window: int, missing_id: int, errors: array<int,array<string,mixed>>}
     */
    public function pullRecentLeads(?Carbon $since = null, int $limitPerPage = 50, int $maxPagesPerForm = 3): array
    {
        $pageId = (string) config('meta.page_id', '');
        $forms = $this->resolveFormIds();

        if (! count($forms)) {
            return [
                'forms' => [],
                'fetched' => 0,
                'inserted' => 0,
                'inserted_lead_ids' => [],
                'inserted_lead_ids_truncated' => false,
                'skipped' => 0,
                'out_of_window' => 0,
                'missing_id' => 0,
                'errors' => [],
            ];
        }

        if (! $since) {
            $latest = MetaLead::query()->max('created_time');
            if ($latest) {
                // Pull a small overlap window to account for ordering/latency.
                $since = Carbon::parse((string) $latest)->subHour();
            } else {
                $since = now()->subDays(14);
            }
        }

        $fetched = 0;
        $inserted = 0;
        $insertedLeadIds = [];
        $insertedLeadIdsLimit = 500;
        $insertedLeadIdsTruncated = false;
        $skipped = 0;
        $outOfWindow = 0;
        $missingId = 0;
        $errors = [];
        $errorLimit = 25;

        foreach ($forms as $formId) {
            $after = null;
            for ($page = 0; $page < $maxPagesPerForm; $page++) {
                try {
                    [$items, $nextAfter] = $this->fetchFormLeadsPage($formId, $after, $limitPerPage);
                } catch (\Throwable $e) {
                    Log::error('meta.graph.form_leads.failed', [
                        'form_id' => $formId,
                        'message' => $e->getMessage(),
                    ]);

                    if (count($errors) < $errorLimit) {
                        $errors[] = [
                            'type' => 'form_fetch_failed',
                            'form_id' => $formId,
                            'message' => $e->getMessage(),
                        ];
                    }

                    break;
                }

                if (! count($items)) {
                    break;
                }

                $stopForm = false;
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $itemTime = $this->parseMetaTime(Arr::get($item, 'created_time'));
                    if ($itemTime && $itemTime->lt($since)) {
                        // Stop scanning this form once we hit older data.
                        $outOfWindow++;
                        $stopForm = true;
                        break;
                    }

                    $leadgenId = (string) Arr::get($item, 'id', '');
                    if (! $leadgenId) {
                        $missingId++;
                        continue;
                    }

                    $fetched++;

                    $exists = MetaLead::query()->where('leadgen_id', $leadgenId)->exists();

                    try {
                        $this->upsertFromGraphLead($item, [
                            'page_id' => $pageId,
                            'form_id' => $formId,
                        ]);
                        if ($exists) {
                            $skipped++;
                        } else {
                            $inserted++;
                            if (count($insertedLeadIds) < $insertedLeadIdsLimit) {
                                $crmLeadId = Lead::query()->where('meta_lead_id', $leadgenId)->value('id');
                                if ($crmLeadId) {
                                    $insertedLeadIds[] = (int) $crmLeadId;
                                }
                            } else {
                                $insertedLeadIdsTruncated = true;
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::error('meta.graph.lead.upsert_failed', [
                            'form_id' => $formId,
                            'leadgen_id' => $leadgenId,
                            'message' => $e->getMessage(),
                        ]);

                        if (count($errors) < $errorLimit) {
                            $errors[] = [
                                'type' => 'lead_upsert_failed',
                                'form_id' => $formId,
                                'leadgen_id' => $leadgenId,
                                'message' => $e->getMessage(),
                            ];
                        }
                    }
                }

                if ($stopForm) {
                    break;
                }

                if (! $nextAfter) {
                    break;
                }

                $after = $nextAfter;
            }
        }

        return [
            'forms' => $forms,
            'fetched' => $fetched,
            'inserted' => $inserted,
            'inserted_lead_ids' => $insertedLeadIds,
            'inserted_lead_ids_truncated' => $insertedLeadIdsTruncated,
            'skipped' => $skipped,
            'out_of_window' => $outOfWindow,
            'missing_id' => $missingId,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{0: array<int,array<string,mixed>>, 1: string|null}
     */
    private function fetchFormLeadsPage(string $formId, ?string $after = null, int $limit = 50): array
    {
        $query = [
            'fields' => 'id,created_time,field_data,ad_id,adgroup_id,campaign_id,form_id',
            'limit' => $limit,
        ];
        if ($after) {
            $query['after'] = $after;
        }

        $json = $this->graphGet("{$formId}/leads", $query);

        $data = Arr::get($json, 'data', []);
        $items = is_array($data) ? $data : [];

        $nextAfter = Arr::get($json, 'paging.cursors.after');
        $afterCursor = $nextAfter ? (string) $nextAfter : null;

        if ((bool) config('meta.log_payloads')) {
            Log::info('meta.graph.form_leads.page', [
                'form_id' => $formId,
                'count' => count($items),
                'after' => $after,
                'next_after' => $afterCursor,
            ]);
        }

        return [$items, $afterCursor];
    }

    /**
     * Upsert using an already-fetched lead payload (either /{leadgen_id} or /{form_id}/leads item).
     *
     * @param  array<string,mixed>  $lead
     * @param  array<string,mixed>  $context
     */
    public function upsertFromGraphLead(array $lead, array $context = []): MetaLead
    {
        $leadgenId = (string) (Arr::get($lead, 'id') ?? Arr::get($context, 'leadgen_id') ?? '');
        if (! $leadgenId) {
            throw new \InvalidArgumentException('Missing leadgen id');
        }

        $formId = (string) (Arr::get($lead, 'form_id') ?? Arr::get($context, 'form_id') ?? '');
        $pageId = (string) (Arr::get($context, 'page_id') ?? (string) config('meta.page_id', ''));

        $createdTime = $this->parseMetaTime(Arr::get($lead, 'created_time') ?? Arr::get($context, 'created_time'));

        [$mapped, $custom] = $this->parseFieldData((array) Arr::get($lead, 'field_data', []));

        $metaLead = MetaLead::query()->updateOrCreate(
            ['leadgen_id' => $leadgenId],
            [
                'form_id' => $formId ?: null,
                'page_id' => $pageId ?: null,
                'ad_id' => ($v = (string) (Arr::get($lead, 'ad_id') ?? Arr::get($context, 'ad_id') ?? '')) ? $v : null,
                'adgroup_id' => ($v = (string) (Arr::get($lead, 'adgroup_id') ?? Arr::get($context, 'adgroup_id') ?? '')) ? $v : null,
                'campaign_id' => ($v = (string) (Arr::get($lead, 'campaign_id') ?? Arr::get($context, 'campaign_id') ?? '')) ? $v : null,
                'created_time' => $createdTime,
                'raw_json' => json_encode($lead, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'full_name' => $mapped['full_name'] ?? null,
                'first_name' => $mapped['first_name'] ?? null,
                'last_name' => $mapped['last_name'] ?? null,
                'email' => $mapped['email'] ?? null,
                'phone' => $mapped['phone'] ?? null,
                'city' => $mapped['city'] ?? null,
                'state' => $mapped['state'] ?? null,
                'zip_code' => $mapped['zip_code'] ?? null,
                'country' => $mapped['country'] ?? null,
                'job_title' => $mapped['job_title'] ?? null,
                'company_name' => $mapped['company_name'] ?? null,
                'custom_fields_json' => $custom ?: null,
                'synced_at' => now(),
            ],
        );

        if ((bool) config('meta.sync_to_crm_leads', true)) {
            $this->syncToCrmLead($metaLead, $lead, $context);
        }

        return $metaLead;
    }

    /**
     * @param  array<int,array<string,mixed>>  $fieldData
     * @return array{0: array<string,string>, 1: array<string,mixed>}
     */
    public function parseFieldData(array $fieldData): array
    {
        $mapped = [];
        $custom = [];

        foreach ($fieldData as $item) {
            $rawName = (string) Arr::get($item, 'name', '');
            $values = Arr::get($item, 'values', []);
            $firstValue = is_array($values) && array_key_exists(0, $values) ? (string) $values[0] : null;
            if ($rawName === '') continue;

            $name = $this->normalizeFieldName($rawName);
            if ($name === '') continue;

            $col = $this->mapToColumn($name);
            if ($col) {
                if ($firstValue !== null && $firstValue !== '') {
                    $mapped[$col] = $this->normalizeValue($col, $firstValue);
                }
                continue;
            }

            // Unknown/custom fields are stored as-is.
            $custom[$name] = $values;
        }

        // Derive full_name when only first/last exist.
        if (! isset($mapped['full_name'])) {
            $first = $mapped['first_name'] ?? null;
            $last = $mapped['last_name'] ?? null;
            $full = trim((string) ($first ? $first.' ' : '').(string) ($last ?? ''));
            if ($full !== '') {
                $mapped['full_name'] = $full;
            }
        }

        return [$mapped, $custom];
    }

    private function syncToCrmLead(MetaLead $metaLead, array $leadJson, array $context = []): void
    {
        $leadgenId = (string) $metaLead->leadgen_id;
        if (! $leadgenId) {
            return;
        }

        $metaFormId = $metaLead->form_id;
        $metaPageId = $metaLead->page_id;

        Lead::query()->updateOrCreate(
            ['meta_lead_id' => $leadgenId],
            [
                'platform' => 'meta',
                'lead_source' => 'meta_lead_form',
                'meta_lead_id' => $leadgenId,
                'meta_form_id' => $metaFormId ?: null,
                'meta_ad_id' => ($v = (string) (Arr::get($leadJson, 'ad_id') ?? Arr::get($context, 'ad_id') ?? '')) ? $v : null,
                'meta_adset_id' => ($v = (string) (Arr::get($leadJson, 'adgroup_id') ?? Arr::get($context, 'adgroup_id') ?? '')) ? $v : null,
                'meta_campaign_id' => ($v = (string) (Arr::get($leadJson, 'campaign_id') ?? Arr::get($context, 'campaign_id') ?? '')) ? $v : null,
                'meta_page_id' => $metaPageId ?: null,
                'lead_form_name' => null,
                'name' => $metaLead->full_name ?: null,
                'phone' => $metaLead->phone ?: null,
                'email' => $metaLead->email ?: null,
                'city' => $metaLead->city ?: null,
                'raw_payload' => $leadJson,
                'status' => 'new',
            ],
        );
    }

    private function parseMetaTime($value): ?Carbon
    {
        if (! $value) return null;
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeFieldName(string $value): string
    {
        $s = strtolower(trim($value));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? $s;
        return trim($s, '_');
    }

    private function mapToColumn(string $normalizedName): ?string
    {
        $map = [
            'full_name' => 'full_name',
            'name' => 'full_name',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'email' => 'email',
            'phone_number' => 'phone',
            'phone' => 'phone',
            'mobile_phone_number' => 'phone',
            'mobile' => 'phone',
            'city' => 'city',
            'state' => 'state',
            'zip' => 'zip_code',
            'zip_code' => 'zip_code',
            'postal_code' => 'zip_code',
            'country' => 'country',
            'job_title' => 'job_title',
            'company_name' => 'company_name',
            'company' => 'company_name',
        ];

        return $map[$normalizedName] ?? null;
    }

    private function normalizeValue(string $column, string $value): string
    {
        $v = trim($value);

        if ($column === 'phone') {
            $v = preg_replace('/[^0-9+]/', '', $v) ?? $v;
        }

        if ($column === 'email') {
            $v = strtolower($v);
        }

        return $v;
    }

    /**
     * @return array<int,string>
     */
    private function resolveFormIds(): array
    {
        $explicit = (array) config('meta.form_ids', []);
        $explicit = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $explicit)));
        if (count($explicit)) {
            return $explicit;
        }

        $pageId = (string) config('meta.page_id', '');
        if (! $pageId) {
            return [];
        }

        $forms = $this->listLeadgenForms($pageId);
        $ids = [];
        foreach ($forms as $f) {
            $id = (string) Arr::get($f, 'id', '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string,mixed>
     */
    private function graphGet(string $path, array $query = [], int $timeoutSeconds = 20): array
    {
        $token = (string) config('meta.access_token');
        $version = (string) config('meta.graph_version', 'v25.0');
        $base = rtrim((string) config('meta.api_base', 'https://graph.facebook.com'), '/');

        if (! $token) {
            throw new \RuntimeException('META_ACCESS_TOKEN is not configured.');
        }

        $path = ltrim($path, '/');
        $url = "{$base}/{$version}/{$path}";

        $response = Http::timeout($timeoutSeconds)
            ->retry(2, 250)
            ->get($url, array_merge($query, [
                'access_token' => $token,
            ]));

        $response->throw();

        /** @var array<string,mixed> */
        return $response->json() ?? [];
    }
}
