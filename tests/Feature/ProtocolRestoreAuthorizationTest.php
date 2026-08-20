<?php

/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
|
| Import the role enum, application models, and database testing tool used
| by the recycle-bin and protocol-restoration authorization tests.
|
*/

use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Refresh the database
|--------------------------------------------------------------------------
|
| RefreshDatabase gives every test a clean database. This prevents the
| protocols and users created by one test from affecting another test.
|
*/

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Deleted protocol test helper
|--------------------------------------------------------------------------
|
| These tests repeatedly need a protocol that has already been moved to
| the recycle bin. This helper creates the protocol and then soft deletes
| it, which gives its deleted_at column a value without removing the row.
|
| The owner may be null so that we can also represent an ownerless protocol
| imported from the legacy application.
|
*/

function createDeletedProtocolForRestoreTest(
    ?User $owner,
    int $protocolNumber,
    string $subject
): Protocol {
    $protocol = Protocol::create([
        'protocol_number' => $protocolNumber,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-17',
        'direction' => 'incoming',
        'subject' => $subject,
        'sender' => 'Restore test sender',
        'recipient' => 'Restore test recipient',
        'notes' => null,

        // A null value represents an ownerless legacy protocol.
        'created_by' => $owner?->id,
    ]);

    // Soft delete the protocol so that it appears in the recycle bin.
    $protocol->delete();

    return $protocol;
}

/*
|--------------------------------------------------------------------------
| Test: Guests cannot view the recycle bin
|--------------------------------------------------------------------------
|
| The deleted-protocol page belongs to the authenticated part of the
| application. A visitor who has not logged in must be sent to login.
|
*/

test('a guest cannot view the deleted protocols page', function () {
    // Request the recycle-bin page without authenticating a user.
    $response = $this->get(route('protocols.deleted'));

    // The authentication middleware must redirect the guest to login.
    $response->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| Test: Administrators can view every deleted protocol
|--------------------------------------------------------------------------
|
| An Administrator manages the complete recycle bin. The list must include
| protocols owned by any user and ownerless records from the legacy system.
|
*/

test('an administrator can view all deleted protocols', function () {
    // Create an Administrator and a Protocol Officer.
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    // Create deleted protocols with three different ownership situations.
    createDeletedProtocolForRestoreTest(
        $administrator,
        401,
        'Deleted protocol owned by administrator'
    );

    createDeletedProtocolForRestoreTest(
        $officer,
        402,
        'Deleted protocol owned by officer'
    );

    createDeletedProtocolForRestoreTest(
        null,
        403,
        'Deleted ownerless legacy protocol'
    );

    // Open the recycle bin as the Administrator.
    $response = $this
        ->actingAs($administrator)
        ->get(route('protocols.deleted'));

    // The page must be available and contain every deleted protocol.
    $response
        ->assertOk()
        ->assertSee('Deleted protocol owned by administrator')
        ->assertSee('Deleted protocol owned by officer')
        ->assertSee('Deleted ownerless legacy protocol');
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officers see only their own deleted protocols
|--------------------------------------------------------------------------
|
| A Protocol Officer may restore their own work, but must not see another
| user's deleted protocols or ownerless records in the recycle-bin list.
|
*/

test('a protocol officer can view only their own deleted protocols', function () {
    // Create two different Protocol Officers.
    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $otherOfficer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    // Create one deleted protocol for each ownership situation.
    createDeletedProtocolForRestoreTest(
        $officer,
        404,
        'Deleted protocol belonging to current officer'
    );

    createDeletedProtocolForRestoreTest(
        $otherOfficer,
        405,
        'Deleted protocol belonging to another officer'
    );

    createDeletedProtocolForRestoreTest(
        null,
        406,
        'Deleted ownerless protocol hidden from officer'
    );

    // Open the recycle bin as the first Protocol Officer.
    $response = $this
        ->actingAs($officer)
        ->get(route('protocols.deleted'));

    /*
     * The officer must see their own deleted protocol, but neither the
     * other officer's record nor the ownerless legacy record.
     */
    $response
        ->assertOk()
        ->assertSee('Deleted protocol belonging to current officer')
        ->assertDontSee('Deleted protocol belonging to another officer')
        ->assertDontSee('Deleted ownerless protocol hidden from officer');
});

/*
|--------------------------------------------------------------------------
| Test: Viewers cannot view the recycle bin
|--------------------------------------------------------------------------
|
| A Viewer has read-only access to active protocols. Soft-deleted records
| are not part of that permission, so the recycle bin must return HTTP 403.
|
*/

test('a viewer cannot view the deleted protocols page', function () {
    // Create a user with the read-only Viewer role.
    $viewer = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    // Attempt to open the recycle bin as the Viewer.
    $response = $this
        ->actingAs($viewer)
        ->get(route('protocols.deleted'));

    // Server-side authorization must reject the request.
    $response->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Test: Administrators can restore another user's protocol
|--------------------------------------------------------------------------
|
| Administrators have global protocol-management permission. They may
| therefore restore a deleted protocol even when another user created it.
|
*/

test('an administrator can restore another users protocol', function () {
    // Create the Administrator and the officer who owns the protocol.
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $protocol = createDeletedProtocolForRestoreTest(
        $officer,
        407,
        'Officer protocol restored by administrator'
    );

    // Submit the restoration request as the Administrator.
    $response = $this
        ->actingAs($administrator)
        ->post(route('protocols.restore', [
            'protocol' => $protocol->id,
        ]));

    // A successful restoration returns the user to the recycle bin.
    $response->assertRedirect(route('protocols.deleted'));
    $response->assertSessionHas('success', __('flash.protocols.restored'));

    // Restoring sets deleted_at back to null on the existing record.
    $this->assertDatabaseHas('protocols', [
        'id' => $protocol->id,
        'created_by' => $officer->id,
        'deleted_at' => null,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Administrators can restore ownerless legacy protocols
|--------------------------------------------------------------------------
|
| Ownerless records have no Protocol Officer who can manage them. Global
| Administrator permission must therefore cover their restoration.
|
*/

test('an administrator can restore an ownerless legacy protocol', function () {
    // Create the Administrator who will perform the restoration.
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    // Passing null creates an ownerless deleted protocol.
    $protocol = createDeletedProtocolForRestoreTest(
        null,
        408,
        'Ownerless protocol restored by administrator'
    );

    // Submit the restoration request as the Administrator.
    $response = $this
        ->actingAs($administrator)
        ->post(route('protocols.restore', [
            'protocol' => $protocol->id,
        ]));

    $response->assertRedirect(route('protocols.deleted'));

    // The record remains ownerless, but it is active again.
    $this->assertDatabaseHas('protocols', [
        'id' => $protocol->id,
        'created_by' => null,
        'deleted_at' => null,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officers can restore their own protocols
|--------------------------------------------------------------------------
|
| A Protocol Officer may manage protocols they created. That ownership
| permission includes restoring their own record from the recycle bin.
|
*/

test('a protocol officer can restore their own protocol', function () {
    // Create the Protocol Officer and one of their deleted protocols.
    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $protocol = createDeletedProtocolForRestoreTest(
        $officer,
        409,
        'Officer restores own protocol'
    );

    // Submit the restoration request as the protocol owner.
    $response = $this
        ->actingAs($officer)
        ->post(route('protocols.restore', [
            'protocol' => $protocol->id,
        ]));

    $response->assertRedirect(route('protocols.deleted'));
    $response->assertSessionHas('success', __('flash.protocols.restored'));

    // Confirm that the soft-delete timestamp has been cleared.
    $this->assertDatabaseHas('protocols', [
        'id' => $protocol->id,
        'created_by' => $officer->id,
        'deleted_at' => null,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officers cannot restore another user's protocol
|--------------------------------------------------------------------------
|
| Ownership restrictions must also be enforced on direct POST requests.
| Hiding a Restore button in Blade would not be sufficient protection.
|
*/

test('a protocol officer cannot restore another users protocol', function () {
    // Create two Protocol Officers.
    $owner = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $otherOfficer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $protocol = createDeletedProtocolForRestoreTest(
        $owner,
        410,
        'Protocol protected from another officer'
    );

    // The non-owner manually submits a restoration request.
    $response = $this
        ->actingAs($otherOfficer)
        ->post(route('protocols.restore', [
            'protocol' => $protocol->id,
        ]));

    // ProtocolPolicy::restore() must reject the request.
    $response->assertForbidden();

    // The protocol must remain safely in the recycle bin.
    $this->assertSoftDeleted('protocols', [
        'id' => $protocol->id,
        'created_by' => $owner->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officers cannot restore ownerless legacy protocols
|--------------------------------------------------------------------------
|
| An ownerless protocol cannot satisfy the officer-owns-protocol rule.
| Only an Administrator is allowed to restore this kind of legacy record.
|
*/

test('a protocol officer cannot restore an ownerless legacy protocol', function () {
    // Create the Protocol Officer attempting the action.
    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $protocol = createDeletedProtocolForRestoreTest(
        null,
        411,
        'Ownerless protocol protected from officer'
    );

    // Submit a direct restoration request as the officer.
    $response = $this
        ->actingAs($officer)
        ->post(route('protocols.restore', [
            'protocol' => $protocol->id,
        ]));

    $response->assertForbidden();

    // The ownerless protocol must remain soft deleted.
    $this->assertSoftDeleted('protocols', [
        'id' => $protocol->id,
        'created_by' => null,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Viewers cannot restore protocols
|--------------------------------------------------------------------------
|
| A Viewer cannot modify protocol data. This remains true even if an old
| record happens to contain that Viewer's ID in its created_by column.
|
*/

test('a viewer cannot restore a protocol even when they own it', function () {
    // Create a Viewer and a deleted protocol attributed to that user.
    $viewer = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $protocol = createDeletedProtocolForRestoreTest(
        $viewer,
        412,
        'Viewer-owned protocol remains deleted'
    );

    // Attempt to bypass the interface with a direct POST request.
    $response = $this
        ->actingAs($viewer)
        ->post(route('protocols.restore', [
            'protocol' => $protocol->id,
        ]));

    $response->assertForbidden();

    // The denied request must not change the protocol.
    $this->assertSoftDeleted('protocols', [
        'id' => $protocol->id,
        'created_by' => $viewer->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test: Guests cannot restore protocols
|--------------------------------------------------------------------------
|
| A restoration request changes stored data. The authentication middleware
| must therefore stop unauthenticated requests before policy evaluation.
|
*/

test('a guest cannot restore a deleted protocol', function () {
    // Create an officer and one deleted protocol.
    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $protocol = createDeletedProtocolForRestoreTest(
        $officer,
        413,
        'Protocol protected from guest restoration'
    );

    // Submit the request without logging in.
    $response = $this->post(route('protocols.restore', [
        'protocol' => $protocol->id,
    ]));

    $response->assertRedirect(route('login'));

    // The protocol must still be in the recycle bin.
    $this->assertSoftDeleted('protocols', [
        'id' => $protocol->id,
        'created_by' => $officer->id,
    ]);
});
