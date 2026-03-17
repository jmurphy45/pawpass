<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop FK from notification_logs → notifications
        DB::statement('ALTER TABLE notification_logs DROP CONSTRAINT IF EXISTS notification_logs_notification_id_foreign');

        // 2. Null out old ULID references (incompatible with uuid type), then change column type
        DB::statement('UPDATE notification_logs SET notification_id = NULL');
        DB::statement('ALTER TABLE notification_logs ALTER COLUMN notification_id TYPE uuid USING notification_id::uuid');

        // 3. Drop and recreate notifications table with Laravel standard schema
        Schema::drop('notifications');

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('notifiable_type');
            $table->text('notifiable_id');
            $table->text('type');
            $table->json('data');
            $table->timestampTz('read_at')->nullable();
            $table->char('tenant_id', 26)->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
        });

        DB::statement('CREATE INDEX notifications_notifiable_read_at_idx ON notifications(notifiable_id, read_at)');
        DB::statement('CREATE INDEX notifications_tenant_id_idx ON notifications(tenant_id)');

        // 4. Re-add nullable FK from notification_logs → notifications
        DB::statement('ALTER TABLE notification_logs ADD CONSTRAINT notification_logs_notification_id_foreign FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notification_logs DROP CONSTRAINT IF EXISTS notification_logs_notification_id_foreign');
        DB::statement('UPDATE notification_logs SET notification_id = NULL');
        DB::statement('ALTER TABLE notification_logs ALTER COLUMN notification_id TYPE char(26) USING NULL::char(26)');

        Schema::drop('notifications');

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

        DB::statement('ALTER TABLE notification_logs ADD CONSTRAINT notification_logs_notification_id_foreign FOREIGN KEY (notification_id) REFERENCES notifications(id)');
    }
};
