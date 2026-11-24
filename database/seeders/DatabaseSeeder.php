<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            LaundryColorSeeder::class,
            WebsiteSettingSeeder::class,
            LaundryProcedureSeeder::class,
            LaundryFaqSeeder::class,
            LocalDemoSeeder::class, // hanya berjalan saat APP_ENV=local
        ]);
    }
}