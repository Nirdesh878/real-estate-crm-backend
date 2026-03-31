<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MstMenu;
use App\Models\MstPermission;
use Illuminate\Http\Request;

class MenusController extends Controller
{
    public function index()
    {
        return MstMenu::query()
            ->select(['id', 'key', 'label', 'path', 'sort'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100', 'unique:mst_menu,key'],
            'label' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $nextId = (int) (MstMenu::query()->max('id') ?? 0) + 1;

        $menu = MstMenu::create([
            'id' => $nextId,
            'key' => $data['key'],
            'label' => $data['label'],
            'path' => $data['path'],
            'sort' => (int) ($data['sort'] ?? 0),
        ]);

        // Ensure permissions rows exist for each role (disabled by default).
        $roleIds = \App\Models\MstRole::query()->pluck('id')->all();
        foreach ($roleIds as $roleId) {
            MstPermission::query()->updateOrCreate(
                ['role_id' => (int) $roleId, 'menu_id' => (int) $menu->id],
                ['enabled' => false]
            );
        }

        return response()->json($menu, 201);
    }

    public function update(Request $request, MstMenu $menu)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100', 'unique:mst_menu,key,'.$menu->id],
            'label' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $menu->key = $data['key'];
        $menu->label = $data['label'];
        $menu->path = $data['path'];
        $menu->sort = (int) ($data['sort'] ?? 0);
        $menu->save();

        return $menu;
    }

    public function destroy(MstMenu $menu)
    {
        if (in_array($menu->key, ['admin_dashboard', 'user_dashboard'], true)) {
            return response()->json([
                'message' => 'This menu cannot be deleted.',
            ], 422);
        }

        MstPermission::query()->where('menu_id', (int) $menu->id)->delete();
        $menu->delete();

        return response()->noContent();
    }
}