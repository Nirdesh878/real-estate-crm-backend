<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('role_id');
            $table->unsignedSmallInteger('menu_id');
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'menu_id']);
            $table->foreign('role_id')->references('id')->on('mst_role');
            $table->foreign('menu_id')->references('id')->on('mst_menu');
        });

        // Default permissions: admin has all, user has user dashboard only.
        $now = now();
        $menus = DB::table('mst_menu')->select(['id', 'key'])->get();

        foreach ([1, 2] as $roleId) {
            foreach ($menus as $menu) {
                $enabled = false;

                if ($roleId === 1) {
                    $enabled = true;
                } elseif ($roleId === 2 && $menu->key === 'user_dashboard') {
                    $enabled = true;
                }

                DB::table('mst_permissions')->insert([
                    'role_id' => $roleId,
                    'menu_id' => $menu->id,
                    'enabled' => $enabled,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_permissions');
    }
};