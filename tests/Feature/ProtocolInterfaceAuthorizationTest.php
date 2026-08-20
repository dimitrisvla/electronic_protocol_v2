<?php

/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
|
| Import the role enum, application models, and database testing tool used
| by the role-aware Blade interface tests in this file.
|
*/

use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\ProtocolAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Refresh the database
|--------------------------------------------------------------------------
|
| Every test receives a clean database. Users, protocols, and attachments
| created by one interface test therefore cannot affect another test.
|
*/

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Protocol interface test helper
|--------------------------------------------------------------------------
|
| Several tests need a standard active protocol. The optional owner allows
| the same helper to represent both normal records and ownerless protocols
| imported from the legacy application.
|
*/

function createProtocolForInterfaceTest(
    ?User $owner,
    int $protocolNumber,
    string $subject
): Protocol {
    return Protocol::create([
        'protocol_number' => $protocolNumber,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-17',
        'direction' => 'incoming',
        'subject' => $subject,
        'sender' => 'Interface test sender',
        'recipient' => 'Interface test recipient',
        'notes' => null,
        'created_by' => $owner?->id,
    ]);
}

/*
|--------------------------------------------------------------------------
| Attachment interface test helper
|--------------------------------------------------------------------------
|
| The details-page tests need attachment metadata so that Blade renders its
| Download and Delete controls. A physical PDF is unnecessary because these
| tests render the page without requesting the download endpoint.
|
*/

function createAttachmentForInterfaceTest(
    Protocol $protocol,
    User $uploader,
    string $filename
): ProtocolAttachment {
    return $protocol->attachments()->create([
        'original_name' => $filename,
        'file_path' => "protocols/{$protocol->id}/{$filename}",
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_by' => $uploader->id,
    ]);
}

/*
|--------------------------------------------------------------------------
| Test: Administrator controls on the active-protocol list
|--------------------------------------------------------------------------
|
| Administrators have global protocol-management permission. The list must
| therefore show page-level creation/recycle-bin controls and row-level Edit
| and Delete controls for protocols owned by different users.
|
*/

test('an administrator sees all management actions on the protocol list', function () {
    // Create the Administrator and a Protocol Officer.
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    // Create one protocol for each user.
    $administratorProtocol = createProtocolForInterfaceTest(
        $administrator,
        501,
        'Administrator interface protocol'
    );

    $officerProtocol = createProtocolForInterfaceTest(
        $officer,
        502,
        'Officer interface protocol'
    );

    // Open the active-protocol list as the Administrator.
    $response = $this
        ->actingAs($administrator)
        ->get(route('protocols.index'));

    $response
        ->assertOk()

        // Page-level actions are available to Administrators.
        ->assertSeeText(__('protocols.actions.create'))
        ->assertSeeText(__('protocols.actions.deleted_protocols'))

        // Edit links exist for both the Administrator's and officer's rows.
        ->assertSee(
            route('protocols.edit', $administratorProtocol),
            false
        )
        ->assertSee(
            route('protocols.edit', $officerProtocol),
            false
        );

    /*
     * Each authorized row contains the unique confirmation text used by
     * the protocol Delete form. Two protocols therefore require two forms.
     */
    $this->assertSame(
        2,
        substr_count(
            $response->getContent(),
            __('protocols.confirmations.delete')
        )
    );
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officer controls on the active-protocol list
|--------------------------------------------------------------------------
|
| A Protocol Officer may create protocols and enter the recycle bin. Within
| the table, however, Edit and Delete must be rendered only for their own row.
|
*/

test('a protocol officer sees management actions only for their own protocol', function () {
    // Create the authenticated officer and a second officer.
    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $otherOfficer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $ownProtocol = createProtocolForInterfaceTest(
        $officer,
        503,
        'Current officer interface protocol'
    );

    $otherProtocol = createProtocolForInterfaceTest(
        $otherOfficer,
        504,
        'Other officer interface protocol'
    );

    $response = $this
        ->actingAs($officer)
        ->get(route('protocols.index'));

    $response
        ->assertOk()
        ->assertSeeText(__('protocols.actions.create'))
        ->assertSeeText(__('protocols.actions.deleted_protocols'))

        // The officer sees the Edit link for their own row.
        ->assertSee(route('protocols.edit', $ownProtocol), false)

        // The other officer's Edit link must not enter the HTML response.
        ->assertDontSee(route('protocols.edit', $otherProtocol), false);

    // Exactly one protocol row may contain a Delete form.
    $this->assertSame(
        1,
        substr_count(
            $response->getContent(),
            __('protocols.confirmations.delete')
        )
    );
});

/*
|--------------------------------------------------------------------------
| Test: Viewer controls on the active-protocol list
|--------------------------------------------------------------------------
|
| A Viewer is read-only. The protocol details link remains available, while
| creation, recycle-bin access, editing, and deletion are hidden.
|
*/

test('a viewer sees only read actions on the protocol list', function () {
    // Create the Viewer and an active protocol owned by an officer.
    $viewer = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $protocol = createProtocolForInterfaceTest(
        $officer,
        505,
        'Protocol visible to viewer'
    );

    $response = $this
        ->actingAs($viewer)
        ->get(route('protocols.index'));

    $response
        ->assertOk()

        // Viewers may open the protocol details page.
        ->assertSee(route('protocols.show', $protocol), false)

        // Page-level modifying and recycle-bin actions are hidden.
        ->assertDontSeeText(__('protocols.actions.create'))
        ->assertDontSeeText(__('protocols.actions.deleted_protocols'))

        // The row must not contain an Edit link.
        ->assertDontSee(route('protocols.edit', $protocol), false);

    // A read-only list must not contain any protocol Delete form.
    $this->assertSame(
        0,
        substr_count(
            $response->getContent(),
            __('protocols.confirmations.delete')
        )
    );
});

/*
|--------------------------------------------------------------------------
| Test: Administrator controls on another user's details page
|--------------------------------------------------------------------------
|
| Global Administrator permission must be reflected on the details page and
| on an attachment that belongs to a Protocol Officer's protocol.
|
*/

test('an administrator sees all actions on another users protocol page', function () {
    // Create the Administrator, owner, protocol, and attachment metadata.
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $protocol = createProtocolForInterfaceTest(
        $officer,
        506,
        'Officer protocol viewed by administrator'
    );

    $attachment = createAttachmentForInterfaceTest(
        $protocol,
        $officer,
        'administrator-interface-test.pdf'
    );

    $response = $this
        ->actingAs($administrator)
        ->get(route('protocols.show', $protocol));

    $response
        ->assertOk()
        ->assertSeeText(__('protocols.actions.edit_protocol'))
        ->assertSeeText(__('protocols.actions.delete_protocol'))

        // Download remains available to every authenticated role.
        ->assertSee(route('protocols.attachments.download', [
            'protocol' => $protocol,
            'attachment' => $attachment,
        ]), false)

        // Administrators may also delete the officer's attachment.
        ->assertSee(
            'action="' . route('protocols.attachments.destroy', [
                'protocol' => $protocol,
                'attachment' => $attachment,
            ]) . '"',
            false
        );
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officer controls on their own details page
|--------------------------------------------------------------------------
|
| Ownership allows a Protocol Officer to edit and soft-delete the protocol,
| download its attachment, and delete that attachment.
|
*/

test('a protocol officer sees all allowed actions on their own protocol page', function () {
    // Create the officer's protocol and one attachment.
    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $protocol = createProtocolForInterfaceTest(
        $officer,
        507,
        'Officer own details interface protocol'
    );

    $attachment = createAttachmentForInterfaceTest(
        $protocol,
        $officer,
        'officer-own-interface-test.pdf'
    );

    $response = $this
        ->actingAs($officer)
        ->get(route('protocols.show', $protocol));

    $response
        ->assertOk()
        ->assertSeeText(__('protocols.actions.edit_protocol'))
        ->assertSeeText(__('protocols.actions.delete_protocol'))
        ->assertSee(route('protocols.attachments.download', [
            'protocol' => $protocol,
            'attachment' => $attachment,
        ]), false)
        ->assertSee(
            'action="' . route('protocols.attachments.destroy', [
                'protocol' => $protocol,
                'attachment' => $attachment,
            ]) . '"',
            false
        );
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officer controls on another user's details page
|--------------------------------------------------------------------------
|
| An officer may read another officer's protocol and download its attachment,
| but the interface must not expose protocol or attachment modification.
|
*/

test('a protocol officer sees no modify actions on another users protocol page', function () {
    // Create two officers and a protocol belonging to the first officer.
    $owner = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $otherOfficer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $protocol = createProtocolForInterfaceTest(
        $owner,
        508,
        'Other officer protected details protocol'
    );

    $attachment = createAttachmentForInterfaceTest(
        $protocol,
        $owner,
        'other-officer-interface-test.pdf'
    );

    $response = $this
        ->actingAs($otherOfficer)
        ->get(route('protocols.show', $protocol));

    $response
        ->assertOk()

        // Reading and downloading remain allowed.
        ->assertSeeText('Other officer protected details protocol')
        ->assertSee(route('protocols.attachments.download', [
            'protocol' => $protocol,
            'attachment' => $attachment,
        ]), false)

        // Every modifying control must be absent.
        ->assertDontSeeText(__('protocols.actions.edit_protocol'))
        ->assertDontSeeText(__('protocols.actions.delete_protocol'))
        ->assertDontSee(
            'action="' . route('protocols.attachments.destroy', [
                'protocol' => $protocol,
                'attachment' => $attachment,
            ]) . '"',
            false
        );
});

/*
|--------------------------------------------------------------------------
| Test: Viewer controls on the details page
|--------------------------------------------------------------------------
|
| A Viewer may read protocol details and download private attachments through
| the protected endpoint, but cannot modify the protocol or its attachments.
|
*/

test('a viewer sees only read and download actions on a protocol page', function () {
    // Create a Viewer and an officer-owned protocol with an attachment.
    $viewer = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $protocol = createProtocolForInterfaceTest(
        $officer,
        509,
        'Read-only viewer details protocol'
    );

    $attachment = createAttachmentForInterfaceTest(
        $protocol,
        $officer,
        'viewer-download-interface-test.pdf'
    );

    $response = $this
        ->actingAs($viewer)
        ->get(route('protocols.show', $protocol));

    $response
        ->assertOk()
        ->assertSeeText('Read-only viewer details protocol')
        ->assertSee(route('protocols.attachments.download', [
            'protocol' => $protocol,
            'attachment' => $attachment,
        ]), false)
        ->assertDontSeeText(__('protocols.actions.edit_protocol'))
        ->assertDontSeeText(__('protocols.actions.delete_protocol'))
        ->assertDontSee(
            'action="' . route('protocols.attachments.destroy', [
                'protocol' => $protocol,
                'attachment' => $attachment,
            ]) . '"',
            false
        );
});

/*
|--------------------------------------------------------------------------
| Test: Administrator controls in the recycle bin
|--------------------------------------------------------------------------
|
| Administrators may restore and permanently delete any trashed protocol,
| including an ownerless legacy record.
|
*/

test('an administrator sees restore and permanent delete actions', function () {
    // Create the Administrator and an ownerless legacy protocol.
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $protocol = createProtocolForInterfaceTest(
        null,
        510,
        'Ownerless deleted interface protocol'
    );

    // Move the protocol into the recycle bin.
    $protocol->delete();

    $response = $this
        ->actingAs($administrator)
        ->get(route('protocols.deleted'));

    $response
        ->assertOk()
        ->assertSeeText('Ownerless deleted interface protocol')
        ->assertSeeText(__('protocols.actions.restore'))
        ->assertSeeText(__('protocols.actions.force_delete'))
        ->assertSee(route('protocols.restore', [
            'protocol' => $protocol->id,
        ]), false)
        ->assertSee(route('protocols.force-delete', [
            'protocol' => $protocol->id,
        ]), false);
});

/*
|--------------------------------------------------------------------------
| Test: Protocol Officer controls in the recycle bin
|--------------------------------------------------------------------------
|
| The controller shows an officer only their own deleted records. For each
| listed record, Restore is available while Permanent Delete remains hidden.
|
*/

test('a protocol officer sees restore but not permanent delete', function () {
    // Create the Protocol Officer and one of their protocols.
    $officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $protocol = createProtocolForInterfaceTest(
        $officer,
        511,
        'Officer deleted interface protocol'
    );

    $protocol->delete();

    $response = $this
        ->actingAs($officer)
        ->get(route('protocols.deleted'));

    $response
        ->assertOk()
        ->assertSeeText('Officer deleted interface protocol')
        ->assertSeeText(__('protocols.actions.restore'))
        ->assertDontSeeText(__('protocols.actions.force_delete'))
        ->assertSee(route('protocols.restore', [
            'protocol' => $protocol->id,
        ]), false)
        ->assertDontSee(route('protocols.force-delete', [
            'protocol' => $protocol->id,
        ]), false);
});
