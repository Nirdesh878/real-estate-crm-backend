<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MstRole;
use App\Models\User;
use Illuminate\Http\Request;

class RolesController extends Controller
{
    public function index(Request $request)
    {
        $roleId = (int) ($request->user()?->role_id ?? 0);

        if ($roleId === User::ROLE_ROOT) {
            return MstRole::query()->select(['id', 'name'])->orderBy('id')->get();
        }

        if ($roleId === User::ROLE_ADMIN) {
            return MstRole::query()
                ->select(['id', 'name'])
                ->whereIn('id', [User::ROLE_MANAGER, User::ROLE_CALLER])
                ->orderBy('id')
                ->get();
        }

        return [];
    }
}