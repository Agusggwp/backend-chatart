<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for local development only.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $this->call([
            UserSeeder::class,
        ]);
    }
}
