<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename existing role 2 from "user" to "caller" (keeps existing IDs stable).
        DB::table('mst_role')->updateOrInsert(
            ['id' => 2],
            ['name' => 'caller', 'updated_at' => now(), 'created_at' => DB::raw('COALESCE(created_at, NOW())')]
        );

        DB::table('mst_role')->updateOrInsert(
            ['id' => 3],
            ['name' => 'manager', 'created_at' => now(), 'updated_at' => now()]
        );

        DB::table('mst_role')->updateOrInsert(
            ['id' => 4],
            ['name' => 'root', 'created_at' => now(), 'updated_at' => now()]
        );

        $menuIds = DB::table('mst_menu')->pluck('id', 'key');

        // Ensure permissions rows exist for new roles for all menus.
        $menus = DB::table('mst_menu')->select(['id'])->get();
        foreach ([3, 4] as $roleId) {
            foreach ($menus as $menu) {
                DB::table('mst_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => (int) $menu->id],
                    ['enabled' => false, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // Default enables
        $adminEnabledKeys = ['admin_dashboard', 'users', 'permissions', 'menus', 'leads'];
        $userEnabledKeys = ['user_dashboard', 'leads'];

        $adminEnabled = array_values(array_filter(array_map(fn ($k) => (int) ($menuIds[$k] ?? 0), $adminEnabledKeys)));
        $userEnabled = array_values(array_filter(array_map(fn ($k) => (int) ($menuIds[$k] ?? 0), $userEnabledKeys)));

        // Root (4): admin defaults
        DB::table('mst_permissions')->where('role_id', 4)->update(['enabled' => false]);
        if (count($adminEnabled)) {
            DB::table('mst_permissions')->where('role_id', 4)->whereIn('menu_id', $adminEnabled)->update(['enabled' => true]);
        }

        // Manager (3): user defaults
        DB::table('mst_permissions')->where('role_id', 3)->update(['enabled' => false]);
        if (count($userEnabled)) {
            DB::table('mst_permissions')->where('role_id', 3)->whereIn('menu_id', $userEnabled)->update(['enabled' => true]);
        }

        // Caller (2): user defaults (keep existing rows, but ensure leads enabled too)
        if (count($userEnabled)) {
            DB::table('mst_permissions')->where('role_id', 2)->whereIn('menu_id', $userEnabled)->update(['enabled' => true]);
        }
    }

    public function down(): void
    {
        // No-op.
    }
};