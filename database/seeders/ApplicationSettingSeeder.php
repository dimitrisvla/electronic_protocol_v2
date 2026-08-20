<?php

namespace Database\Seeders;

use App\Models\ApplicationSetting;
use App\Services\ApplicationSettings;
use Illuminate\Database\Seeder;

/**
 * Insert the original workflow's initial operational settings.
 */
class ApplicationSettingSeeder extends Seeder
{
    /**
     * Seed missing defaults without overwriting administrator choices.
     */
    public function run(): void
    {
        $defaults = [
            ApplicationSettings::ORGANIZATION_NAME => 'Όνομα Σχολείου',
            ApplicationSettings::ACTIVE_PROTOCOL_YEAR => (string) now()->year,
            ApplicationSettings::STARTING_PROTOCOL_NUMBER => '1',
            ApplicationSettings::AUTOMATIC_PROTOCOL_NUMBERING => '1',
        ];

        foreach ($defaults as $key => $value) {
            ApplicationSetting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
