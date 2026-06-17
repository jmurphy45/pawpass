<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:generate-keys {--show : Print keys to console only, do not write to .env}';

    protected $description = 'Generate VAPID keys for web push notifications';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $publicKey = $keys['publicKey'];
        $privateKey = $keys['privateKey'];

        if ($this->option('show')) {
            $this->line("VAPID_PUBLIC_KEY={$publicKey}");
            $this->line("VAPID_PRIVATE_KEY={$privateKey}");

            return self::SUCCESS;
        }

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error('.env file not found.');

            return self::FAILURE;
        }

        $env = file_get_contents($envPath);

        if (str_contains($env, 'VAPID_PUBLIC_KEY=')) {
            $env = preg_replace('/^VAPID_PUBLIC_KEY=.*/m', "VAPID_PUBLIC_KEY={$publicKey}", $env);
        } else {
            $env .= "\nVAPID_PUBLIC_KEY={$publicKey}";
        }

        if (str_contains($env, 'VAPID_PRIVATE_KEY=')) {
            $env = preg_replace('/^VAPID_PRIVATE_KEY=.*/m', "VAPID_PRIVATE_KEY={$privateKey}", $env);
        } else {
            $env .= "\nVAPID_PRIVATE_KEY={$privateKey}";
        }

        file_put_contents($envPath, $env);

        $this->info('VAPID keys written to .env');
        $this->line("VAPID_PUBLIC_KEY={$publicKey}");
        $this->line("VAPID_PRIVATE_KEY={$privateKey}");

        return self::SUCCESS;
    }
}
