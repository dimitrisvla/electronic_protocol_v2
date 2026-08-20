<?php

use App\Models\ApplicationSetting;
use App\Services\ApplicationSettings;
use Database\Seeders\ApplicationSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('application settings table has the normalized foundation columns', function () {
    expect(Schema::hasColumns('application_settings', [
        'id',
        'key',
        'value',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('settings service supplies typed defaults before seeding', function () {
    $settings = app(ApplicationSettings::class);

    expect($settings->organizationName())->toBe('Όνομα Σχολείου')
        ->and($settings->activeProtocolYear())->toBe(now()->year)
        ->and($settings->startingProtocolNumber())->toBe(1)
        ->and($settings->usesAutomaticProtocolNumbering())->toBeTrue();
});

test('settings seeder stores the original workflow defaults', function () {
    $this->seed(ApplicationSettingSeeder::class);

    $this->assertDatabaseHas('application_settings', [
        'key' => ApplicationSettings::ORGANIZATION_NAME,
        'value' => 'Όνομα Σχολείου',
    ]);
    $this->assertDatabaseHas('application_settings', [
        'key' => ApplicationSettings::ACTIVE_PROTOCOL_YEAR,
        'value' => (string) now()->year,
    ]);
    $this->assertDatabaseHas('application_settings', [
        'key' => ApplicationSettings::STARTING_PROTOCOL_NUMBER,
        'value' => '1',
    ]);
    $this->assertDatabaseHas('application_settings', [
        'key' => ApplicationSettings::AUTOMATIC_PROTOCOL_NUMBERING,
        'value' => '1',
    ]);
});

test('settings service persists and returns typed values', function () {
    $settings = app(ApplicationSettings::class);

    $settings->setOrganizationName('1ο Γενικό Λύκειο');
    $settings->setActiveProtocolYear(2027);
    $settings->setStartingProtocolNumber(250);
    $settings->setAutomaticProtocolNumbering(false);

    expect($settings->organizationName())->toBe('1ο Γενικό Λύκειο')
        ->and($settings->activeProtocolYear())->toBe(2027)
        ->and($settings->startingProtocolNumber())->toBe(250)
        ->and($settings->usesAutomaticProtocolNumbering())->toBeFalse();
});

test('active year can be cleared for continuous numbering', function () {
    $settings = app(ApplicationSettings::class);
    $settings->setActiveProtocolYear(null);

    expect($settings->activeProtocolYear())->toBeNull();

    $this->assertDatabaseHas('application_settings', [
        'key' => ApplicationSettings::ACTIVE_PROTOCOL_YEAR,
        'value' => null,
    ]);
});

test('starting protocol number never falls below one', function () {
    $settings = app(ApplicationSettings::class);
    $settings->setStartingProtocolNumber(0);

    expect($settings->startingProtocolNumber())->toBe(1);
});

test('settings seeder is idempotent and preserves administrator choices', function () {
    $this->seed(ApplicationSettingSeeder::class);

    ApplicationSetting::query()
        ->where('key', ApplicationSettings::ORGANIZATION_NAME)
        ->update(['value' => 'Προσαρμοσμένο Σχολείο']);

    $this->seed(ApplicationSettingSeeder::class);

    expect(ApplicationSetting::query()->count())->toBe(4)
        ->and(app(ApplicationSettings::class)->organizationName())
        ->toBe('Προσαρμοσμένο Σχολείο');
});
