<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE qr_codes
            SET target_url = '/my/arrive/' || parking_spots.tenant_id || '/' || parking_spots.id
            FROM parking_spots
            WHERE qr_codes.tenant_id = parking_spots.tenant_id
              AND qr_codes.key = 'parking-' || parking_spots.spot_number
              AND qr_codes.target_url LIKE '/admin/parking-spots/%'
              AND parking_spots.deleted_at IS NULL
        ");
    }

    public function down(): void {}
};
