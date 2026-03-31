<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('mst_menu')->where('key', 'leads')->exists();

        if (! $exists) {
            $nextId = (int) (DB::table('mst_menu')->max('id') ?? 0) + 1;

            DB::table('mst_menu')->insert([
                'id' => $nextId,
                'key' => 'leads',
                'label' => 'Leads',
                'path' => '/leads',
                'sort' => 15,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

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
        } else {
            $menuId = DB::table('mst_menu')->where('key', 'leads')->value('id');
            if ($menuId) {
                DB::table('mst_permissions')
                    ->where('role_id', 1)
                    ->where('menu_id', (int) $menuId)
                    ->update(['enabled' => true]);
            }
        }
    }

    public function down(): void
    {
        // No-op.
    }
};