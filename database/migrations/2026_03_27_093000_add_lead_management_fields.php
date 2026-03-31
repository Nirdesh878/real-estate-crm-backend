<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'assigned_user_id')) {
                $table->foreignId('assigned_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
                $table->index('assigned_user_id');
            }

            if (! Schema::hasColumn('leads', 'follow_up_at')) {
                $table->timestamp('follow_up_at')->nullable()->after('assigned_user_id')->index();
            }

            if (! Schema::hasColumn('leads', 'notes')) {
                $table->text('notes')->nullable()->after('follow_up_at');
            }

            if (! Schema::hasColumn('leads', 'source_url')) {
                $table->string('source_url')->nullable()->after('lead_form_name');
            }

            if (! Schema::hasColumn('leads', 'utm_source')) {
                $table->string('utm_source')->nullable()->after('source_url');
                $table->string('utm_medium')->nullable()->after('utm_source');
                $table->string('utm_campaign')->nullable()->after('utm_medium');
                $table->string('utm_content')->nullable()->after('utm_campaign');
                $table->string('utm_term')->nullable()->after('utm_content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'assigned_user_id')) {
                $table->dropConstrainedForeignId('assigned_user_id');
            }

            if (Schema::hasColumn('leads', 'follow_up_at')) {
                $table->dropColumn('follow_up_at');
            }

            if (Schema::hasColumn('leads', 'notes')) {
                $table->dropColumn('notes');
            }

            if (Schema::hasColumn('leads', 'source_url')) {
                $table->dropColumn('source_url');
            }

            if (Schema::hasColumn('leads', 'utm_source')) {
                $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']);
            }
        });
    }
};