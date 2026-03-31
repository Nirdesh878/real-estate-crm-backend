<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeadIngestController extends Controller
{
    public function ingest(Request $request)
    {
        $secret = env('LEAD_INGEST_SECRET');
        if ($secret && $request->header('X-Lead-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],

            'budget' => ['nullable'],
            'plot_size' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:50'],
            'timeline_to_buy' => ['nullable', 'string', 'max:100'],
            'loan_required' => ['nullable', 'boolean'],

            'campaign_name' => ['nullable', 'string', 'max:255'],
            'ad_set_name' => ['nullable', 'string', 'max:255'],
            'ad_name' => ['nullable', 'string', 'max:255'],
            'lead_form_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
        ]);

        $qualification = $request->input('qualification');
        if (! is_array($qualification)) {
            $qualification = [];
        }

        $lead = Lead::create([
            'platform' => $data['platform'] ?? 'landing_page',
            'lead_source' => 'landing_page',
            'campaign_name' => $data['campaign_name'] ?? null,
            'ad_set_name' => $data['ad_set_name'] ?? null,
            'ad_name' => $data['ad_name'] ?? null,
            'lead_form_name' => $data['lead_form_name'] ?? null,

            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,

            'budget' => isset($data['budget']) ? (float) preg_replace('/[^0-9.]/', '', (string) $data['budget']) : null,
            'plot_size' => $data['plot_size'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'timeline_to_buy' => $data['timeline_to_buy'] ?? null,
            'loan_required' => array_key_exists('loan_required', $data) ? (bool) $data['loan_required'] : null,

            'qualification' => $qualification,
            'raw_payload' => $request->all(),
            'status' => 'new',
        ]);

        return response()->json([
            'id' => $lead->id,
            'message' => 'Lead captured',
            'ref' => Str::uuid()->toString(),
        ], 201);
    }
}