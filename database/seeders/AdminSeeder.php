<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Create (or update) a single admin account.
     *
     * php artisan db:seed --class=AdminSeeder
     *
     * Credentials can be overridden in .env:
     *   ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@pharma.test');
        $password = env('ADMIN_PASSWORD', 'password');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'مدير النظام'),
                'password' => $password, // hashed by the User model's 'hashed' cast
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info("Admin account ready: {$user->email}");
    }
}
