<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('notification_id', 26)->nullable();
            $table->char('tenant_id', 26);
            $table->char('user_id', 26);
            $table->text('type');
            $table->text('error')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('notification_id')->references('id')->on('notifications');
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('user_id')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE notification_logs ADD COLUMN channel notif_channel NOT NULL');
        DB::statement("ALTER TABLE notification_logs ADD COLUMN status notif_status NOT NULL DEFAULT 'queued'");
        DB::statement('CREATE INDEX notif_logs_tenant_id_idx ON notification_logs(tenant_id)');
        DB::statement('CREATE INDEX notif_logs_user_id_idx ON notification_logs(user_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
