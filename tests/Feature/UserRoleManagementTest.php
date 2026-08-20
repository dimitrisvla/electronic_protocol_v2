<?php

/**
 * Tests the Administrator-only user-role management feature.
 *
 * These tests verify both server-side authorization and the
 * role-aware navigation shown by the application's interface.
 */

// Import the enum that defines every valid application role.
use App\Enums\UserRole;

// Import the User model used to create test accounts.
use App\Models\User;

// Reset the database before each test so every test starts
// with its own clean and predictable data.
use Illuminate\Foundation\Testing\RefreshDatabase;

// Inspect stored passwords without comparing them to plain text.
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);


/*
|--------------------------------------------------------------------------
| User Management Page Authorization Tests
|--------------------------------------------------------------------------
*/

/**
 * A visitor who is not authenticated must not be able to open
 * the protected user management page.
 */
test('guest cannot view the user management page', function () {
    $response = $this->get(route('admin.users.index'));

    $response->assertRedirect(route('login'));
});

/**
 * An Administrator may open the page and see the registered users
 * that are available for role management.
 */
test('administrator can view the user management page', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $managedUser = User::factory()->create([
        'name' => 'Maria Example',
        'email' => 'maria@example.com',
        'role' => UserRole::Viewer,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->get(route('admin.users.index'));

    $response
        ->assertOk()
        ->assertSee($managedUser->name)
        ->assertSee($managedUser->email)
        ->assertSee(__('users.actions.create'))
        ->assertSee(route('admin.users.create'), false);
});

/**
 * Protocol Officers and Viewers are authenticated users, but they
 * must receive HTTP 403 when requesting the Administrator page.
 */
test('non-administrators cannot view the user management page', function (
    UserRole $role
) {
    $user = User::factory()->create([
        'role' => $role,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('admin.users.index'));

    $response->assertForbidden();
})->with([
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);


/*
|--------------------------------------------------------------------------
| Administrator-Managed User Creation Tests
|--------------------------------------------------------------------------
*/

/**
 * A visitor must be redirected to login from both Administrator-only
 * user-creation endpoints.
 */
test('guest cannot access administrator user creation', function () {
    $createResponse = $this->get(route('admin.users.create'));

    $createResponse->assertRedirect(route('login'));

    $storeResponse = $this->post(route('admin.users.store'), [
        'name' => 'Unauthorised User',
        'email' => 'unauthorised@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => UserRole::Viewer->value,
    ]);

    $storeResponse->assertRedirect(route('login'));

    $this->assertDatabaseMissing('users', [
        'email' => 'unauthorised@example.com',
    ]);
});

/**
 * An Administrator may open the creation form and must be offered every
 * role represented by the UserRole enum.
 */
test('administrator can view the user creation page', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->get(route('admin.users.create'));

    $response
        ->assertOk()
        ->assertSee(__('users.actions.create'))
        ->assertSee(UserRole::Viewer->label())
        ->assertSee(UserRole::ProtocolOfficer->label())
        ->assertSee(UserRole::Assigner->label())
        ->assertSee(UserRole::Administrator->label());
});

/**
 * An Administrator may create an account with any valid UserRole value.
 *
 * The submitted password must be hashed before storage. Creating the account
 * must also leave the current Administrator authenticated; the new account
 * must never replace the Administrator in the active session.
 */
test('administrator can create a user with any valid role', function (
    UserRole $role,
    string $email
) {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->post(route('admin.users.store'), [
            'name' => 'Managed User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => $role->value,
        ]);

    $response
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success', __('flash.users.created'));

    $this->assertDatabaseHas('users', [
        'name' => 'Managed User',
        'email' => $email,
        'role' => $role->value,
    ]);

    $createdUser = User::where('email', $email)->firstOrFail();

    // The role column must be converted back to the same enum case.
    $this->assertSame($role, $createdUser->role);

    // The original password must match the stored hash, not be stored as text.
    $this->assertTrue(Hash::check('password123', $createdUser->password));
    $this->assertNotSame('password123', $createdUser->password);

    // Creating another account must not log the Administrator out or switch users.
    $this->assertAuthenticatedAs($administrator);
})->with([
    'viewer' => [UserRole::Viewer, 'viewer@example.com'],
    'protocol officer' => [UserRole::ProtocolOfficer, 'officer@example.com'],
    'assigner' => [UserRole::Assigner, 'assigner@example.com'],
    'administrator' => [UserRole::Administrator, 'administrator@example.com'],
]);

/**
 * Protocol Officers and Viewers must receive HTTP 403 when opening the
 * Administrator-only user creation form.
 */
test('non-administrators cannot view the user creation page', function (
    UserRole $role
) {
    $user = User::factory()->create([
        'role' => $role,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('admin.users.create'));

    $response->assertForbidden();
})->with([
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

/**
 * Protocol Officers and Viewers must also receive HTTP 403 when constructing
 * a direct POST request. Authorization must happen on the server and must not
 * rely only on hiding the creation form.
 */
test('non-administrators cannot create users', function (UserRole $role) {
    $user = User::factory()->create([
        'role' => $role,
    ]);

    $email = $role->value.'@example.com';

    $response = $this
        ->actingAs($user)
        ->post(route('admin.users.store'), [
            'name' => 'Forbidden User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::Viewer->value,
        ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('users', [
        'email' => $email,
    ]);
})->with([
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

/**
 * The creation request must validate role values against UserRole. An
 * arbitrary value must be rejected without creating a database record.
 */
test('user creation rejects a role outside the UserRole enum', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->from(route('admin.users.create'))
        ->post(route('admin.users.store'), [
            'name' => 'Invalid Role User',
            'email' => 'invalid-role@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'invalid-role',
        ]);

    $response
        ->assertRedirect(route('admin.users.create'))
        ->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', [
        'email' => 'invalid-role@example.com',
    ]);

    $this->assertAuthenticatedAs($administrator);
});


/*
|--------------------------------------------------------------------------
| User Role Update Authorization and Validation Tests
|--------------------------------------------------------------------------
*/

/**
 * An Administrator may assign a different valid role to another user.
 */
test("administrator can change another user's role", function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $managedUser = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->patch(route('admin.users.role.update', $managedUser), [
            'role' => UserRole::ProtocolOfficer->value,
        ]);

    $response
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success', __('flash.users.role_updated'));

    $this->assertDatabaseHas('users', [
        'id' => $managedUser->id,
        'role' => UserRole::ProtocolOfficer->value,
    ]);
});

/**
 * Protocol Officers and Viewers must not be able to change roles,
 * even if they submit a direct PATCH request to the update route.
 */
test("non-administrators cannot change another user's role", function (
    UserRole $role
) {
    $user = User::factory()->create([
        'role' => $role,
    ]);

    $managedUser = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('admin.users.role.update', $managedUser), [
            'role' => UserRole::ProtocolOfficer->value,
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('users', [
        'id' => $managedUser->id,
        'role' => UserRole::Viewer->value,
    ]);
})->with([
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

/**
 * An Administrator may submit only values represented by UserRole.
 * An invalid value must be rejected without changing the database.
 */
test('role update rejects an invalid role', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $managedUser = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.role.update', $managedUser), [
            'role' => 'invalid-role',
        ]);

    $response
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors('role');

    $this->assertDatabaseHas('users', [
        'id' => $managedUser->id,
        'role' => UserRole::Viewer->value,
    ]);
});


/*
|--------------------------------------------------------------------------
| Administrator-Managed User Deletion Tests
|--------------------------------------------------------------------------
*/

/**
 * A guest must be redirected to login and must not be able to remove an
 * account by constructing a direct DELETE request.
 */
test('guest cannot delete a user', function () {
    $managedUser = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $response = $this->delete(
        route('admin.users.destroy', $managedUser)
    );

    $response->assertRedirect(route('login'));

    $this->assertDatabaseHas('users', [
        'id' => $managedUser->id,
    ]);
});

/**
 * An Administrator may permanently delete another registered user. The
 * Administrator who performed the action must remain authenticated.
 */
test('administrator can delete another user', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $managedUser = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->delete(route('admin.users.destroy', $managedUser));

    $response
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success', __('flash.users.deleted'));

    $this->assertDatabaseMissing('users', [
        'id' => $managedUser->id,
    ]);

    $this->assertAuthenticatedAs($administrator);
});

/**
 * Protocol Officers and Viewers must receive HTTP 403 when attempting to
 * delete another account directly.
 */
test('non-administrators cannot delete users', function (UserRole $role) {
    $user = User::factory()->create([
        'role' => $role,
    ]);

    $managedUser = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('admin.users.destroy', $managedUser));

    $response->assertForbidden();

    $this->assertDatabaseHas('users', [
        'id' => $managedUser->id,
    ]);
})->with([
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

/**
 * The system supports multiple Administrators. One Administrator may delete
 * another while remaining logged in and retaining administrative capability.
 */
test('administrator can delete another administrator', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $otherAdministrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->delete(route('admin.users.destroy', $otherAdministrator));

    $response->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', [
        'id' => $otherAdministrator->id,
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $administrator->id,
        'role' => UserRole::Administrator->value,
    ]);

    $this->assertAuthenticatedAs($administrator);
});

/**
 * Exact original parity allows the final Administrator to delete their own
 * account. The session must end cleanly, and public registration must reopen
 * because no Administrator remains.
 */
test('administrator can delete own account and registration reopens', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->delete(route('admin.users.destroy', $administrator));

    $response
        ->assertRedirect(route('login'))
        ->assertSessionHas(
            'success',
            __('flash.users.own_account_deleted')
        );

    $this->assertDatabaseMissing('users', [
        'id' => $administrator->id,
    ]);

    $this->assertGuest();

    $this->get(route('register'))->assertOk();
});

/**
 * The Administrator interface must expose a DELETE form for each managed
 * user. Authorization remains enforced by the controller.
 */
test('administrator sees user deletion actions', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $managedUser = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->get(route('admin.users.index'));

    $response
        ->assertOk()
        ->assertSee(__('common.actions.delete'))
        ->assertSee(route('admin.users.destroy', $managedUser), false);
});


/*
|--------------------------------------------------------------------------
| User Management Navigation Tests
|--------------------------------------------------------------------------
*/

/**
 * An Administrator should see a navigation link that provides
 * direct access to the user management page.
 */
test('administrator sees the user management navigation link', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->get(route('protocols.index'));

    $response
        ->assertOk()
        ->assertSee(__('common.navigation.user_management'))
        ->assertSee(route('admin.users.index'), false);
});

/**
 * Protocol Officers and Viewers should not see the Administrator
 * navigation link anywhere in the shared authenticated interface.
 */
test('non-administrators do not see the user management navigation link', function (
    UserRole $role
) {
    $user = User::factory()->create([
        'role' => $role,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('protocols.index'));

    $response
        ->assertOk()
        ->assertDontSee(__('common.navigation.user_management'))
        ->assertDontSee(route('admin.users.index'), false);
})->with([
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);
