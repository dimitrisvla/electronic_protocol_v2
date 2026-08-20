<?php

/**
 * The controller receives requests and decides what the application
 * should do. It retrieves or changes data through the model and
 * sends data to Blade views or redirects the user.
 *
 * File:
 * app/Http/Controllers/ProtocolController.php
 */

namespace App\Http\Controllers;

use App\Actions\Protocols\SyncRelatedProtocols;
// Assignment enums used to separate processing work from information copies.
use App\Enums\ProtocolAssignmentPurpose;

// User roles are used when building the processing-officer selector.
use App\Enums\UserRole;

// Import the validation request used when creating a protocol.
use App\Http\Requests\StoreProtocolRequest;

// Import the validation request used when updating a protocol.
use App\Http\Requests\UpdateProtocolRequest;

// Import the Protocol model.
use App\Models\ArchiveFolder;
use App\Models\Protocol;

// Import the assignment and user models used by the protocol detail page.
use App\Models\ProtocolAssignment;
use App\Models\User;

// Typed application settings and safe protocol-number allocation.
use App\Services\ApplicationSettings;
use App\Services\ProtocolNumbering;

// Raised when a concurrent request obtains the same unique number first.
use Illuminate\Database\UniqueConstraintViolationException;

// Used for controller methods that return a redirect response.
use Illuminate\Http\RedirectResponse;

// Represents one validated file uploaded through a form.
use Illuminate\Http\UploadedFile;

// Used to read search and filter values from the URL query string.
use Illuminate\Http\Request;

// Provides database transaction methods.
use Illuminate\Support\Facades\DB;

// Used to check whether the authenticated user is allowed
// to perform an action on a protocol.
use Illuminate\Support\Facades\Gate;

// Provides access to Laravel's configured filesystem disks.
use Illuminate\Support\Facades\Storage;

// Used for controller methods that return a Blade view.
use Illuminate\View\View;

// Used when Laravel cannot store an uploaded file.
use RuntimeException;

// Represents any exception or error caught during storage.
use Throwable;


class ProtocolController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Display Active Protocols
    |--------------------------------------------------------------------------
    */

    /**
     * Retrieve and display active protocols from the database.
     *
     * HTTP method: GET
     * URL: /protocols
     * Route name: protocols.index
     */
    public function index(Request $request): View
    {
        /*
         * A normal Protocol query automatically excludes
         * soft-deleted records.
         *
         * query() starts building the database query without
         * retrieving the records immediately. This allows us to
         * add search conditions, filters, and sorting before the
         * query is executed by paginate().
         */
        $protocols = Protocol::query()

            /*
             * Apply the search condition only when the search
             * field contains a value.
             *
             * The search checks:
             *
             * - The exact protocol number, when the search value
             *   contains only digits.
             * - Any subject containing the search text.
             *
             * The two conditions are placed inside a nested where
             * group so that they work correctly together with the
             * year and direction filters added below.
             */
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = trim((string) $request->query('search'));

                    $query->where(function ($query) use ($search) {
                        /*
                         * Search for the text anywhere inside the
                         * protocol subject.
                         *
                         * The percent signs are SQL wildcard
                         * characters. For example, searching for
                         * "invoice" will also match:
                         *
                         * "Payment of supplier invoice"
                         */
                        $query->where(
                            'subject',
                            'like',
                            '%' . $search . '%'
                        );

                        /*
                         * A protocol number is an integer. Add the
                         * number condition only when the search value
                         * contains digits and can safely be compared
                         * with the protocol_number column.
                         */
                        if (ctype_digit($search)) {
                            $query->orWhere(
                                'protocol_number',
                                (int) $search
                            );
                        }
                    });
                }
            )

            /*
             * Apply the year filter only when protocol_year was
             * included in the request and is not empty.
             */
            ->when(
                $request->filled('protocol_year'),
                function ($query) use ($request) {
                    $query->where(
                        'protocol_year',
                        $request->query('protocol_year')
                    );
                }
            )

            /*
             * Apply the direction filter only when its value is
             * one of the two directions supported by the system.
             *
             * An empty or unexpected value is ignored.
             */
            ->when(
                in_array(
                    $request->query('direction'),
                    ['incoming', 'outgoing'],
                    true
                ),
                function ($query) use ($request) {
                    $query->where(
                        'direction',
                        $request->query('direction')
                    );
                }
            )

            /*
             * Show the newest protocols first according to their
             * protocol date.
             *
             * If two protocols have the same date, their IDs are
             * used as a secondary sorting criterion.
             *
             * paginate(10) returns ten protocols per page.
             */
            ->orderByDesc('protocol_date')
            ->orderByDesc('id')
            ->paginate(10)

            /*
             * Keep the current search and filter values in the
             * pagination links.
             *
             * Without withQueryString(), moving to page 2 would
             * remove the selected search and filters.
             */
            ->withQueryString();

        /*
         * Retrieve the distinct protocol years stored in active
         * protocol records.
         *
         * These values will be used to build the year dropdown in
         * resources/views/protocols/index.blade.php.
         *
         * pluck('protocol_year') returns a collection containing
         * only the protocol_year values rather than complete
         * Protocol models.
         */
        $protocolYears = Protocol::query()
            ->select('protocol_year')
            ->distinct()
            ->orderByDesc('protocol_year')
            ->pluck('protocol_year');

        /*
         * Pass the protocols to the Blade view.
         *
         * The $protocols variable will be available inside:
         *
         * resources/views/protocols/index.blade.php
         */
        return view('protocols.index', [
            'protocols' => $protocols,
            'protocolYears' => $protocolYears,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Create a Protocol
    |--------------------------------------------------------------------------
    */

    /**
     * Display the form for creating a new protocol.
     *
     * HTTP method: GET
     * URL: /protocols/create
     * Route name: protocols.create
     */
    public function create(
        ApplicationSettings $settings,
        ProtocolNumbering $numbering
    ): View
    {
        /*
         * Ask ProtocolPolicy whether the authenticated user may create
         * protocols.
         *
         * Administrators and Protocol Officers are allowed.
         * Viewers receive HTTP 403 Forbidden.
         */
        Gate::authorize('create', Protocol::class);

        /*
         * Display:
         *
         * resources/views/protocols/create.blade.php
         */
        $archiveFolders = ArchiveFolder::query()
            ->active()
            ->selectable()
            ->ordered()
            ->get();

        $protocolYear = $numbering->defaultYear();

        return view('protocols.create', [
            'archiveFolders' => $archiveFolders,
            'protocolYear' => $protocolYear,
            'suggestedProtocolNumber' =>
                $numbering->suggestedNumber($protocolYear),
            'automaticProtocolNumbering' =>
                $settings->usesAutomaticProtocolNumbering(),
        ]);
    }


    /**
     * Validate and store a newly created protocol.
     *
     * HTTP method: POST
     * URL: /protocols
     * Route name: protocols.store
     */
    public function store(
        StoreProtocolRequest $request,
        ApplicationSettings $settings,
        ProtocolNumbering $numbering,
        SyncRelatedProtocols $syncRelatedProtocols
    ): RedirectResponse {
        /*
         * Check authorization again for the POST request.
         *
         * Hiding or protecting the creation form is not sufficient
         * because a user could send a direct request to this route.
         */
        Gate::authorize('create', Protocol::class);

        /*
         * Before Laravel enters this method, it executes the
         * validation rules defined in:
         *
         * app/Http/Requests/StoreProtocolRequest.php
         *
         * If validation fails, Laravel automatically redirects
         * the user back to the form with validation errors.
         *
         * validated() returns only the validated form data.
         */
        $validatedData = $request->validated();

        /*
         * Extract the uploaded files before creating the protocol.
         *
         * attachments is not a column in the protocols table, so it
         * must not be passed to Protocol::create(). If the user did
         * not select any files, use an empty array.
         */
        $attachmentFiles = $validatedData['attachments'] ?? [];
        unset($validatedData['attachments']);

        $relatedProtocolReferences =
            $validatedData['related_protocols'] ?? [];
        unset($validatedData['related_protocols']);

        /*
         * Store the ID of the authenticated user.
         *
         * We assign created_by inside the controller instead
         * of accepting it from the form. This prevents users
         * from selecting another user as the protocol creator.
         */
        $validatedData['created_by'] = $request->user()->id;

        /*
         * Automatic numbering never trusts protocol_number from the browser.
         * StoreProtocolRequest excludes it and the controller calculates it
         * immediately before insertion. In manual mode the validated number
         * remains unchanged.
         */
        $automaticNumbering =
            $settings->usesAutomaticProtocolNumbering();

        /*
         * Keep the paths of files created during this request.
         *
         * Database transactions can undo database records, but they
         * cannot automatically remove files from the filesystem.
         * This list lets us remove those files if an error occurs.
         */
        $maximumAttempts = $automaticNumbering ? 3 : 1;
        $protocol = null;

        for ($attempt = 1; $attempt <= $maximumAttempts; $attempt++) {
            $storedPaths = [];

            DB::beginTransaction();

            try {
                if ($automaticNumbering) {
                    $validatedData['protocol_number'] =
                        $numbering->nextNumber(
                            (int) $validatedData['protocol_year']
                        );
                }

                /*
                 * Insert the protocol first because its ID is used to
                 * create a separate attachment directory.
                 */
                $protocol = Protocol::create($validatedData);

                $syncRelatedProtocols->execute(
                    $protocol,
                    $relatedProtocolReferences,
                    $request->user()
                );

                /*
                 * Store each validated PDF and insert its metadata into
                 * the protocol_attachments table.
                 */
                $this->storeAttachments(
                    $protocol,
                    $attachmentFiles,
                    $storedPaths
                );

                /*
                 * Make all database changes permanent only after the
                 * protocol and every attachment have succeeded.
                 */
                DB::commit();

                break;
            } catch (UniqueConstraintViolationException $exception) {
                DB::rollBack();

                if ($storedPaths !== []) {
                    Storage::disk('local')->delete($storedPaths);
                }

                /*
                 * An empty sequence cannot provide a row to lock. If two
                 * automatic requests create its first entry simultaneously,
                 * the database unique index selects the winner. Recalculate
                 * and retry the losing request with the now-visible number.
                 */
                if (! $automaticNumbering
                    || $attempt === $maximumAttempts) {
                    throw $exception;
                }
            } catch (Throwable $exception) {
                /*
                 * Undo the protocol and attachment database records.
                 */
                DB::rollBack();

                /*
                 * Remove physical files that were written before the
                 * error occurred.
                 */
                if ($storedPaths !== []) {
                    Storage::disk('local')->delete($storedPaths);
                }

                /*
                 * Re-throw the original error so Laravel can report it.
                 */
                throw $exception;
            }
        }

        /*
         * A completed attempt always assigns the newly stored protocol.
         * This guard documents the invariant for static analysis.
         */
        if (! $protocol instanceof Protocol) {
            throw new RuntimeException(
                'The protocol could not be created.'
            );
        }

        /*
         * Redirect the user to the page of the newly
         * created protocol.
         *
         * The success message is temporarily stored in the
         * session and is available during the next request.
         */
        return redirect()
            ->route('protocols.show', $protocol)
            ->with('success', __('flash.protocols.created'));
    }


    /*
    |--------------------------------------------------------------------------
    | Display One Protocol
    |--------------------------------------------------------------------------
    */

    /**
     * Display one particular protocol.
     *
     * HTTP method: GET
     * URL: /protocols/{protocol}
     * Route name: protocols.show
     *
     * Laravel uses route model binding to retrieve the
     * corresponding Protocol record from the database.
     *
     * Because normal route model binding excludes soft-deleted
     * records, a deleted protocol cannot be displayed here.
     */
    public function show(
        Request $request,
        Protocol $protocol
    ): View
    {
        Gate::authorize('view', $protocol);

        /*
         * Load the page's related records in a fixed order. Loading both user
         * relationships avoids one extra database query for every assignment
         * displayed by the Blade view.
         */
        $protocol->load([
            'archiveFolder',
            'attachments' => fn ($query) => $query->orderBy('id'),
            'assignments' => fn ($query) => $query
                ->with(['assigner', 'assignee'])
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
        ]);

        /*
         * ProtocolAssignmentPolicy controls record-level visibility. This is
         * important because every authenticated user may view a protocol, but
         * unrelated users must not learn who received its assignments.
         */
        $visibleAssignments = $protocol->assignments
            ->filter(
                fn (ProtocolAssignment $assignment): bool =>
                    Gate::forUser($request->user())->allows(
                        'view',
                        $assignment
                    )
            )
            ->values();

        // Keep the model passed to Blade scoped as well, preventing future
        // view changes from accidentally reading the unfiltered collection.
        $protocol->setRelation('assignments', $visibleAssignments);

        $currentProcessingAssignment = $visibleAssignments->first(
            fn (ProtocolAssignment $assignment): bool =>
                $assignment->purpose
                    === ProtocolAssignmentPurpose::Processing
                && $assignment->completed_at === null
                && $assignment->superseded_at === null
        );

        $informationAssignments = $visibleAssignments
            ->filter(
                fn (ProtocolAssignment $assignment): bool =>
                    $assignment->purpose
                        === ProtocolAssignmentPurpose::Information
            )
            ->values();

        $processingHistory = $visibleAssignments
            ->filter(
                fn (ProtocolAssignment $assignment): bool =>
                    $assignment->purpose
                        === ProtocolAssignmentPurpose::Processing
                    && (
                        $assignment->completed_at !== null
                        || $assignment->superseded_at !== null
                    )
            )
            ->values();

        $canManageAssignments = Gate::forUser($request->user())->allows(
            'assign',
            $protocol
        );

        /*
         * Selection lists are needed only by Administrators and Assigners.
         * Other roles therefore never receive the full user directory merely
         * because they opened a protocol detail page.
         */
        $processingOfficers = collect();
        $informationRecipients = collect();

        if ($canManageAssignments) {
            $processingOfficers = User::query()
                ->where('role', UserRole::ProtocolOfficer->value)
                ->orderBy('name')
                ->orderBy('id')
                ->get();

            $informationRecipients = User::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get();
        }

        /*
         * Related protocols are symmetric and ordered by year and number.
         * The model query automatically excludes soft-deleted endpoints.
         */
        $relatedProtocols = $protocol->relatedProtocols();

        /*
         * Pass the protocol and its role-scoped assignment data to the view.
         *
         * resources/views/protocols/show.blade.php
         */
        return view('protocols.show', [
            'protocol' => $protocol,
            'canManageAssignments' => $canManageAssignments,
            'processingOfficers' => $processingOfficers,
            'informationRecipients' => $informationRecipients,
            'currentProcessingAssignment' =>
                $currentProcessingAssignment,
            'informationAssignments' => $informationAssignments,
            'processingHistory' => $processingHistory,
            'relatedProtocols' => $relatedProtocols,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Edit and Update a Protocol
    |--------------------------------------------------------------------------
    */

    /**
     * Display the form for editing a particular protocol.
     *
     * HTTP method: GET
     * URL: /protocols/{protocol}/edit
     * Route name: protocols.edit
     *
     * Laravel uses route model binding to retrieve the
     * corresponding Protocol record from the database.
     */
    public function edit(Protocol $protocol): View
    {
        /*
         * Check the update() method in ProtocolPolicy.
         *
         * If the authenticated user is not allowed to update
         * this protocol, Laravel returns a 403 Forbidden response.
         */
        Gate::authorize('update', $protocol);

        /*
         * Pass the selected protocol to edit.blade.php.
         *
         * The $protocol variable will be available inside:
         *
         * resources/views/protocols/edit.blade.php
         */
        $archiveFolders = ArchiveFolder::query()
            ->where(function ($query) use ($protocol) {
                $query->where(function ($query) {
                    $query
                        ->where('is_active', true)
                        ->where('is_selectable', true);
                });

                if ($protocol->archive_folder_id !== null) {
                    $query->orWhere(
                        'id',
                        $protocol->archive_folder_id
                    );
                }
            })
            ->ordered()
            ->get();

        return view('protocols.edit', [
            'protocol' => $protocol,
            'archiveFolders' => $archiveFolders,
            'relatedProtocols' => $protocol->relatedProtocols(),
        ]);
    }


    /**
     * Validate and update a particular protocol.
     *
     * HTTP methods: PUT or PATCH
     * URL: /protocols/{protocol}
     * Route name: protocols.update
     */
    public function update(
        UpdateProtocolRequest $request,
        Protocol $protocol,
        SyncRelatedProtocols $syncRelatedProtocols
    ): RedirectResponse {
        /*
         * Check the update() method in ProtocolPolicy.
         *
         * This server-side check is necessary even if the Edit
         * button is hidden in Blade. A user could manually enter
         * the update URL or send an update request.
         */
        Gate::authorize('update', $protocol);

        /*
         * Before Laravel continues, it executes the validation
         * rules defined in:
         *
         * app/Http/Requests/UpdateProtocolRequest.php
         *
         * If validation fails, Laravel automatically redirects
         * the user back to the edit form with validation errors.
         *
         * validated() returns only the validated form data.
         */
        $validatedData = $request->validated();

        /*
         * Extract newly selected PDFs before updating the protocol.
         * Existing attachments are not changed by this operation.
         */
        $attachmentFiles = $validatedData['attachments'] ?? [];
        unset($validatedData['attachments']);

        $relatedProtocolReferences =
            $validatedData['related_protocols'] ?? [];
        unset($validatedData['related_protocols']);

        /*
         * Keep track of newly stored files so they can be removed
         * if the database transaction fails.
         */
        $storedPaths = [];

        DB::beginTransaction();

        try {
            /*
             * Update only fields belonging to the protocols table.
             */
            $protocol->update($validatedData);

            $syncRelatedProtocols->execute(
                $protocol,
                $relatedProtocolReferences,
                $request->user()
            );

            /*
             * Add the new PDFs to this protocol. Submitting the edit
             * form without files simply passes an empty array.
             */
            $this->storeAttachments(
                $protocol,
                $attachmentFiles,
                $storedPaths
            );

            DB::commit();
        } catch (Throwable $exception) {
            /*
             * Restore the protocol's previous database values and
             * remove any newly created attachment records.
             */
            DB::rollBack();

            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }

        /*
         * The update and every new attachment were stored
         * successfully at this point.
         *
         * Existing attachment records and files remain unchanged.
         */

        /*
         * Redirect the user to the page of the updated protocol.
         *
         * The success message is temporarily stored in the
         * session and is available during the next request.
         */
        return redirect()
            ->route('protocols.show', $protocol)
            ->with('success', __('flash.protocols.updated'));
    }


    /*
    |--------------------------------------------------------------------------
    | Delete a Protocol
    |--------------------------------------------------------------------------
    */

    /**
     * Soft-delete a particular protocol.
     *
     * HTTP method: DELETE
     * URL: /protocols/{protocol}
     * Route name: protocols.destroy
     *
     * Laravel uses route model binding to retrieve the
     * corresponding Protocol record from the database.
     */
    public function destroy(
        Protocol $protocol
    ): RedirectResponse {
        /*
         * Check the delete() method in ProtocolPolicy.
         *
         * If the authenticated user is not allowed to delete
         * this protocol, Laravel returns a 403 Forbidden response.
         */
        Gate::authorize('delete', $protocol);

        /*
         * Soft-delete the selected protocol.
         *
         * Because the Protocol model uses Laravel's SoftDeletes
         * trait, delete() does not permanently remove the database
         * record.
         *
         * Instead, Laravel stores the current date and time in
         * the protocol's deleted_at column.
         */
        $protocol->delete();

        /*
         * Redirect the user to the active-protocol listing because
         * a soft-deleted protocol is no longer available through
         * its normal show page.
         *
         * The success message is temporarily stored in the session.
         */
        return redirect()
            ->route('protocols.index')
            ->with('success', __('flash.protocols.deleted'));
    }


    /*
    |--------------------------------------------------------------------------
    | Display Deleted Protocols
    |--------------------------------------------------------------------------
    */

    /**
     * Display a paginated list of soft-deleted protocols.
     *
     * HTTP method: GET
     * URL: /protocols/deleted
     * Route name: protocols.deleted
     *
     * This method returns records that have a value in
     * their deleted_at column.
     */
    public function deleted(Request $request): View
    {
        /*
         * Check the viewDeleted() method in ProtocolPolicy before
         * retrieving any deleted records.
         *
         * Administrators and Protocol Officers may continue.
         * Viewers receive HTTP 403 Forbidden.
         */
        Gate::authorize('viewDeleted', Protocol::class);

        /*
         * Protocol::onlyTrashed() changes the query so that it
         * retrieves only soft-deleted protocols.
         *
         * In SQL terms, these are approximately the records where:
         *
         * deleted_at IS NOT NULL
         */
        $query = Protocol::onlyTrashed();

        /*
         * A Protocol Officer may see only protocols they created.
         *
         * This database condition is essential: hiding Restore buttons
         * in Blade would not prevent another officer's deleted records
         * from being included in the HTML response.
         *
         * Administrators do not receive this condition, so their query
         * continues to include all deleted and ownerless protocols.
         */
        if ($request->user()->isProtocolOfficer()) {
            $query->where(
                'created_by',
                $request->user()->id
            );
        }

        /*
         * Apply sorting and pagination only after the role-dependent
         * ownership condition has been added to the query.
         */
        $protocols = $query

            /*
             * Sort the records according to their deletion time.
             *
             * The most recently deleted protocol will appear first.
             */
            ->orderByDesc('deleted_at')

            /*
             * If two protocols were deleted at approximately the
             * same time, sort the protocol with the higher ID first.
             */
            ->orderByDesc('id')

            /*
             * Divide the results into pages containing ten
             * deleted protocols each.
             *
             * Laravel will provide pagination information and
             * navigation links through the $protocols variable.
             */
            ->paginate(10);

        /*
         * Display:
         *
         * resources/views/protocols/deleted.blade.php
         *
         * Pass the $protocols variable to the view.
         */
        return view('protocols.deleted', [
            'protocols' => $protocols,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Restore a Deleted Protocol
    |--------------------------------------------------------------------------
    */

    /**
     * Restore one soft-deleted protocol.
     *
     * HTTP method: POST
     * URL: /protocols/{protocol}/restore
     * Route name: protocols.restore
     *
     * The value of $protocol comes from the {protocol}
     * route parameter.
     *
     * For example:
     *
     * /protocols/5/restore
     *
     * means that $protocol contains the integer 5.
     */
    public function restore(
        int $protocol
    ): RedirectResponse {
        /*
         * We cannot use normal Protocol route model binding here.
         *
         * Normal model binding searches only active protocols.
         * Therefore, it cannot retrieve a soft-deleted protocol.
         *
         * onlyTrashed() tells Laravel to search specifically among
         * records whose deleted_at value is not null.
         *
         * findOrFail() searches for the deleted protocol using its
         * primary key.
         *
         * If no deleted protocol with this ID exists, Laravel
         * automatically returns a 404 Not Found response.
         */
        $deletedProtocol = Protocol::onlyTrashed()
            ->findOrFail($protocol);

        /*
         * Check the restore() method in ProtocolPolicy.
         *
         * This must happen after retrieving the soft-deleted model
         * because the policy needs the complete Protocol object to
         * determine whether the authenticated user may restore it.
         *
         * If the user is not allowed to restore this protocol,
         * Laravel returns a 403 Forbidden response.
         */
        Gate::authorize('restore', $deletedProtocol);

        /*
         * Restore the protocol.
         *
         * Laravel sets the deleted_at column back to null.
         *
         * The existing database record is reused:
         *
         * - It keeps the same ID.
         * - It keeps the same protocol number.
         * - It keeps its original creator.
         * - It is not inserted as a new record.
         */
        $deletedProtocol->restore();

        /*
         * Redirect the user back to the deleted-protocol listing.
         *
         * If the restoration was successful, the restored protocol
         * will no longer appear in that listing because its
         * deleted_at value is now null.
         *
         * The success message is temporarily stored in the session.
         */
        return redirect()
            ->route('protocols.deleted')
            ->with('success', __('flash.protocols.restored'));
    }


    /*
    |--------------------------------------------------------------------------
    | Permanently Delete a Protocol
    |--------------------------------------------------------------------------
    */

    /**
     * Permanently delete one soft-deleted protocol.
     *
     * HTTP method: DELETE
     * URL: /protocols/{protocol}/force-delete
     * Route name: protocols.force-delete
     *
     * The value of $protocol comes from the {protocol}
     * route parameter.
     *
     * Unlike destroy(), this action cannot be undone. It removes:
     *
     * - The protocol record.
     * - The protocol's attachment records.
     * - The protocol's privately stored PDF files.
     */
    public function forceDelete(
        int $protocol
    ): RedirectResponse {
        /*
         * Retrieve the protocol only from the recycle bin.
         *
         * Normal Protocol queries and normal route model binding
         * exclude soft-deleted records. onlyTrashed() searches only
         * records whose deleted_at value is not null.
         *
         * This also prevents an active protocol from being
         * permanently deleted through this controller action.
         *
         * If the deleted protocol does not exist, findOrFail()
         * automatically returns a 404 Not Found response.
         */
        $deletedProtocol = Protocol::onlyTrashed()
            ->findOrFail($protocol);

        /*
         * Check the forceDelete() method in ProtocolPolicy.
         *
         * Only the user who originally created the protocol may
         * permanently delete it.
         *
         * If the authenticated user is not allowed to perform this
         * action, Laravel returns a 403 Forbidden response.
         */
        Gate::authorize('forceDelete', $deletedProtocol);

        /*
         * Each protocol stores its private PDF files inside:
         *
         * storage/app/private/protocols/{protocol-id}/
         *
         * Save this directory path before permanently deleting the
         * protocol record because its ID is required to build it.
         */
        $attachmentDirectory = "protocols/{$deletedProtocol->id}";

        /*
         * Delete the attachment metadata and the protocol inside one
         * database transaction.
         *
         * If either database operation fails, Laravel rolls back both
         * operations and leaves the database in its previous state.
         */
        DB::transaction(function () use ($deletedProtocol) {
            /*
             * Permanently remove all database records describing
             * attachments that belong to this protocol.
             */
            $deletedProtocol->attachments()->delete();

            /*
             * forceDelete() permanently removes the protocol row.
             *
             * Unlike delete(), it does not update deleted_at and the
             * protocol cannot be restored afterward.
             */
            $deletedProtocol->forceDelete();
        });

        /*
         * Database transactions cannot remove physical files.
         * Therefore, delete the protocol's private directory after
         * the database transaction has completed successfully.
         *
         * deleteDirectory() also succeeds safely when the protocol
         * has no stored attachment directory.
         */
        Storage::disk('local')
            ->deleteDirectory($attachmentDirectory);

        /*
         * Redirect the user back to the recycle bin.
         *
         * The permanently deleted protocol will no longer appear in
         * this listing because its database record no longer exists.
         *
         * The success message is temporarily stored in the session.
         */
        return redirect()
            ->route('protocols.deleted')
            ->with(
                'success',
                __('flash.protocols.permanently_deleted')
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Store Protocol Attachments
    |--------------------------------------------------------------------------
    */

    /**
     * Store validated PDFs privately and record their metadata.
     *
     * @param  array<int, UploadedFile>  $attachmentFiles
     * @param  array<int, string>  $storedPaths
     */
    private function storeAttachments(
        Protocol $protocol,
        array $attachmentFiles,
        array &$storedPaths
    ): void {
        foreach ($attachmentFiles as $attachmentFile) {
            /*
             * Store the file on the local disk under:
             *
             * storage/app/private/protocols/{protocol-id}/
             *
             * Laravel generates a unique physical filename. The
             * user's original filename is stored only as metadata.
             */
            $filePath = $attachmentFile->store(
                "protocols/{$protocol->id}",
                'local'
            );

            /*
             * The local disk has throw set to false, so a failed
             * write may return false instead of throwing an error.
             */
            if ($filePath === false) {
                throw new RuntimeException(
                    'The attachment could not be stored.'
                );
            }

            /*
             * Record the path before inserting metadata. If that
             * database insert fails, the catch block can delete the
             * physical file.
             */
            $storedPaths[] = $filePath;

            /*
             * Creating through the relationship automatically fills
             * protocol_id with the current protocol's ID.
             */
            $protocol->attachments()->create([
                'original_name' => $attachmentFile
                    ->getClientOriginalName(),
                'file_path' => $filePath,
                'mime_type' => $attachmentFile->getMimeType(),
                'file_size' => (int) $attachmentFile->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }
    }
}
