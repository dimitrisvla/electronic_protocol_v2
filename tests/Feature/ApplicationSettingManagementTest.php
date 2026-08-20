<?php

use App\Enums\UserRole;
use App\Models\ApplicationSetting;
use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Return valid application-settings form data.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validApplicationSettingsData(array $overrides = []): array
{
    return array_merge([
        'organization_name' => '1ο Γενικό Λύκειο',
        'active_protocol_year' => 2027,
        'starting_protocol_number' => 100,
        'automatic_protocol_numbering' => '1',
    ], $overrides);
}

test('guest cannot view application settings', function () {
    $this->get(route('admin.settings.index'))
        ->assertRedirect(route('login'));
});

test('administrator can view application settings and its navigation link', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->get(route('admin.settings.index'));

    $response
        ->assertOk()
        ->assertSee(__('settings.index.title'))
        ->assertSee('name="organization_name"', false)
        ->assertSee('name="active_protocol_year"', false)
        ->assertSee('name="starting_protocol_number"', false)
        ->assertSee('name="automatic_protocol_numbering"', false)
        ->assertSee(route('admin.settings.index'), false);
});

test('non administrators cannot view application settings', function (
    UserRole $role
) {
    $user = User::factory()->create(['role' => $role]);

    $this
        ->actingAs($user)
        ->get(route('admin.settings.index'))
        ->assertForbidden();
})->with([
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

test('administrator can update application settings', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->put(
            route('admin.settings.update'),
            validApplicationSettingsData([
                'automatic_protocol_numbering' => '0',
            ])
        );

    $response
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionHas('success', __('flash.settings.updated'));

    $settings = app(ApplicationSettings::class);

    expect($settings->organizationName())->toBe('1ο Γενικό Λύκειο')
        ->and($settings->activeProtocolYear())->toBe(2027)
        ->and($settings->startingProtocolNumber())->toBe(100)
        ->and($settings->usesAutomaticProtocolNumbering())->toBeFalse();
});

test('administrator can clear active year for continuous numbering', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $this
        ->actingAs($administrator)
        ->put(route('admin.settings.update'), validApplicationSettingsData([
            'active_protocol_year' => '',
        ]))
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionDoesntHaveErrors();

    expect(app(ApplicationSettings::class)->activeProtocolYear())->toBeNull();
});

test('application settings update validates every operational value', function (
    array $overrides,
    string $expectedError
) {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $this
        ->actingAs($administrator)
        ->from(route('admin.settings.index'))
        ->put(
            route('admin.settings.update'),
            validApplicationSettingsData($overrides)
        )
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionHasErrors($expectedError);

    expect(ApplicationSetting::query()->count())->toBe(0);
})->with([
    'missing organization name' => [
        ['organization_name' => ''],
        'organization_name',
    ],
    'invalid active year' => [
        ['active_protocol_year' => 27],
        'active_protocol_year',
    ],
    'starting number below one' => [
        ['starting_protocol_number' => 0],
        'starting_protocol_number',
    ],
    'invalid automatic numbering flag' => [
        ['automatic_protocol_numbering' => '2'],
        'automatic_protocol_numbering',
    ],
]);

test('non administrators cannot update application settings', function (
    UserRole $role
) {
    $user = User::factory()->create(['role' => $role]);

    $this
        ->actingAs($user)
        ->put(
            route('admin.settings.update'),
            validApplicationSettingsData()
        )
        ->assertForbidden();

    expect(ApplicationSetting::query()->count())->toBe(0);
})->with([
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

test('non administrators do not see application settings navigation', function (
    UserRole $role
) {
    $user = User::factory()->create(['role' => $role]);

    $this
        ->actingAs($user)
        ->get(route('protocols.index'))
        ->assertOk()
        ->assertDontSee(route('admin.settings.index'), false)
        ->assertDontSee(__('settings.navigation'));
})->with([
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);
