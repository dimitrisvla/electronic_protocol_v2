<?php

/**
 * Defines the application's web routes.
 *
 * This file connects incoming HTTP requests and URLs
 * to the appropriate controller methods.
 *
 * Examples:
 *
 * GET /protocols
 *     -> ProtocolController@index()
 *
 * POST /protocols
 *     -> ProtocolController@store()
 */


// Import the authentication controllers.
//
// RegisteredUserController handles conditional first-Administrator
// registration.
// AuthenticatedSessionController handles login and logout.
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;

// Allow public registration only while the application has no
// Administrator account.
use App\Http\Middleware\EnsureNoAdministratorExists;

// Import the Administrator controller responsible for displaying users
// and changing their application roles.
//
// The alias makes it clear that this controller belongs to the
// Administrator area of the application.
use App\Http\Controllers\Admin\UserController as AdminUserController;

// Import the controller that displays the retention catalogue to every
// authenticated role and restricts modifications to Administrators.
use App\Http\Controllers\Admin\ArchiveFolderController;

// Import the Administrator-only application settings controller.
use App\Http\Controllers\Admin\ApplicationSettingController;

// Import the controller responsible for protocol CRUD operations.
use App\Http\Controllers\ProtocolController;

// Import the authenticated advanced protocol search controller.
use App\Http\Controllers\ProtocolSearchController;

// Import the controller responsible for assigning protocols and completing
// their active processing assignments.
use App\Http\Controllers\ProtocolAssignmentController;

// Import the controller responsible for role-scoped assignment queues.
use App\Http\Controllers\ProtocolAssignmentQueueController;

// Import the controller responsible for operations that belong
// specifically to protocol attachments.
//
// Keeping attachment operations in their own controller prevents
// ProtocolController from becoming responsible for two resources.
use App\Http\Controllers\ProtocolAttachmentController;

// Import Laravel's Route facade.
//
// The Route facade provides methods such as:
// Route::get(), Route::post(), Route::delete(), and Route::resource().
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Home Route
|--------------------------------------------------------------------------
*/

/**
 * Redirect the application's home page:
 *
 * http://127.0.0.1:8000/
 *
 * to the named route:
 *
 * protocols.index
 *
 * which will correspond to:
 *
 * http://127.0.0.1:8000/protocols
 */
Route::get('/', function () {
    return redirect()->route('protocols.index');
});


/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Conditional Registration Routes
|--------------------------------------------------------------------------
|
| Public registration is available only when no Administrator exists.
|
| Both routes use:
|
| - "guest" to prevent authenticated users from registering again.
| - EnsureNoAdministratorExists to close registration as soon as an
|   Administrator exists.
|
| Applying the custom middleware to POST /register is essential. Hiding the
| form alone would not prevent a manually constructed registration request.
|
*/
Route::middleware([
    'guest',
    EnsureNoAdministratorExists::class,
])->group(function () {
    /**
     * Display the first-Administrator registration form.
     *
     * HTTP method: GET
     * URL: /register
     * Route name: register
     */
    Route::get('/register', [RegisteredUserController::class, 'create'])
        ->name('register');

    /**
     * Validate the form, create the Administrator, and authenticate them.
     *
     * HTTP method: POST
     * URL: /register
     */
    Route::post('/register', [RegisteredUserController::class, 'store']);
});


/**
 * The "guest" middleware allows access only to visitors who are
 * not currently authenticated.
 *
 * If an authenticated user tries to access one of these routes,
 * Laravel prevents them from seeing the login page.
 */
Route::middleware('guest')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Login Routes
    |--------------------------------------------------------------------------
    */

    /**
     * Display the login form.
     *
     * HTTP method: GET
     * URL: /login
     * Controller method: AuthenticatedSessionController@create
     * Route name: login
     */
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    /**
     * Process the submitted login form.
     *
     * HTTP method: POST
     * URL: /login
     * Controller method: AuthenticatedSessionController@store
     *
     * The controller validates the user's credentials and,
     * if they are correct, authenticates the user.
     */
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});


/*
|--------------------------------------------------------------------------
| Logout Route
|--------------------------------------------------------------------------
*/

/**
 * Log the authenticated user out of the application.
 *
 * HTTP method: POST
 * URL: /logout
 * Controller method: AuthenticatedSessionController@destroy
 * Route name: logout
 *
 * The "auth" middleware ensures that only an authenticated user
 * can perform this operation.
 *
 * POST is used instead of GET because logging out changes
 * the current session state.
 */
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');


/*
|--------------------------------------------------------------------------
| Protected Application Routes
|--------------------------------------------------------------------------
*/

/**
 * All routes inside this group use the "auth" middleware.
 *
 * This means that a visitor must be logged in before they can:
 *
 * - View protocols
 * - Create protocols
 * - Edit protocols
 * - Delete protocols
 * - View deleted protocols
 * - Restore deleted protocols
 * - Permanently delete protocols
 * - Download protocol attachments
 * - Delete protocol attachments
 * - View registered users
 * - Create registered users
 * - Change user roles
 * - Delete registered users
 *
 * If the visitor is not authenticated, Laravel redirects them
 * to the route named "login".
 *
 * Authentication and authorization have different responsibilities:
 *
 * - The "auth" middleware confirms that the visitor is logged in.
 * - Controller Gate checks and Form Request authorization confirm that
 *   the authenticated user may perform the requested operation.
 */
Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Advanced Protocol Search
    |--------------------------------------------------------------------------
    */
    Route::get(
        '/protocols/search',
        [ProtocolSearchController::class, 'index']
    )->name('protocols.search');

    /*
    |--------------------------------------------------------------------------
    | Protocol Assignment Queue
    |--------------------------------------------------------------------------
    */

    /**
     * Display the authenticated user's assignment queue.
     *
     * Administrators and Assigners receive organization-wide oversight.
     * Other authenticated roles see only assignments addressed to them.
     * The optional queue query parameter accepts processing, information,
     * or completed.
     *
     * HTTP method: GET
     * URL examples: /assignments and /assignments?queue=information
     * Controller method: ProtocolAssignmentQueueController@index
     * Route name: assignments.index
     */
    Route::get(
        '/assignments',
        [ProtocolAssignmentQueueController::class, 'index']
    )->name('assignments.index');

    /*
    |--------------------------------------------------------------------------
    | Administrator User Management Routes
    |--------------------------------------------------------------------------
    */

    /**
     * The "/admin" URL prefix groups routes that belong to the
     * Administrator area of the application.
     *
     * The "admin." name prefix gives every route inside this group
     * a consistent Administrator route name.
     *
     * Authentication is already required by the surrounding "auth"
     * middleware group. The controller and Form Request perform the
     * additional authorization checks that restrict these actions
     * to users with the Administrator role.
     */
    Route::prefix('admin')
        ->name('admin.')
        ->group(function () {

            /**
             * Display all registered users and their current roles.
             *
             * HTTP method: GET
             * URL: /admin/users
             * Controller method: Admin\UserController@index
             * Route name: admin.users.index
             *
             * The index() action confirms that the authenticated user
             * has the Administrator role before displaying the page.
             */
            Route::get('/users', [AdminUserController::class, 'index'])
                ->name('users.index');

            /**
             * Display the Administrator-only user creation form.
             *
             * HTTP method: GET
             * URL: /admin/users/create
             * Controller method: Admin\UserController@create
             * Route name: admin.users.create
             *
             * The controller confirms that the authenticated user has
             * the Administrator role before displaying the form.
             */
            Route::get('/users/create', [AdminUserController::class, 'create'])
                ->name('users.create');

            /**
             * Validate and create a new user account.
             *
             * HTTP method: POST
             * URL: /admin/users
             * Controller method: Admin\UserController@store
             * Route name: admin.users.store
             *
             * StoreUserRequest restricts this action to Administrators and
             * validates the selected role against the UserRole enum.
             */
            Route::post('/users', [AdminUserController::class, 'store'])
                ->name('users.store');

            /**
             * Change one registered user's application role.
             *
             * HTTP method: PATCH
             * URL example: /admin/users/5/role
             * Controller method: Admin\UserController@updateRole
             * Route name: admin.users.role.update
             *
             * The {user} route parameter identifies the user whose
             * role should be changed. Laravel uses route model binding
             * to convert this value into a User model object.
             *
             * UpdateUserRoleRequest confirms that the authenticated
             * user is an Administrator and validates the submitted role
             * against the UserRole enum before the update is performed.
             *
             * PATCH is appropriate because only the role field of the
             * existing user record is being changed.
             */
            Route::patch(
                '/users/{user}/role',
                [AdminUserController::class, 'updateRole']
            )->name('users.role.update');

            /**
             * Permanently delete one registered user.
             *
             * HTTP method: DELETE
             * URL example: /admin/users/5
             * Controller method: Admin\UserController@destroy
             * Route name: admin.users.destroy
             *
             * The {user} parameter identifies the account that should be
             * deleted. Laravel's route model binding retrieves the matching
             * User model before calling the controller.
             *
             * Authentication is already required by the surrounding "auth"
             * middleware. UserController::destroy() performs the additional
             * Administrator-role check, so Protocol Officers and Viewers
             * receive HTTP 403 even if they construct a direct request.
             *
             * To reproduce the original application's behavior, an
             * Administrator may delete:
             *
             * - A Viewer
             * - A Protocol Officer
             * - Another Administrator
             * - Their own Administrator account
             *
             * If an Administrator deletes their own account, the controller
             * logs them out and invalidates the session. If that account was
             * the final Administrator, conditional public registration
             * becomes available again.
             *
             * DELETE is used because this request permanently removes a user
             * record and is neither a read nor a partial update operation.
             */
            Route::delete(
                '/users/{user}',
                [AdminUserController::class, 'destroy']
            )->name('users.destroy');

        });

    /*
    |----------------------------------------------------------------------
    | Administrator Application Settings
    |----------------------------------------------------------------------
    */
    Route::prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get(
                '/settings',
                [ApplicationSettingController::class, 'index']
            )->name('settings.index');

            Route::put(
                '/settings',
                [ApplicationSettingController::class, 'update']
            )->name('settings.update');
        });

    /*
    |----------------------------------------------------------------------
    | Archive Folder and Retention Management
    |----------------------------------------------------------------------
    |
    | The original application allowed every authenticated user to consult
    | the retention catalogue, while only an Administrator could add, edit,
    | or delete definitions. ArchiveFolderPolicy and the two Form Requests
    | enforce those rules on the server.
    |
    | The create and edit controls share the index page, matching the original
    | one-page "Διατήρηση αρχείων" workflow. A separate create page and an
    | individual show page are therefore omitted.
    */
    Route::prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::resource(
                'archive-folders',
                ArchiveFolderController::class
            )
                ->parameters([
                    'archive-folders' => 'archiveFolder',
                ])
                ->only([
                    'index',
                    'store',
                    'edit',
                    'update',
                    'destroy',
                ]);
        });

    /*
    |--------------------------------------------------------------------------
    | Deleted Protocol Routes
    |--------------------------------------------------------------------------
    */

    /**
     * Display all soft-deleted protocols.
     *
     * HTTP method: GET
     * URL: /protocols/deleted
     * Controller method: ProtocolController@deleted
     * Route name: protocols.deleted
     *
     * This route must be declared before Route::resource().
     *
     * Route::resource() creates the following dynamic route:
     *
     * GET /protocols/{protocol}
     *
     * If /protocols/deleted were declared after that route,
     * Laravel could interpret the word "deleted" as the value
     * of the {protocol} parameter.
     */
    Route::get('/protocols/deleted', [ProtocolController::class, 'deleted'])
        ->name('protocols.deleted');

    /**
     * Restore one soft-deleted protocol.
     *
     * HTTP method: POST
     * URL example: /protocols/5/restore
     * Controller method: ProtocolController@restore
     * Route name: protocols.restore
     *
     * The {protocol} part is a route parameter.
     *
     * For example, in:
     *
     * /protocols/5/restore
     *
     * the value of {protocol} is 5.
     *
     * We use POST because restoring a protocol changes data
     * in the database.
     */
    Route::post(
        '/protocols/{protocol}/restore',
        [ProtocolController::class, 'restore']
    )->name('protocols.restore');

    /**
     * Permanently delete one soft-deleted protocol.
     *
     * HTTP method: DELETE
     * URL example: /protocols/5/force-delete
     * Controller method: ProtocolController@forceDelete
     * Route name: protocols.force-delete
     *
     * The {protocol} part is a route parameter containing the ID
     * of the soft-deleted protocol.
     *
     * The controller uses onlyTrashed() to retrieve the record.
     * Therefore, this route can permanently delete only a protocol
     * that is already inside the recycle bin.
     *
     * The controller also checks the forceDelete() method in
     * ProtocolPolicy. Only an Administrator may permanently
     * delete the protocol.
     *
     * DELETE is appropriate because this operation permanently
     * removes the protocol, its attachment records, and its private
     * PDF files. The operation cannot be undone.
     */
    Route::delete(
        '/protocols/{protocol}/force-delete',
        [ProtocolController::class, 'forceDelete']
    )->name('protocols.force-delete');


    /*
    |--------------------------------------------------------------------------
    | Protocol Assignment Routes
    |--------------------------------------------------------------------------
    */

    /**
     * Synchronize all active assignments for one protocol.
     *
     * HTTP method: PUT
     * URL example: /protocols/5/assignments
     * Controller method: ProtocolAssignmentController@update
     * Route name: protocols.assignments.update
     *
     * UpdateProtocolAssignmentsRequest authorizes the Administrator and
     * Assigner roles and validates the selected processing officer, deadline,
     * and information recipients before either assignment action runs.
     */
    Route::put(
        '/protocols/{protocol}/assignments',
        [ProtocolAssignmentController::class, 'update']
    )->name('protocols.assignments.update');

    /**
     * Complete one active processing assignment.
     *
     * HTTP method: PATCH
     * URL example: /protocols/5/assignments/12/complete
     * Controller method: ProtocolAssignmentController@complete
     * Route name: protocols.assignments.complete
     *
     * The controller confirms that the nested assignment belongs to the
     * protocol. ProtocolAssignmentPolicy then allows only an Administrator or
     * the assigned Protocol Officer to complete active processing work.
     */
    Route::patch(
        '/protocols/{protocol}/assignments/{protocolAssignment}/complete',
        [ProtocolAssignmentController::class, 'complete']
    )->name('protocols.assignments.complete');


    /*
    |--------------------------------------------------------------------------
    | Protocol Attachment Routes
    |--------------------------------------------------------------------------
    */

    /**
     * Download one private attachment belonging to a protocol.
     *
     * HTTP method: GET
     *
     * URL example:
     *
     * /protocols/5/attachments/12/download
     *
     * Controller method:
     *
     * ProtocolAttachmentController@download
     *
     * Route name:
     *
     * protocols.attachments.download
     *
     * The route contains two route parameters:
     *
     * {protocol}
     *     Identifies the parent protocol.
     *
     * {attachment}
     *     Identifies the attachment that should be downloaded.
     *
     * Laravel uses route model binding to convert these parameter
     * values into Protocol and ProtocolAttachment model objects.
     *
     * Placing the route inside the "auth" middleware group ensures
     * that unauthenticated visitors cannot access the download action.
     *
     * Authentication alone is not enough. The controller will also:
     *
     * 1. Confirm that the attachment belongs to the protocol.
     * 2. Authorize "view" access through ProtocolPolicy.
     * 3. Confirm that the physical file exists.
     * 4. Stream the file from private storage.
     *
     * The database storage path is never placed in the browser URL.
     * The browser receives only this controlled application route.
     *
     * GET is appropriate because downloading a file reads existing
     * data and does not modify the application's state.
     */
    Route::get(
        '/protocols/{protocol}/attachments/{attachment}/download',
        [ProtocolAttachmentController::class, 'download']
    )->name('protocols.attachments.download');

    /**
     * Delete one private attachment belonging to a protocol.
     *
     * HTTP method: DELETE
     *
     * URL example:
     *
     * /protocols/5/attachments/12
     *
     * Controller method:
     *
     * ProtocolAttachmentController@destroy
     *
     * Route name:
     *
     * protocols.attachments.destroy
     *
     * The "auth" middleware requires the visitor to be logged in.
     * The destroy() action then performs two additional checks:
     *
     * 1. It confirms that the attachment belongs to the protocol.
     * 2. It authorizes "update" access through ProtocolPolicy.
     *
     * DELETE is appropriate because this operation removes both
     * the private PDF and its database record.
     */
    Route::delete(
        '/protocols/{protocol}/attachments/{attachment}',
        [ProtocolAttachmentController::class, 'destroy']
    )->name('protocols.attachments.destroy');


    /*
    |--------------------------------------------------------------------------
    | Protocol Registration Receipt
    |--------------------------------------------------------------------------
    |
    | Display a printer-friendly registration receipt for one active protocol.
    | The surrounding auth middleware requires a signed-in user, while the
    | controller applies ProtocolPolicy's view permission. Normal route model
    | binding excludes protocols that are currently in the recycle bin.
    |
    */
    Route::get(
        '/protocols/{protocol}/receipt',
        [ProtocolController::class, 'receipt']
    )->name('protocols.receipt');


    /*
    |--------------------------------------------------------------------------
    | Standard Protocol CRUD Routes
    |--------------------------------------------------------------------------
    */

    /**
     * Route::resource() creates the standard CRUD routes for protocols.
     *
     * The only() method limits the generated routes to the operations
     * currently supported by ProtocolController.
     */
    Route::resource('protocols', ProtocolController::class)
        ->only([
            // GET /protocols
            // Display all active protocols.
            'index',

            // GET /protocols/create
            // Display the protocol creation form.
            'create',

            // POST /protocols
            // Validate and save a new protocol.
            'store',

            // GET /protocols/{protocol}
            // Display one particular protocol.
            'show',

            // GET /protocols/{protocol}/edit
            // Display the form for editing a protocol.
            'edit',

            // PUT or PATCH /protocols/{protocol}
            // Validate and update an existing protocol.
            'update',

            // DELETE /protocols/{protocol}
            // Soft-delete an existing protocol.
            'destroy',
        ]);
});
