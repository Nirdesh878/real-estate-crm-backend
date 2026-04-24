<?php

namespace App;

use App\Services\MetaLeadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMetaLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string,mixed>  $context
     */
    public function __construct(
        public string $leadgenId,
        public array $context = [],
    ) {
    }

    public function handle(MetaLeadService $meta): void
    {
        try {
            $meta->syncLeadgenId($this->leadgenId, $this->context);
        } catch (\Throwable $e) {
            Log::error('meta.lead.sync_failed', [
                'leadgen_id' => $this->leadgenId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

