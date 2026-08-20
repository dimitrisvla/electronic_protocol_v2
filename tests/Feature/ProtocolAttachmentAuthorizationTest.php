<?php

/**
 * Test role-based authorization for deleting protocol attachments.
 *
 * These tests confirm that attachment permissions follow the parent
 * protocol rather than the identity of the attachment uploader.
 *
 * File:
 * tests/Feature/ProtocolAttachmentAuthorizationTest.php
 */

namespace Tests\Feature;

// Import the role enum used to create explicit test users.
use App\Enums\UserRole;

// Import the application models used by the test helpers.
use App\Models\Protocol;
use App\Models\ProtocolAttachment;
use App\Models\User;

// Recreate the test database for every test method.
use Illuminate\Foundation\Testing\RefreshDatabase;

// Provide access to Laravel's fake private storage disk.
use Illuminate\Support\Facades\Storage;

// Import the application's base test class.
use Tests\TestCase;


class ProtocolAttachmentAuthorizationTest extends TestCase
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
         * This prevents the tests from creating or deleting files
         * inside storage/app/private.
         */
        Storage::fake('local');
    }


    /**
     * Confirm that a Protocol Officer cannot delete an attachment
     * belonging to another officer's protocol.
     */
    public function test_non_owner_protocol_officer_cannot_directly_delete_an_attachment(): void
    {
        // Create two different Protocol Officers.
        $owner = User::factory()->create([
            'name' => 'Dimitris Vlachos',
            'role' => UserRole::ProtocolOfficer,
        ]);

        $otherOfficer = User::factory()->create([
            'name' => 'Alice',
            'role' => UserRole::ProtocolOfficer,
        ]);

        // The protocol and attachment belong to the first officer.
        $protocol = $this->createProtocol($owner, 100);
        $attachment = $this->createAttachment(
            $protocol,
            $owner,
            'authorization-test.pdf'
        );

        $filePath = $attachment->file_path;

        /*
         * Submit a direct DELETE request as the other officer.
         *
         * This does not depend on whether the Delete button is visible.
         * Server-side authorization must reject a manual request too.
         */
        $response = $this
            ->actingAs($otherOfficer)
            ->delete(route('protocols.attachments.destroy', [
                'protocol' => $protocol,
                'attachment' => $attachment,
            ]));

        // ProtocolPolicy::update() must reject the non-owner.
        $response->assertForbidden();

        // The database record and private PDF must remain.
        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachment->id,
            'protocol_id' => $protocol->id,
        ]);

        Storage::disk('local')->assertExists($filePath);
    }


    /**
     * Confirm that a Protocol Officer can delete an attachment from
     * a protocol they own.
     */
    public function test_owner_protocol_officer_can_delete_attachment_record_and_private_file(): void
    {
        $owner = User::factory()->create([
            'name' => 'Dimitris Vlachos',
            'role' => UserRole::ProtocolOfficer,
        ]);

        $protocol = $this->createProtocol($owner, 101);
        $attachment = $this->createAttachment(
            $protocol,
            $owner,
            'authorized-deletion-test.pdf'
        );

        $filePath = $attachment->file_path;

        // Confirm that both resources exist before deletion.
        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('local')->assertExists($filePath);

        // Submit the DELETE request as the owning Protocol Officer.
        $response = $this
            ->actingAs($owner)
            ->delete(route('protocols.attachments.destroy', [
                'protocol' => $protocol,
                'attachment' => $attachment,
            ]));

        $response->assertRedirect(route('protocols.show', $protocol));

        $response->assertSessionHas(
            'success',
            __('flash.attachments.deleted')
        );

        // The database record and private PDF must be removed.
        $this->assertDatabaseMissing('protocol_attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('local')->assertMissing($filePath);
    }


    /**
     * Confirm that an Administrator can delete an attachment from
     * another user's protocol.
     */
    public function test_administrator_can_delete_attachment_from_another_users_protocol(): void
    {
        $administrator = User::factory()->create([
            'name' => 'Administrator',
            'role' => UserRole::Administrator,
        ]);

        $officer = User::factory()->create([
            'name' => 'Alice',
            'role' => UserRole::ProtocolOfficer,
        ]);

        $protocol = $this->createProtocol($officer, 102);
        $attachment = $this->createAttachment(
            $protocol,
            $officer,
            'administrator-deletion-test.pdf'
        );

        $filePath = $attachment->file_path;

        /*
         * Administrator permission does not depend on who owns the
         * protocol or who uploaded the attachment.
         */
        $response = $this
            ->actingAs($administrator)
            ->delete(route('protocols.attachments.destroy', [
                'protocol' => $protocol,
                'attachment' => $attachment,
            ]));

        $response->assertRedirect(route('protocols.show', $protocol));

        // The attachment metadata and private PDF must be removed.
        $this->assertDatabaseMissing('protocol_attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('local')->assertMissing($filePath);
    }


    /**
     * Confirm that a Viewer cannot delete an attachment, even when
     * the parent protocol belongs to that Viewer.
     */
    public function test_viewer_cannot_delete_attachment_from_own_protocol(): void
    {
        $viewer = User::factory()->create([
            'name' => 'Read Only User',
            'role' => UserRole::Viewer,
        ]);

        $protocol = $this->createProtocol($viewer, 103);
        $attachment = $this->createAttachment(
            $protocol,
            $viewer,
            'viewer-protected-test.pdf'
        );

        $filePath = $attachment->file_path;

        // A direct deletion request must be rejected with HTTP 403.
        $this
            ->actingAs($viewer)
            ->delete(route('protocols.attachments.destroy', [
                'protocol' => $protocol,
                'attachment' => $attachment,
            ]))
            ->assertForbidden();

        // The attachment metadata and private PDF must remain.
        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachment->id,
            'protocol_id' => $protocol->id,
        ]);

        Storage::disk('local')->assertExists($filePath);
    }


    /*
    |--------------------------------------------------------------------------
    | Test Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create a protocol belonging to the supplied user.
     */
    private function createProtocol(
        User $owner,
        int $protocolNumber
    ): Protocol {
        return Protocol::create([
            'protocol_number' => $protocolNumber,
            'protocol_year' => 2026,
            'protocol_date' => '2026-08-16',
            'direction' => 'incoming',
            'subject' => 'Attachment authorization test',
            'sender' => 'Test Sender',
            'recipient' => 'Test Recipient',
            'notes' => null,
            'created_by' => $owner->id,
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
        $filePath = "protocols/{$protocol->id}/{$fileName}";

        // Create a small fake PDF on the isolated local disk.
        Storage::disk('local')->put(
            $filePath,
            '%PDF-1.4 attachment authorization test'
        );

        /*
         * Create metadata through the relationship so Laravel fills
         * protocol_id automatically.
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
