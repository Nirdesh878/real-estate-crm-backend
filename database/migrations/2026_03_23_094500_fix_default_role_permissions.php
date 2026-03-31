<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $menuIds = DB::table('mst_menu')->pluck('id', 'key');

        // Admin should see admin dashboard + management pages by default.
        $adminEnabled = [
            (int) ($menuIds['admin_dashboard'] ?? 0),
            (int) ($menuIds['users'] ?? 0),
            (int) ($menuIds['permissions'] ?? 0),
        ];

        // User should see user dashboard by default.
        $userEnabled = [
            (int) ($menuIds['user_dashboard'] ?? 0),
        ];

        DB::table('mst_permissions')->where('role_id', 1)->update(['enabled' => false]);
        DB::table('mst_permissions')->where('role_id', 2)->update(['enabled' => false]);

        DB::table('mst_permissions')
            ->where('role_id', 1)
            ->whereIn('menu_id', array_filter($adminEnabled))
            ->update(['enabled' => true]);

        DB::table('mst_permissions')
            ->where('role_id', 2)
            ->whereIn('menu_id', array_filter($userEnabled))
            ->update(['enabled' => true]);
    }

    public function down(): void
    {
        // No-op.
    }
};