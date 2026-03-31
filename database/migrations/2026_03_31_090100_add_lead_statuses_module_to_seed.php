<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('mst_menu')->where('key', 'lead_statuses')->exists();

        if (! $exists) {
            $nextId = (int) (DB::table('mst_menu')->max('id') ?? 0) + 1;

            DB::table('mst_menu')->insert([
                'id' => $nextId,
                'key' => 'lead_statuses',
                'label' => 'Lead Statuses',
                'path' => '/lead-statuses',
                'sort' => 16,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roleIds = DB::table('mst_role')->pluck('id');
            foreach ($roleIds as $roleId) {
                $rid = (int) $roleId;
                DB::table('mst_permissions')->insert([
                    'role_id' => $rid,
                    'menu_id' => $nextId,
                    'enabled' => ($rid === 1 || $rid === 4),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } else {
            $menuId = DB::table('mst_menu')->where('key', 'lead_statuses')->value('id');
            if ($menuId) {
                DB::table('mst_permissions')
                    ->whereIn('role_id', [1, 4])
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
