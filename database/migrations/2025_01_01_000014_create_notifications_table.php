<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('user_id', 26);
            $table->text('type');
            $table->text('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('user_id')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE notifications ADD COLUMN channel notif_channel NOT NULL');
        DB::statement("ALTER TABLE notifications ADD COLUMN status notif_status NOT NULL DEFAULT 'queued'");
        DB::statement('CREATE INDEX notifications_user_id_idx ON notifications(user_id, created_at DESC)');
        DB::statement('CREATE INDEX notifications_tenant_id_idx ON notifications(tenant_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
