<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'whatsapp_eligible')) {
                $table->boolean('whatsapp_eligible')->default(true)->after('phone')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'whatsapp_eligible')) {
                $table->dropColumn('whatsapp_eligible');
            }
        });
    }
};

