<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->index();
            $table->char('dog_id', 26);
            $table->char('customer_id', 26);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->integer('price_cents')->nullable();
            $table->char('resource_id', 26)->nullable();
            $table->char('assigned_user_id', 26)->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->char('cancelled_by', 26)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->softDeletes();
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('dog_id')->references('id')->on('dogs')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('resource_id')->references('id')->on('bookable_resources')->onDelete('set null');
            $table->foreign('assigned_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['dog_id', 'starts_at']);
            $table->index(['customer_id', 'starts_at']);
        });

        DB::statement("ALTER TABLE appointments ADD COLUMN service_type appointment_service_type NOT NULL DEFAULT 'daycare'");
        DB::statement("ALTER TABLE appointments ADD COLUMN status appointment_status NOT NULL DEFAULT 'draft'");

        // Covering index for calendar queries — served entirely from index for the common case
        DB::statement('
            CREATE INDEX appt_tenant_calendar_idx ON appointments (tenant_id, starts_at, status)
            INCLUDE (service_type, dog_id, customer_id, price_cents, resource_id)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
