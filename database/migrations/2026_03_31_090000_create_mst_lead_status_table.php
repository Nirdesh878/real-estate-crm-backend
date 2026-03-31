<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_lead_status', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label', 255);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed defaults
        $defaults = [
            ['key' => 'new', 'label' => 'New', 'sort' => 10, 'is_active' => true],
            ['key' => 'contacted', 'label' => 'Contacted', 'sort' => 20, 'is_active' => true],
            ['key' => 'qualified', 'label' => 'Qualified', 'sort' => 30, 'is_active' => true],
            ['key' => 'unqualified', 'label' => 'Unqualified', 'sort' => 40, 'is_active' => true],
            ['key' => 'closed', 'label' => 'Closed', 'sort' => 50, 'is_active' => true],
        ];

        foreach ($defaults as $row) {
            DB::table('mst_lead_status')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'label' => $row['label'],
                    'sort' => (int) $row['sort'],
                    'is_active' => (bool) $row['is_active'],
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        // Normalize existing leads to known statuses (fallback: new)
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'status')) {
            $keys = DB::table('mst_lead_status')->pluck('key')->all();
            DB::table('leads')->whereNull('status')->orWhere('status', '')->update(['status' => 'new']);
            if (count($keys)) {
                DB::table('leads')->whereNotIn('status', $keys)->update(['status' => 'new']);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_lead_status');
    }
};
