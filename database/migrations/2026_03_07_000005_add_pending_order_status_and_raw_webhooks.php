<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("ALTER TYPE order_status ADD VALUE IF NOT EXISTS 'pending' BEFORE 'paid'");

        DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'pending'");

        Schema::create('raw_webhooks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('provider');
            $table->text('event_id')->nullable();
            $table->jsonb('payload');
            $table->timestampTz('received_at')->default(DB::raw('now()'));
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_webhooks');
        DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'paid'");
    }
};
