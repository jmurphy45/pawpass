<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_pending', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('user_id', 26);
            $table->text('type');
            $table->json('dog_ids')->default('[]');
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('user_id')->references('id')->on('users');
        });

        DB::statement('CREATE INDEX pending_user_type_idx ON notification_pending(user_id, type, dispatched_at) WHERE dispatched_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_pending');
    }
};
