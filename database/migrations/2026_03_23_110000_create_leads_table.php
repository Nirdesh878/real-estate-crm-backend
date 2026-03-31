<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Source / tracking
            $table->string('platform')->nullable(); // e.g. meta, landing_page
            $table->string('lead_source')->nullable(); // meta_lead_form, landing_page
            $table->string('campaign_name')->nullable();
            $table->string('ad_set_name')->nullable();
            $table->string('ad_name')->nullable();
            $table->string('lead_form_name')->nullable();

            // Meta identifiers
            $table->string('meta_lead_id')->nullable()->unique();
            $table->string('meta_form_id')->nullable();
            $table->string('meta_ad_id')->nullable();
            $table->string('meta_adset_id')->nullable();
            $table->string('meta_campaign_id')->nullable();
            $table->string('meta_page_id')->nullable();

            // Basic fields
            $table->string('name')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('city')->nullable()->index();

            // Qualification
            $table->decimal('budget', 14, 2)->nullable();
            $table->string('plot_size')->nullable();
            $table->string('purpose')->nullable(); // investment/self_use
            $table->string('timeline_to_buy')->nullable();
            $table->boolean('loan_required')->nullable();

            $table->string('status')->default('new')->index();

            $table->json('qualification')->nullable();
            $table->json('raw_payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};