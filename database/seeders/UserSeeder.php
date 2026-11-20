<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed 2 development user accounts for local testing only.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $seedUsers = [
            [
                'name' => 'Agus Artdevata',
                'username' => 'agus.artdevata',
                'email' => 'agus@artdevata.id',
                'phone' => '+6281234567890',
                'bio' => 'Founder & Developer ARTDEVATA Chat',
                'password' => 'Password123',
                'is_online' => true,
            ],
            [
                'name' => 'Siti Rahma',
                'username' => 'siti.rahma',
                'email' => 'siti@artdevata.id',
                'phone' => '+6281298765432',
                'bio' => 'UI/UX Designer di ARTDEVATA',
                'password' => 'Password123',
                'is_online' => false,
            ],
        ];

        foreach ($seedUsers as $index => $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'phone' => $data['phone'],
                    'bio' => $data['bio'],
                    'password' => Hash::make($data['password']),
                    'email_verified_at' => now(),
                ]
            );

            $user->presence()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'is_online' => $data['is_online'],
                    'last_seen_at' => $data['is_online'] ? now() : now()->subMinutes(15 * ($index + 1)),
                ]
            );
        }
    }
}
