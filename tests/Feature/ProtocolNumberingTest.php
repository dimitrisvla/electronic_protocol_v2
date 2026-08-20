<?php

use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Return valid protocol input for numbering tests.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validProtocolNumberingData(array $overrides = []): array
{
    return array_merge([
        'protocol_number' => 999,
        'protocol_year' => 2027,
        'protocol_date' => '2027-01-10',
        'direction' => 'incoming',
        'subject' => 'Δοκιμή αρίθμησης πρωτοκόλλου',
        'sender' => 'Δοκιμαστικός αποστολέας',
        'recipient' => null,
        'notes' => null,
        'archive_folder_id' => null,
    ], $overrides);
}

/**
 * Insert an existing protocol directly for sequence preparation.
 */
function createNumberingProtocol(
    User $owner,
    int $number,
    int $year
): Protocol {
    return Protocol::query()->create([
        ...validProtocolNumberingData([
            'protocol_number' => $number,
            'protocol_year' => $year,
        ]),
        'created_by' => $owner->id,
    ]);
}

test('create form displays the active year and automatic proposal', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $settings = app(ApplicationSettings::class);
    $settings->setActiveProtocolYear(2031);
    $settings->setStartingProtocolNumber(50);
    $settings->setAutomaticProtocolNumbering(true);

    $response = $this
        ->actingAs($administrator)
        ->get(route('protocols.create'));

    $response
        ->assertOk()
        ->assertSee('name="protocol_number"', false)
        ->assertSee('value="50"', false)
        ->assertSee('readonly', false)
        ->assertSee('name="protocol_year"', false)
        ->assertSee('value="2031"', false)
        ->assertSee(__('protocols.numbering.automatic_help'));
});

test('automatic numbering uses the configured starting number', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $settings = app(ApplicationSettings::class);
    $settings->setActiveProtocolYear(2027);
    $settings->setStartingProtocolNumber(500);
    $settings->setAutomaticProtocolNumbering(true);

    $response = $this
        ->actingAs($administrator)
        ->post(route('protocols.store'), validProtocolNumberingData([
            'protocol_number' => 'τιμή που δεν πρέπει να χρησιμοποιηθεί',
        ]));

    $response->assertSessionHasNoErrors();

    $protocol = Protocol::query()->firstOrFail();

    expect($protocol->protocol_number)->toBe(500)
        ->and($protocol->protocol_year)->toBe(2027);

    $response->assertRedirect(route('protocols.show', $protocol));
});

test('yearly numbering advances inside the submitted year', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $settings = app(ApplicationSettings::class);
    $settings->setActiveProtocolYear(2027);
    $settings->setStartingProtocolNumber(100);
    $settings->setAutomaticProtocolNumbering(true);

    createNumberingProtocol($administrator, 900, 2026);
    $deleted = createNumberingProtocol($administrator, 104, 2027);
    $deleted->delete();

    $this
        ->actingAs($administrator)
        ->post(route('protocols.store'), validProtocolNumberingData())
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('protocols', [
        'protocol_number' => 105,
        'protocol_year' => 2027,
        'deleted_at' => null,
    ]);
});

test('a new yearly sequence starts from the configured number', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $settings = app(ApplicationSettings::class);
    $settings->setActiveProtocolYear(2027);
    $settings->setStartingProtocolNumber(250);
    $settings->setAutomaticProtocolNumbering(true);

    createNumberingProtocol($administrator, 800, 2026);

    $this
        ->actingAs($administrator)
        ->post(route('protocols.store'), validProtocolNumberingData())
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('protocols', [
        'protocol_number' => 250,
        'protocol_year' => 2027,
    ]);
});

test('blank active year continues numbering across all years', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $settings = app(ApplicationSettings::class);
    $settings->setActiveProtocolYear(null);
    $settings->setStartingProtocolNumber(10);
    $settings->setAutomaticProtocolNumbering(true);

    createNumberingProtocol($administrator, 40, 2025);
    createNumberingProtocol($administrator, 12, 2026);

    $this
        ->actingAs($administrator)
        ->post(route('protocols.store'), validProtocolNumberingData())
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('protocols', [
        'protocol_number' => 41,
        'protocol_year' => 2027,
    ]);
});

test('manual numbering remains editable and stores the submitted number', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $settings = app(ApplicationSettings::class);
    $settings->setActiveProtocolYear(2027);
    $settings->setStartingProtocolNumber(1);
    $settings->setAutomaticProtocolNumbering(false);

    $this
        ->actingAs($administrator)
        ->get(route('protocols.create'))
        ->assertOk()
        ->assertDontSee('readonly', false)
        ->assertSee(__('protocols.numbering.manual_help'));

    $this
        ->actingAs($administrator)
        ->post(route('protocols.store'), validProtocolNumberingData([
            'protocol_number' => 77,
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('protocols', [
        'protocol_number' => 77,
        'protocol_year' => 2027,
    ]);
});

test('manual numbering requires a unique number inside the year', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $settings = app(ApplicationSettings::class);
    $settings->setActiveProtocolYear(2027);
    $settings->setAutomaticProtocolNumbering(false);

    createNumberingProtocol($administrator, 77, 2027);

    $this
        ->actingAs($administrator)
        ->from(route('protocols.create'))
        ->post(route('protocols.store'), validProtocolNumberingData([
            'protocol_number' => 77,
        ]))
        ->assertRedirect(route('protocols.create'))
        ->assertSessionHasErrors('protocol_number');

    $this
        ->actingAs($administrator)
        ->from(route('protocols.create'))
        ->post(route('protocols.store'), validProtocolNumberingData([
            'protocol_number' => null,
        ]))
        ->assertRedirect(route('protocols.create'))
        ->assertSessionHasErrors('protocol_number');
});

test('manual numbering permits the same number in another year', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $settings = app(ApplicationSettings::class);
    $settings->setActiveProtocolYear(2027);
    $settings->setAutomaticProtocolNumbering(false);

    createNumberingProtocol($administrator, 77, 2026);

    $this
        ->actingAs($administrator)
        ->post(route('protocols.store'), validProtocolNumberingData([
            'protocol_number' => 77,
            'protocol_year' => 2027,
        ]))
        ->assertSessionHasNoErrors();

    expect(Protocol::withTrashed()
        ->where('protocol_number', 77)
        ->count())->toBe(2);
});
