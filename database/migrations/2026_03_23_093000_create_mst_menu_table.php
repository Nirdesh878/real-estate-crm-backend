<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_menu', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('path');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        DB::table('mst_menu')->insert([
            ['id' => 1, 'key' => 'admin_dashboard', 'label' => 'Dashboard', 'path' => '/dashboard', 'sort' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'key' => 'user_dashboard', 'label' => 'Dashboard', 'path' => '/user-dashboard', 'sort' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'key' => 'users', 'label' => 'Users', 'path' => '/users', 'sort' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'key' => 'permissions', 'label' => 'Permissions', 'path' => '/permissions', 'sort' => 30, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_menu');
    }
};