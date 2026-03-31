<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\MstLeadStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadStatusesController extends Controller
{
    public function index()
    {
        return MstLeadStatus::query()
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'key', 'label', 'sort', 'is_active', 'created_at', 'updated_at']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('mst_lead_status', 'key')],
            'label' => ['required', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $status = MstLeadStatus::create([
            'key' => (string) $data['key'],
            'label' => (string) $data['label'],
            'sort' => (int) ($data['sort'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return response()->json($status, 201);
    }

    public function update(Request $request, MstLeadStatus $leadStatus)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('mst_lead_status', 'key')->ignore($leadStatus->id)],
            'label' => ['required', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Prevent breaking existing leads if key changes.
        if ($leadStatus->key !== $data['key']) {
            $inUse = Lead::query()->where('status', $leadStatus->key)->exists();
            if ($inUse) {
                return response()->json(['message' => 'Status key is in use by leads.'], 422);
            }
        }

        $leadStatus->fill([
            'key' => (string) $data['key'],
            'label' => (string) $data['label'],
            'sort' => (int) ($data['sort'] ?? 0),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $leadStatus->is_active,
        ]);
        $leadStatus->save();

        return $leadStatus;
    }

    public function destroy(MstLeadStatus $leadStatus)
    {
        $inUse = Lead::query()->where('status', $leadStatus->key)->exists();
        if ($inUse) {
            return response()->json(['message' => 'Status is in use by leads.'], 422);
        }

        $leadStatus->delete();

        return response()->noContent();
    }
}
