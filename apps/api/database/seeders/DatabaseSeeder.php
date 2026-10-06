<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan migrate --seed` → a complete demo semester
 * (login: student@edusmart.lk / password).
 *
 * Model events stay enabled on purpose: the services that build the demo
 * (extraction, summaries, risk, reminders) rely on them.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
