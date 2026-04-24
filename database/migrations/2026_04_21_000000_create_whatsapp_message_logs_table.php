<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('phone')->index();
            $table->string('template_name')->index();

            $table->longText('api_response')->nullable();
            $table->string('delivery_status')->default('queued')->index(); // queued|sent|failed
            $table->timestamp('sent_at')->nullable()->index();

            $table->timestamps();

            // Prevent duplicate sends for the same lead + campaign(template)
            $table->unique(['lead_id', 'template_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_logs');
    }
};

