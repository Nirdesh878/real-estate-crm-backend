<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('meta:pull-leads {--since=} {--hours=} {--limit=50} {--pages=3}', function () {
    /** @var \App\Services\MetaLeadService $meta */
    $meta = app(\App\Services\MetaLeadService::class);

    $limit = (int) $this->option('limit');
    $pages = (int) $this->option('pages');

    $since = null;
    if ($this->option('since')) {
        try {
            $since = \Carbon\Carbon::parse((string) $this->option('since'));
        } catch (\Throwable $e) {
            $this->error('Invalid --since value');
            return Command::FAILURE;
        }
    } elseif ($this->option('hours')) {
        $hours = (int) $this->option('hours');
        if ($hours > 0) {
            $since = now()->subHours($hours);
        }
    }

    try {
        $result = $meta->pullRecentLeads($since, $limit, $pages);
        $this->info('Meta leads pull complete');
        $this->line('Forms: '.implode(',', $result['forms'] ?? []));
        $this->line('Fetched: '.(string) ($result['fetched'] ?? 0).', upserted: '.(string) ($result['upserted'] ?? 0));
        return Command::SUCCESS;
    } catch (\Throwable $e) {
        $this->error('Meta leads pull failed: '.$e->getMessage());
        return Command::FAILURE;
    }
})->purpose('Pull recent Meta Lead Ads leads and save into DB');
