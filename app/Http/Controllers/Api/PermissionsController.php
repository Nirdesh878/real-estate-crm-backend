<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MstMenu;
use App\Models\MstPermission;
use App\Models\MstRole;
use Illuminate\Http\Request;

class PermissionsController extends Controller
{
    public function show(MstRole $role)
    {
        $menus = MstMenu::query()->select(['id', 'key', 'label', 'path', 'sort'])->orderBy('sort')->orderBy('id')->get();
        $permissions = MstPermission::query()
            ->where('role_id', $role->id)
            ->get()
            ->keyBy('menu_id');

        return $menus->map(function ($menu) use ($permissions) {
            $perm = $permissions->get($menu->id);
            return [
                'menu_id' => $menu->id,
                'key' => $menu->key,
                'label' => $menu->label,
                'path' => $menu->path,
                'sort' => $menu->sort,
                'enabled' => $perm ? (bool) $perm->enabled : false,
            ];
        });
    }

    public function update(Request $request, MstRole $role)
    {
        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*.menu_id' => ['required', 'integer', 'exists:mst_menu,id'],
            'permissions.*.enabled' => ['required', 'boolean'],
        ]);

        foreach ($data['permissions'] as $perm) {
            MstPermission::query()->updateOrCreate(
                ['role_id' => $role->id, 'menu_id' => $perm['menu_id']],
                ['enabled' => (bool) $perm['enabled']]
            );
        }

        return response()->noContent();
    }
}