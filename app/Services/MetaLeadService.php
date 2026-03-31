<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MetaLeadService
{
    public function fetchLead(string $leadId): array
    {
        $token = env('META_ACCESS_TOKEN');
        $version = env('META_GRAPH_VERSION', 'v19.0');

        if (! $token) {
            throw new \RuntimeException('META_ACCESS_TOKEN is not configured.');
        }

        $url = "https://graph.facebook.com/{$version}/{$leadId}";

        $response = Http::timeout(15)->get($url, [
            'access_token' => $token,
            'fields' => 'created_time,field_data,ad_id,adgroup_id,campaign_id,form_id',
        ]);

        $response->throw();

        return $response->json();
    }

    public function fetchName(string $objectId): ?string
    {
        $token = env('META_ACCESS_TOKEN');
        $version = env('META_GRAPH_VERSION', 'v19.0');

        if (! $token) return null;

        $url = "https://graph.facebook.com/{$version}/{$objectId}";

        $response = Http::timeout(15)->get($url, [
            'access_token' => $token,
            'fields' => 'name',
        ]);

        if (! $response->successful()) return null;

        return $response->json('name');
    }
}