<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /*
         * Keep domain seeders explicit and idempotent. Re-running the main
         * seeder adds missing defaults without replacing administrator data.
         */
        $this->call([
            ArchiveFolderSeeder::class,
            ApplicationSettingSeeder::class,
        ]);

        // Preserve the development user that already existed in this project.
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
