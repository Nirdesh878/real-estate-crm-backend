<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_leads', function (Blueprint $table) {
            $table->id();

            $table->string('leadgen_id')->unique();
            $table->string('form_id')->nullable()->index();
            $table->string('page_id')->nullable()->index();

            $table->string('ad_id')->nullable()->index();
            $table->string('adgroup_id')->nullable()->index();
            $table->string('campaign_id')->nullable()->index();

            $table->timestamp('created_time')->nullable()->index();

            $table->longText('raw_json')->nullable();

            $table->string('full_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('city')->nullable()->index();
            $table->string('state')->nullable()->index();
            $table->string('zip_code')->nullable()->index();
            $table->string('country')->nullable()->index();
            $table->string('job_title')->nullable();
            $table->string('company_name')->nullable();

            $table->json('custom_fields_json')->nullable();
            $table->timestamp('synced_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_leads');
    }
};

