<?php

// orders.type is a plain text column backed by the PHP OrderType enum.
// No database change is needed — adding the enum case to OrderType.php is sufficient.

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
