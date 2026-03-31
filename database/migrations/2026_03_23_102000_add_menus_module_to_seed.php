<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('mst_menu')->where('key', 'menus')->exists();

        if (! $exists) {
            $nextId = (int) (DB::table('mst_menu')->max('id') ?? 0) + 1;

            DB::table('mst_menu')->insert([
                'id' => $nextId,
                'key' => 'menus',
                'label' => 'Menus',
                'path' => '/menus',
                'sort' => 25,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create permission rows for each role.
            $roleIds = DB::table('mst_role')->pluck('id');
            foreach ($roleIds as $roleId) {
                DB::table('mst_permissions')->insert([
                    'role_id' => (int) $roleId,
                    'menu_id' => $nextId,
                    'enabled' => (int) $roleId === 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Ensure admin has menus enabled.
        $menuId = DB::table('mst_menu')->where('key', 'menus')->value('id');
        if ($menuId) {
            DB::table('mst_permissions')
                ->where('role_id', 1)
                ->where('menu_id', (int) $menuId)
                ->update(['enabled' => true]);
        }
    }

    public function down(): void
    {
        // No-op.
    }
};