<?php

namespace App\Console\Commands;

use App\Services\GoGetSSLService;
use Illuminate\Console\Command;

class ClearGoGetSSLAuthCommand extends Command
{
    protected $signature = 'gogetssl:clear-auth';
    protected $description = 'Clear GoGetSSL auth key cache';

    public function handle(): int
    {
        $service = app(GoGetSSLService::class);
        $service->clearAuthCache();
        
        $this->info('✅ GoGetSSL auth key cache cleared');
        
        return self::SUCCESS;
    }
}
