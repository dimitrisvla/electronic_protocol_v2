<?php

use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createProtocolForReceipt(
    User $owner,
    int $number = 120,
    int $year = 2026
): Protocol {
    return Protocol::query()->create([
        'protocol_number' => $number,
        'protocol_year' => $year,
        'protocol_date' => "{$year}-08-21",
        'direction' => 'incoming',
        'subject' => 'Αίτηση έκδοσης βεβαίωσης',
        'sender' => 'Δοκιμαστικός αποστολέας',
        'recipient' => 'Γραμματεία',
        'notes' => null,
        'archive_folder_id' => null,
        'created_by' => $owner->id,
    ]);
}

test('guest cannot view a protocol registration receipt', function () {
    $owner = User::factory()->create();
    $protocol = createProtocolForReceipt($owner);

    $this
        ->get(route('protocols.receipt', $protocol))
        ->assertRedirect(route('login'));
});

test('every authenticated role can view an active protocol receipt', function (
    UserRole $role
) {
    $user = User::factory()->create(['role' => $role]);
    $protocol = createProtocolForReceipt($user);

    $this
        ->actingAs($user)
        ->get(route('protocols.receipt', $protocol))
        ->assertOk()
        ->assertSee(__('protocols.receipt.heading'))
        ->assertSee('120/2026')
        ->assertSee('21/08/2026');
})->with([
    'administrator' => UserRole::Administrator,
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

test('protocol page exposes the receipt action', function () {
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);
    $protocol = createProtocolForReceipt($viewer, 121);

    $this
        ->actingAs($viewer)
        ->get(route('protocols.show', $protocol))
        ->assertOk()
        ->assertSee(__('protocols.actions.print_receipt'))
        ->assertSee(route('protocols.receipt', $protocol), false);
});

test('receipt uses configured organization and normalized protocol data', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $protocol = createProtocolForReceipt($administrator, 122, 2025);
    app(ApplicationSettings::class)
        ->setOrganizationName('1ο Γενικό Λύκειο Δοκιμής');

    $this
        ->actingAs($administrator)
        ->get(route('protocols.receipt', $protocol))
        ->assertOk()
        ->assertSee('1ο Γενικό Λύκειο Δοκιμής')
        ->assertSee('122/2025')
        ->assertSee('21/08/2025')
        ->assertSee(__('protocols.directions.incoming'))
        ->assertSee('Αίτηση έκδοσης βεβαίωσης')
        ->assertSee('Δοκιμαστικός αποστολέας')
        ->assertSee('Γραμματεία');
});

test('receipt supplies browser printing controls and print styles', function () {
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);
    $protocol = createProtocolForReceipt($viewer, 123);

    $this
        ->actingAs($viewer)
        ->get(route('protocols.receipt', $protocol))
        ->assertOk()
        ->assertSee(__('protocols.receipt.print'))
        ->assertSee(__('protocols.receipt.back'))
        ->assertSee('window.print()', false)
        ->assertSee('@media print', false)
        ->assertSee('@page', false);
});

test('soft deleted protocol has no printable receipt', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $protocol = createProtocolForReceipt($administrator, 124);
    $protocol->delete();

    $this
        ->actingAs($administrator)
        ->get(route('protocols.receipt', $protocol->getKey()))
        ->assertNotFound();
});
