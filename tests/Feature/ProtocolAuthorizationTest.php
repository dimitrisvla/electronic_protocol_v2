<?php

/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
|
| Import the role enum, application models, and database testing tools.
|
*/

use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Refresh the database
|--------------------------------------------------------------------------
|
| Every test receives a clean database. Laravel runs the migrations before
| each test and removes test data afterward.
|
*/

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| User test helper
|--------------------------------------------------------------------------
|
| Create a user with an explicit role.
|
| Roles must be assigned explicitly because the database default is Viewer.
|
*/

function createProtocolAuthorizationUser(UserRole $role): User
{
    return User::factory()->create([
        'role' => $role,
    ]);
}

/*
|--------------------------------------------------------------------------
| Protocol test helper
|--------------------------------------------------------------------------
|
| Create a standard protocol belonging to the supplied user.
|
| The optional overrides array allows individual tests to replace fields
| without repeating the entire protocol definition.
|
*/

function createAuthorizationTestProtocol(
    User $owner,
    array $overrides = []
): Protocol {
    return Protocol::create(array_merge([
        'protocol_number' => 100,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-15',
        'direction' => 'incoming',
        'subject' => 'Authorization test protocol',
        'sender' => 'Test sender',
        'recipient' => 'Test recipient',
        'notes' => null,

        // The supplied user owns the protocol.
        'created_by' => $owner->id,
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| Ownerless protocol test helper
|--------------------------------------------------------------------------
|
| Create a protocol that represents data created before protocol ownership
| was introduced. These records have created_by set to null.
|
*/

function createOwnerlessAuthorizationTestProtocol(): Protocol
{
    return Protocol::create([
        'protocol_number' => 200,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-15',
        'direction' => 'incoming',
        'subject' => 'Ownerless legacy protocol',
        'sender' => null,
        'recipient' => null,
        'notes' => null,
        'created_by' => null,
    ]);
}

/*
|--------------------------------------------------------------------------
| Valid protocol request data
|--------------------------------------------------------------------------
|
| Return the fields submitted by the protocol creation and update forms.
|
*/

function validProtocolAuthorizationData(array $overrides = []): array
{
    return array_merge([
        'protocol_number' => 101,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-15',
        'direction' => 'incoming',
        'subject' => 'New authorization test protocol',
        'sender' => 'Test sender',
        'recipient' => 'Test recipient',
        'notes' => null,
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Test: Guests cannot access protected protocol pages
|--------------------------------------------------------------------------
|
| A user who has not logged in must be redirected to the login page.
|
*/

test('a guest cannot access the protocol creation page', function () {
    $this
        ->get(route('protocols.create'))
        ->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| Test: All roles can view active protocols
|--------------------------------------------------------------------------
|
| Administrator, Protocol Officer, and Viewer are all allowed to view the
| active protocol list and the details of an active protocol.
|
*/

test('all roles can view active protocols', function () {
    // Create the protocol using an Administrator as its owner.
    $owner = createProtocolAuthorizationUser(UserRole::Administrator);
    $protocol = createAuthorizationTestProtocol($owner);

    $roles = [
        UserRole::Administrator,
        UserRole::ProtocolOfficer,
        UserRole::Viewer,
    ];

    foreach ($roles as $role) {
        $user = createProtocolAuthorizationUser($role);

        // Every authenticated role can open the protocol list.
        $this
            ->actingAs($user)
            ->get(route('protocols.index'))
            ->assertOk();

        // Every authenticated role can view an active protocol.
        $this
            ->actingAs($user)
            ->get(route('protocols.show', $protocol))
            ->assertOk();
    }
});

/*
|--------------------------------------------------------------------------
| Test: Administrator can create protocols
|--------------------------------------------------------------------------
|
| An Administrator is allowed to open the creation form and create a new
| protocol. The new protocol belongs to that Administrator.
|
*/

test('an administrator can create a protocol', function () {
    $administrator = createProtocolAuthorizationUser(
        UserRole::Administrator
    );

    /*
     * This authorization test verifies that the submitted protocol number is
     * stored. Disable automatic numbering explicitly so Step 13.9C does not
     * replace 101 with the server-issued number. Automatic behavior has its
     * own dedicated coverage in ProtocolNumberingTest.
     */
    app(ApplicationSettings::class)
        ->setAutomaticProtocolNumbering(false);

    // The Administrator can open the creation page.
    $this
        ->actingAs($administrator)
        ->get(route('protocols.create'))
        ->assertOk();

    // The Administrator can submit the creation form.
    $response = $this
        ->actingAs($administrator)
        ->post(
            route('protocols.store'),
            validProtocolAuthorizationData()
        );

    $response->assertRedirect();

    // The controller assigns the authenticated Administrator as owner.
    $this->assertDatabaseHas('protocols', [
        'protocol_number' => 101,
        'protocol_year' => 2026,
        'created_by' => $administrator->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officer can create protocols
|--------------------------------------------------------------------------
|
| A Protocol Officer can create protocols. The controller must assign the
| authenticated officer automatically through created_by.
|
*/

test('a protocol officer can create a protocol', function () {
    $officer = createProtocolAuthorizationUser(
        UserRole::ProtocolOfficer
    );

    /*
     * Keep this test focused on role authorization and ownership. Manual
     * numbering preserves the submitted value used by the assertion below.
     */
    app(ApplicationSettings::class)
        ->setAutomaticProtocolNumbering(false);

    // The Protocol Officer can open the creation page.
    $this
        ->actingAs($officer)
        ->get(route('protocols.create'))
        ->assertOk();

    // The created_by field is intentionally not sent by the test.
    $response = $this
        ->actingAs($officer)
        ->post(
            route('protocols.store'),
            validProtocolAuthorizationData()
        );

    $response->assertRedirect();

    // The new protocol must belong to the authenticated officer.
    $this->assertDatabaseHas('protocols', [
        'protocol_number' => 101,
        'protocol_year' => 2026,
        'created_by' => $officer->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Viewer cannot create protocols
|--------------------------------------------------------------------------
|
| A Viewer has read-only access and must not be able to open or submit the
| protocol creation form.
|
*/

test('a viewer cannot create a protocol', function () {
    $viewer = createProtocolAuthorizationUser(UserRole::Viewer);

    // The creation page is forbidden.
    $this
        ->actingAs($viewer)
        ->get(route('protocols.create'))
        ->assertForbidden();

    // A direct POST request must also be forbidden.
    $this
        ->actingAs($viewer)
        ->post(
            route('protocols.store'),
            validProtocolAuthorizationData()
        )
        ->assertForbidden();

    // No protocol should have been stored.
    $this->assertDatabaseMissing('protocols', [
        'protocol_number' => 101,
        'protocol_year' => 2026,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officer can edit their own protocol
|--------------------------------------------------------------------------
|
| A Protocol Officer may open the edit page for a protocol they own.
|
*/

test('a protocol officer can open their own protocol edit page', function () {
    $officer = createProtocolAuthorizationUser(
        UserRole::ProtocolOfficer
    );

    $protocol = createAuthorizationTestProtocol($officer);

    $this
        ->actingAs($officer)
        ->get(route('protocols.edit', $protocol))
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officer can update their own protocol
|--------------------------------------------------------------------------
|
| A Protocol Officer may submit changes to a protocol they own.
|
*/

test('a protocol officer can update their own protocol', function () {
    $officer = createProtocolAuthorizationUser(
        UserRole::ProtocolOfficer
    );

    $protocol = createAuthorizationTestProtocol($officer);

    $response = $this
        ->actingAs($officer)
        ->put(
            route('protocols.update', $protocol),
            validProtocolAuthorizationData([
                'protocol_number' => 100,
                'direction' => 'outgoing',
                'subject' => 'Updated by owning officer',
                'notes' => 'Authorized update',
            ])
        );

    $response->assertRedirect(route('protocols.show', $protocol));

    $this->assertDatabaseHas('protocols', [
        'id' => $protocol->id,
        'direction' => 'outgoing',
        'subject' => 'Updated by owning officer',
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officer can soft-delete their own protocol
|--------------------------------------------------------------------------
|
| A Protocol Officer may soft-delete a protocol they own.
|
*/

test('a protocol officer can soft delete their own protocol', function () {
    $officer = createProtocolAuthorizationUser(
        UserRole::ProtocolOfficer
    );

    $protocol = createAuthorizationTestProtocol($officer);

    $this
        ->actingAs($officer)
        ->delete(route('protocols.destroy', $protocol))
        ->assertRedirect(route('protocols.index'));

    // The row remains in the database with deleted_at populated.
    $this->assertSoftDeleted('protocols', [
        'id' => $protocol->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officer cannot modify another officer's protocol
|--------------------------------------------------------------------------
|
| Ownership restrictions remain active for Protocol Officers. An officer
| must not edit, update, or delete another officer's protocol.
|
*/

test('a protocol officer cannot modify another officers protocol', function () {
    $owner = createProtocolAuthorizationUser(
        UserRole::ProtocolOfficer
    );

    $otherOfficer = createProtocolAuthorizationUser(
        UserRole::ProtocolOfficer
    );

    $protocol = createAuthorizationTestProtocol($owner);

    // The other officer cannot open the edit page.
    $this
        ->actingAs($otherOfficer)
        ->get(route('protocols.edit', $protocol))
        ->assertForbidden();

    // The other officer cannot submit an update.
    $this
        ->actingAs($otherOfficer)
        ->put(
            route('protocols.update', $protocol),
            validProtocolAuthorizationData([
                'protocol_number' => 100,
                'subject' => 'Unauthorized officer update',
            ])
        )
        ->assertForbidden();

    // The other officer cannot soft-delete the protocol.
    $this
        ->actingAs($otherOfficer)
        ->delete(route('protocols.destroy', $protocol))
        ->assertForbidden();

    // The original data must remain unchanged.
    $this->assertDatabaseHas('protocols', [
        'id' => $protocol->id,
        'subject' => 'Authorization test protocol',
        'deleted_at' => null,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Administrator can manage another user's protocol
|--------------------------------------------------------------------------
|
| Administrators are not restricted by ownership. They can edit, update,
| and soft-delete protocols belonging to other users.
|
*/

test('an administrator can manage another users protocol', function () {
    $administrator = createProtocolAuthorizationUser(
        UserRole::Administrator
    );

    $officer = createProtocolAuthorizationUser(
        UserRole::ProtocolOfficer
    );

    $protocol = createAuthorizationTestProtocol($officer);

    // The Administrator can open the edit page.
    $this
        ->actingAs($administrator)
        ->get(route('protocols.edit', $protocol))
        ->assertOk();

    // The Administrator can update the officer's protocol.
    $this
        ->actingAs($administrator)
        ->put(
            route('protocols.update', $protocol),
            validProtocolAuthorizationData([
                'protocol_number' => 100,
                'subject' => 'Updated by Administrator',
            ])
        )
        ->assertRedirect(route('protocols.show', $protocol));

    $this->assertDatabaseHas('protocols', [
        'id' => $protocol->id,
        'subject' => 'Updated by Administrator',
    ]);

    // The Administrator can soft-delete the officer's protocol.
    $this
        ->actingAs($administrator)
        ->delete(route('protocols.destroy', $protocol))
        ->assertRedirect(route('protocols.index'));

    $this->assertSoftDeleted('protocols', [
        'id' => $protocol->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Viewer cannot modify protocols
|--------------------------------------------------------------------------
|
| A Viewer remains read-only even when created_by identifies that Viewer
| as the owner of an older protocol.
|
*/

test('a viewer cannot modify a protocol even when they own it', function () {
    $viewer = createProtocolAuthorizationUser(UserRole::Viewer);
    $protocol = createAuthorizationTestProtocol($viewer);

    // The Viewer cannot open the edit page.
    $this
        ->actingAs($viewer)
        ->get(route('protocols.edit', $protocol))
        ->assertForbidden();

    // The Viewer cannot submit an update.
    $this
        ->actingAs($viewer)
        ->put(
            route('protocols.update', $protocol),
            validProtocolAuthorizationData([
                'protocol_number' => 100,
                'subject' => 'Unauthorized Viewer update',
            ])
        )
        ->assertForbidden();

    // The Viewer cannot soft-delete the protocol.
    $this
        ->actingAs($viewer)
        ->delete(route('protocols.destroy', $protocol))
        ->assertForbidden();

    // Nothing should have changed.
    $this->assertDatabaseHas('protocols', [
        'id' => $protocol->id,
        'subject' => 'Authorization test protocol',
        'deleted_at' => null,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Administrator can manage ownerless protocols
|--------------------------------------------------------------------------
|
| Ownerless legacy protocols require administrative intervention.
| An Administrator can update or soft-delete these records.
|
*/

test('an administrator can manage an ownerless legacy protocol', function () {
    $administrator = createProtocolAuthorizationUser(
        UserRole::Administrator
    );

    $protocol = createOwnerlessAuthorizationTestProtocol();

    // The Administrator can open the ownerless protocol's edit page.
    $this
        ->actingAs($administrator)
        ->get(route('protocols.edit', $protocol))
        ->assertOk();

    // The Administrator can update the ownerless protocol.
    $this
        ->actingAs($administrator)
        ->put(
            route('protocols.update', $protocol),
            validProtocolAuthorizationData([
                'protocol_number' => 200,
                'subject' => 'Legacy protocol updated by Administrator',
            ])
        )
        ->assertRedirect(route('protocols.show', $protocol));

    $this->assertDatabaseHas('protocols', [
        'id' => $protocol->id,
        'created_by' => null,
        'subject' => 'Legacy protocol updated by Administrator',
    ]);

    // The Administrator can soft-delete the ownerless protocol.
    $this
        ->actingAs($administrator)
        ->delete(route('protocols.destroy', $protocol))
        ->assertRedirect(route('protocols.index'));

    $this->assertSoftDeleted('protocols', [
        'id' => $protocol->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officer cannot manage ownerless protocols
|--------------------------------------------------------------------------
|
| An ownerless record does not belong to a Protocol Officer. Only an
| Administrator can modify it.
|
*/

test('a protocol officer cannot manage an ownerless legacy protocol', function () {
    $officer = createProtocolAuthorizationUser(
        UserRole::ProtocolOfficer
    );

    $protocol = createOwnerlessAuthorizationTestProtocol();

    // The officer cannot open the edit page.
    $this
        ->actingAs($officer)
        ->get(route('protocols.edit', $protocol))
        ->assertForbidden();

    // The officer cannot update the ownerless protocol.
    $this
        ->actingAs($officer)
        ->put(
            route('protocols.update', $protocol),
            validProtocolAuthorizationData([
                'protocol_number' => 200,
                'subject' => 'Unauthorized legacy update',
            ])
        )
        ->assertForbidden();

    // The officer cannot soft-delete the ownerless protocol.
    $this
        ->actingAs($officer)
        ->delete(route('protocols.destroy', $protocol))
        ->assertForbidden();

    // Confirm that the ownerless protocol remains unchanged.
    $this->assertDatabaseHas('protocols', [
        'id' => $protocol->id,
        'created_by' => null,
        'subject' => 'Ownerless legacy protocol',
        'deleted_at' => null,
    ]);
});
