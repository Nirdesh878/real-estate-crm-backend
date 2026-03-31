<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadsController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query()->with(['assignee:id,name,email'])->orderByDesc('id');

        if ($request->filled('platform')) {
            $query->where('platform', $request->string('platform'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $q = (string) $request->string('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('campaign_name', 'like', "%{$q}%");
            });
        }

        return $query->limit(200)->get([
            'id',
            'platform',
            'lead_source',
            'name',
            'phone',
            'email',
            'city',
            'budget',
            'plot_size',
            'purpose',
            'timeline_to_buy',
            'loan_required',
            'campaign_name',
            'ad_set_name',
            'ad_name',
            'lead_form_name',
            'status',
            'assigned_user_id',
            'follow_up_at',
            'created_at',
        ]);
    }

    public function show(Lead $lead)
    {
        return $lead->load('assignee:id,name,email');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $lead = Lead::create($data);

        return response()->json($lead->load('assignee:id,name,email'), 201);
    }

    public function update(Request $request, Lead $lead)
    {
        $data = $this->validated($request, updating: true);

        $lead->fill($data);
        $lead->save();

        return $lead->load('assignee:id,name,email');
    }

    private function validated(Request $request, bool $updating = false): array
    {
        $statusRule = $updating
            ? Rule::exists('mst_lead_status', 'key')
            : Rule::exists('mst_lead_status', 'key')->where('is_active', true);

        $rules = [
            'status' => ['nullable', 'string', 'max:50', $statusRule],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'follow_up_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],

            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],

            'budget' => ['nullable'],
            'plot_size' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:50'],
            'timeline_to_buy' => ['nullable', 'string', 'max:100'],
            'loan_required' => ['nullable', 'boolean'],

            'platform' => ['nullable', 'string', 'max:50'],
            'lead_source' => ['nullable', 'string', 'max:100'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'ad_set_name' => ['nullable', 'string', 'max:255'],
            'ad_name' => ['nullable', 'string', 'max:255'],
            'lead_form_name' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'string', 'max:2048'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
        ];

        $data = $request->validate($rules);

        if (array_key_exists('budget', $data)) {
            $b = $data['budget'];
            $data['budget'] = is_null($b) ? null : (float) preg_replace('/[^0-9.]/', '', (string) $b);
        }

        return $data;
    }
}
