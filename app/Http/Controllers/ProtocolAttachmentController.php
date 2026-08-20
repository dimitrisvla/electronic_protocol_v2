<?php

namespace App\Http\Controllers;

// Import the models used by the download action.
use App\Models\Protocol;
use App\Models\ProtocolAttachment;

// RedirectResponse is returned after an attachment is deleted.
use Illuminate\Http\RedirectResponse;

// Gate allows us to run authorization checks through ProtocolPolicy.
use Illuminate\Support\Facades\Gate;

// Storage provides secure access to files stored on Laravel's
// private local disk.
use Illuminate\Support\Facades\Storage;

// The download() method returns this response type.
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProtocolAttachmentController extends Controller
{
    /**
     * Download a private attachment.
     *
     * Both parameters will later be supplied through route model binding.
     */
    public function download(
        Protocol $protocol,
        ProtocolAttachment $attachment
    ): StreamedResponse {
        /**
         * Check that the attachment belongs to the protocol
         * appearing in the URL.
         *
         * Without this check, someone could combine one protocol ID with
         * an attachment belonging to a different protocol.
         *
         * We return 404 because the requested attachment does not exist
         * under this particular protocol.
         */
        abort_unless(
            (int) $attachment->protocol_id === (int) $protocol->id,
            404
        );

        /**
         * Authorize access through ProtocolPolicy::view().
         *
         * We authorize the parent protocol because attachment access
         * should follow the same rule as viewing that protocol.
         *
         * Laravel automatically returns HTTP 403 when authorization fails.
         */
        Gate::authorize('view', $protocol);

        /**
         * Confirm that the physical file still exists.
         *
         * The database record could exist even if the file was accidentally
         * removed from storage.
         */
        abort_unless(
            Storage::disk('local')->exists($attachment->file_path),
            404
        );

        /**
         * Return the private file as a download.
         *
         * file_path is the internal relative storage path.
         * original_name is the filename presented to the user.
         *
         * No public storage URL or physical server path is exposed.
         */
        return Storage::disk('local')->download(
            $attachment->file_path,
            $attachment->original_name
        );
    }

    /**
     * Delete an individual private attachment.
     *
     * Both parameters will be supplied through route model binding.
     */
    public function destroy(
        Protocol $protocol,
        ProtocolAttachment $attachment
    ): RedirectResponse {
        /**
         * Check that the attachment belongs to the protocol
         * appearing in the URL.
         *
         * This prevents someone from combining one protocol ID with
         * an attachment belonging to a different protocol.
         */
        abort_unless(
            (int) $attachment->protocol_id === (int) $protocol->id,
            404
        );

        /**
         * Authorize deletion through ProtocolPolicy::update().
         *
         * Only the protocol's creator may update the protocol, so the same
         * authorization rule controls who may remove its attachments.
         *
         * Laravel automatically returns HTTP 403 when authorization fails.
         */
        Gate::authorize('update', $protocol);

        /**
         * Delete the physical PDF from private storage if it still exists.
         *
         * Checking first also allows a stale database record to be removed
         * when its physical file has already gone missing.
         */
        if (Storage::disk('local')->exists($attachment->file_path)) {
            Storage::disk('local')->delete($attachment->file_path);
        }

        // Delete the attachment metadata from the database.
        $attachment->delete();

        return redirect()
            ->route('protocols.show', $protocol)
            ->with('success', __('flash.attachments.deleted'));
    }
}
