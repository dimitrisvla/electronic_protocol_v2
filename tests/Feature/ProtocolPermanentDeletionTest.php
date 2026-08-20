<?php

/**
 * Test the permanent deletion of soft-deleted protocols.
 *
 * These tests confirm that the force-delete operation:
 *
 * - Is available only to an Administrator.
 * - Works only for protocols already in the recycle bin.
 * - Permanently removes the protocol database record.
 * - Permanently removes its attachment database records.
 * - Removes its private PDF files from storage.
 *
 * File:
 * tests/Feature/ProtocolPermanentDeletionTest.php
 */

namespace Tests\Feature;

// Import the role enum used by the authorization tests.
use App\Enums\UserRole;

// Import the Protocol model.
use App\Models\Protocol;

// Import the ProtocolAttachment model.
use App\Models\ProtocolAttachment;

// Import the User model.
use App\Models\User;

// Recreate the test database for every test method.
use Illuminate\Foundation\Testing\RefreshDatabase;

// Provide access to Laravel's fake local storage disk.
use Illuminate\Support\Facades\Storage;

// Import the application's base test class.
use Tests\TestCase;


class ProtocolPermanentDeletionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prepare isolated private storage before every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Replace the real local disk with an isolated fake disk.
         *
         * This prevents the automated tests from creating or
         * deleting files inside storage/app/private.
         */
        Storage::fake('local');
    }


    /**
     * Confirm that an Administrator can permanently delete a trashed
     * protocol belonging to a Protocol Officer, together with its
     * attachment record and private PDF file.
     */
    public function test_administrator_can_permanently_delete_trashed_protocol_and_attachments(): void
    {
        // Create the Administrator who performs the irreversible action.
        $administrator = User::factory()->create([
            'name' => 'Dimitris Vlachos',
            'role' => UserRole::Administrator,
        ]);

        // Create the Protocol Officer who owns the protocol.
        $owner = User::factory()->create([
            'name' => 'Alice',
            'role' => UserRole::ProtocolOfficer,
        ]);

        // Create an active protocol owned by this user.
        $protocol = $this->createProtocol($owner, 200);

        // Create a private PDF and its attachment database record.
        $attachment = $this->createAttachment(
            $protocol,
            $owner,
            'permanent-deletion-test.pdf'
        );

        // Store values needed after the models have been deleted.
        $protocolId = $protocol->id;
        $attachmentId = $attachment->id;
        $filePath = $attachment->file_path;

        // Move the protocol into the recycle bin first.
        $protocol->delete();

        // Confirm that all resources exist before force deletion.
        $this->assertSoftDeleted('protocols', [
            'id' => $protocolId,
        ]);

        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachmentId,
            'protocol_id' => $protocolId,
        ]);

        Storage::disk('local')->assertExists($filePath);

        /*
         * Submit the permanent-deletion request as the Administrator.
         * Administrator permission does not depend on ownership.
         */
        $response = $this
            ->actingAs($administrator)
            ->delete(route('protocols.force-delete', [
                'protocol' => $protocolId,
            ]));

        // The controller returns the Administrator to the recycle bin.
        $response->assertRedirect(route('protocols.deleted'));

        $response->assertSessionHas(
            'success',
            __('flash.protocols.permanently_deleted')
        );

        /*
         * The protocol must no longer exist, even when a query
         * includes soft-deleted records.
         */
        $this->assertNull(
            Protocol::withTrashed()->find($protocolId)
        );

        $this->assertDatabaseMissing('protocols', [
            'id' => $protocolId,
        ]);

        // The attachment metadata must also be removed.
        $this->assertDatabaseMissing('protocol_attachments', [
            'id' => $attachmentId,
            'protocol_id' => $protocolId,
        ]);

        // The private PDF must also be removed.
        Storage::disk('local')->assertMissing($filePath);
    }


    /**
     * Confirm that a Protocol Officer cannot permanently delete a
     * trashed protocol, even when they are its owner.
     */
    public function test_protocol_officer_cannot_permanently_delete_own_trashed_protocol(): void
    {
        // Create a Protocol Officer who owns the protocol.
        $owner = User::factory()->create([
            'name' => 'Alice',
            'role' => UserRole::ProtocolOfficer,
        ]);

        $protocol = $this->createProtocol($owner, 201);

        $attachment = $this->createAttachment(
            $protocol,
            $owner,
            'protected-from-non-owner.pdf'
        );

        $protocolId = $protocol->id;
        $attachmentId = $attachment->id;
        $filePath = $attachment->file_path;

        // Move the owner's protocol into the recycle bin.
        $protocol->delete();

        /*
         * Submit a direct request as the owning Protocol Officer.
         *
         * This test does not depend on whether the Blade button is
         * visible because server-side authorization must also stop
         * a manually submitted request.
         */
        $response = $this
            ->actingAs($owner)
            ->delete(route('protocols.force-delete', [
                'protocol' => $protocolId,
            ]));

        // Only an Administrator may permanently delete a protocol.
        $response->assertForbidden();

        // The soft-deleted protocol must remain in the database.
        $this->assertSoftDeleted('protocols', [
            'id' => $protocolId,
            'created_by' => $owner->id,
        ]);

        // The attachment metadata and PDF must also remain.
        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachmentId,
            'protocol_id' => $protocolId,
        ]);

        Storage::disk('local')->assertExists($filePath);
    }


    /**
     * Confirm that a Viewer cannot permanently delete a trashed
     * protocol, even when created_by contains that Viewer's ID.
     */
    public function test_viewer_cannot_permanently_delete_own_trashed_protocol(): void
    {
        // Create a Viewer and an older protocol assigned to that user.
        $viewer = User::factory()->create([
            'name' => 'Read Only User',
            'role' => UserRole::Viewer,
        ]);

        $protocol = $this->createProtocol($viewer, 202);
        $protocolId = $protocol->id;

        // Move the protocol into the recycle bin.
        $protocol->delete();

        // A direct permanent-deletion request must be rejected.
        $this
            ->actingAs($viewer)
            ->delete(route('protocols.force-delete', [
                'protocol' => $protocolId,
            ]))
            ->assertForbidden();

        // The soft-deleted protocol must remain in the database.
        $this->assertSoftDeleted('protocols', [
            'id' => $protocolId,
            'created_by' => $viewer->id,
        ]);
    }


    /**
     * Confirm that an unauthenticated visitor cannot permanently
     * delete a protocol from the recycle bin.
     */
    public function test_guest_cannot_permanently_delete_trashed_protocol(): void
    {
        // Create an owner and a soft-deleted protocol.
        $owner = User::factory()->create([
            'role' => UserRole::ProtocolOfficer,
        ]);
        $protocol = $this->createProtocol($owner, 203);

        $attachment = $this->createAttachment(
            $protocol,
            $owner,
            'protected-from-guest.pdf'
        );

        $protocolId = $protocol->id;
        $attachmentId = $attachment->id;
        $filePath = $attachment->file_path;

        $protocol->delete();

        /*
         * Submit the request without actingAs(). Therefore, the
         * request is made as an unauthenticated visitor.
         */
        $response = $this->delete(
            route('protocols.force-delete', [
                'protocol' => $protocolId,
            ])
        );

        // The auth middleware must redirect the visitor to login.
        $response->assertRedirect(route('login'));

        // No database record or physical file may be removed.
        $this->assertSoftDeleted('protocols', [
            'id' => $protocolId,
        ]);

        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachmentId,
            'protocol_id' => $protocolId,
        ]);

        Storage::disk('local')->assertExists($filePath);
    }


    /**
     * Confirm that the force-delete route cannot permanently delete
     * a protocol that is still active.
     */
    public function test_active_protocol_cannot_be_permanently_deleted_through_force_delete_route(): void
    {
        // Use an Administrator so authorization succeeds before the
        // controller checks whether the protocol is in the recycle bin.
        $administrator = User::factory()->create([
            'role' => UserRole::Administrator,
        ]);

        $protocol = $this->createProtocol($administrator, 204);

        $attachment = $this->createAttachment(
            $protocol,
            $administrator,
            'active-protocol.pdf'
        );

        $protocolId = $protocol->id;
        $attachmentId = $attachment->id;
        $filePath = $attachment->file_path;

        /*
         * Submit the permanent-deletion request without first
         * soft-deleting the protocol.
         */
        $response = $this
            ->actingAs($administrator)
            ->delete(route('protocols.force-delete', [
                'protocol' => $protocolId,
            ]));

        /*
         * ProtocolController::forceDelete() uses onlyTrashed().
         * Therefore, it cannot find an active protocol and returns
         * 404 Not Found.
         */
        $response->assertNotFound();

        // The active protocol and its attachment must remain.
        $this->assertDatabaseHas('protocols', [
            'id' => $protocolId,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachmentId,
            'protocol_id' => $protocolId,
        ]);

        Storage::disk('local')->assertExists($filePath);
    }


    /**
     * Confirm that an Administrator can permanently delete a trashed
     * ownerless legacy protocol.
     */
    public function test_administrator_can_permanently_delete_ownerless_legacy_protocol(): void
    {
        // Administrator permission does not depend on created_by.
        $administrator = User::factory()->create([
            'role' => UserRole::Administrator,
        ]);

        /*
         * Passing null creates a legacy protocol whose created_by
         * value is null.
         */
        $protocol = $this->createProtocol(null, 205);
        $protocolId = $protocol->id;

        $protocol->delete();

        $response = $this
            ->actingAs($administrator)
            ->delete(route('protocols.force-delete', [
                'protocol' => $protocolId,
            ]));

        $response->assertRedirect(route('protocols.deleted'));

        // The ownerless protocol must be removed completely.
        $this->assertDatabaseMissing('protocols', [
            'id' => $protocolId,
        ]);

        $this->assertNull(
            Protocol::withTrashed()->find($protocolId)
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Test Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create a protocol for a test.
     *
     * Passing null as the owner creates an ownerless legacy record.
     */
    private function createProtocol(
        ?User $owner,
        int $protocolNumber
    ): Protocol {
        return Protocol::create([
            'protocol_number' => $protocolNumber,
            'protocol_year' => 2026,
            'protocol_date' => '2026-08-17',
            'direction' => 'incoming',
            'subject' => 'Permanent protocol deletion test',
            'sender' => 'Test Sender',
            'recipient' => 'Test Recipient',
            'notes' => 'Created by ProtocolPermanentDeletionTest',
            'created_by' => $owner?->id,
        ]);
    }


    /**
     * Create one private PDF and its attachment metadata record.
     */
    private function createAttachment(
        Protocol $protocol,
        User $uploader,
        string $fileName
    ): ProtocolAttachment {
        /*
         * Use the same directory structure as the real application:
         *
         * storage/app/private/protocols/{protocol-id}/
         */
        $filePath = "protocols/{$protocol->id}/{$fileName}";

        // Create a small fake PDF on the isolated local disk.
        Storage::disk('local')->put(
            $filePath,
            '%PDF-1.4 permanent deletion test'
        );

        /*
         * Create the metadata through the relationship so Laravel
         * automatically fills protocol_id.
         */
        return $protocol->attachments()->create([
            'original_name' => $fileName,
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size' => Storage::disk('local')->size($filePath),
            'uploaded_by' => $uploader->id,
        ]);
    }
}
