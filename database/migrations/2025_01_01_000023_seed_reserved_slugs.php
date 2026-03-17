<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $slugs = [
        'www', 'app', 'api', 'platform', 'admin', 'docs', 'mail', 'static',
        'assets', 'status', 'support', 'help', 'billing', 'webhook', 'webhooks',
        'dashboard', 'login', 'register', 'signup', 'auth', 'oauth', 'health',
        'stripe', 'twilio', 'postmark', 'internal', 'system', 'pawpass',
    ];

    public function up(): void
    {
        $rows = array_map(fn ($slug) => ['slug' => $slug], $this->slugs);
        DB::table('reserved_slugs')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        DB::table('reserved_slugs')->whereIn('slug', $this->slugs)->delete();
    }
};
