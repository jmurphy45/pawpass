<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_webhooks', function (Blueprint $table) {
            $table->unique(['provider', 'event_id'], 'raw_webhooks_provider_event_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('raw_webhooks', function (Blueprint $table) {
            $table->dropUnique('raw_webhooks_provider_event_id_unique');
        });
    }
};
