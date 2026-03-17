<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('user_id', 26);
            $table->char('tenant_id', 26);
            $table->text('type');
            $table->boolean('is_enabled')->default(true);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        DB::statement('ALTER TABLE user_notification_preferences ADD COLUMN channel notif_channel NOT NULL');
        DB::statement('CREATE UNIQUE INDEX user_notif_pref_key ON user_notification_preferences(user_id, type, channel)');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
