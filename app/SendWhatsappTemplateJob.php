<?php

namespace App;

use App\Models\WhatsappMessageLog;
use App\Services\EmovurWhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsappTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $logId,
        public array $variables = [],
    ) {
    }

    public function handle(EmovurWhatsappService $emovur): void
    {
        $logRow = WhatsappMessageLog::query()->find($this->logId);
        if (! $logRow) {
            return;
        }

        if (in_array((string) $logRow->delivery_status, ['sent'], true)) {
            return;
        }

        try {
            $res = $emovur->sendTemplate((string) $logRow->phone, (string) $logRow->template_name, $this->variables);

            $logRow->api_response = json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $logRow->delivery_status = $res['ok'] ? 'sent' : 'failed';
            $logRow->sent_at = now();
            $logRow->save();
        } catch (\Throwable $e) {
            Log::error('whatsapp.send.job_failed', [
                'log_id' => $this->logId,
                'message' => $e->getMessage(),
            ]);

            $logRow->api_response = json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $logRow->delivery_status = 'failed';
            $logRow->sent_at = now();
            $logRow->save();

            throw $e;
        }
    }
}

