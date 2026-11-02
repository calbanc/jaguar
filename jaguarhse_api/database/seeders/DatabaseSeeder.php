<?php

namespace Database\Seeders;

use App\Services\HsePermissions;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed permission definitions without creating accounts or default passwords.
     */
    public function run(): void
    {
        app(HsePermissions::class)->synchronize();
    }
}
