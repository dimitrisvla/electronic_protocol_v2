<?php

namespace Tests\Feature;

// Import the role enum so every test user has an explicit permission level.
use App\Enums\UserRole;

use App\Models\Protocol;
use App\Models\ProtocolAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProtocolAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_owner_can_upload_pdf_when_creating_protocol(): void
    {
        // A Protocol Officer may create protocols and upload attachments.
        $user = User::factory()->create([
            'role' => UserRole::ProtocolOfficer,
        ]);

        $pdf = UploadedFile::fake()->create(
            'incoming-document.pdf',
            500,
            'application/pdf'
        );

        $response = $this->actingAs($user)->post(
            route('protocols.store'),
            [
                'protocol_number' => 100,
                'protocol_year' => 2026,
                'protocol_date' => '2026-08-16',
                'direction' => 'incoming',
                'subject' => 'Protocol with attachment',
                'sender' => 'Test Sender',
                'recipient' => 'Test Recipient',
                'notes' => 'Attachment upload test',
                'attachments' => [$pdf],
            ]
        );

        $protocol = Protocol::firstOrFail();
        $attachment = ProtocolAttachment::firstOrFail();

        $response->assertRedirect(route('protocols.show', $protocol));

        $this->assertDatabaseHas('protocols', [
            'id' => $protocol->id,
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachment->id,
            'protocol_id' => $protocol->id,
            'original_name' => 'incoming-document.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id,
        ]);

        Storage::disk('local')->assertExists($attachment->file_path);
    }




    public function test_owner_can_upload_multiple_pdfs_when_creating_protocol(): void
    {

        // A Protocol Officer may create protocols and upload attachments.
        $user = User::factory()->create([
            'role' => UserRole::ProtocolOfficer,
        ]);

        $pdfs = [
            UploadedFile::fake()->create(
                'document-one.pdf',
                500,
                'application/pdf'
            ),
            UploadedFile::fake()->create(
                'document-two.pdf',
                750,
                'application/pdf'
            ),
            UploadedFile::fake()->create(
                'document-three.pdf',
                1000,
                'application/pdf'
            ),
        ];

        $response = $this->actingAs($user)->post(
            route('protocols.store'),
            [
                'protocol_number' => 101,
                'protocol_year' => 2026,
                'protocol_date' => '2026-08-16',
                'direction' => 'incoming',
                'subject' => 'Protocol with multiple attachments',
                'sender' => 'Test Sender',
                'recipient' => 'Test Recipient',
                'notes' => 'Multiple attachment upload test',
                'attachments' => $pdfs,
            ]
        );

        $protocol = Protocol::firstOrFail();
        $attachments = ProtocolAttachment::where(
            'protocol_id',
            $protocol->id
        )->get();

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('protocols.show', $protocol));

        $this->assertCount(3, $attachments);

        $this->assertDatabaseHas('protocol_attachments', [
            'protocol_id' => $protocol->id,
            'original_name' => 'document-one.pdf',
        ]);

        $this->assertDatabaseHas('protocol_attachments', [
            'protocol_id' => $protocol->id,
            'original_name' => 'document-two.pdf',
        ]);

        $this->assertDatabaseHas('protocol_attachments', [
            'protocol_id' => $protocol->id,
            'original_name' => 'document-three.pdf',
        ]);

        foreach ($attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->file_path);
        }
    }



    public function test_owner_can_upload_pdf_when_updating_protocol(): void
    {
        // A Protocol Officer may update a protocol they own.
        $user = User::factory()->create([
            'role' => UserRole::ProtocolOfficer,
        ]);

        $protocol = Protocol::create([
            'protocol_number' => 102,
            'protocol_year' => 2026,
            'protocol_date' => '2026-08-16',
            'direction' => 'incoming',
            'subject' => 'Original subject',
            'sender' => 'Original Sender',
            'recipient' => 'Original Recipient',
            'notes' => 'Original notes',
            'created_by' => $user->id,
        ]);

        $pdf = UploadedFile::fake()->create(
            'update-document.pdf',
            500,
            'application/pdf'
        );

        $response = $this->actingAs($user)->put(
            route('protocols.update', $protocol),
            [
                'protocol_number' => 102,
                'protocol_year' => 2026,
                'protocol_date' => '2026-08-16',
                'direction' => 'outgoing',
                'subject' => 'Updated subject',
                'sender' => 'Updated Sender',
                'recipient' => 'Updated Recipient',
                'notes' => 'Updated notes',
                'attachments' => [$pdf],
            ]
        );

        $attachment = ProtocolAttachment::where(
            'protocol_id',
            $protocol->id
        )->firstOrFail();

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('protocols.show', $protocol));

        $this->assertDatabaseHas('protocols', [
            'id' => $protocol->id,
            'direction' => 'outgoing',
            'subject' => 'Updated subject',
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachment->id,
            'protocol_id' => $protocol->id,
            'original_name' => 'update-document.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id,
        ]);

        Storage::disk('local')->assertExists($attachment->file_path);
    }



    public function test_updating_protocol_preserves_existing_attachments(): void
    {
        // A Protocol Officer may update a protocol they own.
        $user = User::factory()->create([
            'role' => UserRole::ProtocolOfficer,
        ]);

        $protocol = Protocol::create([
            'protocol_number' => 103,
            'protocol_year' => 2026,
            'protocol_date' => '2026-08-16',
            'direction' => 'incoming',
            'subject' => 'Original subject',
            'sender' => 'Original Sender',
            'recipient' => 'Original Recipient',
            'notes' => 'Original notes',
            'created_by' => $user->id,
        ]);

        $filePath = "protocols/{$protocol->id}/existing-document.pdf";

        Storage::disk('local')->put(
            $filePath,
            '%PDF-1.4 test document'
        );

        $attachment = ProtocolAttachment::create([
            'protocol_id' => $protocol->id,
            'original_name' => 'existing-document.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size' => 22,
            'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->put(
            route('protocols.update', $protocol),
            [
                'protocol_number' => 103,
                'protocol_year' => 2026,
                'protocol_date' => '2026-08-16',
                'direction' => 'outgoing',
                'subject' => 'Updated without new attachments',
                'sender' => 'Updated Sender',
                'recipient' => 'Updated Recipient',
                'notes' => 'Existing attachment should remain',
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('protocols.show', $protocol));

        $this->assertDatabaseHas('protocols', [
            'id' => $protocol->id,
            'direction' => 'outgoing',
            'subject' => 'Updated without new attachments',
        ]);

        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachment->id,
            'protocol_id' => $protocol->id,
            'original_name' => 'existing-document.pdf',
            'file_path' => $filePath,
        ]);

        $this->assertSame(
            1,
            ProtocolAttachment::where('protocol_id', $protocol->id)->count()
        );

        Storage::disk('local')->assertExists($filePath);
    }



    public function test_non_pdf_attachment_is_rejected(): void
    {
        /*
         * Use an authorized Protocol Officer so the failure comes from
         * file validation rather than role authorization.
         */
        $user = User::factory()->create([
            'role' => UserRole::ProtocolOfficer,
        ]);

        $textFile = UploadedFile::fake()->create(
            'notes.txt',
            100,
            'text/plain'
        );

        $response = $this->actingAs($user)->post(
            route('protocols.store'),
            [
                'protocol_number' => 104,
                'protocol_year' => 2026,
                'protocol_date' => '2026-08-16',
                'direction' => 'incoming',
                'subject' => 'Protocol with invalid attachment',
                'sender' => 'Test Sender',
                'recipient' => 'Test Recipient',
                'notes' => 'The text attachment must be rejected',
                'attachments' => [$textFile],
            ]
        );

        $response->assertSessionHasErrors([
            'attachments.0',
        ]);

        $this->assertDatabaseCount('protocols', 0);
        $this->assertDatabaseCount('protocol_attachments', 0);
    }



    public function test_ten_attachments_are_accepted(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ProtocolOfficer,
        ]);

        $pdfs = [];

        for ($number = 1; $number <= 10; $number++) {
            $pdfs[] = UploadedFile::fake()->create(
                "document-{$number}.pdf",
                100,
                'application/pdf'
            );
        }

        $response = $this->actingAs($user)->post(
            route('protocols.store'),
            [
                'protocol_number' => 105,
                'protocol_year' => 2026,
                'protocol_date' => '2026-08-16',
                'direction' => 'incoming',
                'subject' => 'Protocol with ten attachments',
                'sender' => 'Test Sender',
                'recipient' => 'Test Recipient',
                'notes' => 'The maximum allowed number of attachments',
                'attachments' => $pdfs,
            ]
        );

        $protocol = Protocol::firstOrFail();

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('protocols.show', $protocol));

        $this->assertSame(
            10,
            ProtocolAttachment::where('protocol_id', $protocol->id)->count()
        );

        foreach ($protocol->attachments()->get() as $attachment) {
            Storage::disk('local')->assertExists($attachment->file_path);
        }
    }




    public function test_more_than_ten_attachments_are_rejected(): void
    {
        /*
         * Use an authorized Protocol Officer so this test isolates the
         * maximum attachment-count validation rule.
         */
        $user = User::factory()->create([
            'role' => UserRole::ProtocolOfficer,
        ]);

        $pdfs = [];

        for ($number = 1; $number <= 11; $number++) {
            $pdfs[] = UploadedFile::fake()->create(
                "document-{$number}.pdf",
                100,
                'application/pdf'
            );
        }

        $response = $this->actingAs($user)->post(
            route('protocols.store'),
            [
                'protocol_number' => 106,
                'protocol_year' => 2026,
                'protocol_date' => '2026-08-16',
                'direction' => 'incoming',
                'subject' => 'Protocol with too many attachments',
                'sender' => 'Test Sender',
                'recipient' => 'Test Recipient',
                'notes' => 'Eleven attachments must be rejected',
                'attachments' => $pdfs,
            ]
        );

        $response->assertSessionHasErrors([
            'attachments',
        ]);

        $this->assertDatabaseCount('protocols', 0);
        $this->assertDatabaseCount('protocol_attachments', 0);
    }




    public function test_pdf_of_exactly_ten_megabytes_is_accepted(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ProtocolOfficer,
        ]);

        $pdf = UploadedFile::fake()->create(
            'maximum-size-document.pdf',
            10 * 1024,
            'application/pdf'
        );

        $response = $this->actingAs($user)->post(
            route('protocols.store'),
            [
                'protocol_number' => 107,
                'protocol_year' => 2026,
                'protocol_date' => '2026-08-16',
                'direction' => 'incoming',
                'subject' => 'Protocol with maximum-size attachment',
                'sender' => 'Test Sender',
                'recipient' => 'Test Recipient',
                'notes' => 'Exactly 10 MB should be accepted',
                'attachments' => [$pdf],
            ]
        );

        $response->assertSessionHasNoErrors();

        $protocol = Protocol::firstOrFail();
        $attachment = ProtocolAttachment::firstOrFail();

        $response->assertRedirect(route('protocols.show', $protocol));

        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachment->id,
            'protocol_id' => $protocol->id,
            'original_name' => 'maximum-size-document.pdf',
            'uploaded_by' => $user->id,
        ]);

        Storage::disk('local')->assertExists($attachment->file_path);
    }



    public function test_pdf_larger_than_ten_megabytes_is_rejected(): void
    {
        /*
         * Use an authorized Protocol Officer so this test isolates the
         * maximum file-size validation rule.
         */
        $user = User::factory()->create([
            'role' => UserRole::ProtocolOfficer,
        ]);

        $pdf = UploadedFile::fake()->create(
            'oversized-document.pdf',
            (10 * 1024) + 1,
            'application/pdf'
        );

        $response = $this->actingAs($user)->post(
            route('protocols.store'),
            [
                'protocol_number' => 108,
                'protocol_year' => 2026,
                'protocol_date' => '2026-08-16',
                'direction' => 'incoming',
                'subject' => 'Protocol with oversized attachment',
                'sender' => 'Test Sender',
                'recipient' => 'Test Recipient',
                'notes' => 'A 10,241 KB PDF must be rejected',
                'attachments' => [$pdf],
            ]
        );

        $response->assertSessionHasErrors([
            'attachments.0',
        ]);

        $this->assertDatabaseCount('protocols', 0);
        $this->assertDatabaseCount('protocol_attachments', 0);
    }



    public function test_authenticated_user_can_download_authorized_attachment(): void
    {
        /*
         * Viewers may read protocols and download their attachments.
         * This confirms that download access remains available to the
         * application's read-only role.
         */
        $user = User::factory()->create([
            'role' => UserRole::Viewer,
        ]);

        $protocol = Protocol::create([
            'protocol_number' => 109,
            'protocol_year' => 2026,
            'protocol_date' => '2026-08-16',
            'direction' => 'incoming',
            'subject' => 'Protocol with downloadable attachment',
            'sender' => 'Test Sender',
            'recipient' => 'Test Recipient',
            'notes' => 'Authorized download test',
            'created_by' => $user->id,
        ]);

        $filePath = "protocols/{$protocol->id}/private-document.pdf";

        Storage::disk('local')->put(
            $filePath,
            '%PDF-1.4 private test document'
        );

        $attachment = ProtocolAttachment::create([
            'protocol_id' => $protocol->id,
            'original_name' => 'private-document.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size' => 30,
            'uploaded_by' => $user->id,
        ]);

        Storage::disk('local')->assertExists($filePath);

        $response = $this->actingAs($user)->get(
            route(
                'protocols.attachments.download',
                [$protocol, $attachment]
            )
        );

        $response->assertOk();
        $response->assertDownload('private-document.pdf');

        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachment->id,
            'protocol_id' => $protocol->id,
            'file_path' => $filePath,
        ]);

        Storage::disk('local')->assertExists($filePath);
    }



    public function test_guest_cannot_download_private_attachment(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::ProtocolOfficer,
        ]);

        $protocol = Protocol::create([
            'protocol_number' => 110,
            'protocol_year' => 2026,
            'protocol_date' => '2026-08-16',
            'direction' => 'incoming',
            'subject' => 'Protocol with protected attachment',
            'sender' => 'Test Sender',
            'recipient' => 'Test Recipient',
            'notes' => 'Guest download protection test',
            'created_by' => $owner->id,
        ]);

        $filePath = "protocols/{$protocol->id}/protected-document.pdf";

        Storage::disk('local')->put(
            $filePath,
            '%PDF-1.4 protected test document'
        );

        $attachment = ProtocolAttachment::create([
            'protocol_id' => $protocol->id,
            'original_name' => 'protected-document.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size' => 32,
            'uploaded_by' => $owner->id,
        ]);

        $response = $this->get(
            route(
                'protocols.attachments.download',
                [$protocol, $attachment]
            )
        );

        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachment->id,
            'protocol_id' => $protocol->id,
        ]);

        Storage::disk('local')->assertExists($filePath);
    }



    public function test_attachment_cannot_be_downloaded_through_wrong_protocol(): void
    {
        /*
         * A Viewer is authorized to download attachments, but nested
         * route binding must still reject the wrong parent protocol.
         */
        $user = User::factory()->create([
            'role' => UserRole::Viewer,
        ]);

        $correctProtocol = Protocol::create([
            'protocol_number' => 111,
            'protocol_year' => 2026,
            'protocol_date' => '2026-08-16',
            'direction' => 'incoming',
            'subject' => 'Correct protocol',
            'sender' => 'Test Sender',
            'recipient' => 'Test Recipient',
            'notes' => 'The attachment belongs to this protocol',
            'created_by' => $user->id,
        ]);

        $wrongProtocol = Protocol::create([
            'protocol_number' => 112,
            'protocol_year' => 2026,
            'protocol_date' => '2026-08-16',
            'direction' => 'outgoing',
            'subject' => 'Wrong protocol',
            'sender' => 'Test Sender',
            'recipient' => 'Test Recipient',
            'notes' => 'The attachment does not belong to this protocol',
            'created_by' => $user->id,
        ]);

        $filePath = "protocols/{$correctProtocol->id}/private-document.pdf";

        Storage::disk('local')->put(
            $filePath,
            '%PDF-1.4 private test document'
        );

        $attachment = ProtocolAttachment::create([
            'protocol_id' => $correctProtocol->id,
            'original_name' => 'private-document.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size' => 30,
            'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(
            route(
                'protocols.attachments.download',
                [$wrongProtocol, $attachment]
            )
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('protocol_attachments', [
            'id' => $attachment->id,
            'protocol_id' => $correctProtocol->id,
            'file_path' => $filePath,
        ]);

        Storage::disk('local')->assertExists($filePath);
    }




    public function test_owner_can_delete_attachment(): void
    {
        // A Protocol Officer may delete attachments from their own protocol.
        $owner = User::factory()->create([
            'role' => UserRole::ProtocolOfficer,
        ]);

        $protocol = Protocol::create([
            'protocol_number' => 113,
            'protocol_year' => 2026,
            'protocol_date' => '2026-08-16',
            'direction' => 'incoming',
            'subject' => 'Protocol with deletable attachment',
            'sender' => 'Test Sender',
            'recipient' => 'Test Recipient',
            'notes' => 'Owner-authorized deletion test',
            'created_by' => $owner->id,
        ]);

        $filePath = "protocols/{$protocol->id}/deletable-document.pdf";

        Storage::disk('local')->put(
            $filePath,
            '%PDF-1.4 deletable test document'
        );

        $attachment = ProtocolAttachment::create([
            'protocol_id' => $protocol->id,
            'original_name' => 'deletable-document.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size' => 32,
            'uploaded_by' => $owner->id,
        ]);

        Storage::disk('local')->assertExists($filePath);

        $response = $this->actingAs($owner)->delete(
            route(
                'protocols.attachments.destroy',
                [$protocol, $attachment]
            )
        );

        $response->assertRedirect(route('protocols.show', $protocol));

        $response->assertSessionHas(
            'success',
            __('flash.attachments.deleted')
        );

        $this->assertDatabaseMissing('protocol_attachments', [
            'id' => $attachment->id,
        ]);

        $this->assertDatabaseHas('protocols', [
            'id' => $protocol->id,
            'created_by' => $owner->id,
        ]);

        Storage::disk('local')->assertMissing($filePath);
    }



}
