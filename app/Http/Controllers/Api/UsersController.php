<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $roleId = (int) ($request->user()?->role_id ?? 0);

        // Root sees all. Admin sees managers + callers only.
        $query = User::query();

        if ($roleId === User::ROLE_ADMIN) {
            $query->whereIn('role_id', [User::ROLE_MANAGER, User::ROLE_CALLER]);
        }

        return $query
            ->select(['id', 'name', 'email', 'role_id', 'manager_id', 'created_at'])
            ->with([
                'role:id,name',
                'manager:id,name,email',
            ])
            ->orderByDesc('id')
            ->get();
    }

    private function assertCanCreateRole(int $actorRoleId, int $targetRoleId): void
    {
        if ($actorRoleId === User::ROLE_ROOT) {
            return;
        }

        if ($actorRoleId === User::ROLE_ADMIN) {
            if (in_array($targetRoleId, [User::ROLE_MANAGER, User::ROLE_CALLER], true)) {
                return;
            }
        }

        abort(403);
    }

    private function assertCanEditRole(int $actorRoleId, int $targetRoleId): void
    {
        // Same logic as create for now.
        $this->assertCanCreateRole($actorRoleId, $targetRoleId);
    }

    public function store(Request $request)
    {
        $actorRoleId = (int) ($request->user()?->role_id ?? 0);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role_id' => ['required', 'integer', 'exists:mst_role,id'],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role_id', User::ROLE_MANAGER),
            ],
        ]);

        $targetRoleId = (int) $data['role_id'];
        $this->assertCanCreateRole($actorRoleId, $targetRoleId);

        $managerId = null;
        if ($targetRoleId === User::ROLE_CALLER) {
            $managerId = array_key_exists('manager_id', $data) ? $data['manager_id'] : null;
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $targetRoleId,
            'manager_id' => $managerId,
        ]);

        return response()->json($user->load('role:id,name', 'manager:id,name,email'), 201);
    }

    public function update(Request $request, User $user)
    {
        $actorRoleId = (int) ($request->user()?->role_id ?? 0);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', Password::defaults()],
            'role_id' => ['required', 'integer', 'exists:mst_role,id'],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role_id', User::ROLE_MANAGER),
            ],
        ]);

        $targetRoleId = (int) $data['role_id'];
        $this->assertCanEditRole($actorRoleId, $targetRoleId);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role_id = $targetRoleId;

        if ($targetRoleId === User::ROLE_CALLER) {
            $user->manager_id = array_key_exists('manager_id', $data) ? $data['manager_id'] : null;
        } else {
            $user->manager_id = null;
        }

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return $user->load('role:id,name', 'manager:id,name,email');
    }

    public function destroy(Request $request, User $user)
    {
        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }
}