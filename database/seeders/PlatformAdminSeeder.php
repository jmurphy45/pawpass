<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('PLATFORM_ADMIN_EMAIL', 'admin@pawpass.com');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'               => 'Platform Admin',
                'role'               => 'platform_admin',
                'status'             => 'active',
                'tenant_id'          => null,
                'customer_id'        => null,
                'email_verified_at'  => now(),
            ]
        );

        $this->command->info("Platform admin ready: {$email}");
    }
}
